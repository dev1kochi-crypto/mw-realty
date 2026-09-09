<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('portal_users', function (Blueprint $table) {
            $table->id();

            // "company" and "agent" are separate, unrelated account types —
            // both get identical portal capabilities (own properties + own CRM).
            $table->enum('type', ['company', 'agent']);

            $table->string('name');
            $table->string('company_name')->nullable(); // only meaningful for type=company
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('license_no')->nullable(); // optional RERA/trade license number
            $table->string('password');

            // New accounts start pending; only "approved" accounts can log in.
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('portal_users');
    }
};
