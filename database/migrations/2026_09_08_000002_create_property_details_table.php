<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('property_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->json('amenities')->nullable();
            $table->unsignedInteger('year_built')->nullable();
            $table->string('floor')->nullable();
            $table->unsignedInteger('parking')->nullable();
            $table->boolean('furnished')->default(false);
            $table->string('view')->nullable();

            // Catch-all for any future property field that doesn't warrant a migration yet.
            $table->json('extra_attributes')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_details');
    }
};
