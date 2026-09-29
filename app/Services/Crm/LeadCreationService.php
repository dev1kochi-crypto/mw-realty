<?php

namespace App\Services\Crm;

use App\Mail\NewLeadReceived;
use App\Models\CmsKit\Admin;
use App\Models\CmsKit\SiteInformation;
use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\LeadNote;
use App\Models\PortalUser;
use App\Models\Property;
use App\Notifications\NewLeadNotification;
use App\Services\Agency\AssignmentActor;
use App\Services\Agency\LeadAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * THE single entry point for adding a lead — website enquiries (property / brochure / agent &
 * agency profile / custom request), the CRM "Add Lead" form and the Excel import all call
 * create(). Anything that should happen to every new lead belongs here.
 *
 * create() does, in order:
 *   1. Owner   — the account the lead belongs to (explicit, or resolved from the property).
 *   2. Dedup   — same email OR phone as an existing lead of that owner → update that lead
 *                (see findDuplicate / mergeInto) instead of creating a duplicate.
 *   3. Create  — otherwise a new lead, with its tags.
 *   4. Assign  — preferred agent, or the automatic property-agent / round-robin rules.
 *   5. Notify  — owner (or Super Admin for unassigned leads) and the agent working it.
 *
 * Duplicate boundary = the owning account (leads.portal_user_id). An agency and the agents it
 * shares leads with work from one lead; the same person enquiring with an unrelated agent or
 * agency is a separate lead there — expected, not a duplicate. Ownerless leads (Super Admin's
 * unassigned pool) de-duplicate among themselves. Deleted leads never match.
 */
class LeadCreationService
{
    public function __construct(private readonly LeadAssignmentService $assignment)
    {
    }

    /**
     * @param  array  $attributes  Lead columns (name, email, phone, message, page_source, property_id, …).
     * @param  int|null  $ownerId  Owning account. Omit for a property lead to use the listing's owner;
     *                             null with no property = Super Admin's unassigned pool.
     * @param  int|null  $preferredAgentId  Agency agent the enquirer chose (agent-profile request).
     * @param  bool  $autoAssign  Run the automatic assignment rules for a new lead.
     * @param  int[]  $tagIds
     * @param  bool  $notify  Bell + email the owner / agent (website enquiries).
     * @param  string|null  $noteAuthor  Shown on the "Repeat enquiry" history entry.
     * @return Lead  The new lead, or the existing one it was merged into ($lead->wasMerged).
     */
    public function create(
        array $attributes,
        ?int $ownerId = null,
        ?int $preferredAgentId = null,
        bool $autoAssign = true,
        array $tagIds = [],
        bool $notify = true,
        ?string $noteAuthor = null,
    ): Lead {
        $property = !empty($attributes['property_id']) ? Property::find($attributes['property_id']) : null;
        $ownerId ??= $property ? $this->assignment->resolveOwnerId($property, $property->portal_user_id) : null;

        $lead = DB::transaction(function () use ($attributes, $ownerId, $preferredAgentId, $autoAssign, $tagIds, $noteAuthor, $property) {
            if ($existing = $this->findDuplicate($ownerId, $attributes['email'] ?? null, $attributes['phone'] ?? null)) {
                $this->mergeInto($existing, $attributes, $noteAuthor);
                if ($tagIds) {
                    $existing->tags()->syncWithoutDetaching($tagIds);
                }
                // It keeps the agent already working it; an unworked lead is routed like a new one
                // (chosen agent, else the listing just enquired about / round robin) so it reaches an agent.
                if (!$existing->agent_id) {
                    if ($preferredAgentId) {
                        $this->assignment->assignManually($existing, $preferredAgentId, AssignmentActor::system(), 'Repeat request from agent profile');
                    } elseif ($autoAssign) {
                        $this->assignment->assignNewLead($existing, $property);
                    }
                    if ($existing->agent_id) {
                        $existing->assignedOnMerge = true;
                    }
                }

                return $existing;
            }

            $lead = Lead::create(['portal_user_id' => $ownerId] + $attributes);
            if ($tagIds) {
                $lead->tags()->sync($tagIds);
            }

            if ($preferredAgentId) {
                $this->assignment->assignManually($lead, $preferredAgentId, AssignmentActor::system(), 'Custom request from agent profile');
            } elseif ($autoAssign) {
                $this->assignment->assignNewLead($lead);
            }

            return $lead;
        }, LeadAssignmentService::TRANSACTION_ATTEMPTS);

        if ($notify) {
            $this->notify($lead);
        }

        return $lead;
    }

