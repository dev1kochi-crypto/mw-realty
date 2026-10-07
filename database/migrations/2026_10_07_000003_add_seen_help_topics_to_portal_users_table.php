<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal help popups (top bar "!" icon): each module's guide opens by itself on the first visit,
 * then only on demand. The topics a user has already been shown are kept here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->json('seen_help_topics')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn('seen_help_topics');
        });
    }
};
