<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('why_choose_us_items', function (Blueprint $table) {
            $table->id();

            $table->string('image')->nullable(); // icon
            $table->string('image_alt')->nullable();

            // Multilingual (title, description)
            $table->json('translations')->nullable();

            $table->integer('order_index')->default(0);
            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('why_choose_us_items');
    }
};
