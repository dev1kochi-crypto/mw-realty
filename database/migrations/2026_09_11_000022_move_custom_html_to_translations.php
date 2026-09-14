<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom HTML/CSS needs to be per-language just like title/content, since this
 * site is bilingual — moves it from its own columns into translations.{lang}.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn(['custom_html', 'custom_css']);
        });
    }

    public function down()
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->longText('custom_html')->nullable();
            $table->longText('custom_css')->nullable();
        });
    }
};
