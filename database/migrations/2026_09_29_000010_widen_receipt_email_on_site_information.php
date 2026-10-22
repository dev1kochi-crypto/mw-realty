<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Recipient Email now holds several comma-separated addresses (see SiteInformation::notificationEmail). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_information', function (Blueprint $table) {
            $table->string('receipt_email', 1000)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('site_information', function (Blueprint $table) {
            $table->string('receipt_email')->nullable()->change();
        });
    }
};
