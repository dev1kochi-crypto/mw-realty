<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadTag extends Model
{
    use Concerns\SharedMasterData;

    protected $fillable = ['portal_user_id', 'name', 'color'];

    public const DEFAULTS = [
        ['name' => 'Buyer Lead', 'color' => '#0ea5e9'],
        ['name' => 'Seller Lead', 'color' => '#14b8a6'],
        ['name' => 'Hot Lead', 'color' => '#ef4444'],
        ['name' => 'Follow Up', 'color' => '#f59e0b'],
        ['name' => 'VIP', 'color' => '#8b5cf6'],
    ];

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function leads()
    {
        return $this->belongsToMany(Lead::class, 'lead_tag_pivot');
    }

    /** Tags sit on a pivot — a lead that already has this tag just drops the copy. */
    protected function moveLeadsFrom(array $fromIds): void
    {
        $pivot = \Illuminate\Support\Facades\DB::table('lead_tag_pivot');
        $alreadyTagged = (clone $pivot)->where('lead_tag_id', $this->id)->pluck('lead_id')->all();

        (clone $pivot)->whereIn('lead_tag_id', $fromIds)->whereIn('lead_id', $alreadyTagged)->delete();
        // Two copies on one lead would collide once both point here — keep one row per lead.
        $rows = (clone $pivot)->whereIn('lead_tag_id', $fromIds)->orderBy('lead_id')->get(['lead_id', 'lead_tag_id']);
        foreach ($rows->groupBy('lead_id') as $leadId => $leadRows) {
            foreach ($leadRows->skip(1) as $extra) {
                (clone $pivot)->where('lead_id', $leadId)->where('lead_tag_id', $extra->lead_tag_id)->delete();
            }
        }
        (clone $pivot)->whereIn('lead_tag_id', $fromIds)->update(['lead_tag_id' => $this->id]);
    }

    /** The defaults are Super Admin's global tags, seeded once (idempotent) — no per-account copies. */
    public static function seedDefaultsFor(?PortalUser $owner = null): void
    {
        $owner = PortalUser::findOrFail(static::globalOwnerId());
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
