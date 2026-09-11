<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lead_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#14b8a6');
            $table->timestamps();

            $table->unique(['portal_user_id', 'name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lead_tags');
    }
};
