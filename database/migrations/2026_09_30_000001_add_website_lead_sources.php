<?php

use App\Models\LeadSource;
use Illuminate\Database\Migrations\Migration;

/**
 * The website forms' / system channels' sources (Agent Profile, Custom Request, AI Chatbot, …) are
 * now set up by Super Admin for every account up front (LeadSource::systemNames()) — add the ones
 * that don't exist yet. Other new sources are created for the lead's own account from now on.
 */
return new class extends Migration
{
    public function up(): void
    {
        LeadSource::ensureSystemSources();
    }

    public function down(): void
    {
        // Data fill only — the sources may already be on leads.
    }
};
