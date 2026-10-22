<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Mark as sold / rented" on a listing: the deal (price, date, buyer lead, closing agent) lives on
 * the property itself. A sold listing is unpublished (status = false) — status_before_sold lets
 * "Revert to available" put it back exactly as it was. See App\Services\PropertySaleService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->timestamp('sold_at')->nullable()->after('published_at');
            $table->string('sold_type', 10)->nullable()->after('sold_at'); // sold | rented
            $table->decimal('sold_price', 15, 2)->nullable()->after('sold_type');
            $table->decimal('sold_commission', 15, 2)->nullable()->after('sold_price');
            $table->date('rented_until')->nullable()->after('sold_commission');
            $table->foreignId('sold_lead_id')->nullable()->after('rented_until')->constrained('leads')->nullOnDelete();
            $table->foreignId('sold_agent_id')->nullable()->after('sold_lead_id')->constrained('portal_users')->nullOnDelete();
            $table->text('sold_notes')->nullable()->after('sold_agent_id');
            $table->boolean('status_before_sold')->nullable()->after('sold_notes');

            $table->index(['portal_user_id', 'sold_at']);
            $table->index('sold_at');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['portal_user_id', 'sold_at']);
            $table->dropIndex(['sold_at']);
            $table->dropConstrainedForeignId('sold_lead_id');
            $table->dropConstrainedForeignId('sold_agent_id');
            $table->dropColumn(['sold_at', 'sold_type', 'sold_price', 'sold_commission', 'rented_until', 'sold_notes', 'status_before_sold']);
        });
    }
};
