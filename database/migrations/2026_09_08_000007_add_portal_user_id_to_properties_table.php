<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('properties', function (Blueprint $table) {
            // Nullable: Super Admin can still add properties directly with no portal owner.
            $table->foreignId('portal_user_id')->nullable()->after('id')
                ->constrained('portal_users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('portal_user_id');
        });
    }
};
