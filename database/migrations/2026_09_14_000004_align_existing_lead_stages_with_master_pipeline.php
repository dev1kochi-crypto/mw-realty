<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $masterOwnerId = DB::table('portal_users')
            ->where('email', 'admin@internal.mw-realty')
            ->value('id');

        if (!$masterOwnerId) {
            return;
        }

        $masterStageIds = DB::table('lead_stages')
            ->where('portal_user_id', $masterOwnerId)
            ->pluck('id', 'name');

        $stageMap = [
            'New' => 'New',
            'Contacted' => 'Connected',
            'Site Visit Scheduled' => 'Connected',
            'Negotiation' => 'Negotiation',
            'Closed Won' => 'Closed',
            'Closed Lost' => 'Dropped',
        ];

        foreach ($stageMap as $oldStage => $masterStage) {
            $masterStageId = $masterStageIds->get($masterStage);

            if (!$masterStageId) {
                continue;
            }

            $oldStageIds = DB::table('lead_stages')
                ->where('name', $oldStage)
                ->pluck('id');

            if ($oldStageIds->isNotEmpty()) {
                DB::table('leads')
                    ->whereIn('stage_id', $oldStageIds)
                    ->update([
                        'stage_id' => $masterStageId,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // The prior per-owner stage id is not recoverable after consolidation.
    }
};
