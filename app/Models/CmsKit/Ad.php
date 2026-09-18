<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'name',
        'image',
        'image_alt',
        'mobile_image',
        'link_url',
        'placement',
        'starts_at',
        'ends_at',
        'order_index',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /** Active AND (no date window set, or today falls inside it) — what a frontend placement query should use. */
    public function scopeCurrentlyRunning($query)
    {
        $today = now()->toDateString();

        return $query->active()
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $today));
    }

    public function scopeForPlacement($query, string $placement)
    {
        return $query->where('placement', $placement);
    }
}
