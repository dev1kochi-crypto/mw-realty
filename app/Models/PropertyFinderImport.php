<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One Property Finder listing imported as a property. pf_listing_id is unique: a listing is never
 * imported twice, whichever account syncs it, and a rejected one is not brought back by later syncs.
 * Imported properties stay off the website until Super Admin approves them here.
 */
class PropertyFinderImport extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'property_finder_connection_id', 'portal_user_id', 'pf_listing_id', 'pf_reference', 'property_id',
        'review_status', 'reviewed_at', 'reviewed_by', 'review_note', 'pf_created_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime', 'pf_created_at' => 'datetime'];

    public function connection()
    {
        return $this->belongsTo(PropertyFinderConnection::class, 'property_finder_connection_id');
    }

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
