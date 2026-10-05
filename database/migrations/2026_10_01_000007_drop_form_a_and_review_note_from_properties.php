<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Form A / title deed and Super Admin's review notes are no longer part of the listing flow (the
 * permit is validated in the property form; there is no review). Remove their columns and data:
 * the private files on the `kyc` disk, the columns, and the notes Super Admin typed into the
 * listing history (automatic entries like "Permit verified with DLD." stay).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('properties')
            ->where(fn ($q) => $q->whereNotNull('authorization_document')->orWhereNotNull('title_deed_document'))
            ->select(['id', 'authorization_document', 'title_deed_document'])
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    foreach ([$row->authorization_document, $row->title_deed_document] as $path) {
                        if (!$path) {
                            continue;
                        }
                        try {
                            Storage::disk('kyc')->delete($path);
                        } catch (\Throwable $e) {
                            Log::warning("Could not delete listing document {$path}: " . $e->getMessage());
                        }
                    }
                }
            });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'authorization_type', 'authorization_expires_at', 'authorization_document',
                'title_deed_no', 'title_deed_document', 'compliance_note',
            ]);
        });

        DB::table('property_compliance_logs')->where('actor_type', 'admin')->update(['note' => null]);
    }

    public function down(): void
    {
        // Columns come back empty — the files and notes are gone.
        Schema::table('properties', function (Blueprint $table) {
            $table->string('authorization_type', 30)->nullable();
            $table->date('authorization_expires_at')->nullable();
            $table->string('authorization_document')->nullable();
            $table->string('title_deed_no', 64)->nullable();
            $table->string('title_deed_document')->nullable();
            $table->text('compliance_note')->nullable();
        });
    }
};
