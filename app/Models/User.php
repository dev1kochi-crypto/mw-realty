<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The public-site "customer" (buyer/visitor) account — guard 'web'. Distinct from
 * App\Models\PortalUser (agent/company, guard 'portal') and CmsKit\Admin (guard 'cms').
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'phone',
        'location',
        'avatar',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function wishlistProperties()
    {
        return $this->belongsToMany(Property::class, 'property_wishlists')->withTimestamps();
    }

    public function savedSearches()
    {
        return $this->hasMany(SavedSearch::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Overridden because this app has no `password.reset` named route (no Breeze/Jetstream
     * scaffolding) — the default notification's resetUrl() would throw trying to build one.
     * Points at the Vue SPA's own reset-password page instead (see resources/js/pages/
     * ResetPassword.vue + CustomerAuthController::resetPassword(), which verifies this same
     * token via Password::broker()).
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = url('/reset-password?' . http_build_query([
            'token' => $token,
            'email' => $this->email,
        ]));

        $this->notify(new \App\Notifications\CustomerResetPassword($url));
    }
}
