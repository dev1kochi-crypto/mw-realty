<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undoes 2026_09_08_000008: leads are a distinct concept from general
 * contact-us enquiries and get their own `leads` table instead (see
 * create_leads_table). No code path ever wrote property_id/portal_user_id
 * here, so this is safe to drop with no data migration needed.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
            $table->dropConstrainedForeignId('portal_user_id');
            $table->dropColumn(['status', 'notes']);
        });
    }

    public function down()
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('id')
                ->constrained('properties')->nullOnDelete();
            $table->foreignId('portal_user_id')->nullable()->after('property_id')
                ->constrained('portal_users')->nullOnDelete();
            $table->enum('status', ['new', 'contacted', 'closed'])->default('new')->after('message');
            $table->text('notes')->nullable()->after('status');
        });
    }
};
