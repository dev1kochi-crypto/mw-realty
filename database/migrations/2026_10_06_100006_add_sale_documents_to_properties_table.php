<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof of the deal, uploaded with "Mark as sold / rented": the ownership document (title deed) and
 * the DLD contract (Ejari for a rental, Form F / sale contract for a sale), plus the password if the
 * PDFs are protected (stored encrypted). Files live on the private `kyc` disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('sold_ownership_document')->nullable()->after('sold_notes');
            $table->string('sold_contract_document')->nullable()->after('sold_ownership_document');
            $table->text('sold_documents_password')->nullable()->after('sold_contract_document');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['sold_ownership_document', 'sold_contract_document', 'sold_documents_password']);
        });
    }
};
