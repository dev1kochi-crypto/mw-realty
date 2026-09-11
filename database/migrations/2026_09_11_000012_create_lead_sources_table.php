<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();

            $table->unique(['portal_user_id', 'name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lead_sources');
    }
};
