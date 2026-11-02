<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadFollowUp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo lead follow-ups (not part of DatabaseSeeder — run it on demand):
 *   php artisan db:seed --class=FollowUpDemoSeeder
 *
 * On the 30 most recent leads: overdue, due today, upcoming, completed (with an outcome) and
 * cancelled Calls / WhatsApps / Emails. Past ones count as already reminded, so no reminder burst.
 * Re-running replaces the previous demo follow-ups (matched by their demo notes).
 */
class FollowUpDemoSeeder extends Seeder
{
    private const NOTES = [
        'call' => ['Confirm budget and preferred communities', 'Discuss mortgage pre-approval', 'Arrange a viewing this week', 'Check if still looking — no reply to last message'],
        'whatsapp' => ['Send floor plan and payment plan', 'Share 3 similar listings in the same area', 'Send viewing location pin', 'Follow up on the offer feedback'],
        'email' => ['Send brochure and service charge details', 'Email the SPA draft for review', 'Send comparable sales report', 'Share the off-plan payment schedule'],
    ];

    private const OUTCOMES = [
        'Interested — booked a viewing for Saturday 11am.',
        'Budget is 2.2M max, wants 2 bedrooms near the metro.',
        'No answer, left a voicemail.',
        'Client went with another agency.',
        'Asked to call back after Eid.',
        'Sent the documents; waiting for the bank valuation.',
    ];

    public function run(): void
    {
        $leads = Lead::with('owner')->latest('id')->limit(30)->get();
        if ($leads->isEmpty()) {
            $this->command?->warn('No leads to add follow-ups to.');
            return;
        }

        DB::transaction(function () use ($leads) {
            LeadFollowUp::whereIn('note', collect(self::NOTES)->flatten())->delete();

            $count = 0;
            foreach ($leads as $i => $lead) {
                $by = $lead->owner?->displayName() ?? 'MW Realty';
                foreach ($this->plan($i) as [$state, $offsetHours]) {
                    $type = array_keys(LeadFollowUp::TYPES)[($i + $count) % 3];
                    $due = now()->addHours($offsetHours)->setMinute([0, 15, 30, 45][$count % 4])->setSecond(0);
                    LeadFollowUp::create([
                        'lead_id' => $lead->id,
                        'type' => $type,
                        'status' => $state === 'overdue' || $state === 'scheduled' ? LeadFollowUp::SCHEDULED : $state,
                        'due_at' => $due,
                        'note' => self::NOTES[$type][$count % 4],
                        'outcome' => $state === LeadFollowUp::COMPLETED ? self::OUTCOMES[$count % count(self::OUTCOMES)] : null,
                        'completed_at' => $state === LeadFollowUp::COMPLETED ? $due->copy()->addMinutes(20) : null,
                        'cancelled_at' => $state === LeadFollowUp::CANCELLED ? $due->copy()->subHours(2) : null,
                        // Already in the past = already reminded (the scheduler won't send them now).
                        'reminded_at' => $due->isPast() ? $due : null,
                        'created_by_name' => $by,
                        'completed_by_name' => $state === LeadFollowUp::COMPLETED ? $by : null,
                        'created_at' => $due->copy()->subDays(2),
                        'updated_at' => now(),
                    ]);
                    $count++;
                }
            }
            $this->command?->info("Added {$count} demo follow-ups on {$leads->count()} leads.");
        });
    }

    /** [state, hours from now] per lead — a mix of overdue, today, upcoming, done and cancelled. */
    private function plan(int $i): array
    {
        return match ($i % 6) {
            0 => [['overdue', -26], ['completed', -72]],
            1 => [['scheduled', 2]],
            2 => [['scheduled', 30], ['completed', -50]],
            3 => [['overdue', -5]],
            4 => [['scheduled', 4], ['cancelled', -20]],
            default => [['scheduled', 96], ['completed', -120]],
        };
    }
}
