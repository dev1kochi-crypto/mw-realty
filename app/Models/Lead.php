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
    ];

    public const ASSIGN_PROPERTY_AGENT = 'property_agent';
    public const ASSIGN_ROUND_ROBIN = 'round_robin';
    public const ASSIGN_AGENCY_UNASSIGNED = 'agency_unassigned';
    public const ASSIGN_MANUAL = 'manual';
    public const ASSIGN_REASSIGNED = 'reassigned';

    protected $casts = [
        'extra_fields' => 'array',
        'assigned_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
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
