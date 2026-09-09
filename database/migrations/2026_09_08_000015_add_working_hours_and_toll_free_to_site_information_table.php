<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('site_information', function (Blueprint $table) {
            $table->string('working_hours')->nullable()->after('fax'); // translatable via translations JSON, like fax/address
            $table->string('toll_free')->nullable()->after('whatsapp_number');
        });
    }

    public function down()
    {
        Schema::table('site_information', function (Blueprint $table) {
            $table->dropColumn(['working_hours', 'toll_free']);
        });
    }
};
