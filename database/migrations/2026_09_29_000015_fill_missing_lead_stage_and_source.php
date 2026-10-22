<?php

use App\Models\Lead;
use Illuminate\Database\Migrations\Migration;

/**
 * Every lead now always has a stage and a source (Lead::applyStageAndSourceDefaults()) — give the
 * existing leads without one the same defaults. Quiet saves: not recorded as team activity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Lead::withTrashed()->where(fn ($q) => $q->whereNull('stage_id')->orWhereNull('source_id'))
            ->chunkById(200, function ($leads) {
                foreach ($leads as $lead) {
                    $lead->applyStageAndSourceDefaults();
                    $lead->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Data fill only — nothing to undo.
    }
};
