<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Persistent round-robin pointer per agency — see LeadAssignmentService::nextRoundRobinAgent(). */
class AgencyLeadAssignmentSetting extends Model
{
    protected $fillable = ['agency_id', 'last_agent_id', 'last_assigned_at'];

    protected $casts = ['last_assigned_at' => 'datetime'];

    public function agency()
    {
        return $this->belongsTo(PortalUser::class, 'agency_id');
    }

    public function lastAgent()
    {
        return $this->belongsTo(PortalUser::class, 'last_agent_id');
    }
}
