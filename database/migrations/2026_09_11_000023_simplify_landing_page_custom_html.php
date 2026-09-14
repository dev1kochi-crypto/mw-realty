<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simplifies Custom HTML pages to the model actually asked for: one shared
 * HTML/CSS (with images), text auto-detected from it, and a plain per-language
 * translation list for that text — instead of separate HTML per language.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->longText('custom_html')->nullable()->after('feature_image_alt');
            $table->longText('custom_css')->nullable()->after('custom_html');
            $table->json('text_translations')->nullable()->after('custom_css');
        });
    }

    public function down()
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn(['custom_html', 'custom_css', 'text_translations']);
        });
    }
};
