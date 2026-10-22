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

    /**
     * Sources the website forms and system channels stamp on leads (Lead::SOURCE_NAMES) — Super
     * Admin's global sources, so every agency / agent gets them. Any other channel's source is
     * created for the lead's own account instead (Lead::applyStageAndSourceDefaults()).
     */
    public static function systemNames(): array
    {
        return array_values(array_unique(array_merge(array_values(Lead::SOURCE_NAMES), ['Landing Page'])));
    }

    public static function isSystemName(string $name): bool
    {
        return in_array(mb_strtolower($name), array_map('mb_strtolower', static::systemNames()), true);
    }

    /** Adds whichever website / system sources Super Admin doesn't have yet (idempotent, by name). */
    public static function ensureSystemSources(): void
    {
        $globalId = static::globalOwnerId();
        $existing = static::where('portal_user_id', $globalId)->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $order = (int) static::where('portal_user_id', $globalId)->max('order_index');

        foreach (static::systemNames() as $name) {
            if (!in_array(mb_strtolower($name), $existing, true)) {
                static::create(['portal_user_id' => $globalId, 'name' => $name, 'order_index' => ++$order]);
            }
        }
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
