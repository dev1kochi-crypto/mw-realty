<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nearby_places', function (Blueprint $table) {
            // Per-language name/address, same shape as every other translatable model in this app.
            $table->json('translations')->nullable()->after('category');
            $table->decimal('latitude', 10, 7)->nullable()->after('translations');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            // No table had any rows yet, so this is a clean swap rather than a data migration.
            $table->dropColumn(['name', 'icon']);
        });
    }

    public function down(): void
    {
        Schema::table('nearby_places', function (Blueprint $table) {
            $table->string('name')->nullable()->after('category');
            $table->string('icon')->nullable()->after('name');
            $table->dropColumn(['translations', 'latitude', 'longitude']);
        });
    }
};
