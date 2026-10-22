<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Background lead import of a Facebook Page (ImportFacebookPageLeads — existing leads at connect time,
 * or the leads missed while it needed reconnecting): its progress, shown live on CRM › Integrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_page_connections', function (Blueprint $table) {
            $table->string('import_status', 16)->nullable()->after('reconnect_notified_at'); // queued | running | done | failed
            $table->unsignedInteger('import_added')->default(0)->after('import_status');
            $table->unsignedInteger('import_skipped')->default(0)->after('import_added');
            $table->text('import_error')->nullable()->after('import_skipped');
            $table->timestamp('import_finished_at')->nullable()->after('import_error');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_page_connections', function (Blueprint $table) {
            $table->dropColumn(['import_status', 'import_added', 'import_skipped', 'import_error', 'import_finished_at']);
        });
    }
};
