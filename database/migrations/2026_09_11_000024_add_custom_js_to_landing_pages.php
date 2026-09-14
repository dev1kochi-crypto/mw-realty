<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->longText('custom_js')->nullable()->after('custom_css');
        });
    }

    public function down()
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn('custom_js');
        });
    }
};
