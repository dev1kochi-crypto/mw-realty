<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Set when the enquirer was a logged-in customer account, so their "My Enquiries"
            // dashboard can show enquiries they sent — null for anonymous/guest submissions.
            $table->foreignId('user_id')->nullable()->after('portal_user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
