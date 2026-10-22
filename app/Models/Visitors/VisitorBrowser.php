<?php

namespace App\Models\Visitors;

use Illuminate\Database\Eloquent\Model;

/** One browser (the mw_vid cookie). visitor_lead_id = whoever is using it right now. */
class VisitorBrowser extends Model
{
    protected $fillable = ['token', 'visitor_lead_id', 'ip_address', 'user_agent', 'last_seen_at'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function visitorLead()
    {
        return $this->belongsTo(VisitorLead::class);
    }
}
