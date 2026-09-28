<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only log of property ownership / agent-assignment changes. */
class PropertyAssignmentHistory extends Model
{
    protected $table = 'property_assignment_history';

    protected $fillable = [
        'property_id', 'action', 'from_agency_id', 'to_agency_id', 'from_agent_id', 'to_agent_id',
        'changed_by_type', 'changed_by_id', 'note',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function fromAgent()
    {
        return $this->belongsTo(PortalUser::class, 'from_agent_id');
    }

    public function toAgent()
    {
        return $this->belongsTo(PortalUser::class, 'to_agent_id');
    }
}
