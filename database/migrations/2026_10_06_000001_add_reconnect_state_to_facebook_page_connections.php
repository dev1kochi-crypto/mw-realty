<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facebook Page connections whose token Facebook rejected (FacebookConnectionHealth): when it broke,
 * and when the owner was emailed to reconnect — so they are emailed once per breakage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_page_connections', function (Blueprint $table) {
            $table->timestamp('needs_reconnect_at')->nullable()->after('last_error');
            $table->timestamp('reconnect_notified_at')->nullable()->after('needs_reconnect_at');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_page_connections', function (Blueprint $table) {
            $table->dropColumn(['needs_reconnect_at', 'reconnect_notified_at']);
        });
    }
};
