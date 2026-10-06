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
        'subscribed_at', 'last_synced_at', 'last_lead_at', 'leads_count', 'last_error', 'needs_reconnect_at', 'reconnect_notified_at',
        'import_status', 'import_added', 'import_skipped', 'import_error', 'import_finished_at',
    ];

    protected $hidden = ['page_access_token'];

    protected $casts = [
        'page_access_token' => 'encrypted',
        'subscribed_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_lead_at' => 'datetime',
        'needs_reconnect_at' => 'datetime',
        'reconnect_notified_at' => 'datetime',
        'import_finished_at' => 'datetime',
    ];

    public function importInProgress(): bool
    {
        return in_array($this->import_status, ['queued', 'running'], true);
    }

    /**
     * An import that stopped reporting progress (worker killed / never picked up) is shown as stopped
     * instead of spinning forever. Running jobs touch the row every few leads.
     */
    public function failStaleImport(): void
    {
        if (!$this->importInProgress()) {
            return;
        }
        $error = match (true) {
            $this->import_status === 'queued' && $this->updated_at?->lt(now()->subMinutes(20)) => 'The import never started — the server\'s background worker (cron running "schedule:run") may be off. Click Sync now to fetch the leads directly.',
            $this->import_status === 'running' && $this->updated_at?->lt(now()->subMinutes(15)) => 'The import stopped unexpectedly. Click Sync now to try again.',
            default => null,
        };
        if ($error) {
            $this->forceFill(['import_status' => 'failed', 'import_error' => $error, 'import_finished_at' => now()])->save();
        }
    }

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function leads()
    {
        return $this->hasMany(FacebookLead::class);
    }
}
