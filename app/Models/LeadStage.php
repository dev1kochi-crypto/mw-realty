<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadStage extends Model
{
    protected $fillable = ['portal_user_id', 'name', 'color', 'order_index', 'is_closed', 'is_default'];

    protected $casts = [
        'is_closed' => 'boolean',
        'is_default' => 'boolean',
    ];

    public const DEFAULTS = [
        ['name' => 'New', 'color' => '#4f46e5', 'is_default' => true],
        ['name' => 'Contacted', 'color' => '#f59e0b'],
        ['name' => 'Site Visit Scheduled', 'color' => '#0ea5e9'],
        ['name' => 'Negotiation', 'color' => '#8b5cf6'],
        ['name' => 'Closed Won', 'color' => '#14b8a6', 'is_closed' => true],
        ['name' => 'Closed Lost', 'color' => '#ef4444', 'is_closed' => true],
    ];

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'stage_id');
    }

    public function scopeForOwner($query, ?int $ownerId)
    {
        return $query->when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));
    }

    /** Idempotent — only seeds if this owner has no stages yet. */
    public static function seedDefaultsFor(PortalUser $owner): void
    {
        if (static::where('portal_user_id', $owner->id)->exists()) {
            return;
        }

        foreach (static::DEFAULTS as $index => $stage) {
            static::create([
                'portal_user_id' => $owner->id,
                'name' => $stage['name'],
                'color' => $stage['color'],
                'order_index' => $index + 1,
                'is_closed' => $stage['is_closed'] ?? false,
                'is_default' => $stage['is_default'] ?? false,
            ]);
        }
    }
}
