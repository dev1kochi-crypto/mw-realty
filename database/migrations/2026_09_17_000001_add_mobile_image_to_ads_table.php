<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A separate image an ad can use on mobile screens instead of the desktop one —
 * optional; falls back to the main image when not set.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->string('mobile_image')->nullable()->after('image_alt');
        });
    }

    public function down()
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn('mobile_image');
        });
    }
};
