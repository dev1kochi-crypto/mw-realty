<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('plans', 'featured_per_month')) Schema::table('plans', function (Blueprint $table) {
            // 0 = not included; featured_max_days / agent_limit NULL = no cap.
            $table->unsignedInteger('featured_per_month')->default(0)->after('property_limit');
            $table->unsignedInteger('featured_max_days')->nullable()->after('featured_per_month');
            $table->unsignedInteger('agent_limit')->default(0)->nullable()->after('featured_max_days');
            $table->boolean('reports_access')->default(false)->after('agent_limit');
        });

        if (!Schema::hasColumn('properties', 'featured_until')) Schema::table('properties', function (Blueprint $table) {
            // When a plan-based feature runs out; NULL while featured means featured by Super Admin
            // with no end date.
            $table->timestamp('featured_until')->nullable()->after('featured');
        });

        // One row per time an owner features a listing — counted per calendar month against the
        // plan's featured_per_month, so un-featuring early doesn't hand the slot back.
        Schema::create('property_featurings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('stopped_at')->nullable();
            $table->timestamps();

            $table->index(['portal_user_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_featurings');
        Schema::table('properties', fn (Blueprint $table) => $table->dropColumn('featured_until'));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['featured_per_month', 'featured_max_days', 'agent_limit', 'reports_access']));
    }
};
