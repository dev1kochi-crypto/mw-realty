<?php

namespace App\Models\CmsKit;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use Notifiable, HasRoles;

    protected $table = 'cms_admins';
    protected $guard_name = 'cms';

    protected $fillable = [
        'name', 'email', 'password', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \CMS\SiteManager\Notifications\ResetPasswordNotification($token));
    }

    /**
     * Check if admin is a superadmin
     */
    public function isSuperAdmin()
    {
        return $this->hasRole('superadmin');
    }

    /**
     * Check if admin is protected (cannot be deleted or deactivated)
     */
    public function isProtected()
    {
        return $this->isSuperAdmin();
    }
}


