<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A property can have no agent/company assigned (Property::portal_user_id null), and
 * LeadCaptureController now still captures the enquiry in that case instead of rejecting
 * it — notifying admin so it can be transferred to an agent later (see cms.unassigned-leads).
 * Raw SQL rather than Schema::table()->change(): a `foreignId` column change needs
 * doctrine/dbal, which this project doesn't otherwise depend on.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (the test database) has no MODIFY — Laravel 12 can change() the column natively there.
        if (DB::getDriverName() !== 'mysql') {
            \Illuminate\Support\Facades\Schema::table('leads', fn ($t) => $t->unsignedBigInteger('portal_user_id')->nullable()->change());

            return;
        }

        DB::statement('ALTER TABLE leads MODIFY portal_user_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE leads MODIFY portal_user_id BIGINT UNSIGNED NOT NULL');
    }
};
