<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Lead is an enquiry a visitor submits about a specific property, routed to
 * that property's owning company/agent — distinct from the general-purpose
 * `enquiries` table (site-wide contact-us submissions seen only by admin).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('country')->nullable();
            $table->text('message')->nullable();

            $table->string('page_url')->nullable();
            $table->string('page_source')->nullable();

            $table->enum('status', ['new', 'contacted', 'closed'])->default('new');
            $table->foreignId('stage_id')->nullable()->constrained('lead_stages')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('lead_sources')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('extra_fields')->nullable();

            $table->timestamps();

            $table->index(['portal_user_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('leads');
    }
};
