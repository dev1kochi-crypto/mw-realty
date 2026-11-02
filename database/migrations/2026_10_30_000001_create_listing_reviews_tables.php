<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listing Review (App\Services\ListingReview\ListingReviewService): a unit another agency already
 * lists, and listings with risk signals, are held off the website until Super Admin approves or
 * rejects them.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('properties', function (Blueprint $table) {
            // Off the website while waiting for review, or rejected (until the agency fixes and resubmits).
            $table->boolean('review_hold')->default(false)->after('compliance_reviewed_by');
        });

        // Perceptual (dHash) fingerprint per gallery photo — near-identical photos have close hashes.
        Schema::create('property_image_hashes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->bigInteger('hash');
            $table->timestamps();
        });

        Schema::create('listing_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);                      // duplicate | risk
            $table->string('status', 20)->default('open');   // open | resolved
            $table->string('resolution', 20)->nullable();    // approved | rejected | auto_cleared
            $table->json('signals')->nullable();             // risk: [{code, label, detail}]
            $table->text('resolution_note')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'status']);
        });

        Schema::create('listing_review_property', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->json('reasons')->nullable();
            $table->boolean('held')->default(false);         // waiting for this review, off the website
            $table->string('outcome', 20)->nullable();       // approved | rejected
            $table->text('note')->nullable();                // Super Admin's reason / note for this listing
            $table->timestamps();
            $table->unique(['listing_review_id', 'property_id']);
        });

        // Listings Super Admin approved side by side (different agencies advertising the same unit) —
        // never flagged against each other again.
        Schema::create('listing_distinct_pairs', function (Blueprint $table) {
            $table->unsignedBigInteger('low_id');
            $table->unsignedBigInteger('high_id');
            $table->timestamp('created_at')->nullable();
            $table->primary(['low_id', 'high_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('listing_distinct_pairs');
        Schema::dropIfExists('listing_review_property');
        Schema::dropIfExists('listing_reviews');
        Schema::dropIfExists('property_image_hashes');
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('review_hold');
        });
    }
};
