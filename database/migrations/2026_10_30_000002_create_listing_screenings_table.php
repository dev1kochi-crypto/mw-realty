<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per automated screening of a listing on save (ListingReviewService::screen) — the basis of
 * Listing Review's "time saved" measure: screenings nobody had to check by hand.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('listing_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->boolean('flagged')->default(false); // left waiting for Super Admin
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down()
    {
        Schema::dropIfExists('listing_screenings');
    }
};
