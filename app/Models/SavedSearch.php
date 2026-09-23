<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A customer's saved property search — a label plus the filter criteria to re-run it. */
class SavedSearch extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'criteria',
    ];

    protected $casts = [
        'criteria' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
