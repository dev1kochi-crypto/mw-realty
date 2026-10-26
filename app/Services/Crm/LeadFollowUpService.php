<?php

namespace App\Services\Crm;

use App\Mail\LeadFollowUpReminderMail;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\PortalUser;
use App\Notifications\LeadFollowUpReminderNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lead follow-ups (ENH-LF-01). The team records a Call / WhatsApp / Email on a lead — either
 * scheduled for later or logged as done with its outcome. Nothing is sent to the customer.
 *
 * - Every change (schedule, log, reschedule, complete, cancel) is written to the lead's timeline
 *   (LeadNoteService, type "follow_up") with who did it.
 * - Times are entered and shown in UAE time (TIMEZONE) and stored in UTC.
 * - The reminder (bell + email) goes to whoever is responsible for the lead when it fires —
 *   the assigned agent, else the owning account — so a reassigned lead reminds its new agent.
 * - Each due time is reminded once (reminded_at, claimed atomically); rescheduling re-arms it,
 *   completing / cancelling stops it.
 */
class LeadFollowUpService
{
    public const TIMEZONE = 'Asia/Dubai';

    /** The reminder goes out this long before the follow-up is due. */
    public const REMIND_BEFORE_MINUTES = 15;

    public function __construct(private readonly LeadNoteService $notes)
    {
    }

    public function schedule(Lead $lead, string $type, CarbonInterface $dueAt, ?string $note, ?string $actor): LeadFollowUp
    {
        $followUp = $lead->followUps()->create([
            'type' => $type,
            'status' => LeadFollowUp::SCHEDULED,
            'due_at' => $dueAt,
            'note' => $this->clean($note),
            'created_by_name' => $actor,
        ]);
        $this->log($lead, "Scheduled {$followUp->typeLabel()} for " . self::label($dueAt), $followUp->note, $actor, $followUp, 'scheduled');

        return $followUp;
    }

    /** An activity already done (e.g. "Called — no answer"), recorded with its outcome. */
    public function logActivity(Lead $lead, string $type, string $outcome, ?string $actor): LeadFollowUp
    {
        $followUp = $lead->followUps()->create([
            'type' => $type,
            'status' => LeadFollowUp::COMPLETED,
            'outcome' => $this->clean($outcome),
            'completed_at' => now(),
            'created_by_name' => $actor,
            'completed_by_name' => $actor,
        ]);
        $this->log($lead, "Logged {$followUp->typeLabel()}", $followUp->outcome, $actor, $followUp, 'logged');

        return $followUp;
    }

    public function reschedule(LeadFollowUp $followUp, CarbonInterface $dueAt, ?string $note, ?string $actor): LeadFollowUp
    {
        $this->ensureOpen($followUp);
        $from = $followUp->due_at;
        $followUp->update([
            'due_at' => $dueAt,
            'note' => $note === null ? $followUp->note : $this->clean($note),
            // The new time gets its own reminder.
            'reminded_at' => null,
            'reminded_to' => null,
        ]);
        $this->log($followUp->lead, "Rescheduled {$followUp->typeLabel()} from " . self::label($from) . ' to ' . self::label($dueAt),
            $followUp->note, $actor, $followUp, 'rescheduled', ['from' => $from?->toIso8601String(), 'to' => $dueAt->toIso8601String()]);

        return $followUp;
    }

    public function complete(LeadFollowUp $followUp, string $outcome, ?string $actor): LeadFollowUp
    {
        $this->ensureOpen($followUp);
        $followUp->update([
            'status' => LeadFollowUp::COMPLETED,
            'outcome' => $this->clean($outcome),
            'completed_at' => now(),
            'completed_by_name' => $actor,
        ]);
        $this->log($followUp->lead, "Completed {$followUp->typeLabel()} (was due " . self::label($followUp->due_at) . ')',
            $followUp->outcome, $actor, $followUp, 'completed');

        return $followUp;
    }

    public function cancel(LeadFollowUp $followUp, ?string $reason, ?string $actor): LeadFollowUp
    {
        $this->ensureOpen($followUp);
        $followUp->update([
            'status' => LeadFollowUp::CANCELLED,
            'outcome' => $this->clean($reason),
            'cancelled_at' => now(),
            'completed_by_name' => $actor,
        ]);
        $this->log($followUp->lead, "Cancelled {$followUp->typeLabel()} due " . self::label($followUp->due_at),
            $followUp->outcome, $actor, $followUp, 'cancelled');

        return $followUp;
    }

    /** Who gets the reminder right now: the assigned agent while still on the lead's account, else the owner. */
    public function responsibleFor(Lead $lead): ?PortalUser
    {
        $agent = $lead->agent;
        $agentStillOnLead = $agent && $agent->is_active
            && ($agent->id === $lead->portal_user_id || $agent->company_id === $lead->portal_user_id);

        if ($agentStillOnLead) {
            return $agent;
        }

        return $lead->owner?->is_active ? $lead->owner : null;
    }

    /** Follow-ups on the leads this viewer can see (null = Super Admin, every lead). */
    public function visibleTo(?int $ownerId): Builder
    {
        return LeadFollowUp::query()->whereHas('lead', fn ($q) => $q->forOwner($ownerId));
    }

