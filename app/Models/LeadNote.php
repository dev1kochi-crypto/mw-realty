<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry in a Lead's activity history — see LeadNoteService for how these are created.
 * type: note (written by the team) | enquiry (repeat enquiry) | stage | source | status |
 * tags | details | owner (system-logged changes).
 */
class LeadNote extends Model
{
    public const TYPE_NOTE = 'note';
    public const TYPE_ENQUIRY = 'enquiry';

    protected $fillable = [
        'lead_id',
        'type',
        'body',
        'meta',
        'author_name',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
