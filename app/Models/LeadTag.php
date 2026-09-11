<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadTag extends Model
{
    protected $fillable = ['portal_user_id', 'name', 'color'];

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function leads()
    {
        return $this->belongsToMany(Lead::class, 'lead_tag_pivot');
    }

    public function scopeForOwner($query, ?int $ownerId)
    {
        return $query->when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));
    }
}
