<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad Management: admin-uploaded image/GIF banners shown in a named placement
 * (a free-text slot key like "home-middle" — no fixed page layout exists yet,
 * so this stays flexible until the public frontend design lands).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image');
            $table->string('image_alt')->nullable();
            $table->string('link_url')->nullable();
            $table->string('placement');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('placement');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ads');
    }
};
