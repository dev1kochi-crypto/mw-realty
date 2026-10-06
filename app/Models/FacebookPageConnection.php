<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Facebook Page connected in CRM › Integrations: its Lead Ads leads arrive in the account's CRM
 * (App\Services\Integrations\FacebookLeadImporter). The page access token is stored encrypted.
 */
class FacebookPageConnection extends Model
{
    protected $fillable = [
        'portal_user_id', 'page_id', 'page_name', 'page_access_token', 'connected_by',
        'subscribed_at', 'last_synced_at', 'last_lead_at', 'leads_count', 'last_error',
    ];

    protected $hidden = ['page_access_token'];

    protected $casts = [
        'page_access_token' => 'encrypted',
        'subscribed_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_lead_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function leads()
    {
        return $this->hasMany(FacebookLead::class);
    }
}
