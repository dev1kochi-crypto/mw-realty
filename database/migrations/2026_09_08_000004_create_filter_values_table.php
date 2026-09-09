<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('filter_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filter_id')->constrained()->cascadeOnDelete();

            // Actual value stored on properties.<filter.key>, e.g. "apartment".
            $table->string('value');

            // Label per language, e.g. { "en": "Apartment", "ar": "..." }
            $table->json('translations')->nullable();

            $table->integer('order_index')->default(0);
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->unique(['filter_id', 'value']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('filter_values');
    }
};
