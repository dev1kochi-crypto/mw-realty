<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Services\Crm\LeadNoteService;
use App\Services\Crm\LeadService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Adds an entry to a Lead's activity history. Reads/looks up go through LeadService/LeadNoteService — nothing lead-specific happens here beyond request coordination. */
class LeadNoteController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadNoteService $leadNoteService,
    ) {
    }

    public function store(Request $request, $leadId)
    {
        $lead = $this->leadService->getLead($this->ownerId(), $leadId);

        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $note = $this->leadNoteService->addNote($lead, $request->input('body'), $this->actorName());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Note added.',
                'note' => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'author_name' => $note->author_name,
                    'created_at' => $note->created_at->format('d M Y, H:i'),
                ],
            ]);
        }

        return redirect()->route('portal.crm.leads.index', ['lead' => $lead->id])->with('success', 'Note added.');
    }
}
