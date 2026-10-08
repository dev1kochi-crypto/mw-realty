<?php

namespace App\Imports;

use App\Models\FacebookLead;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Rules\PhoneNumber;
use App\Services\Agency\LeadAssignmentService;
use App\Services\Crm\LeadCreationService;
use App\Services\Crm\LeadService;
use App\Services\Integrations\FacebookLeadImporter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Bulk-creates Leads for one owner from an uploaded file, in one of two formats:
 *
 *  - FORMAT_TEMPLATE — our own template (LeadsImportTemplateExport) or a Leads export (LeadsExport,
 *    whose columns are a superset of the template's), so an exported file can be imported again.
 *  - FORMAT_FACEBOOK — the CSV Meta's Leads Center / Ads Manager "Download leads" produces
 *    (id, created_time, ad_name, adset_name, campaign_name, form_name, platform, the form's
 *    questions, email, full_name, phone_number…). Leads are shaped like FacebookLeadImporter's:
 *    source = ad set (else ad) name, campaign / ad set / ad / form on the lead, round robin by the
 *    agency's Facebook setting.
 *
 * A file of the wrong format (or a modified template) is rejected up front with $structureError.
 * Otherwise every row is validated on its own and gets a status — Added, Updated (merged into the
 * existing lead with that email / phone) or Skipped with the reason — in $results, which becomes
 * the status column of the result file. Rows are independent: a bad row never blocks the rest.
 *
 * Bulk, so no per-lead bell / email (to the owner or to round-robin agents): $agents collects
 * what each agent was given so the caller can send one summary each.
 */
class LeadsImport
{
    public const FORMAT_TEMPLATE = 'template';
    public const FORMAT_FACEBOOK = 'facebook';

    public const STATUS_ADDED = 'Added';
    public const STATUS_UPDATED = 'Updated';
    public const STATUS_SKIPPED = 'Skipped';

    public const REQUIRED_COLUMNS = ['name', 'email', 'phone', 'message', 'tags', 'source', 'stage', 'notes', 'owner'];

    /** A Facebook file has these, plus a name, email and phone column (any of the *_FIELDS). */
    public const FACEBOOK_REQUIRED_COLUMNS = ['id', 'created_time', 'form_name'];

    private const FB_NAME_FIELDS = ['full_name', 'name'];
    private const FB_EMAIL_FIELDS = ['email', 'work_email'];
    private const FB_PHONE_FIELDS = ['phone_number', 'phone', 'mobile_number', 'work_phone_number'];

    /** Facebook's own columns — not form answers, so they don't go into the lead's message. */
    private const FB_META_COLUMNS = ['id', 'created_time', 'ad_id', 'ad_name', 'adset_id', 'adset_name', 'campaign_id', 'campaign_name',
        'form_id', 'form_name', 'is_organic', 'platform', 'lead_status', 'inbox_url', 'first_name', 'last_name', 'company_name', 'country'];

    public ?string $structureError = null;

    public int $added = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var string[] The file's headings, as written. */
    public array $headers = [];

    /** @var array<int, string[]> The file's rows, as read (blank trailing rows dropped). */
    public array $rows = [];

    /** @var array<int, array{status:string, remarks:string}> Per row of $rows, same index. */
    public array $results = [];

    /** agent id => ['count' => n, 'new' => n, 'updated' => n, 'sources' => [name => n]] — round-robin results. */
    public array $agents = [];

    /** heading key => column index */
    private array $columns = [];

    /** email / phone match key => file row number of the first row that used it. */
    private array $seenContacts = [];

    public function __construct(
        private readonly int $ownerId,
        private readonly string $ownerDisplayName,
        public readonly string $format,
        private readonly LeadService $leadService,
        private readonly LeadCreationService $leadCreation,
    ) {
    }

    /**
     * Load the file and check its format (fast — the upload request does this, so a wrong file is
     * reported at once). Returns the problem with the file as a whole, or null when it can be imported.
     *
     * @param string[] $headers @param array<int, string[]> $rows As read by LeadImportReader.
     */
    public function prepare(array $headers, array $rows): ?string
    {
        $this->headers = $headers;
        $this->rows = $rows;
        $this->columns = [];
        foreach ($headers as $index => $heading) {
            $this->columns[LeadImportReader::key($heading)] ??= $index;
        }

        return $this->structureError = $this->checkStructure();
    }

    /**
     * Import every row of a prepare()d file (the slow part — run by ProcessLeadImport on the queue).
     * $progress(processed, added, updated, skipped) is called after each row.
     */
    public function process(?callable $progress = null): void
    {
        // Bulk: agents get one summary email each (see $agents), not one per lead.
        LeadAssignmentService::muteAgentNotifications(function () use ($progress) {
            $context = $this->format === self::FORMAT_FACEBOOK ? $this->facebookContext() : $this->templateContext();
            $processed = 0;

            foreach ($this->rows as $index => $row) {
                $this->results[$index] = $this->importRow($row, $index + 2, $context);
                match ($this->results[$index]['status']) {
                    self::STATUS_ADDED => $this->added++,
                    self::STATUS_UPDATED => $this->updated++,
                    default => $this->skipped++,
                };
                if ($progress) {
                    $progress(++$processed, $this->added, $this->updated, $this->skipped);
                }
            }
        });
    }

    /** prepare() + process() in one go. */
    public function run(array $headers, array $rows): void
    {
        if (!$this->prepare($headers, $rows)) {
            $this->process();
        }
    }

    /** Problems with the file as a whole — wrong format, missing columns, no data — else null. */
    private function checkStructure(): ?string
    {
        $looksFacebook = $this->has(self::FACEBOOK_REQUIRED_COLUMNS) || ($this->has(['full_name']) && $this->hasAny(['ad_name', 'adset_name', 'campaign_name', 'form_id']));
        $looksTemplate = $this->has(['name', 'email', 'phone']) && !$this->has(['full_name']);

        if ($this->format === self::FORMAT_FACEBOOK) {
            if (!$looksFacebook && $looksTemplate) {
                return 'This file is in the MW Realty lead template format, not a Facebook leads export. Choose "Direct import" to import it.';
            }
            $missing = array_diff(self::FACEBOOK_REQUIRED_COLUMNS, array_keys($this->columns));
            if (!$this->hasAny(self::FB_NAME_FIELDS) && !$this->has(['first_name'])) {
                $missing[] = 'full_name';
            }
            if (!$this->hasAny(self::FB_EMAIL_FIELDS)) {
                $missing[] = 'email';
            }
            if (!$this->hasAny(self::FB_PHONE_FIELDS)) {
                $missing[] = 'phone_number';
            }
            if ($missing) {
                return 'The file format doesn\'t match a Facebook leads export. Upload the CSV downloaded from Meta Leads Center / Ads Manager '
                    . '(see the sample file). Missing column(s): ' . implode(', ', $missing) . '.';
            }
        } else {
            if ($looksFacebook) {
                return 'This file is a Facebook leads export. Choose "Facebook leads" to import it.';
            }
            $missing = array_diff(self::REQUIRED_COLUMNS, array_keys($this->columns));
            if ($missing) {
                return 'The file format has been changed. Please use the provided Excel import template (or a file exported from Leads) '
                    . 'without renaming or removing columns. Missing column(s): ' . implode(', ', array_map('ucfirst', $missing)) . '.';
            }
        }

        if (!array_filter($this->rows, fn ($row) => !LeadImportReader::isBlank($row))) {
            return 'The uploaded file has no data rows.';
        }

        return null;
    }

    private function templateContext(): array
    {
        return [
            'stages' => LeadStage::forOwner($this->ownerId)->get()->keyBy(fn ($s) => mb_strtolower($s->name)),
            'sources' => LeadSource::forOwner($this->ownerId)->get()->keyBy(fn ($s) => mb_strtolower($s->name)),
        ];
    }

    private function facebookContext(): array
    {
        return ['importer' => app(FacebookLeadImporter::class), 'sourceIds' => []];
    }

    /** @return array{status:string, remarks:string} */
    private function importRow(array $row, int $rowNumber, array &$context): array
    {
        if (LeadImportReader::isBlank($row)) {
            return $this->skip('Empty row.');
        }

        try {
            return $this->format === self::FORMAT_FACEBOOK
                ? $this->importFacebookRow($row, $rowNumber, $context)
                : $this->importTemplateRow($row, $rowNumber, $context);
        } catch (\Throwable $e) {
            Log::error("Lead import row {$rowNumber} for owner {$this->ownerId} failed: " . $e->getMessage());

            return $this->skip('Could not be saved because of an unexpected error — please try this row again.');
        }
    }

    private function importTemplateRow(array $row, int $rowNumber, array $context): array
    {
        $data = [
            'name' => $this->value($row, 'name'),
            'email' => $this->value($row, 'email'),
            'phone' => $this->value($row, 'phone'),
            'message' => $this->value($row, 'message'),
            'notes' => $this->value($row, 'notes'),
        ];
        $errors = $this->contactErrors($data['name'], $data['email'], $data['phone']);

        $stageName = $this->value($row, 'stage');
        $sourceName = $this->value($row, 'source');
        $ownerName = $this->value($row, 'owner');
        // Present in a Leads export (Active / Inactive) — optional, the template has no Status column.
        $status = mb_strtolower($this->value($row, 'status'));

        $stage = $stageName !== '' ? $context['stages']->get(mb_strtolower($stageName)) : null;
        $source = $sourceName !== '' ? $context['sources']->get(mb_strtolower($sourceName)) : null;

        if ($stageName !== '' && !$stage) {
            $errors[] = "Unknown stage \"{$stageName}\" — it must already exist under Master > Stage.";
        }
        if ($sourceName !== '' && !$source) {
            $errors[] = "Unknown source \"{$sourceName}\" — it must already exist under Master > Source.";
        }
        // Leads are always imported into the current account — the Owner column is a sanity check, not a cross-account reassignment.
        if ($ownerName !== '' && strcasecmp($ownerName, $this->ownerDisplayName) !== 0) {
            $errors[] = "Owner \"{$ownerName}\" does not match your account (\"{$this->ownerDisplayName}\").";
        }
        if ($status !== '' && !in_array($status, ['active', 'inactive'], true)) {
            $errors[] = "Unknown status \"{$this->value($row, 'status')}\" — use Active or Inactive.";
        }

        if ($errors) {
            return $this->skip(implode(' ', $errors));
        }
        if ($duplicate = $this->duplicateInFile($rowNumber, $data['email'], $data['phone'])) {
            return $this->skip($duplicate);
        }

        [$code, $phone] = $this->splitPhone($data['phone']);
        $tagNames = array_filter(array_map('trim', explode(',', $this->value($row, 'tags'))));

        $lead = $this->leadCreation->create([
            'name' => $data['name'],
            'email' => $data['email'] ?: null,
            'phone' => $phone,
            'phone_country_code' => $code,
            'message' => $data['message'] ?: null,
            'notes' => $data['notes'] ?: null,
            'stage_id' => $stage?->id,
            'source_id' => $source?->id,
            'status' => $status ?: 'active',
            'page_source' => 'import',
        ],
            ownerId: $this->ownerId,
            tagIds: $this->leadService->resolveTagIds($this->ownerId, $tagNames),
            notify: false,
            noteAuthor: 'Excel import',
        );

        return $this->saved($lead, $source?->name ?? 'Excel import');
    }

    private function importFacebookRow(array $row, int $rowNumber, array &$context): array
    {
        /** @var FacebookLeadImporter $importer */
        $importer = $context['importer'];
        $leadgenId = $this->stripPrefix($this->value($row, 'id'));
        $name = $this->first($row, self::FB_NAME_FIELDS) ?: trim($this->value($row, 'first_name') . ' ' . $this->value($row, 'last_name'));
        $email = $this->first($row, self::FB_EMAIL_FIELDS);
        $rawPhone = $this->stripPrefix($this->first($row, self::FB_PHONE_FIELDS));

        if ($errors = $this->contactErrors($name, $email, $rawPhone)) {
            return $this->skip(implode(' ', $errors));
        }

        // The same Facebook lead, already imported (by an earlier file, or by the connected Page).
        if ($leadgenId !== '') {
            $existingId = \App\Models\Lead::withTrashed()->where('portal_user_id', $this->ownerId)
                ->where('extra_fields->facebook_lead_id', $leadgenId)->value('id')
                ?? FacebookLead::where('leadgen_id', $leadgenId)->whereNotNull('lead_id')
                    ->whereHas('lead', fn ($q) => $q->withTrashed()->where('portal_user_id', $this->ownerId))->value('lead_id');
            if ($existingId) {
                return $this->skip("Already imported — this Facebook lead ({$leadgenId}) is lead #{$existingId}.");
            }
        }
        if ($duplicate = $this->duplicateInFile($rowNumber, $email, $rawPhone)) {
            return $this->skip($duplicate);
        }

        $adName = $this->stripQuotes($this->value($row, 'ad_name'));
        $adsetName = $this->stripQuotes($this->value($row, 'adset_name'));
        $campaignName = $this->stripQuotes($this->value($row, 'campaign_name'));
        $formName = $this->stripQuotes($this->value($row, 'form_name'));
        $sourceName = $adsetName ?: $adName ?: FacebookLeadImporter::FALLBACK_SOURCE;
        $sourceId = $context['sourceIds'][$sourceName] ??= $importer->sourceId($this->ownerId, $sourceName);
        [$code, $phone] = $this->splitPhone($rawPhone);

        // Same person, last seen from this very ad set → nothing new to record (as the Page sync does).
        $existing = $this->leadCreation->findDuplicate($this->ownerId, $email ?: null, $phone);
        if ($existing && !$existing->trashed() && $importer->latestSourceId($existing) === $sourceId) {
            return $this->skip("Already in your leads (lead #{$existing->id}) from the same ad set \"{$sourceName}\".");
        }

        // Every other column is one of the form's questions.
        $answers = [];
        foreach ($this->columns as $key => $index) {
            if (!in_array($key, [...self::FB_META_COLUMNS, ...self::FB_NAME_FIELDS, ...self::FB_EMAIL_FIELDS, ...self::FB_PHONE_FIELDS], true)
                && ($answer = $this->stripQuotes(trim((string) ($row[$index] ?? '')))) !== '') {
                $answers[rtrim($this->headers[$index], '?: ')] = str_replace('_', ' ', $answer);
            }
        }
        $platform = $this->value($row, 'platform');

        $lead = $this->leadCreation->create([
            'name' => Str::limit($name, 255, ''),
            'email' => $email ?: null,
            'phone' => $phone,
            'phone_country_code' => $code,
            'company' => $this->value($row, 'company_name') ?: null,
            'country' => $this->value($row, 'country') ?: null,
            'message' => $importer->message($answers, [
                'Campaign' => $campaignName,
                'Ad set' => $adsetName,
                'Ad' => $adName,
                'Form' => $formName,
                'Platform' => $platform !== '' ? Str::upper($platform) : null,
            ]),
            'page_source' => FacebookLeadImporter::PAGE_SOURCE,
            'source_id' => $sourceId,
            'extra_fields' => array_filter([
                'facebook_lead_id' => $leadgenId,
                'facebook_campaign' => $campaignName,
                'facebook_adset' => $adsetName,
                'facebook_ad' => $adName,
                'facebook_form' => $formName,
                'facebook_form_id' => $this->stripPrefix($this->value($row, 'form_id')),
                'facebook_created_time' => $this->value($row, 'created_time'),
            ]),
        ], ownerId: $this->ownerId, notify: false, noteAuthor: 'Facebook leads import');

        return $this->saved($lead, $sourceName);
    }

    /** Required name, valid email / phone, and at least one of the two. @return string[] */
    private function contactErrors(string $name, string $email, string $phone): array
    {
        $validator = Validator::make(['name' => $name, 'email' => $email], [
            'name' => ['required', 'string', 'max:255'],
            'email' => PhoneNumber::emailRules(required: false),
        ], [
            'name.required' => 'Name is missing.',
            'email.email' => "Invalid email address \"{$email}\".",
        ]);
        $errors = $validator->errors()->all();

        if ($email === '' && $phone === '') {
            $errors[] = 'Email or phone is required.';
        }
        if ($phone !== '' && ($phoneError = $this->phoneError($phone))) {
            $errors[] = $phoneError;
        }

        return $errors;
    }

    /** "+971 50 123 4567", "971501234567", "050 123 4567" are fine; letters or too few / many digits aren't. */
    private function phoneError(string $phone): ?string
    {
        if (!preg_match('/^\+?[\d\s().\-]+$/', $phone)) {
            return "Invalid phone number \"{$phone}\" — only digits, spaces and a leading + are allowed.";
        }
        $digits = strlen(preg_replace('/\D/', '', $phone));
        if ($digits < PhoneNumber::MIN_DIGITS || $digits > 15) {
            return "Invalid phone number \"{$phone}\" — it must have " . PhoneNumber::MIN_DIGITS . ' to 15 digits.';
        }
        if (str_starts_with($phone, '+') && PhoneNumber::split($phone)[0] === null) {
            return "Invalid phone number \"{$phone}\" — the country code isn't recognised.";
        }

        return null;
    }

    /** → [country code or null, number or null]. "971501234567" (country code, no +) is read as international. */
    private function splitPhone(string $phone): array
    {
        if ($phone === '') {
            return [null, null];
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (!str_starts_with($phone, '+') && !str_starts_with($digits, '0') && strlen($digits) > 10) {
            [$code, $number] = PhoneNumber::split('+' . $digits);
            if ($code) {
                return [$code, $number];
            }
        }
        [$code, $number] = PhoneNumber::split($phone);

        return [$code, $code ? $number : $phone];
    }

    /** A second row in this file with an email / phone an earlier row already used. */
    private function duplicateInFile(int $rowNumber, string $email, string $phone): ?string
    {
        $keys = array_filter([
            $email !== '' ? 'e:' . mb_strtolower($email) : null,
            ($digits = preg_replace('/\D/', '', $phone)) !== '' ? 'p:' . substr($digits, -9) : null,
        ]);
        foreach ($keys as $key) {
            if (isset($this->seenContacts[$key])) {
                return 'Duplicate of row ' . $this->seenContacts[$key] . ' in this file (same ' . (str_starts_with($key, 'e:') ? 'email' : 'phone') . ').';
            }
        }
        foreach ($keys as $key) {
            $this->seenContacts[$key] = $rowNumber;
        }

        return null;
    }

    private function saved(\App\Models\Lead $lead, string $sourceName): array
    {
        $outcome = $lead->wasMerged ? self::STATUS_UPDATED : self::STATUS_ADDED;
        $remarks = $lead->wasMerged ? "Matched existing lead #{$lead->id} (same email / phone) — new enquiry added to its history." : "Lead #{$lead->id} created.";

        if ($lead->agent_id && $lead->agent_id !== $this->ownerId && ($outcome === self::STATUS_ADDED || $lead->assignedOnMerge)) {
            $entry = &$this->agents[$lead->agent_id];
            $entry ??= ['count' => 0, 'new' => 0, 'updated' => 0, 'sources' => []];
            $entry['count']++;
            $entry[$outcome === self::STATUS_ADDED ? 'new' : 'updated']++;
            $entry['sources'][$sourceName] = ($entry['sources'][$sourceName] ?? 0) + 1;
            unset($entry);
            $remarks .= ' Assigned by round robin.';
        }

        return ['status' => $outcome, 'remarks' => $remarks];
    }

    private function skip(string $reason): array
    {
        return ['status' => self::STATUS_SKIPPED, 'remarks' => $reason];
    }

    private function value(array $row, string $key): string
    {
        return isset($this->columns[$key]) ? trim((string) ($row[$this->columns[$key]] ?? '')) : '';
    }

    private function first(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (($value = $this->value($row, $key)) !== '') {
                return $value;
            }
        }

        return '';
    }

    private function has(array $keys): bool
    {
        return !array_diff($keys, array_keys($this->columns));
    }

    private function hasAny(array $keys): bool
    {
        return (bool) array_intersect($keys, array_keys($this->columns));
    }

    /** Facebook prefixes ids and phones with their type: "l:123", "ag:123", "p:+971…". */
    private function stripPrefix(string $value): string
    {
        return (string) preg_replace('/^(l|ag|as|c|f|p):/', '', $value);
    }

    private function stripQuotes(string $value): string
    {
        return trim($value, "\" \t");
    }
}
