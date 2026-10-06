<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A visitor enquiry about one specific property, routed to that property's
 * owning company/agent. Distinct from App\Models\CmsKit\Enquiry, which is
 * for general site-wide contact-us submissions seen only by admin.
 */
class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'property_id',
        'portal_user_id',
        'agent_id',
        'assignment_type',
        'assigned_at',
        'user_id',
        'name',
        'email',
        'phone',
        'phone_country_code',
        'company',
        'country',
        'message',
        'page_url',
        'page_source',
        'status',
        'stage_id',
        'source_id',
        'notes',
        'extra_fields',
        'enquiry_count',
        'last_enquired_at',
    ];

    /** Set (not persisted) when a new enquiry was merged into this existing lead instead of creating one. */
    public bool $wasMerged = false;

    /** Set (not persisted) when a merged, unassigned lead was routed to an agent — who was notified by the assignment. */
    public bool $assignedOnMerge = false;

    public const ASSIGN_PROPERTY_AGENT = 'property_agent';
    public const ASSIGN_ROUND_ROBIN = 'round_robin';
    public const ASSIGN_AGENCY_UNASSIGNED = 'agency_unassigned';
    public const ASSIGN_MANUAL = 'manual';
    public const ASSIGN_REASSIGNED = 'reassigned';

    protected $casts = [
        'extra_fields' => 'array',
        'assigned_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_enquired_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Every lead starts with a stage (the owner's default, e.g. "New") and a source (where it
        // came from — see sourceNameFor()), however it was created.
        static::creating(function (Lead $lead) {
            $lead->applyStageAndSourceDefaults();
        });

        // Stamp when the lead entered a closed stage (cleared if it moves back to an open one) —
        // every stage change path (board drag, edit form, import) goes through here.
        static::saving(function (Lead $lead) {
            if (!$lead->isDirty('stage_id')) {
                return;
            }
            $closed = $lead->stage_id && LeadStage::whereKey($lead->stage_id)->value('is_closed');
            if (!$closed) {
                $lead->closed_at = null;
            } elseif (!$lead->closed_at || !LeadStage::whereKey($lead->getOriginal('stage_id'))->value('is_closed')) {
                $lead->closed_at = now();
            }
        });

        // Every email / phone the lead has used is kept, so duplicate detection matches on any of
        // them — an edited primary email/phone is added, the previous one stays as an alternate.
        static::saved(function (Lead $lead) {
            if ($lead->wasRecentlyCreated || $lead->wasChanged(['email', 'phone'])) {
                $lead->recordContacts($lead->email, $lead->phone, $lead->phone_country_code);
            }
        });

        // Activity history: every stage / source / status / contact-detail change, whichever
        // screen made it, lands in the lead's timeline (LeadNoteService).
        static::updated(function (Lead $lead) {
            if (!self::$logActivity) {
                return;
            }
            $log = app(\App\Services\Crm\LeadNoteService::class);

            if ($lead->wasChanged('stage_id')) {
                $from = LeadStage::find($lead->getOriginal('stage_id'));
                $to = LeadStage::find($lead->stage_id);
                $log->log($lead, 'stage', 'Stage changed from ' . ($from?->name ?? 'No stage') . ' to ' . ($to?->name ?? 'No stage'), [
                    'from' => $from?->name, 'to' => $to?->name, 'color' => $to?->color,
                ]);
            }
            if ($lead->wasChanged('source_id')) {
                $from = LeadSource::find($lead->getOriginal('source_id'))?->name;
                $to = LeadSource::find($lead->source_id)?->name;
                $log->log($lead, 'source', 'Source changed from ' . ($from ?? 'No source') . ' to ' . ($to ?? 'No source'), ['from' => $from, 'to' => $to]);
            }
            if ($lead->wasChanged('status')) {
                $log->log($lead, 'status', 'Status changed from ' . ucfirst((string) $lead->getOriginal('status')) . ' to ' . ucfirst((string) $lead->status));
            }

            $labels = ['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'company' => 'Company', 'country' => 'Country', 'message' => 'Enquiry message'];
            $changed = array_keys(array_intersect_key($lead->getChanges(), $labels));
            if ($lead->wasChanged('phone_country_code') && !in_array('phone', $changed)) {
                $changed[] = 'phone';
            }
            if ($changed) {
                $lines = array_map(function ($field) use ($lead, $labels) {
                    if ($field === 'message') {
                        return 'Enquiry message updated';
                    }
                    $old = $field === 'phone' ? trim($lead->getOriginal('phone_country_code') . ' ' . $lead->getOriginal('phone')) : $lead->getOriginal($field);
                    $new = $field === 'phone' ? $lead->formatted_phone : $lead->{$field};

                    return $labels[$field] . ': ' . (filled($old) ? $old : '—') . ' → ' . (filled($new) ? $new : '—');
                }, $changed);
                $log->log($lead, 'details', implode("\n", $lines), ['fields' => $changed]);
            }
        });
    }

    /** Source of a lead added by hand (Add Lead) — preselected in the form, the user can change it. */
    public const DIRECT_SOURCE = 'Direct Lead';

    /** Channel (page_source) → the Source name leads from it get. Unknown channels are title-cased. */
    public const SOURCE_NAMES = [
        'property-detail' => 'Website',
        'property details' => 'Website',
        'ai-chatbot' => 'AI Chatbot',
        'brochure-download' => 'Brochure Download',
        'floor-plan-download' => 'Floor Plan Download',
        'custom-request' => 'Custom Request',
        'agent-profile-request' => 'Agent Profile',
        'agency-profile-request' => 'Agency Profile',
        'marked sold' => 'Direct Sale',
        'manual' => self::DIRECT_SOURCE,
        'import' => 'Import',
        'home page' => 'Website',
        'contact page' => 'Website',
    ];

    public static function sourceNameFor(?string $pageSource): string
    {
        $key = mb_strtolower(trim((string) $pageSource));
        if ($key === '') {
            return self::DIRECT_SOURCE;
        }
        if (str_starts_with($key, 'landing page')) {
            return 'Landing Page';
        }

        return self::SOURCE_NAMES[$key] ?? \Illuminate\Support\Str::of($key)->replace(['-', '_'], ' ')->title()->limit(100, '')->toString();
    }

    /**
     * Fills a missing stage (the owner's default stage — its own, else Super Admin's) and source
     * (matched by name among the owner's own + global sources). A missing website / system source
     * (LeadSource::systemNames()) is re-added to Super Admin's global list; any other new source
     * belongs to the lead's account, which can edit / delete it (no account → Super Admin). Doesn't save.
     */
    public function applyStageAndSourceDefaults(): void
    {
        $ownerId = $this->portal_user_id ?: LeadStage::globalOwnerId();

        if (!$this->stage_id) {
            $this->stage_id = LeadStage::forOwner($ownerId)
                ->orderByDesc('is_default')
                ->orderByRaw('CASE WHEN lead_stages.portal_user_id = ? THEN 0 ELSE 1 END', [$ownerId])
                ->orderBy('order_index')
                ->value('lead_stages.id');
        }

        if (!$this->source_id) {
            $name = self::sourceNameFor($this->page_source);
            $sourceOwnerId = LeadSource::isSystemName($name) ? LeadSource::globalOwnerId() : $ownerId;
            $this->source_id = LeadSource::forOwner($ownerId)->whereRaw('LOWER(lead_sources.name) = ?', [mb_strtolower($name)])->value('lead_sources.id')
                ?? LeadSource::create([
                    'portal_user_id' => $sourceOwnerId,
                    'name' => $name,
                    'order_index' => (int) LeadSource::where('portal_user_id', $sourceOwnerId)->max('order_index') + 1,
                ])->id;
        }
    }

    /** Off while a system clean-up / merge writes to a lead, so it doesn't show up as team activity. */
    private static bool $logActivity = true;

    public static function withoutActivityLog(callable $callback): mixed
    {
        $previous = self::$logActivity;
        self::$logActivity = false;
        try {
            return $callback();
        } finally {
            self::$logActivity = $previous;
        }
    }

    /** Adds the given email / phone to this lead's known contacts (already-known ones are ignored). */
    public function recordContacts(?string $email, ?string $phone, ?string $countryCode = null): void
    {
        $rows = array_map(
            fn ($row) => $row + ['lead_id' => $this->id, 'created_at' => now(), 'updated_at' => now()],
            LeadContact::rowsFor($email, $phone, $countryCode)
        );

        if ($rows) {
            LeadContact::insertOrIgnore($rows);
        }
    }

    public function contacts()
    {
        return $this->hasMany(LeadContact::class)->orderBy('id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    /** The logged-in customer who submitted this enquiry, if any (null for guest submissions). */
    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function stage()
    {
        return $this->belongsTo(LeadStage::class, 'stage_id');
    }

    public function source()
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function tags()
    {
        return $this->belongsToMany(LeadTag::class, 'lead_tag_pivot');
    }

    /** Activity history (notes today; Follow-ups/Calls/Site Visits later) — see LeadNoteService. */
    public function notesHistory()
    {
        return $this->hasMany(LeadNote::class);
    }

    /** The agent working this lead (null = agency-level unassigned). */
    public function agent()
    {
        return $this->belongsTo(PortalUser::class, 'agent_id');
    }

    public function assignmentHistory()
    {
        return $this->hasMany(LeadAssignmentHistory::class)->orderBy('assigned_at')->orderBy('id');
    }

    /**
     * Every CRM lead query goes through here, so this is the one place lead visibility is decided.
     * null = Super Admin's global view. See scopeVisibleTo() for the per-account rules.
     */
    public function scopeForOwner($query, ?int $ownerId)
    {
        if (!$ownerId) {
            return $query;
        }

        $viewer = PortalUser::query()->select(['id', 'type', 'company_id'])->find($ownerId);

        return $viewer ? $this->scopeVisibleTo($query, $viewer) : $query->whereRaw('1 = 0');
    }

    /**
     * - Agency / independent agent: every lead they own.
     * - Agency agent: their own personal leads, plus the agency's leads assigned to them — only
     *   while they are still in that agency (a departed agent loses sight of the agency's leads,
     *   but the assignment itself stays on record).
     */
    public function scopeVisibleTo($query, PortalUser $viewer)
    {
        return $query->where(function ($q) use ($viewer) {
            $q->where('leads.portal_user_id', $viewer->id);

            if ($viewer->type === 'agent' && $viewer->company_id) {
                $q->orWhere(fn ($assigned) => $assigned
                    ->where('leads.agent_id', $viewer->id)
                    ->where('leads.portal_user_id', $viewer->company_id));
            }
        });
    }

    /** Strict ownership — delete/restore/reassign are for the owning account only, never an assigned agent. */
    public function scopeOwnedBy($query, ?int $ownerId)
    {
        return $query->when($ownerId, fn ($q) => $q->where('leads.portal_user_id', $ownerId));
    }

    public function assignmentLabel(): ?string
    {
        return match ($this->assignment_type) {
            self::ASSIGN_PROPERTY_AGENT => 'Property agent',
            self::ASSIGN_ROUND_ROBIN => 'Round robin',
            self::ASSIGN_AGENCY_UNASSIGNED => 'Unassigned',
            self::ASSIGN_MANUAL => 'Manual',
            self::ASSIGN_REASSIGNED => 'Reassigned',
            default => null,
        };
    }

    public function getFormattedPhoneAttribute(): ?string
    {
        $phone = trim((string) $this->phone);

        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '+') || !$this->phone_country_code) {
            return $phone;
        }

        return trim($this->phone_country_code.' '.$phone);
    }
}
