<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class CareerCandidate extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'state',
        'country',
        'apply_for',
        'experience',
        'designation',
        'submitted_at',
        'additional_information',
        'attachment',
        'privacy',
        'extra_fields',
    ];

    protected $casts = [
        'privacy' => 'boolean',
        'submitted_at' => 'datetime',
        'extra_fields' => 'array',
    ];
}
