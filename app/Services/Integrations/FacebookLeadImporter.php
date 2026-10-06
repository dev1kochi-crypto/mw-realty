<?php

namespace App\Services\Integrations;

use App\Models\FacebookLead;
use App\Models\FacebookPageConnection;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadSource;
use App\Rules\PhoneNumber;
use App\Services\Crm\LeadCreationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Turns a Facebook Lead Ads lead into a CRM lead for the account that connected the Page — through
 * LeadCreationService, like every other lead (duplicate merge, agent assignment, notifications).
 * The lead's Source is the ad's name (created for the account when new); leads without an ad
 * (organic / test leads) get "Facebook Lead Ads". Each leadgen id is imported once (facebook_leads).
 */
class FacebookLeadImporter
{
    public const FALLBACK_SOURCE = 'Facebook Lead Ads';
    public const PAGE_SOURCE = 'facebook-lead-ads';

    /** Form fields that map onto lead columns; every other answer goes into the message. */
    private const NAME_FIELDS = ['full_name', 'name'];
    private const EMAIL_FIELDS = ['email', 'work_email'];
    private const PHONE_FIELDS = ['phone_number', 'phone', 'mobile_number', 'work_phone_number'];

    public function __construct(
        private readonly FacebookLeadAds $facebook,
        private readonly LeadCreationService $leads,
    ) {
    }

    /**
     * $data is the lead as Graph API returns it (id, created_time, field_data, ad_name, …).
     * Returns the CRM lead (new, or the existing one it was merged into), or null when it was skipped:
     * this Facebook lead was already imported, or the same person (email / phone) is already a lead
     * whose latest source is this same ad — nothing new to record. The same person from a different
     * ad is merged by LeadCreationService: no duplicate lead, a history entry with the new source.
     * $notify = false for bulk imports of older leads (no bell / email per lead).
     */
    public function import(FacebookPageConnection $connection, array $data, bool $notify = true): ?Lead
    {
        $leadgenId = (string) ($data['id'] ?? '');
        if ($leadgenId === '' || FacebookLead::where('leadgen_id', $leadgenId)->exists()) {
            return null;
        }

        $adName = $data['ad_name'] ?? (!empty($data['ad_id']) ? $this->facebook->adName((string) $data['ad_id'], $connection->page_access_token) : null);
        $answers = $this->answers($data['field_data'] ?? []);

        // Claim the leadgen id first: a webhook retry racing a sync can't import it twice.
        try {
            $record = FacebookLead::create([
                'facebook_page_connection_id' => $connection->id,
                'leadgen_id' => $leadgenId,
                'page_id' => $connection->page_id,
                'form_id' => $data['form_id'] ?? null,
                'ad_id' => $data['ad_id'] ?? null,
                'ad_name' => $adName,
                'payload' => $data,
            ]);
        } catch (QueryException) {
            return null;
        }

        $name = $this->first($answers, self::NAME_FIELDS)
            ?? trim(($answers['first_name'] ?? '') . ' ' . ($answers['last_name'] ?? ''));
        [$code, $phone] = PhoneNumber::split($this->first($answers, self::PHONE_FIELDS));
        $used = [...self::NAME_FIELDS, 'first_name', 'last_name', ...self::EMAIL_FIELDS, ...self::PHONE_FIELDS, 'company_name', 'country'];
        $email = $this->first($answers, self::EMAIL_FIELDS);
        $sourceId = $this->sourceId($connection->portal_user_id, $adName ?: self::FALLBACK_SOURCE);

        // Already a lead, and its latest source is this same ad → it's already in the CRM: skip.
        $existing = $this->leads->findDuplicate($connection->portal_user_id, $email, $phone ?: null);
        if ($existing && $this->latestSourceId($existing) === $sourceId) {
            $record->update(['lead_id' => $existing->id]);

            return null;
        }

        $lead = $this->leads->create([
            'name' => $name !== '' ? Str::limit($name, 255, '') : 'Facebook lead',
            'email' => $email,
            'phone' => $phone ?: null,
            'phone_country_code' => $phone ? $code : null,
            'company' => $answers['company_name'] ?? null,
            'country' => $answers['country'] ?? null,
            'message' => $this->message(array_diff_key($answers, array_flip($used)), $data, $adName),
            'page_source' => self::PAGE_SOURCE,
            'source_id' => $sourceId,
            'extra_fields' => array_filter([
                'facebook_lead_id' => $leadgenId,
                'facebook_page' => $connection->page_name,
                'facebook_form_id' => $data['form_id'] ?? null,
                'facebook_ad' => $adName,
                'facebook_campaign' => $data['campaign_name'] ?? null,
                'facebook_created_time' => $data['created_time'] ?? null,
            ]),
        ], ownerId: $connection->portal_user_id, notify: $notify, noteAuthor: 'Facebook Lead Ads');

        $record->update(['lead_id' => $lead->id]);
        $connection->forceFill(['last_lead_at' => now(), 'leads_count' => $connection->leads_count + 1])->save();

        return $lead;
    }

