<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only history of a listing's DLD compliance review (see ListingComplianceService). */
class PropertyComplianceLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['property_id', 'from_status', 'to_status', 'note', 'actor_type', 'actor_id'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /** "Super Admin", the portal account's name, or "System" (scheduled permit expiry). */
    public function actorName(): string
    {
        return match ($this->actor_type) {
            'admin' => 'Super Admin',
            'system' => 'System',
            default => PortalUser::find($this->actor_id)?->displayName() ?? ucfirst($this->actor_type),
        };
    }
}
