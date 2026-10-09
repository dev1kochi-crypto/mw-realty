<?php

namespace App\Http\Controllers\Crm\Leads;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Services\Crm\LeadNoteService;
use App\Services\Crm\LeadService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group CRM Leads
 */
class LeadNoteController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadNoteService $leadNoteService,
    ) {
    }

    /**
     * Add a note
     *
     * Adds an entry to the lead's activity timeline, signed with your name.
     *
     * @bodyParam body string required Example: Called — wants a viewing on Saturday.
     *
     * @response 200 {"success": true, "message": "Note added.", "note": {"id": 55, "body": "Called — wants a viewing on Saturday.", "author_name": "Sara Ahmed", "created_at": "2026-10-09T14:20:00+00:00"}}
     */
    public function store(Request $request, $leadId)
    {
        $lead = $this->leadService->getLead($this->ownerId(), $leadId);

        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $note = $this->leadNoteService->addNote($lead, $request->input('body'), $this->actorName());

        return response()->json([
            'success' => true,
            'message' => 'Note added.',
            'note' => [
                'id' => $note->id,
                'body' => $note->body,
                'author_name' => $note->author_name,
                'created_at' => $note->created_at->toIso8601String(),
            ],
        ]);
    }
}
