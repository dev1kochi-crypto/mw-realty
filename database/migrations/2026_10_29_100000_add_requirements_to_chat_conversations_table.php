<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The buyer's brief the AI chat picks out of what they type (budget, bedrooms, near schools,
 * investment, commute, …) — merged turn by turn, used to rank listings (PropertyMatcher) and
 * shown to the agent on the lead's Insights panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->json('requirements')->nullable()->after('visitor_browser_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn('requirements');
        });
    }
};
