<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('filters', function (Blueprint $table) {
            $table->id();

            // Column on `properties` this filter queries against, e.g. property_type, bedrooms, price.
            $table->string('key')->unique();

            // Label per language, e.g. { "en": "Property Type", "ar": "..." }
            $table->json('translations')->nullable();

            // select -> uses filter_values as options; range/number -> min/max read live from properties table.
            $table->string('type')->default('select');

            // Where this filter is shown: any subset of ["home","listing"].
            $table->json('show_on')->nullable();

            $table->integer('order_index')->default(0);
            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('filters');
    }
};
