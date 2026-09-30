<?php

use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LeadTag;
use Illuminate\Database\Migrations\Migration;

/**
 * Stage / Tag / Source names are unique across an account's list (its own + Super Admin's global
 * items). Older accounts still hold their own copies of global names (per-account defaults seeded
 * before the global lists existed) — fold each into the global item: leads move over, copies go.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([LeadStage::class, LeadTag::class, LeadSource::class] as $model) {
            $model::where('portal_user_id', $model::globalOwnerId())->get()
                ->each(fn ($item) => $item->absorbOwnCopies());
        }
    }

    public function down(): void
    {
        // Merged copies can't be split back out.
    }
};
