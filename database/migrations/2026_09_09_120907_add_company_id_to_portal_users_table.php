<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn('affiliated_company');
            $table->foreignId('company_id')->nullable()->after('brn_number')
                ->constrained('portal_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->string('affiliated_company')->nullable()->after('brn_number');
        });
    }
};
