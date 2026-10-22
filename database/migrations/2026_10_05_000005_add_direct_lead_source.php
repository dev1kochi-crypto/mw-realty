<?php

use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Database\Migrations\Migration;

/**
 * Leads added by hand (Add Lead) get the "Direct Lead" source (Lead::DIRECT_SOURCE), preselected in
 * the form. Super Admin's global "Manual Entry" source becomes "Direct Lead" — so the leads already
 * on it move with it — then the source is added for every account if it still isn't there.
 */
return new class extends Migration
{
    public function up(): void
    {
        $globalId = LeadSource::globalOwnerId();
        $hasDirect = LeadSource::where('portal_user_id', $globalId)->whereRaw('LOWER(name) = ?', [mb_strtolower(Lead::DIRECT_SOURCE)])->exists();
        if (!$hasDirect) {
            LeadSource::where('portal_user_id', $globalId)->whereRaw('LOWER(name) = ?', ['manual entry'])->update(['name' => Lead::DIRECT_SOURCE]);
        }

        LeadSource::ensureSystemSources();
    }

    public function down(): void
    {
        // Data fill only — the source may already be on leads.
    }
};
