<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('enquiries', function (Blueprint $table) {
            // Which property this enquiry is about (nullable — general contact-us enquiries aren't tied to one).
            $table->foreignId('property_id')->nullable()->after('id')
                ->constrained('properties')->nullOnDelete();

            // Denormalized owner so the portal CRM can scope "my enquiries" without joining through properties.
            $table->foreignId('portal_user_id')->nullable()->after('property_id')
                ->constrained('portal_users')->nullOnDelete();

            $table->enum('status', ['new', 'contacted', 'closed'])->default('new')->after('message');
            $table->text('notes')->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
            $table->dropConstrainedForeignId('portal_user_id');
            $table->dropColumn(['status', 'notes']);
        });
    }
};
