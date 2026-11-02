<?php

namespace App\Models;

use App\Models\CmsKit\Admin;
use Illuminate\Database\Eloquent\Model;

/**
 * A Listing Review case (App\Services\ListingReview\ListingReviewService): a unit listed by more
 * than one agency, or one listing with risk signals (price far off the area, ad ≠ permit, resubmitted
 * after a rejection).
 */
class ListingReview extends Model
{
    public const DUPLICATE = 'duplicate';
    public const RISK = 'risk';

    public const OPEN = 'open';
    public const RESOLVED = 'resolved';

    protected $fillable = ['type', 'status', 'resolution', 'signals', 'resolution_note', 'resolved_by', 'resolved_at'];

    protected $casts = [
        'signals' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function properties()
    {
        return $this->belongsToMany(Property::class, 'listing_review_property')
            ->withPivot(['id', 'reasons', 'held', 'outcome', 'note'])
            ->withTimestamps();
    }

    public function resolver()
    {
        return $this->belongsTo(Admin::class, 'resolved_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::OPEN);
    }
}
