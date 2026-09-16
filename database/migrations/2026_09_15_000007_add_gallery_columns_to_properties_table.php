<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per property holds its whole gallery: `image_path` is the storage folder
     * (e.g. "properties/PROP001"), `image_sequence` is a comma-separated list of the numeric
     * suffixes that exist as files there in DISPLAY order (e.g. "1,3,2"). Filenames are always
     * `{reference_no}-{n}.jpeg`, so reordering only ever rewrites this string — never touches a
     * file on disk — and removing one image just drops its number from the list.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('image_alt');
            $table->string('image_sequence')->nullable()->after('image_path');
            $table->unsignedInteger('image_next_number')->default(0)->after('image_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_sequence', 'image_next_number']);
        });
    }
};
