<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_details', function (Blueprint $table) {
            // Distinct from `parking` (open parking spaces) — a private/covered garage count.
            $table->unsignedInteger('garage')->nullable()->after('parking');
            $table->string('direct_from_owner')->nullable()->after('furnished');
            $table->decimal('security_deposit', 15, 2)->nullable()->after('direct_from_owner');
            $table->string('virtual_tour_url')->nullable()->after('security_deposit');
            // Icon-repeater sections, same {icon, label} shape as `amenities` — distinct from it by section.
            $table->json('easy_access')->nullable()->after('amenities');
            $table->json('property_attributes')->nullable()->after('easy_access');
            // One shared diagram for the whole property (unit-type breakdowns live in property_floor_plans).
            $table->string('floor_plan_image')->nullable()->after('property_attributes');
            $table->string('floor_plan_file')->nullable()->after('floor_plan_image');
        });
    }

    public function down(): void
    {
        Schema::table('property_details', function (Blueprint $table) {
            $table->dropColumn([
                'garage', 'direct_from_owner', 'security_deposit', 'virtual_tour_url',
                'easy_access', 'property_attributes', 'floor_plan_image', 'floor_plan_file',
            ]);
        });
    }
};
