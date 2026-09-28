<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a lead entered a closed (won/lost) stage — the date the Revenue report counts a deal on.
 * Kept up to date by Lead::saving(); existing closed leads are backfilled with their last update.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('stage_id');
            $table->index(['portal_user_id', 'closed_at']);
        });

        DB::table('leads')
            ->whereNotNull('stage_id')
            ->whereIn('stage_id', DB::table('lead_stages')->where('is_closed', true)->select('id'))
            ->update(['closed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['portal_user_id', 'closed_at']);
            $table->dropColumn('closed_at');
        });
    }
};
