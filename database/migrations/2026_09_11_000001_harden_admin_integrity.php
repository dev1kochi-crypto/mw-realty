<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fail before changing schema if old data needs a manual reconciliation.
        if (DB::table('property_details')->groupBy('property_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate property details must be reconciled before migrating.');
        }
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id');
            $table->string('action');
            $table->text('target')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['actor_type', 'actor_id']);
        });
        Schema::table('property_details', fn (Blueprint $t) => $t->unique('property_id'));
        Schema::table('portal_users', function (Blueprint $t) {
            $t->index(['type', 'status', 'created_at']);
            $t->index(['status', 'status_changed_at']);
            $t->index(['payment_status', 'last_payment_at']);
        });
        Schema::table('properties', fn (Blueprint $t) => $t->index(['portal_user_id', 'created_at']));
        Schema::table('enquiries', function (Blueprint $t) {
            $t->index(['portal_user_id', 'status', 'created_at']);
            $t->index(['page_source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $t) {
            $t->dropIndex(['portal_user_id', 'status', 'created_at']);
            $t->dropIndex(['page_source', 'created_at']);
        });
        Schema::table('properties', fn (Blueprint $t) => $t->dropIndex(['portal_user_id', 'created_at']));
        Schema::table('portal_users', function (Blueprint $t) {
            $t->dropIndex(['type', 'status', 'created_at']);
            $t->dropIndex(['status', 'status_changed_at']);
            $t->dropIndex(['payment_status', 'last_payment_at']);
        });
        Schema::table('property_details', fn (Blueprint $t) => $t->dropUnique(['property_id']));
        Schema::dropIfExists('admin_audit_logs');
    }
};
