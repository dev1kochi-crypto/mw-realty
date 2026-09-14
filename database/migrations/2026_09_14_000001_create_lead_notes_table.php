<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Lead's activity history — notes today, with a `type` column so the same
 * table/service can carry Follow-ups, Calls, and Site Visits later without a
 * schema change. Append-only: entries are never overwritten, matching the
 * old single `leads.notes` text field it replaces in the UI (that column is
 * left in place for the Excel import/export, which still reads/writes it).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('type')->default('note');
            $table->text('body');
            $table->string('author_name')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lead_notes');
    }
};
