<?php

namespace App\Services\Integrations;

use App\Models\FacebookLead;
use App\Models\FacebookPageConnection;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadSource;
use App\Models\PortalUser;
use App\Rules\PhoneNumber;
use App\Services\Crm\LeadCreationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Turns a Facebook Lead Ads lead into a CRM lead for the account that connected the Page — through
 * LeadCreationService, like every other lead (duplicate merge, agent assignment, notifications).
 * The lead's Source is the ad set's name, else the ad's (created for the account when new); leads
 * without an ad (organic / test leads) get "Facebook Lead Ads". The campaign, ad set, ad and form
 * are written on the lead too. Each leadgen id is imported once (facebook_leads).
 */
class FacebookLeadImporter
{
    public const FALLBACK_SOURCE = 'Facebook Lead Ads';
    public const PAGE_SOURCE = 'facebook-lead-ads';

    /** What the last import() did: 'new' | 'merged' | 'skipped', and its campaign — read by sync(). */
    private string $lastOutcome = 'skipped';
    private ?string $lastCampaign = null;
    private ?string $lastSource = null;
    private ?Lead $lastLead = null;

    /** Totals of the last sync() — see summary(). */
    private array $summary = [];

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
        $this->lastOutcome = 'skipped';
        $this->lastCampaign = null;
        $this->lastSource = null;
        $this->lastLead = null;
        $leadgenId = (string) ($data['id'] ?? '');
        if ($leadgenId === '') {
            return null;
        }
        // Its CRM lead was permanently deleted (lead_id nulled by the FK) → it may be imported again,
        // e.g. by "All leads". A lead only in Deleted Leads still has its lead_id, so stays skipped.
        // The age check leaves alone a record another import has just claimed and is still filling.
        $reimport = FacebookLead::where('leadgen_id', $leadgenId)
            ->where(fn ($q) => $q->whereNull('lead_id')->orWhereNotExists(
                fn ($leads) => $leads->selectRaw('1')->from('leads')->whereColumn('leads.id', 'facebook_leads.lead_id')
            ))
            ->where('updated_at', '<', now()->subMinutes(10))->delete() > 0;
        if (FacebookLead::where('leadgen_id', $leadgenId)->exists()) {
            return null;
        }

        // Ad / ad set / campaign names come with the lead (ads_read); otherwise look them up by ad id.
        $adName = $data['ad_name'] ?? null;
        $adsetName = $data['adset_name'] ?? null;
        $campaignName = $data['campaign_name'] ?? null;
        if (!empty($data['ad_id']) && (!$adName || !$adsetName || !$campaignName)) {
            $info = $this->facebook->adInfo((string) $data['ad_id'], $connection->page_access_token);
            $adName ??= $info['ad'];
            $adsetName ??= $info['adset'];
            $campaignName ??= $info['campaign'];
        }
        $this->lastCampaign = $campaignName;
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
        $sourceId = $this->sourceId($connection->portal_user_id, $adsetName ?: $adName ?: self::FALLBACK_SOURCE);

