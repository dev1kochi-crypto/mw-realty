<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrlRedirect extends Model
{
    protected $fillable = [
        'old_path',
        'new_url',
        'status_code',
        'hit_count',
        'last_hit_at',
        'source',
        'notes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'hit_count' => 'integer',
        'last_hit_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
