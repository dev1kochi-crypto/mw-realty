<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per uploaded lead file — the queued import's progress (shown live on the Leads page)
 * and, once done, its totals and the status file (upload + Import Status column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            // Super Admin who uploaded on the account's behalf (cms guard), if any.
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('format', 20);
            $table->string('file_name');
            $table->string('file_path');
            $table->string('result_path')->nullable();
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('added')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->json('agents')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_imports');
    }
};
