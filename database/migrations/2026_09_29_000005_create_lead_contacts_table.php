<?php

use App\Models\LeadContact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every email / phone a lead has ever used — one person can enquire under several — so a new
 * enquiry matching ANY of them is merged into the existing lead (see LeadDeduplicationService)
 * instead of creating a duplicate. leads.email / leads.phone stay the primary contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->enum('type', ['email', 'phone']);
            $table->string('value');
            $table->string('phone_country_code', 5)->nullable();
            // Normalised form used for matching: lowercased email / last 9 phone digits.
            $table->string('match_key');
            $table->timestamps();

            $table->unique(['lead_id', 'type', 'match_key']);
            $table->index(['type', 'match_key']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedInteger('enquiry_count')->default(1)->after('extra_fields');
            $table->timestamp('last_enquired_at')->nullable()->after('enquiry_count');
        });

        // Index the contacts existing leads already have. Existing duplicates are left as they
        // are — merging them automatically could combine leads an owner has worked separately.
        DB::table('leads')->select(['id', 'email', 'phone', 'phone_country_code'])->orderBy('id')
            ->chunkById(500, function ($leads) {
                $rows = [];
                foreach ($leads as $lead) {
                    foreach (LeadContact::rowsFor($lead->email, $lead->phone, $lead->phone_country_code) as $row) {
                        $rows[] = $row + ['lead_id' => $lead->id, 'created_at' => now(), 'updated_at' => now()];
                    }
                }
                DB::table('lead_contacts')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['enquiry_count', 'last_enquired_at']);
        });
        Schema::dropIfExists('lead_contacts');
    }
};
