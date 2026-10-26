<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead follow-ups (ENH-LF-01): a Call / WhatsApp / Email the team schedules on a lead, or one
 * already done and logged with its outcome. Nothing is sent to the customer — this only records
 * the activity and reminds whoever is responsible for the lead (LeadFollowUpService).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('lead_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('type', 20); // call | whatsapp | email
            $table->string('status', 20)->default('scheduled'); // scheduled | completed | cancelled
            $table->timestamp('due_at')->nullable(); // UTC; null for an activity logged as already done
            $table->text('note')->nullable(); // what the follow-up is for
            $table->text('outcome')->nullable(); // what happened, once completed
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // Set when the reminder went out; cleared again on reschedule so the new time gets one.
            $table->timestamp('reminded_at')->nullable();
            $table->unsignedBigInteger('reminded_to')->nullable(); // portal user who got that reminder
            $table->string('created_by_name')->nullable();
            $table->string('completed_by_name')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['lead_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lead_follow_ups');
    }
};
