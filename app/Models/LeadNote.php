<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One entry in a Lead's activity history — see LeadNoteService for how these are created. */
class LeadNote extends Model
{
    protected $fillable = [
        'lead_id',
        'type',
        'body',
        'author_name',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
