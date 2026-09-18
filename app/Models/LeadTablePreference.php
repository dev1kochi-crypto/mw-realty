<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Saved Leads-table field selection for either a portal user or CMS user. */
class LeadTablePreference extends Model
{
    protected $fillable = [
        'actor_type',
        'actor_id',
        'columns',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
        ];
    }
}
