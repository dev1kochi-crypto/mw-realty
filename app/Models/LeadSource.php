<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadSource extends Model
{
    use Concerns\SharedMasterData;

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

    /** The defaults are Super Admin's global sources, seeded once (idempotent) — no per-account copies. */
    public static function seedDefaultsFor(?PortalUser $owner = null): void
    {
        $owner = PortalUser::findOrFail(static::globalOwnerId());
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
