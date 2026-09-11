<?php

namespace App\Models;

use App\Models\CmsKit\Admin;
use Illuminate\Database\Eloquent\Model;

class PlanUpgradeRequest extends Model
{
    protected $fillable = [
        'portal_user_id',
        'plan_id',
        'current_plan_id',
        'status',
        'requested_at',
        'decided_at',
        'decided_by',
        'decision_note',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function portalUser()
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function currentPlan()
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    public function decidedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'decided_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
