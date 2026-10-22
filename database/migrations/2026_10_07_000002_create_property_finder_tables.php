<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM › Integrations › Property Finder: an agency's / independent agent's Property Finder API key,
 * and every listing ever imported from it (property_finder_imports) — so a listing is never imported
 * twice, and each import waits for Super Admin review before it can go on the website.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_finder_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->unique()->constrained('portal_users')->cascadeOnDelete();
            $table->text('api_key');     // encrypted
            $table->text('api_secret');  // encrypted
            $table->string('api_key_hash', 64)->unique(); // one MW Realty account per Property Finder key
            $table->string('connected_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            // Background sync: queued | running | done | failed (null = never ran)
            $table->string('sync_status', 16)->nullable();
            $table->string('sync_mode', 8)->nullable(); // all | new
            $table->unsignedInteger('sync_added')->default(0);
            $table->unsignedInteger('sync_skipped')->default(0);
            $table->unsignedInteger('sync_failed')->default(0);
            $table->text('sync_error')->nullable();
            $table->timestamp('sync_finished_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_full_sync_at')->nullable();
            $table->unsignedInteger('imported_count')->default(0);
            $table->timestamps();
        });

        Schema::create('property_finder_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_finder_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('portal_user_id')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->string('pf_listing_id', 64)->unique();
            $table->string('pf_reference', 100)->nullable();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            // pending → approved (may go live, permit rules permitting) | rejected (property removed)
            $table->string('review_status', 16)->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamp('pf_created_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_finder_imports');
        Schema::dropIfExists('property_finder_connections');
    }
};
