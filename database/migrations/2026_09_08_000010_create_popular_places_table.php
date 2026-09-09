<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('popular_places', function (Blueprint $table) {
            $table->id();

            $table->string('image')->nullable();
            $table->string('image_alt')->nullable();

            // Multilingual (name)
            $table->json('translations')->nullable();

            $table->integer('order_index')->default(0);
            $table->json('extra_fields')->nullable();
            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('popular_places');
    }
};
