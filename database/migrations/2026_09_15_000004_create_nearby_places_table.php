<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global, admin-managed master list (school/hospital/restaurant/attraction/...) — the same
        // physical landmark is reused across many properties, so it isn't scoped per portal owner.
        Schema::create('nearby_places', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // school, hospital, restaurant, attraction, ... (free string, not a hard enum)
            $table->string('name');
            $table->string('icon')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('category');
        });

        Schema::create('property_nearby_place', function (Blueprint $table) {
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nearby_place_id')->constrained()->cascadeOnDelete();
            $table->primary(['property_id', 'nearby_place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_nearby_place');
        Schema::dropIfExists('nearby_places');
    }
};
