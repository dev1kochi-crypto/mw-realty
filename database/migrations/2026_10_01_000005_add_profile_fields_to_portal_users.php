<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent / agency profile fields the listing portals ask for: public contact details, the Abu Dhabi
 * and "other" licenses with expiry dates, and the agent's "About me" (position, languages, LinkedIn,
 * experience since). adrec_license_no (added with the permit flow) holds the Abu Dhabi license for
 * both — an agency's ADREC brokerage registration no., an agent's Abu Dhabi Broker License (BLN).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->string('public_email')->nullable()->after('email');
            $table->string('secondary_phone', 30)->nullable()->after('phone');
            $table->string('city', 100)->nullable()->after('office_address');
            $table->date('orn_expiry')->nullable()->after('orn_number');
            $table->date('adrec_license_expiry')->nullable()->after('adrec_license_no');
            $table->string('other_license', 100)->nullable()->after('adrec_license_expiry');
            $table->date('other_license_expiry')->nullable()->after('other_license');
            $table->string('position', 150)->nullable()->after('years_of_experience');
            $table->string('linkedin_url')->nullable()->after('website');
            $table->json('spoken_languages')->nullable()->after('position');
            $table->unsignedSmallInteger('experience_since')->nullable()->after('years_of_experience');
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn(['public_email', 'secondary_phone', 'city', 'orn_expiry', 'adrec_license_expiry', 'other_license', 'other_license_expiry', 'position', 'linkedin_url', 'spoken_languages', 'experience_since']);
        });
    }
};
