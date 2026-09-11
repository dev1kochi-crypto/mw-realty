<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('status');
            $table->json('document_status')->nullable()->after('rera_certificate_document');
        });

        // Backfill so existing rows have a sane "pending since" baseline instead of null.
        DB::table('portal_users')->whereNull('status_changed_at')->update([
            'status_changed_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn(['status_changed_at', 'document_status']);
        });
    }
};
