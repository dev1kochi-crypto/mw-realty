<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            // Multilingual fields (title, description, address, city, country) — one JSON blob per language.
            $table->json('translations')->nullable();

            $table->string('slug')->unique();
            $table->string('reference_no')->nullable();

            // Core filterable columns (kept as real columns for fast WHERE/ORDER BY).
            // These store the FilterValue "value" slug (e.g. "apartment"), not a label —
            // the language-specific label lives on filter_values.translations and is
            // resolved via Property::filterLabel(). No translation needed here.
            $table->string('listing_type')->nullable();       // sale, rent -> value of "listing_type" filter
            $table->string('completion_status')->nullable();  // ready, off_plan -> value of "completion_status" filter
            $table->string('property_type')->nullable();       // apartment, villa... -> value of "property_type" filter
            $table->string('location')->nullable();            // community/area -> value of "location" filter

            $table->unsignedInteger('bedrooms')->nullable();
            $table->unsignedInteger('bathrooms')->nullable();
            $table->unsignedInteger('sqft')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('currency', 10)->default('AED');

            // Cover/primary image only — the rest of the gallery lives in property_images.
            $table->string('image')->nullable();
            $table->string('image_alt')->nullable();

            $table->boolean('featured')->default(false);
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->index('listing_type');
            $table->index('completion_status');
            $table->index('property_type');
            $table->index('location');
        });
    }

    public function down()
    {
        Schema::dropIfExists('properties');
    }
};
