<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadContact;
use App\Services\Crm\LeadCreationService;
use Illuminate\Console\Command;

/**
 * One-off / on-demand clean-up of duplicate leads saved before duplicate detection existed.
 * Uses the same rule as new enquiries (LeadCreationService): within one owning account, leads
 * sharing any email or phone are one person. Chains count too — A shares an email with B and
 * B a phone with C → A, B and C are one lead. The oldest lead is kept; the rest are merged
 * into it and soft-deleted (restorable from Deleted Leads).
 */
class MergeDuplicateLeads extends Command
{
    protected $signature = 'leads:merge-duplicates {--dry-run : Only list the duplicate groups, change nothing}';

    protected $description = 'Merge existing leads that share an email or phone within the same owner';

    public function handle(LeadCreationService $leadCreation): int
    {
        $groups = $this->duplicateGroups();

        if (!$groups) {
            $this->info('No duplicate leads found.');

            return self::SUCCESS;
        }

        $merged = 0;
        foreach ($groups as $ids) {
            $leads = Lead::with(['contacts', 'property', 'owner'])->whereIn('id', $ids)->orderBy('id')->get();
            $keep = $leads->shift();

            $this->line(sprintf(
                'Owner %s — keep #%d %s; merge %s',
                $keep->owner?->displayName() ?? 'Unassigned',
                $keep->id,
                $keep->name,
                $leads->map(fn ($l) => '#' . $l->id . ' (' . ($l->email ?: $l->formatted_phone) . ')')->implode(', ')
            ));

            if ($this->option('dry-run')) {
                continue;
            }

            foreach ($leads as $duplicate) {
                $leadCreation->mergeDuplicate($keep->refresh(), $duplicate);
                $merged++;
            }
        }

        $this->option('dry-run')
            ? $this->warn(count($groups) . ' duplicate group(s) found. Run without --dry-run to merge.')
            : $this->info("Merged {$merged} duplicate lead(s) into " . count($groups) . ' lead(s).');

        return self::SUCCESS;
    }

    /** @return array<int, int[]> Lead-id groups (2+ leads each) that are the same person within one owner. */
    private function duplicateGroups(): array
    {
        $contacts = LeadContact::query()
            ->join('leads', 'leads.id', '=', 'lead_contacts.lead_id')
            ->whereNull('leads.deleted_at')
            ->get(['lead_contacts.lead_id', 'lead_contacts.type', 'lead_contacts.match_key', 'leads.portal_user_id']);

        // Union-find: link every lead to the first lead seen with the same owner + contact.
        $parent = [];
        $find = function (int $id) use (&$parent, &$find): int {
            $parent[$id] ??= $id;

            return $parent[$id] === $id ? $id : ($parent[$id] = $find($parent[$id]));
        };

        $firstByKey = [];
        foreach ($contacts as $c) {
            $key = ($c->portal_user_id ?? 'none') . '|' . $c->type . '|' . $c->match_key;
            $find($c->lead_id);
            if (isset($firstByKey[$key])) {
                $parent[$find($c->lead_id)] = $find($firstByKey[$key]);
            } else {
                $firstByKey[$key] = $c->lead_id;
            }
        }

        $groups = [];
        foreach (array_keys($parent) as $id) {
            $groups[$find($id)][] = $id;
        }

        return array_values(array_filter($groups, fn ($ids) => count($ids) > 1));
    }
}
