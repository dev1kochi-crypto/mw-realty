<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A visitor enquiry about one specific property, routed to that property's
 * owning company/agent. Distinct from App\Models\CmsKit\Enquiry, which is
 * for general site-wide contact-us submissions seen only by admin.
 */
class Lead extends Model
{
    protected $fillable = [
        'property_id',
        'portal_user_id',
        'name',
        'email',
        'phone',
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

    public function scopeForOwner($query, ?int $ownerId)
    {
        return $query->when($ownerId, fn ($q) => $q->where('leads.portal_user_id', $ownerId));
    }
}
