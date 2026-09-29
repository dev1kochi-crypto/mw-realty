<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadStage extends Model
{
    use Concerns\SharedMasterData;

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

    /** Closed stages whose name reads as a lost deal; every other closed stage counts as won. */
    public const LOST_PATTERN = '/\b(lost|drop|dropped|cancel|cancelled|canceled|reject|rejected|dead|junk)\b/i';

    public static function nameIsLost(?string $name): bool
    {
        return (bool) preg_match(self::LOST_PATTERN, (string) $name);
    }

    public function isWon(): bool
    {
        return $this->is_closed && !self::nameIsLost($this->name);
    }

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'stage_id');
    }

    /** The defaults are Super Admin's global stages, seeded once (idempotent) — no per-account copies. */
    public static function seedDefaultsFor(?PortalUser $owner = null): void
    {
        $owner = PortalUser::findOrFail(static::globalOwnerId());
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