    /** Leads › Follow-ups: overdue | today | upcoming | done — counts for every tab plus one page of the chosen one. */
    public function listing(?int $ownerId, string $tab, int $page = 1, int $perPage = 20): array
    {
        $endOfToday = now(self::TIMEZONE)->endOfDay()->utc();
        $tabs = [
            'overdue' => fn ($q) => $q->open()->where('due_at', '<', now())->orderBy('due_at'),
            'today' => fn ($q) => $q->open()->whereBetween('due_at', [now(), $endOfToday])->orderBy('due_at'),
            'upcoming' => fn ($q) => $q->open()->where('due_at', '>', $endOfToday)->orderBy('due_at'),
            'done' => fn ($q) => $q->where('status', '!=', LeadFollowUp::SCHEDULED)->orderByDesc('updated_at')->orderByDesc('id'),
        ];
        $tab = array_key_exists($tab, $tabs) ? $tab : 'overdue';

        /** @var LengthAwarePaginator $rows */
        $rows = $tabs[$tab]($this->visibleTo($ownerId))
            ->with(['lead:id,name,portal_user_id,agent_id', 'lead.agent:id,name', 'lead.owner:id,name,company_name,type'])
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'tab' => $tab,
            'counts' => collect($tabs)->map(fn ($apply, $key) => $key === 'done' ? null : $apply($this->visibleTo($ownerId))->count()),
            'data' => collect($rows->items())->map(fn (LeadFollowUp $f) => $this->toArray($f, withLead: true))->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ];
    }

    /** A lead's follow-ups for its screen: still open (soonest first) and the latest done / cancelled. */
    public function forLead(Lead $lead): array
    {
        return [
            'open' => $lead->followUps()->open()->orderBy('due_at')->get()->map(fn ($f) => $this->toArray($f))->values(),
            'done' => $lead->followUps()->where('status', '!=', LeadFollowUp::SCHEDULED)
                ->orderByDesc('updated_at')->orderByDesc('id')->limit(20)->get()->map(fn ($f) => $this->toArray($f))->values(),
            'responsible' => $this->responsibleFor($lead->loadMissing(['agent', 'owner']))?->displayName(),
        ];
    }

    public function toArray(LeadFollowUp $f, bool $withLead = false): array
    {
        return [
            'id' => $f->id,
            'lead_id' => $f->lead_id,
            'type' => $f->type,
            'type_label' => $f->typeLabel(),
            'status' => $f->status,
            'due_at' => $f->due_at?->copy()->timezone(self::TIMEZONE)->toIso8601String(),
            // Already in UAE time, so it reads the same whatever the viewer's computer is set to.
            'due_label' => $f->due_at ? self::label($f->due_at) : null,
            'due_local' => $f->due_at?->copy()->timezone(self::TIMEZONE)->format('Y-m-d\TH:i'),
            'is_overdue' => $f->isOverdue(),
            'note' => $f->note,
            'outcome' => $f->outcome,
            'done_label' => ($f->completed_at ?? $f->cancelled_at) ? self::label($f->completed_at ?? $f->cancelled_at) : null,
            'reminded' => (bool) $f->reminded_at,
            'created_by_name' => $f->created_by_name,
            'completed_by_name' => $f->completed_by_name,
            'lead' => $withLead && $f->lead ? [
                'id' => $f->lead->id,
                'name' => $f->lead->name,
                'agent' => $f->lead->agent?->name,
                'owner' => $f->lead->owner?->displayName(),
            ] : null,
        ];
    }

    /**
     * The scheduled job (leads:send-follow-up-reminders): every follow-up due within
     * REMIND_BEFORE_MINUTES that hasn't been reminded yet → bell + email to its responsible person.
     * Deleted leads are skipped. Returns how many reminders went out.
     */
    public function sendDueReminders(): int
    {
        $sent = 0;

        LeadFollowUp::open()
            ->whereNull('reminded_at')
            ->where('due_at', '<=', now()->addMinutes(self::REMIND_BEFORE_MINUTES))
            ->whereHas('lead')
            ->with(['lead.agent', 'lead.owner'])
            ->chunkById(100, function ($followUps) use (&$sent) {
                foreach ($followUps as $followUp) {
                    // Claim it first, so an overlapping run never sends the same reminder twice.
                    $claimed = LeadFollowUp::whereKey($followUp->id)->open()->whereNull('reminded_at')
                        ->update(['reminded_at' => now()]);
                    if (!$claimed) {
                        continue;
                    }

                    $recipient = $this->responsibleFor($followUp->lead);
                    if (!$recipient) {
                        continue; // Super Admin's own unassigned lead, or a disabled account — nobody to remind.
                    }

                    try {
                        $recipient->notify(new LeadFollowUpReminderNotification($followUp));
                        Mail::to($recipient->email)->queue(new LeadFollowUpReminderMail($followUp, $recipient));
                        $followUp->update(['reminded_to' => $recipient->id]);
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::error("Follow-up reminder #{$followUp->id} failed: " . $e->getMessage());
                    }
                }
            });

        return $sent;
    }

    /** "Tue 27 Oct 2026, 10:00 (UAE)" */
    public static function label(?CarbonInterface $at): string
    {
        return $at ? $at->copy()->timezone(self::TIMEZONE)->format('D d M Y, H:i') . ' (UAE)' : '—';
    }

    /** A "YYYY-MM-DDTHH:mm" value from the form, read as UAE time. */
    public static function parseLocal(string $value): Carbon
    {
        return Carbon::createFromFormat('Y-m-d\TH:i', $value, self::TIMEZONE)->utc();
    }

    private function ensureOpen(LeadFollowUp $followUp): void
    {
        abort_unless($followUp->status === LeadFollowUp::SCHEDULED, 422, 'This follow-up is already ' . $followUp->status . '.');
    }

    private function log(Lead $lead, string $title, ?string $detail, ?string $actor, LeadFollowUp $followUp, string $event, array $meta = []): void
    {
        $this->notes->addNote($lead, $title . ($detail ? "\n" . $detail : ''), $actor, 'follow_up',
            ['follow_up_id' => $followUp->id, 'event' => $event] + $meta);
    }

    private function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : $text;
    }
}
