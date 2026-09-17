<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->string('whatsapp_number')->nullable()->after('phone');
            $table->json('features')->nullable()->after('preferred_areas'); // agent specialties, e.g. ["Off-Plan", "Luxury Villas"]
        });
    }

    public function down()
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'features']);
        });
    }
};