        // Already a lead, and its latest source is this same ad set / ad → it's already in the CRM: skip.
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
            'message' => $this->message(array_diff_key($answers, array_flip($used)), [
                'Campaign' => $campaignName,
                'Ad set' => $adsetName,
                'Ad' => $adName,
                'Form' => $data['form_name'] ?? null,
                'Platform' => isset($data['platform']) ? Str::upper((string) $data['platform']) : null,
            ]),
            'page_source' => self::PAGE_SOURCE,
            'source_id' => $sourceId,
            'extra_fields' => array_filter([
                'facebook_lead_id' => $leadgenId,
                'facebook_page' => $connection->page_name,
                'facebook_campaign' => $campaignName,
                'facebook_adset' => $adsetName,
                'facebook_ad' => $adName,
                'facebook_form' => $data['form_name'] ?? null,
                'facebook_form_id' => $data['form_id'] ?? null,
                'facebook_created_time' => $data['created_time'] ?? null,
            ]),
        ], ownerId: $connection->portal_user_id, notify: $notify, noteAuthor: 'Facebook Lead Ads');

        $record->update(['lead_id' => $lead->id]);
        // A re-import of a deleted lead was counted the first time round.
        $connection->forceFill(['last_lead_at' => now(), 'leads_count' => $connection->leads_count + ($reimport ? 0 : 1)])->save();
        $this->lastOutcome = $lead->wasMerged ? 'merged' : 'new';
        $this->lastSource = $adsetName ?: $adName ?: self::FALLBACK_SOURCE;
        $this->lastLead = $lead;

        return $lead;
    }

    /**
     * Pulls the leads of every lead form on the Page (all campaigns, active and archived forms) created
     * since $since (default: the last sync; first sync / $allLeads: every lead, no date limit) — "Sync
     * now", the import of existing leads at connect time, and the catch-up after a reconnect. Bulk, so
     * no per-lead emails by default: callers send one summary instead (emailSummary()). $progress
     * (added, skipped) is called after each lead. Returns how many leads were added or merged.
     */
    public function sync(FacebookPageConnection $connection, ?\DateTimeInterface $since = null, bool $notify = false, ?callable $progress = null, bool $allLeads = false): int
    {
        $from = $allLeads ? null : ($since ?? $connection->last_synced_at);
        $sinceAt = $from ? \Illuminate\Support\Carbon::instance($from)->subMinutes(5) : null;
        $since = $sinceAt?->getTimestamp() ?? 0;
        $startedAt = now();
        $added = 0;
        $skipped = 0;
        $forms = $this->facebook->forms($connection->page_id, $connection->page_access_token);
        $this->summary = [
            'since' => $sinceAt?->toDateTimeString(), 'forms' => count($forms), 'facebook_total' => array_sum(array_column($forms, 'leads_count')),
            'fetched' => 0, 'new' => 0, 'merged' => 0, 'skipped' => 0, 'campaigns' => [],
            // agent id => ['count' => n, 'new' => n, 'updated' => n, 'sources' => [name => n]] — round-robin results.
            'agents' => [],
        ];

        $run = function () use ($connection, $forms, $since, $notify, $progress, &$added, &$skipped) {
            foreach ($forms as $form) {
                foreach ($this->facebook->formLeads($form['id'], $connection->page_access_token, $since) as $data) {
                    $this->summary['fetched']++;
                    $this->import($connection, $data + ['form_id' => $form['id'], 'form_name' => $form['name']], $notify) ? $added++ : $skipped++;
                    $this->summary[$this->lastOutcome]++;
                    if ($this->lastOutcome !== 'skipped') {
                        $campaign = $this->lastCampaign ?: 'No campaign (organic / test)';
                        $this->summary['campaigns'][$campaign] = ($this->summary['campaigns'][$campaign] ?? 0) + 1;
                        $this->recordAgentLead($connection);
                    }
                    if ($progress) {
                        $progress($added, $skipped);
                    }
                }
            }
        };
        // Bulk: no per-lead email / bell to the owner ($notify) or to round-robin agents — summaries instead.
        $notify ? $run() : \App\Services\Agency\LeadAssignmentService::muteAgentNotifications($run);
        arsort($this->summary['campaigns']);

        $connection->forceFill(['last_synced_at' => $startedAt, 'last_error' => null]
            // A successful sync supersedes an earlier failed import — stop showing its error.
            + ($connection->import_status === 'failed' ? ['import_status' => null, 'import_error' => null] : []))->save();

        return $added;
    }

    /**
     * Totals of the last sync(): since, forms, facebook_total (Facebook's own lead count over all its
     * forms, all time), fetched, new, merged, skipped, campaigns [name => leads added / merged].
     */
    public function summary(): array
    {
        return $this->summary;
    }

    /**
     * After a bulk sync, one summary email to the Page's account (with how the leads were shared out)
     * and one to each agent that round robin gave leads to, listing theirs — only when leads were
     * added or updated. Replaces the per-lead emails a bulk sync doesn't send.
     */
    public function emailSummary(FacebookPageConnection $connection, string $context = 'sync'): void
    {
        if (($this->summary['new'] ?? 0) + ($this->summary['merged'] ?? 0) === 0) {
            return;
        }
        $agents = PortalUser::whereIn('id', array_keys($this->summary['agents']))->get(['id', 'type', 'name', 'company_name', 'email'])->keyBy('id');
        $this->summary['agent_names'] = $agents->map(fn (PortalUser $agent) => $agent->displayName())->all();

        $owner = $connection->owner;
        if ($owner?->email) {
            $this->queueMail($owner->email, new \App\Mail\FacebookLeadsSyncedMail($connection, $this->summary, $context), $connection);
        }

        foreach ($this->summary['agents'] as $agentId => $assigned) {
            $agent = $agents->get($agentId);
            if ($agent?->email) {
                $this->queueMail($agent->email, new \App\Mail\FacebookLeadsAssignedMail($connection, $agent, $assigned, $context), $connection);
            }
        }
    }

    private function queueMail(string $to, \Illuminate\Mail\Mailable $mail, FacebookPageConnection $connection): void
    {
        try {
            \Illuminate\Support\Facades\Mail::to($to)->queue($mail->afterCommit());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Facebook sync summary email for page {$connection->page_id} failed: " . $e->getMessage());
        }
    }

    /** The lead just imported went to an agent (round robin / their existing agent) — note it for their summary. */
    private function recordAgentLead(FacebookPageConnection $connection): void
    {
        $lead = $this->lastLead;
        if (!$lead?->agent_id || $lead->agent_id === $connection->portal_user_id) {
            return;
        }

        // Totals only — the agent's email is a summary, not a list of leads.
        $entry = &$this->summary['agents'][$lead->agent_id];
        $entry ??= ['count' => 0, 'new' => 0, 'updated' => 0, 'sources' => []];
        $entry['count']++;
        $entry[$this->lastOutcome === 'merged' ? 'updated' : 'new']++;
        $source = $this->lastSource ?: self::FALLBACK_SOURCE;
        $entry['sources'][$source] = ($entry['sources'][$source] ?? 0) + 1;
        unset($entry);
    }

    /** The source the lead last came in from: its latest enquiry's source, else the source it was created with. */
    public function latestSourceId(Lead $lead): ?int
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

    /** The form's other answers, then where the lead came from (campaign → ad set → ad → form). */
    public function message(array $otherAnswers, array $origin): string
    {
        $lines = [];
        foreach ($otherAnswers as $question => $answer) {
            $lines[] = Str::of($question)->replace('_', ' ')->ucfirst() . ': ' . $answer;
        }
        $lines[] = '';
        $lines[] = 'Facebook Lead Ads';
        foreach (array_filter($origin) as $label => $value) {
            $lines[] = "{$label}: {$value}";
        }

        return trim(implode("\n", $lines));
    }

    /** The account's source with this name (its own or a global one), created for the account when new. */
    public function sourceId(int $ownerId, string $name): int
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
