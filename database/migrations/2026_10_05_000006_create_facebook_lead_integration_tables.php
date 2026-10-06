<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facebook Lead Ads → CRM (CRM › Integrations): the Facebook Pages each account connected, and every
 * Facebook lead received — by its leadgen id, so a webhook retry or a manual sync never imports it twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_page_connections', function (Blueprint $table) {
            $table->id();
            // The CRM account the page's leads go to (Super Admin's own = the Admin owner row).
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            // One account per page: the webhook names only the page, so it must point to one CRM.
            $table->string('page_id', 64)->unique();
            $table->string('page_name');
            $table->text('page_access_token'); // encrypted (model cast)
            $table->string('connected_by')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_lead_at')->nullable();
            $table->unsignedInteger('leads_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('facebook_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_page_connection_id')->nullable()->constrained('facebook_page_connections')->nullOnDelete();
            $table->string('leadgen_id', 64)->unique();
            $table->string('page_id', 64)->index();
            $table->string('form_id', 64)->nullable();
            $table->string('ad_id', 64)->nullable();
            $table->string('ad_name')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_leads');
        Schema::dropIfExists('facebook_page_connections');
    }
};
