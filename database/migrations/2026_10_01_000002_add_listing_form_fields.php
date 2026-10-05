<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Property form fields that were missing compared with the portal listing flow (Core details /
 * Specifications / Price), plus an icon per master option — Amenities, Easy Access and Attributes
 * are now picked from Master › Property Options instead of typed per listing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filter_values', function (Blueprint $table) {
            // Cloudinary URL of the option's icon (Amenities / Easy Access / Attributes).
            $table->string('icon')->nullable()->after('translations');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('emirate', 100)->nullable()->after('category');
            $table->string('rental_period', 50)->nullable()->after('listing_type');
            // Open house / viewing dates (Y-m-d). Empty = available immediately.
            $table->json('available_dates')->nullable()->after('rental_period');
        });

        Schema::table('property_details', function (Blueprint $table) {
            $table->string('developer')->nullable()->after('property_attributes');
            $table->string('unit_number', 100)->nullable()->after('developer'); // internal only, never shown on the site
            $table->string('owner_name')->nullable()->after('unit_number');
            $table->boolean('upgraded')->default(false)->after('furnished');
            $table->string('video_tour_url', 2048)->nullable()->after('virtual_tour_url');
            $table->unsignedTinyInteger('cheques')->nullable()->after('security_deposit');
        });
    }

    public function down(): void
    {
        Schema::table('property_details', function (Blueprint $table) {
            $table->dropColumn(['developer', 'unit_number', 'owner_name', 'upgraded', 'video_tour_url', 'cheques']);
        });
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['emirate', 'rental_period', 'available_dates']);
        });
        Schema::table('filter_values', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
