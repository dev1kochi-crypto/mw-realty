<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            // Multilingual (name, description, features[])
            $table->json('translations')->nullable();

            $table->decimal('price', 10, 2)->default(0);
            $table->string('billing_cycle')->default('monthly'); // free, monthly, yearly, one_time

            // Null = unlimited listings
            $table->unsignedInteger('property_limit')->nullable();

            $table->boolean('is_popular')->default(false);
            $table->integer('order_index')->default(0);
            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plans');
    }
};
