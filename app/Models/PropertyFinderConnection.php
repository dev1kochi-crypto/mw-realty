<?php

namespace App\Models;

use App\Services\PropertyFinder\PropertyFinderClient;
use Illuminate\Database\Eloquent\Model;

/**
 * A Property Finder account connected in CRM › Integrations by an agency or independent agent (one
 * each). Its listings are imported as that account's properties (App\Services\PropertyFinder\
 * PropertyFinderImporter). The API key and secret are stored encrypted; an agency's agents see
 * the connection (masked key) and may sync it.
 */
class PropertyFinderConnection extends Model
{
    protected $fillable = [
        'portal_user_id', 'api_key', 'api_secret', 'api_key_hash', 'connected_by', 'verified_at',
        'sync_status', 'sync_mode', 'sync_added', 'sync_skipped', 'sync_failed', 'sync_error', 'sync_finished_at',
        'last_synced_at', 'last_full_sync_at', 'imported_count',
    ];

    protected $hidden = ['api_key', 'api_secret'];

    protected $casts = [
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'verified_at' => 'datetime',
        'sync_finished_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_full_sync_at' => 'datetime',
    ];

    public static function hashKey(string $apiKey): string
    {
        return hash('sha256', trim($apiKey));
    }

    public function client(): PropertyFinderClient
    {
        return new PropertyFinderClient($this->api_key, $this->api_secret);
    }

    /** "pk_live_••••••3f9a" — enough to recognise the key without showing it. */
    public function maskedKey(): string
    {
        $key = (string) $this->api_key;

        return strlen($key) <= 8 ? str_repeat('•', strlen($key)) : substr($key, 0, 4) . str_repeat('•', 6) . substr($key, -4);
    }

    public function syncInProgress(): bool
    {
        return in_array($this->sync_status, ['queued', 'running'], true);
    }

    /** A sync that stopped reporting progress (worker killed / never started) is shown as stopped. */
    public function failStaleSync(): void
    {
        $error = match (true) {
            $this->sync_status === 'queued' && $this->updated_at?->lt(now()->subMinutes(15)) => 'The sync never started — the background worker isn\'t running (cron "schedule:run", or "php artisan queue:work imports --queue=imports" locally). Start it and click Sync again.',
            $this->sync_status === 'running' && $this->updated_at?->lt(now()->subMinutes(20)) => 'The sync stopped unexpectedly. Click Sync again to continue — listings already imported are skipped.',
            default => null,
        };
        if ($error) {
            $this->forceFill(['sync_status' => 'failed', 'sync_error' => $error, 'sync_finished_at' => now()])->save();
        }
    }

    /** Queued but not picked up by a worker for a while — the page says it's waiting. */
    public function waitingForWorker(): bool
    {
        return $this->sync_status === 'queued' && $this->updated_at?->lt(now()->subSeconds(75));
    }

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function imports()
    {
        return $this->hasMany(PropertyFinderImport::class);
    }
}
