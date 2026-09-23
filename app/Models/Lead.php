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

    protected $casts = [
        'extra_fields' => 'array',
    ];

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

    public function scopeForOwner($query, ?int $ownerId)
    {
        return $query->when($ownerId, fn ($q) => $q->where('leads.portal_user_id', $ownerId));
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
