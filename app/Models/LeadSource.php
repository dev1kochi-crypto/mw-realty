<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadSource extends Model
{
    protected $fillable = ['portal_user_id', 'name', 'order_index'];

    public const DEFAULTS = ['Website', 'Referral', 'Walk-in', 'Social Media', 'Property Portal'];

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'source_id');
    }

    public function scopeForOwner($query, ?int $ownerId)
    {
        return $query->when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));
    }

    /** Idempotent — only seeds if this owner has no sources yet. */
    public static function seedDefaultsFor(PortalUser $owner): void
    {
        if (static::where('portal_user_id', $owner->id)->exists()) {
            return;
        }

        foreach (static::DEFAULTS as $index => $name) {
            static::create([
                'portal_user_id' => $owner->id,
                'name' => $name,
                'order_index' => $index + 1,
            ]);
        }
    }
}
