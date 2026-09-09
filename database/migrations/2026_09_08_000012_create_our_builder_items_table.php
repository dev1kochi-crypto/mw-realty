<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('our_builder_items', function (Blueprint $table) {
            $table->id();

            $table->string('image')->nullable(); // builder logo
            $table->string('image_alt')->nullable();

            $table->integer('order_index')->default(0);
            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('our_builder_items');
    }
};
