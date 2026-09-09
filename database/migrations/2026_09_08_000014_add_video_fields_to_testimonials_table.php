<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('testimonials', function (Blueprint $table) {
            // 'text' (existing content+rating card) or 'video' (photo + play button, plays video_url/video_file)
            $table->string('type')->default('text')->after('image_alt');
            $table->string('video_source')->nullable()->after('type'); // "url" or "file"
            $table->string('video_url')->nullable()->after('video_source');
            $table->string('video_file')->nullable()->after('video_url');
        });
    }

    public function down()
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn(['type', 'video_source', 'video_url', 'video_file']);
        });
    }
};
