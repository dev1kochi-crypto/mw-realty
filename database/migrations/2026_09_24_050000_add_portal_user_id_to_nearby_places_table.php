<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nearby_places', function (Blueprint $table) {
            // NULL = shared place managed by Super Admin (visible to everyone); set = a place an
            // Agent/Company added for their own listings (visible/manageable only by them).
            $table->foreignId('portal_user_id')->nullable()->after('id')->constrained('portal_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nearby_places', function (Blueprint $table) {
            $table->dropConstrainedForeignId('portal_user_id');
        });
    }
};
