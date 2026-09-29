<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admin's hand-picked "Realty Property" list (portal › Listings › Marketing Properties):
 * any agency's / agent's listings, in a chosen order. The home section shows the first 12;
 * /marketing-properties shows them all. See MarketingPropertyService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained('properties')->cascadeOnDelete();
            $table->unsignedInteger('order_index')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_properties');
    }
};
