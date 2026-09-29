<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ads can carry optional text over their image — translations[lang] = {eyebrow, title, text} —
 * so a card like the property page's "Sell Your Property Faster" is managed in Admin › Ads
 * instead of being hard-coded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('image_alt');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn('translations');
        });
    }
};
