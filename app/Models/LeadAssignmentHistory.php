<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only log of every time a lead was (re)assigned — who got it, when, why, and by whom. */
class LeadAssignmentHistory extends Model
{
    protected $table = 'lead_assignment_history';

    protected $fillable = [
        'lead_id', 'agency_id', 'agent_id', 'previous_agent_id', 'assignment_type',
        'assigned_by_type', 'assigned_by_id', 'note', 'assigned_at',
    ];

    protected $casts = ['assigned_at' => 'datetime'];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent()
    {
        return $this->belongsTo(PortalUser::class, 'agent_id');
    }

    public function previousAgent()
    {
        return $this->belongsTo(PortalUser::class, 'previous_agent_id');
    }
}