    /** The owner's existing (not deleted) lead sharing this email or phone — any contact it has used. */
    public function findDuplicate(?int $ownerId, ?string $email, ?string $phone): ?Lead
    {
        $emailKey = LeadContact::emailKey($email);
        $phoneKey = LeadContact::phoneKey($phone);

        if (!$emailKey && !$phoneKey) {
            return null;
        }

        return Lead::query()
            ->when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId), fn ($q) => $q->whereNull('portal_user_id'))
            ->whereHas('contacts', fn ($q) => $q->where(function ($match) use ($emailKey, $phoneKey) {
                if ($emailKey) {
                    $match->orWhere(fn ($c) => $c->where('type', LeadContact::TYPE_EMAIL)->where('match_key', $emailKey));
                }
                if ($phoneKey) {
                    $match->orWhere(fn ($c) => $c->where('type', LeadContact::TYPE_PHONE)->where('match_key', $phoneKey));
                }
            }))
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Fold a repeat enquiry into the existing lead: remember any new email / phone, fill in
     * details it was missing, bump the enquiry count and log the enquiry in its activity
     * history. Stage, status, owner and assigned agent stay as the team set them.
     */
    private function mergeInto(Lead $lead, array $attributes, ?string $noteAuthor): void
    {
        $lead->recordContacts($attributes['email'] ?? null, $attributes['phone'] ?? null, $attributes['phone_country_code'] ?? null);

        $fill = [];
        foreach (['name', 'email', 'phone', 'phone_country_code', 'company', 'country', 'property_id', 'user_id', 'message', 'stage_id', 'source_id', 'notes'] as $field) {
            if (blank($lead->{$field}) && filled($attributes[$field] ?? null)) {
                $fill[$field] = $attributes[$field];
            }
        }
        if (!empty($attributes['extra_fields'])) {
            $fill['extra_fields'] = array_merge($lead->extra_fields ?? [], array_filter($attributes['extra_fields'], fn ($v) => $v !== null && $v !== ''));
        }

        // Blanks filled from the enquiry aren't team edits — the enquiry entry below covers them.
        Lead::withoutActivityLog(fn () => $lead->fill($fill + [
            'enquiry_count' => (int) $lead->enquiry_count + 1,
            'last_enquired_at' => now(),
        ])->save());

        // meta feeds the lead page's Source history (where each enquiry came from).
        $lead->notesHistory()->create([
            'type' => LeadNote::TYPE_ENQUIRY,
            'body' => $this->enquirySummary($attributes),
            'meta' => array_filter([
                'page_source' => $attributes['page_source'] ?? null,
                'page_url' => $attributes['page_url'] ?? null,
                'property_id' => $attributes['property_id'] ?? null,
                'source_id' => $attributes['source_id'] ?? null,
                'name' => $attributes['name'] ?? null,
                'email' => $attributes['email'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'message' => $attributes['message'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'author_name' => $noteAuthor,
        ]);

        $lead->wasMerged = true;
    }

    /**
     * Clean-up for duplicates created before duplicate detection existed (leads:merge-duplicates):
     * folds $duplicate into $keep the same way a repeat enquiry is folded in — contacts, tags and
     * notes move over, blanks (incl. agent, if $keep has none) are filled, enquiry
     * counts add up — then $duplicate is soft-deleted, so it can still be restored from Deleted Leads.
     */
    public function mergeDuplicate(Lead $keep, Lead $duplicate): void
    {
        DB::transaction(function () use ($keep, $duplicate) {
            foreach ($duplicate->contacts as $contact) {
                $keep->recordContacts(
                    $contact->type === LeadContact::TYPE_EMAIL ? $contact->value : null,
                    $contact->type === LeadContact::TYPE_PHONE ? $contact->value : null,
                    $contact->phone_country_code,
                );
            }

            $fill = [];
            foreach (['name', 'email', 'phone', 'phone_country_code', 'company', 'country', 'property_id', 'user_id', 'message', 'stage_id', 'source_id', 'notes'] as $field) {
                if (blank($keep->{$field}) && filled($duplicate->{$field})) {
                    $fill[$field] = $duplicate->{$field};
                }
            }
            if (!$keep->agent_id && $duplicate->agent_id) {
                $fill += ['agent_id' => $duplicate->agent_id, 'assignment_type' => $duplicate->assignment_type, 'assigned_at' => $duplicate->assigned_at];
            }
            $fill['extra_fields'] = array_merge($duplicate->extra_fields ?? [], $keep->extra_fields ?? []) ?: null;

            Lead::withoutActivityLog(fn () => $keep->forceFill($fill + [
                'enquiry_count' => (int) $keep->enquiry_count + (int) $duplicate->enquiry_count,
                'last_enquired_at' => max(array_filter([
                    $keep->last_enquired_at, $duplicate->last_enquired_at ?? $duplicate->created_at, $keep->created_at,
                ])),
            ])->save());

            $keep->tags()->syncWithoutDetaching($duplicate->tags()->pluck('lead_tags.id')->all());
            // The team's own notes move over; the merge itself isn't logged — it's a data clean-up,
            // not something that happened with the client.
            $duplicate->notesHistory()->update(['lead_id' => $keep->id]);
            $duplicate->delete();
        });
    }

    private function enquirySummary(array $attributes): string
    {
        $contact = implode(' · ', array_filter([$attributes['name'] ?? null, $attributes['email'] ?? null, $attributes['phone'] ?? null]));
        $property = !empty($attributes['property_id']) ? Property::find($attributes['property_id'])?->getTranslation('title') : null;

        return implode("\n", array_filter([
            'Repeat enquiry' . (!empty($attributes['page_source']) ? ' (' . $attributes['page_source'] . ')' : '') . ($contact ? ' — ' . $contact : ''),
            $property ? 'Property: ' . $property : null,
            $attributes['message'] ?? null,
        ]));
    }

    /**
     * The owning account (agency / agent) is told about every enquiry; with no owner, Super Admin
     * is, so it can transfer the lead. A NEW lead's assigned agent is told by LeadAssignmentService;
     * a merged one isn't re-assigned, so its agent is told here.
     */
    private function notify(Lead $lead): void
    {
        if ($owner = $lead->owner) {
            $this->notifyAccount($owner, $lead);
        } else {
            $this->notifyAdmins($lead);
        }

        if ($lead->wasMerged && !$lead->assignedOnMerge && $lead->agent_id && $lead->agent_id !== $lead->portal_user_id
            && ($agent = PortalUser::find($lead->agent_id))) {
            $this->notifyAccount($agent, $lead);
        }
    }

    private function notifyAccount(PortalUser $account, Lead $lead): void
    {
        try {
            $account->notify(new NewLeadNotification($lead));
        } catch (\Throwable $e) {
            Log::error('Failed to create new-lead bell notification: ' . $e->getMessage());
        }

        if ($account->email) {
            Mail::to($account->email)->queue((new NewLeadReceived($lead))->afterCommit());
        }
    }

    private function notifyAdmins(Lead $lead): void
    {
        try {
            Admin::role('superadmin')->get()->each(fn (Admin $admin) => $admin->notify(new NewLeadNotification($lead)));
        } catch (\Throwable $e) {
            Log::error('Failed to create unassigned-lead bell notification: ' . $e->getMessage());
        }

        if ($adminEmail = SiteInformation::notificationEmail()) {
            Mail::to($adminEmail)->queue((new NewLeadReceived($lead))->afterCommit());
        }
    }
}
