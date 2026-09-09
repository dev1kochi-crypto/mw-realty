<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('status')
                ->constrained('plans')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });
    }
};
