<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // How featured_per_month is counted: 'concurrent' = that many listings featured at the
            // same time (a slot frees up when one ends); 'month' = that many featurings started per
            // calendar month.
            $table->string('featured_period', 20)->default('concurrent')->after('featured_max_days');
        });
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('featured_period'));
    }
};
