<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lead_notes is the lead's whole activity history (see LeadNoteService): team notes plus
 * system entries — repeat enquiries, stage / source / status / tag / detail changes. `meta`
 * holds the structured side of a system entry (e.g. an enquiry's page_source + property, a
 * stage change's from/to) so the lead page can show a Source history and badges, not just text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_notes', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('body');
            $table->index(['lead_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('lead_notes', function (Blueprint $table) {
            $table->dropIndex(['lead_id', 'type']);
            $table->dropColumn('meta');
        });
    }
};
