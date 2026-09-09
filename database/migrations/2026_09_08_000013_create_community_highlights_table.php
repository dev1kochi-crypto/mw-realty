<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('community_highlights', function (Blueprint $table) {
            $table->id();

            $table->string('image')->nullable(); // icon
            $table->string('image_alt')->nullable();

            // Multilingual (title/display label)
            $table->json('translations')->nullable();

            // Matches a FilterValue.value under the "location" filter — links this
            // card to the same community used for property search filtering.
            $table->string('community')->nullable();

            $table->integer('order_index')->default(0);
            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('community_highlights');
    }
};
