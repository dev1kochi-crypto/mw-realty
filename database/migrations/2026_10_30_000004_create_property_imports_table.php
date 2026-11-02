<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per listing spreadsheet an agency uploads (CRM › Properties › Import) — the column
 * mapping it was imported with, the queued import's progress and, once done, its totals and the
 * status file. The totals are also the onboarding numbers: how many rows came in ready to go
 * (`complete`), how many still need details, and how long the account took (started → finished).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_imports', function (Blueprint $table) {
            $table->id();
            // The account the listings belong to (an agency agent's upload belongs to the agency).
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            // Who uploaded it — the account itself or one of its agents.
            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->string('segment', 20);
            $table->string('file_name');
            $table->string('file_path');
            $table->string('result_path')->nullable();
            // mapping (choosing columns) → queued → running → done | failed
            $table->string('status', 20)->default('mapping')->index();
            $table->json('mapping')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('added')->default(0);
            $table->unsignedInteger('complete')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('ai_written')->default(0);
            $table->unsignedInteger('translated')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_imports');
    }
};
