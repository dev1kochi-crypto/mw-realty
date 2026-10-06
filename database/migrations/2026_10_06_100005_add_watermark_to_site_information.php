<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_information', function (Blueprint $table) {
            // Super Admin's default listing photo watermark — used for its own listings and for any
            // agency / agent that hasn't set one up. See App\Services\Watermark.
            $table->json('watermark')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_information', function (Blueprint $table) {
            $table->dropColumn('watermark');
        });
    }
};
