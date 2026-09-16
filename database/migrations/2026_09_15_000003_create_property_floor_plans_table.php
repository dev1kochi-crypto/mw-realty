<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_floor_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // e.g. "1 Bedroom Apartments"
            $table->unsignedInteger('size_from')->nullable();
            $table->unsignedInteger('size_to')->nullable();
            $table->decimal('price_from', 15, 2)->nullable();
            $table->decimal('price_to', 15, 2)->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_floor_plans');
    }
};
