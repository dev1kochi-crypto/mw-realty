<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dubai listing compliance (DLD / RERA). A listing may only be live on the website once it carries
 * a DLD advertising permit (Trakheesi permit number + Madmoun QR) and the owner's marketing
 * authorisation (Form A), and Super Admin has reviewed it. See ListingComplianceService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // DLD advertising permit, issued to the brokerage through Trakheesi.
            $table->string('permit_number', 64)->nullable()->after('rera_id')->index();
            $table->date('permit_expires_at')->nullable()->after('permit_number');
            $table->string('permit_qr')->nullable()->after('permit_expires_at');
            $table->string('permit_verification_url', 2048)->nullable()->after('permit_qr');
            $table->timestamp('permit_expiry_notified_at')->nullable()->after('permit_verification_url');

            // Owner -> brokerage marketing authorisation (RERA Form A). Documents live on the private `kyc` disk.
            $table->string('authorization_type', 20)->nullable()->after('permit_expiry_notified_at');
            $table->date('authorization_expires_at')->nullable()->after('authorization_type');
            $table->string('authorization_document')->nullable()->after('authorization_expires_at');
            $table->string('title_deed_no', 64)->nullable()->after('authorization_document');
            $table->string('title_deed_document')->nullable()->after('title_deed_no');

            // Platform review state — separate from `status` (the website on/off switch).
            $table->string('compliance_status', 24)->default('draft')->after('status')->index();
            $table->text('compliance_note')->nullable()->after('compliance_status');
            $table->timestamp('compliance_submitted_at')->nullable()->after('compliance_note');
            $table->timestamp('compliance_reviewed_at')->nullable()->after('compliance_submitted_at');
            $table->unsignedBigInteger('compliance_reviewed_by')->nullable()->after('compliance_reviewed_at');
        });

        Schema::create('property_compliance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('note')->nullable();
            // admin (cms user) | agency | agent (portal user) | system (scheduled expiry)
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['property_id', 'id']);
        });

        // Listings that existed before this review step keep their current state on the website;
        // Super Admin can still request changes on any of them from Listing Approvals.
        DB::table('properties')->update([
            'compliance_status' => 'approved',
            'compliance_note' => 'Listed before DLD permit review was introduced.',
            'compliance_reviewed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('property_compliance_logs');

        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['permit_number']);
            $table->dropIndex(['compliance_status']);
            $table->dropColumn([
                'permit_number', 'permit_expires_at', 'permit_qr', 'permit_verification_url', 'permit_expiry_notified_at',
                'authorization_type', 'authorization_expires_at', 'authorization_document', 'title_deed_no', 'title_deed_document',
                'compliance_status', 'compliance_note', 'compliance_submitted_at', 'compliance_reviewed_at', 'compliance_reviewed_by',
            ]);
        });
    }
};
