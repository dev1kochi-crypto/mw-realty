<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('property_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            // Stored file is named using the property's own id, e.g. property-12-1.jpg,
            // so files are traceable back to their property without a DB lookup.
            $table->string('image');
            $table->string('image_alt')->nullable();

            $table->integer('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_images');
    }
};
