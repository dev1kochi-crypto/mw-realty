<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PortalUser extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'type',
        'name',
        'company_name',
        'email',
        'phone',
        'license_no',
        'password',
        'status',
        'plan_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * How many more properties this owner can list under their current plan,
     * or null if unlimited (no plan assigned = unlimited, matching legacy behavior).
     */
    public function remainingPropertySlots(): ?int
    {
        if (!$this->plan || $this->plan->isUnlimited()) {
            return null;
        }

        $used = $this->properties()->count();
        return max(0, $this->plan->property_limit - $used);
    }

    public function enquiries()
    {
        return $this->hasMany(\App\Models\CmsKit\Enquiry::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
