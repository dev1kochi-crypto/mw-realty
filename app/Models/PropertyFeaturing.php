<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A single plan-quota "feature this listing" use — see App\Services\FeaturedListingService. */
class PropertyFeaturing extends Model
{
    protected $fillable = ['portal_user_id', 'property_id', 'starts_at', 'ends_at', 'stopped_at'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function scopeThisMonth($query)
    {
        return $query->where('starts_at', '>=', now()->startOfMonth());
    }
}
