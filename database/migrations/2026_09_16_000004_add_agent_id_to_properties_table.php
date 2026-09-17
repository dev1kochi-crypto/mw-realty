<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('properties', function (Blueprint $table) {
            // Nullable: a listing doesn't have to be assigned to a specific agent — an agency
            // can leave it unassigned, and Super Admin can add a house/MW Realty listing directly.
            $table->foreignId('agent_id')->nullable()->after('portal_user_id')
                ->constrained('portal_users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
        });
    }
};