    /**
     * Pulls the Page's leads since $since (default: the last sync; first sync: the last 30 days) —
     * the "Sync now" button, the import of existing leads at connect time, and the catch-up for leads
     * missed while the Page needed reconnecting. $progress(added, skipped) is called after each lead.
     * Returns how many leads were added or merged (skipped ones — already in the CRM — not counted).
     */
    public function sync(FacebookPageConnection $connection, ?\DateTimeInterface $since = null, bool $notify = true, ?callable $progress = null): int
    {
        $since = \Illuminate\Support\Carbon::instance($since ?? $connection->last_synced_at ?? now()->subDays(30))->subMinutes(5)->getTimestamp();
        $startedAt = now();
        $added = 0;
        $skipped = 0;

        foreach ($this->facebook->forms($connection->page_id, $connection->page_access_token) as $form) {
            foreach ($this->facebook->formLeads($form['id'], $connection->page_access_token, $since) as $data) {
                $this->import($connection, $data + ['form_id' => $form['id']], $notify) ? $added++ : $skipped++;
                if ($progress) {
                    $progress($added, $skipped);
                }
            }
        }

        $connection->forceFill(['last_synced_at' => $startedAt, 'last_error' => null]
            // A successful sync supersedes an earlier failed import — stop showing its error.
            + ($connection->import_status === 'failed' ? ['import_status' => null, 'import_error' => null] : []))->save();

        return $added;
    }

    /** The source the lead last came in from: its latest enquiry's source, else the source it was created with. */
    private function latestSourceId(Lead $lead): ?int
    {
        $meta = $lead->notesHistory()->where('type', LeadNote::TYPE_ENQUIRY)->latest('id')->value('meta');
        $fromHistory = is_array($meta) ? ($meta['source_id'] ?? null) : (json_decode((string) $meta, true)['source_id'] ?? null);

        return (int) ($fromHistory ?? $lead->source_id) ?: null;
    }

    /** field_data [{name, values[]}] → [name => "value, value"]. */
    private function answers(array $fieldData): array
    {
        $answers = [];
        foreach ($fieldData as $field) {
            $key = Str::lower((string) ($field['name'] ?? ''));
            $value = trim(implode(', ', array_map('strval', (array) ($field['values'] ?? []))));
            if ($key !== '' && $value !== '') {
                $answers[$key] = $value;
            }
        }

        return $answers;
    }

    private function first(array $answers, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!empty($answers[$key])) {
                return $answers[$key];
            }
        }

        return null;
    }

    /** The form's other answers + where the lead came from, readable in the CRM. */
    private function message(array $otherAnswers, array $data, ?string $adName): string
    {
        $lines = [];
        foreach ($otherAnswers as $question => $answer) {
            $lines[] = Str::of($question)->replace('_', ' ')->ucfirst() . ': ' . $answer;
        }
        $lines[] = '';
        $lines[] = 'Facebook lead form' . ($adName ? " — ad: {$adName}" : '') . (!empty($data['campaign_name']) ? " (campaign: {$data['campaign_name']})" : '');

        return trim(implode("\n", $lines));
    }

    /** The account's source with this name (its own or a global one), created for the account when new. */
    private function sourceId(int $ownerId, string $name): int
    {
        $name = Str::limit(trim($name), 100, '');

        return LeadSource::forOwner($ownerId)->whereRaw('LOWER(lead_sources.name) = ?', [mb_strtolower($name)])->value('lead_sources.id')
            ?? LeadSource::create([
                'portal_user_id' => $ownerId,
                'name' => $name,
                'order_index' => (int) LeadSource::where('portal_user_id', $ownerId)->max('order_index') + 1,
            ])->id;
    }
}
