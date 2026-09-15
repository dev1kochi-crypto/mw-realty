<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadTag extends Model
{
    protected $fillable = ['portal_user_id', 'name', 'color'];

    public const DEFAULTS = [
        ['name' => 'Hot Lead', 'color' => '#ef4444'],
        ['name' => 'Follow Up', 'color' => '#f59e0b'],
        ['name' => 'VIP', 'color' => '#8b5cf6'],
        ['name' => 'Cold', 'color' => '#3b82f6'],
        ['name' => 'High Budget', 'color' => '#14b8a6'],
    ];

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function leads()
    {
        return $this->belongsToMany(Lead::class, 'lead_tag_pivot');
    }

    public function scopeForOwner($query, ?int $ownerId)
    {
        return $query->when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));
    }

    /** Idempotent — only seeds if this owner has no tags yet. */
    public static function seedDefaultsFor(PortalUser $owner): void
    {
        if (static::where('portal_user_id', $owner->id)->exists()) {
            return;
        }

        foreach (static::DEFAULTS as $tag) {
            static::create([
                'portal_user_id' => $owner->id,
                'name' => $tag['name'],
                'color' => $tag['color'],
            ]);
        }
    }
}
