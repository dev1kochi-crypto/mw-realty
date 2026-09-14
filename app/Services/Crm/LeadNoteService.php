<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Support\Collection;

/**
 * Single source of truth for a Lead's activity history. Notes are the first
 * caller, but every method takes a $type so Follow-ups, Calls, and Site
 * Visits can reuse the exact same add/list logic later instead of each
 * growing their own table and controller.
 */
class LeadNoteService
{
    public function addNote(Lead $lead, string $body, ?string $authorName = null, string $type = 'note'): LeadNote
    {
        return $lead->notesHistory()->create([
            'type' => $type,
            'body' => trim($body),
            'author_name' => $authorName,
        ]);
    }

    /** Newest first — ordered by id rather than created_at so two notes added within the same second still sort correctly. */
    public function getHistoryFor(Lead $lead): Collection
    {
        return $lead->notesHistory()->latest('id')->get();
    }
}
