<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listings the account deleted itself from the CRM (single or bulk delete). On a paid plan the
 * allowance is "listings added this month" — deleting one must not give the slot back, so the
 * listing's added date is kept here and PortalUser::usedPropertySlots() still counts it for that
 * month. Free plans count the listings they have, so a delete there still frees the slot.
 * Listings paid for with credits never counted against the plan and aren't recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deleted_listing_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->unsignedBigInteger('property_id');      // the deleted listing's id, for reference only
            $table->timestamp('listing_created_at');         // the month whose allowance it used
            $table->timestamp('deleted_at')->useCurrent();

            $table->index(['portal_user_id', 'listing_created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_listing_slots');
    }
};
