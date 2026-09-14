<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landing Pages: either 'template' (same translatable title/content shape as
 * Blog, rendered through whatever shared page design the frontend settles on)
 * or 'custom' (admin-authored raw HTML/CSS, optionally opting out of the
 * site's shared header/footer).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->enum('page_type', ['template', 'custom'])->default('template');
            $table->json('translations')->nullable();
            $table->string('feature_image')->nullable();
            $table->string('feature_image_alt')->nullable();
            $table->longText('custom_html')->nullable();
            $table->longText('custom_css')->nullable();
            $table->boolean('use_site_header_footer')->default(true);
            $table->json('metadata')->nullable();
            $table->date('published_at')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('landing_pages');
    }
};
