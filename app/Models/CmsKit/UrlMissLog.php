<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class UrlMissLog extends Model
{
    protected $fillable = [
        'path',
        'hit_count',
        'last_referer',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'hit_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
