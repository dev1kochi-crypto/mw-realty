<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->string('kyc_review_status', 32)->default('submitted')->after('document_status');
            $table->timestamp('kyc_submitted_at')->nullable()->after('kyc_review_status');
            $table->text('kyc_review_note')->nullable()->after('kyc_submitted_at');
        });

        DB::table('portal_users')->where('status', 'approved')->update(['kyc_review_status' => 'approved']);
        DB::table('portal_users')->where('status', 'rejected')->update(['kyc_review_status' => 'changes_requested']);
        DB::table('portal_users')->where('status', 'pending')->update([
            'kyc_review_status' => 'submitted',
            'kyc_submitted_at' => DB::raw('status_changed_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn(['kyc_review_status', 'kyc_submitted_at', 'kyc_review_note']);
        });
    }
};
