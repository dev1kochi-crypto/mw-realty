<?php

use Database\Seeders\HelpGuideSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The CRM sidebar's own Insights group (Property Insights, Agent Insights, Lead Insights, Reports):
 * adds the Help Desk guide for the new Property Insights page and re-seeds the Agent Insights guide
 * for its tabbed agent page.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('help_guides')->whereIn('topic', ['property-insights', 'agent-insights'])->delete();
        (new HelpGuideSeeder)->run(); // adds only topics that don't exist yet
    }

    public function down(): void
    {
        DB::table('help_guides')->where('topic', 'property-insights')->delete();
    }
};
