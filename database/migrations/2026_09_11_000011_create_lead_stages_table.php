<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lead_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#4f46e5');
            $table->unsignedInteger('order_index')->default(0);
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['portal_user_id', 'name']);
            $table->index(['portal_user_id', 'order_index']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lead_stages');
    }
};
