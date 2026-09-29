<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Single source of truth for a Lead's activity history. Team notes (type "note") and
 * system-logged activity (repeat enquiries, stage / source / status / tag / detail changes)
 * share one table and one add/list API, so Follow-ups, Calls and Site Visits can reuse it too.
 */
class LeadNoteService
{
    public function addNote(Lead $lead, string $body, ?string $authorName = null, string $type = LeadNote::TYPE_NOTE, ?array $meta = null): LeadNote
    {
        return $lead->notesHistory()->create([
            'type' => $type,
            'body' => trim($body),
            'meta' => $meta,
            'author_name' => $authorName,
        ]);
    }

    /** Log a system activity entry, attributed to whoever is signed in (null = the website / system). */
    public function log(Lead $lead, string $type, string $body, ?array $meta = null): LeadNote
    {
        return $this->addNote($lead, $body, self::currentActorName(), $type, $meta);
    }

    /** Newest first — ordered by id rather than created_at so two notes added within the same second still sort correctly. */
    public function getHistoryFor(Lead $lead, ?string $type = null): Collection
    {
        return $lead->notesHistory()->when($type, fn ($q) => $q->where('type', $type))->latest('id')->get();
    }

    /** Name of the signed-in portal user / admin, or null outside a CRM session (website, queue, CLI). */
    public static function currentActorName(): ?string
    {
        if (!Auth::guard('portal')->check() && !Auth::guard('cms')->check()) {
            return null;
        }

        return app(OwnerContext::class)->actorName();
    }
}
