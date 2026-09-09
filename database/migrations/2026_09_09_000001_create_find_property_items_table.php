<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('find_property_items', function (Blueprint $table) {
            $table->id();

            $table->string('image')->nullable();
            $table->string('image_alt')->nullable();

            // Multilingual (title/display label)
            $table->json('translations')->nullable();

            // Matches a FilterValue.value under the "property_type" filter — clicking
            // the card links to the property listing pre-filtered by this type.
            // The property count shown on the card is computed live, not stored here,
            // so it never goes stale as listings are added/removed.
            $table->string('property_type')->nullable();

            $table->integer('order_index')->default(0);
            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('find_property_items');
    }
};
