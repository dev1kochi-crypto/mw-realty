<?php

namespace App\Http\Controllers\Crm\Leads;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\LeadFollowUp;
use App\Services\Crm\LeadFollowUpService;
use App\Services\Crm\LeadService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @group CRM Lead follow-ups
 *
 * Schedule a Call / WhatsApp / Email on a lead, or log one already done with its outcome. Nothing
 * is sent to the customer. Whoever is responsible for the lead (the assigned agent, else the owning
 * account) gets a bell + email reminder 15 minutes before it's due. Times are UAE time: send
 * `due_at` as `YYYY-MM-DDTHH:mm`. Works on the leads you can see, like the rest of CRM Leads.
 */
class LeadFollowUpController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadFollowUpService $followUps,
    ) {
    }

    /**
     * Follow-ups list
     *
     * Leads › Follow-ups. Counts for every tab plus one page (20) of the chosen tab.
     *
     * @queryParam tab string overdue (default), today, upcoming or done. Example: today
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        return response()->json($this->followUps->listing(
            $this->ownerId(),
            (string) $request->input('tab', 'overdue'),
            max(1, (int) $request->input('page', 1)),
        ));
    }

    /**
     * A lead's follow-ups
     *
     * Open ones (soonest first), the latest 20 done / cancelled, and who gets the reminders.
     */
    public function forLead($leadId)
    {
        return response()->json($this->followUps->forLead($this->leadService->getLead($this->ownerId(), $leadId)));
    }

    /**
     * Schedule or log
     *
     * `mode: schedule` = a follow-up for later (`due_at` required). `mode: log` = an activity
     * already done (`outcome` required), optionally with the next follow-up (`next_due_at`).
     *
     * @bodyParam mode string required schedule or log. Example: schedule
     * @bodyParam type string required call, whatsapp or email. Example: call
     * @bodyParam due_at string schedule: UAE time. Example: 2026-10-27T10:00
     * @bodyParam note string What it's for. Example: Confirm the Saturday viewing.
     * @bodyParam outcome string log: what happened. Example: No answer — left a voicemail.
     * @bodyParam next_type string log: the next follow-up's type. Example: whatsapp
     * @bodyParam next_due_at string log: the next follow-up's time (UAE). Example: 2026-10-28T11:00
     * @bodyParam next_note string Example: Send the brochure.
     */
    public function store(Request $request, $leadId)
    {
        $lead = $this->leadService->getLead($this->ownerId(), $leadId);
        $data = $request->validate([
            'mode' => ['required', Rule::in(['schedule', 'log'])],
            'type' => ['required', Rule::in(array_keys(LeadFollowUp::TYPES))],
            'due_at' => ['required_if:mode,schedule', 'nullable', 'date_format:Y-m-d\TH:i'],
            'note' => ['nullable', 'string', 'max:2000'],
            'outcome' => ['required_if:mode,log', 'nullable', 'string', 'max:2000'],
        ] + $this->nextRules(), $this->messages());

        $actor = $this->actorName();
        DB::transaction(function () use ($data, $lead, $actor) {
            if ($data['mode'] === 'schedule') {
                $this->followUps->schedule($lead, $data['type'], $this->futureTime($data['due_at'], 'due_at'), $data['note'] ?? null, $actor);
            } else {
                $this->followUps->logActivity($lead, $data['type'], $data['outcome'], $actor);
                $this->scheduleNext($lead, $data, $actor);
            }
        });

        return response()->json([
            'success' => true,
            'message' => $data['mode'] === 'schedule' ? 'Follow-up scheduled.' : (!empty($data['next_due_at']) ? 'Activity logged and next follow-up scheduled.' : 'Activity logged.'),
        ]);
    }

    /**
     * Reschedule
     *
     * A new time (UAE) — the reminder is sent again for it.
     *
     * @bodyParam due_at string required Example: 2026-10-28T09:30
     * @bodyParam note string Example: Customer asked to call after 9.
     */
    public function update(Request $request, $id)
    {
        $followUp = $this->findVisible($id);
        $data = $request->validate([
            'due_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], $this->messages());

        $this->followUps->reschedule($followUp, $this->futureTime($data['due_at'], 'due_at'), $request->has('note') ? (string) $request->input('note') : null, $this->actorName());

        return response()->json(['success' => true, 'message' => 'Follow-up rescheduled.']);
    }

    /**
     * Complete
     *
     * Mark it done with what happened — optionally schedule the next one in the same step.
     *
     * @bodyParam outcome string required Example: Spoke to her — viewing booked for Saturday.
     * @bodyParam next_type string Example: call
     * @bodyParam next_due_at string Example: 2026-11-01T10:00
     * @bodyParam next_note string Example: Confirm the viewing.
     */
    public function complete(Request $request, $id)
    {
        $followUp = $this->findVisible($id);
        $data = $request->validate(['outcome' => ['required', 'string', 'max:2000']] + $this->nextRules(), $this->messages());

        $actor = $this->actorName();
        DB::transaction(function () use ($followUp, $data, $actor) {
            $this->followUps->complete($followUp, $data['outcome'], $actor);
            $this->scheduleNext($followUp->lead, $data, $actor);
        });

        return response()->json(['success' => true, 'message' => !empty($data['next_due_at']) ? 'Follow-up completed and the next one scheduled.' : 'Follow-up completed.']);
    }

    /**
     * Cancel
     *
     * No reminder is sent for it any more.
     *
     * @bodyParam reason string Example: Customer bought elsewhere.
     */
    public function cancel(Request $request, $id)
    {
        $followUp = $this->findVisible($id);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        $this->followUps->cancel($followUp, $data['reason'] ?? null, $this->actorName());

        return response()->json(['success' => true, 'message' => 'Follow-up cancelled.']);
    }

    /** Only follow-ups on a lead this viewer can see. */
    private function findVisible($id): LeadFollowUp
    {
        $followUp = LeadFollowUp::findOrFail($id);
        $followUp->setRelation('lead', $this->leadService->getLead($this->ownerId(), $followUp->lead_id));

        return $followUp;
    }

    private function scheduleNext($lead, array $data, ?string $actor): void
    {
        if (!empty($data['next_due_at'])) {
            $this->followUps->schedule($lead, $data['next_type'], $this->futureTime($data['next_due_at'], 'next_due_at'), $data['next_note'] ?? null, $actor);
        }
    }

    private function nextRules(): array
    {
        return [
            'next_due_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'next_type' => ['required_with:next_due_at', 'nullable', Rule::in(array_keys(LeadFollowUp::TYPES))],
            'next_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function messages(): array
    {
        return [
            'due_at.required' => 'Choose when the follow-up is due.',
            'due_at.required_if' => 'Choose when the follow-up is due.',
            'outcome.required' => 'Write what happened.',
            'outcome.required_if' => 'Write what happened.',
            'next_type.required_with' => 'Choose the type of the next follow-up.',
        ];
    }

    /** A form time (UAE) that has to be in the future. */
    private function futureTime(string $value, string $field)
    {
        $at = LeadFollowUpService::parseLocal($value);
        if ($at->isPast()) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Choose a time in the future (UAE time).']);
        }

        return $at;
    }
}
