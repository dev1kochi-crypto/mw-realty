<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per agency: the round-robin pointer (see LeadAssignmentService::nextRoundRobinAgent()), plus how
 * new leads are assigned — automatically by round robin, or manually by the agency — and which
 * kinds of lead round robin covers. Leads it doesn't cover wait unassigned for the agency.
 */
class AgencyLeadAssignmentSetting extends Model
{
    public const MODE_AUTOMATIC = 'automatic';
    public const MODE_MANUAL = 'manual';

    /** Lead kinds — see LeadAssignmentService::leadKind(). */
    public const SOURCE_PROPERTY = 'property';
    public const SOURCE_GENERIC = 'generic';
    public const SOURCE_FACEBOOK = 'facebook';

    public const SOURCES = [
        self::SOURCE_PROPERTY => 'Property enquiries',
        self::SOURCE_GENERIC => 'Generic enquiries',
        self::SOURCE_FACEBOOK => 'Facebook leads',
    ];

    protected $fillable = ['agency_id', 'mode', 'round_robin_sources', 'last_agent_id', 'last_assigned_at'];

    protected $casts = ['last_assigned_at' => 'datetime', 'round_robin_sources' => 'array'];

    public function isManual(): bool
    {
        return $this->mode === self::MODE_MANUAL;
    }

    /** Lead kinds round robin assigns automatically (null in the row = all of them). */
    public function roundRobinSources(): array
    {
        return $this->round_robin_sources ?? array_keys(self::SOURCES);
    }

    /** Does round robin pick an agent for a new lead of this kind? */
    public function roundRobins(string $kind): bool
    {
        return !$this->isManual() && in_array($kind, $this->roundRobinSources(), true);
    }

    public function agency()
    {
        return $this->belongsTo(PortalUser::class, 'agency_id');
    }

    public function lastAgent()
    {
        return $this->belongsTo(PortalUser::class, 'last_agent_id');
    }
}
