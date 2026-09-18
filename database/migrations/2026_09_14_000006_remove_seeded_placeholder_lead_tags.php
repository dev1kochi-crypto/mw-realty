<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Remove the old sample tags that were never intended as usable master data. */
    public function up(): void
    {
        $adminOwnerId = DB::table('portal_users')
            ->where('email', 'admin@internal.mw-realty')
            ->value('id');

        if (!$adminOwnerId) {
            return;
        }

        $placeholderIds = DB::table('lead_tags')
            ->where('portal_user_id', $adminOwnerId)
            ->whereIn('name', ['Tag 1', 'Tag 2', 'Tag 3', 'Tag 4', 'Tag 5'])
            ->pluck('id');

        if ($placeholderIds->isEmpty()) {
            return;
        }

        DB::table('lead_tag_pivot')->whereIn('lead_tag_id', $placeholderIds)->delete();
        DB::table('lead_tags')->whereIn('id', $placeholderIds)->delete();
    }

    public function down(): void
    {
        // Placeholder data should not be restored.
    }
};
