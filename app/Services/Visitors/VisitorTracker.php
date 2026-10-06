<?php

namespace App\Services\Visitors;

use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\User;
use App\Models\Visitors\VisitorBrowser;
use App\Models\Visitors\VisitorEvent;
use App\Models\Visitors\VisitorLead;
use App\Services\Agency\AssignmentActor;
use App\Services\Agency\LeadAssignmentService;
use App\Services\Crm\LeadCreationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Website visitor tracking. A browser is identified by the long-lived mw_vid cookie
 * (VisitorBrowser); it points at the VisitorLead currently using it. Identity changes follow the
 * person, not the browser:
 *
 *   - AI chat details form / any enquiry form / customer sign-in → identify(): the lead with that
 *     account, email or phone (else a new one) becomes the browser's current lead. A different
 *     email / phone in the same browser therefore switches tracking to another lead, and coming
 *     back with the first email / phone (or signing in as that customer) switches it back.
 *   - Anything done before identifying (anonymous page views) is attached to the first lead.
 *   - Customer sign-out detaches the browser, so the next person on it isn't tracked as them.
 *
 * Every event (page / property views with time spent, searches, favorites, saved searches, chats,
 * enquiries) is stored against the current lead. A lead from the AI chat / a general form / a
 * customer account waits in Super Admin's Website Leads pool; once it visits the same listing, or
 * the same agent's / agency's listings, twice, it is routed to that agency / agent and stays there
 * (routeIfInterested). Super Admin can also transfer() it by hand.
 *
 * Tracking must never break the page it runs on, so the form-facing helpers swallow and log errors.
 */
class VisitorTracker
{
    public const COOKIE = 'mw_vid';
    private const COOKIE_MINUTES = 60 * 24 * 365 * 2;
    /** Visits (same listing / agent / agency) before a pool lead is routed — see routeIfInterested(). */
    private const ROUTE_AFTER_VISITS = 2;
    private const VISIT_GAP_MINUTES = 30;
    private const BOT_PATTERN ='/bot|crawl|spider|slurp|lighthouse|headless|preview|facebookexternalhit|bingpreview|pingdom|uptime/i';

    public function __construct(
        private readonly LeadCreationService $leadCreation,
        private readonly LeadAssignmentService $assignment,
    ) {
    }

    public function isBot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(self::BOT_PATTERN, $agent) === 1;
    }

    /**
     * This request's browser. Created (and the cookie set) only on web routes, which can send
     * cookies back; on API routes an unknown browser is simply null.
     */
    public function browser(Request $request): ?VisitorBrowser
    {
        if ($request->attributes->has('visitor_browser')) {
            return $request->attributes->get('visitor_browser');
        }

        $browser = null;
        if (!$this->isBot($request)) {
            $token = $request->cookie(self::COOKIE);
            $token = is_string($token) && preg_match('/^[A-Za-z0-9]{40}$/', $token) ? $token : null;
            $browser = $token ? VisitorBrowser::where('token', $token)->first() : null;

            if (!$browser && $request->hasSession()) {
                $token ??= Str::random(40);
                $browser = VisitorBrowser::firstOrCreate(['token' => $token], [
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
                    'last_seen_at' => now(),
                ]);
                Cookie::queue(Cookie::make(self::COOKIE, $token, self::COOKIE_MINUTES, '/', null, null, true, false, 'lax'));
            }

            if ($browser && (!$browser->last_seen_at || $browser->last_seen_at->lt(now()->subMinute()))) {
                $browser->forceFill(['last_seen_at' => now(), 'ip_address' => $request->ip()])->saveQuietly();
            }
        }

        $request->attributes->set('visitor_browser', $browser);

        return $browser;
    }

    /** Whoever is using this browser — a signed-in customer always wins over a stale identity. */
    public function currentLead(Request $request): ?VisitorLead
    {
        $browser = $this->browser($request);
        $user = $request->hasSession() ? Auth::guard('web')->user() : null;

        if ($user && $browser?->visitorLead?->user_id !== $user->id) {
            return $this->identifyCustomer($request, $user);
        }

        return $browser?->visitorLead;
    }

    /**
     * Find (by account, then email, then phone) or create the lead for these contact details and
     * make it the browser's current lead. Blank details are filled in; known ones are never overwritten.
     *
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, phone_country_code?: ?string}  $contact
     */
    public function identify(Request $request, array $contact, string $source, ?User $user = null): ?VisitorLead
    {
        $email = LeadContact::emailKey($contact['email'] ?? null);
        $phone = trim((string) ($contact['phone'] ?? '')) ?: null;
        $phoneKey = LeadContact::phoneKey($phone);

        if (!$email && !$phoneKey && !$user) {
            return null;
        }

        $lead = DB::transaction(function () use ($request, $contact, $source, $user, $email, $phone, $phoneKey) {
            $lead = ($user ? VisitorLead::where('user_id', $user->id)->lockForUpdate()->first() : null)
                ?? ($email ? VisitorLead::where('email', $email)->orderBy('id')->lockForUpdate()->first() : null)
                ?? ($phoneKey ? VisitorLead::where('phone_key', $phoneKey)->orderBy('id')->lockForUpdate()->first() : null);

            $details = array_filter([
                'name' => trim((string) ($contact['name'] ?? '')) ?: null,
                'email' => $email,
                'phone' => $phone,
                'phone_country_code' => $phone ? ($contact['phone_country_code'] ?? null) : null,
                'phone_key' => $phoneKey,
                'user_id' => $user?->id,
            ], fn ($v) => $v !== null);
            $seen = [
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
                'last_seen_at' => now(),
            ];

            if (!$lead) {
                return VisitorLead::create($details + $seen + ['source' => $source]);
            }

            $blanks = array_filter($details, fn ($value, $field) => blank($lead->{$field})
                // A phone is only taken together with its key / dial code.
                && !in_array($field, ['phone_key', 'phone_country_code'], true), ARRAY_FILTER_USE_BOTH);
            if (isset($blanks['phone'])) {
                $blanks += array_intersect_key($details, array_flip(['phone_key', 'phone_country_code']));
            }
            $lead->fill($blanks + $seen)->save();

            return $lead;
        });

        $this->switchBrowser($request, $lead, $source);
        $this->routeFromHistory($lead);

        return $lead;
    }

    public function identifyCustomer(Request $request, User $user): ?VisitorLead
    {
        return $this->identify($request, [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ], VisitorLead::SOURCE_CUSTOMER_ACCOUNT, $user);
    }

    /** Customer signed in (password / OTP / Google) — tracking continues on their own lead. */
    public function customerSignedIn(Request $request, User $user): void
    {
        $this->safely(fn () => $this->identifyCustomer($request, $user));
    }

    /** Customer signed out — stop attributing this browser to them. */
    public function forget(Request $request): void
    {
        $this->safely(function () use ($request) {
            $this->browser($request)?->forceFill(['visitor_lead_id' => null])->save();
        });
    }

    /** Record one event for the current browser / lead. Viewing a property routes the lead to its owner. */
    public function record(Request $request, string $type, array $attributes = []): ?VisitorEvent
    {
        return $this->safely(function () use ($request, $type, $attributes) {
            $browser = $this->browser($request);
            $lead = $this->currentLead($request);
            if (!$browser && !$lead) {
                return null;
            }

            $event = VisitorEvent::create([
                'visitor_lead_id' => $lead?->id,
                'visitor_browser_id' => $browser?->id,
                'type' => $type,
                'url' => isset($attributes['url']) ? Str::limit($attributes['url'], 990, '') : null,
                'title' => isset($attributes['title']) ? Str::limit($attributes['title'], 250, '') : null,
            ] + array_intersect_key($attributes, array_flip(['property_id', 'chat_conversation_id', 'meta'])));

            if ($lead && (!$lead->last_seen_at || $lead->last_seen_at->lt(now()->subMinute()))) {
                $lead->forceFill(['last_seen_at' => now()])->saveQuietly();
            }

            if ($type === VisitorEvent::PROPERTY_VIEW && $lead && $event->property_id) {
                $this->routeIfInterested($lead, Property::find($event->property_id), $event->url);
            }

            return $event;
        });
    }

    /**
     * A website form was submitted (contact / landing page / property enquiry…): identify the
     * person, log the enquiry, and link the CRM lead it created (if any) back to them.
     */
    public function captureEnquiry(Request $request, array $contact, string $source, array $meta = [], ?Lead $crmLead = null): ?VisitorLead
    {
        return $this->safely(function () use ($request, $contact, $source, $meta, $crmLead) {
            $lead = $this->identify($request, $contact, $source);
            if (!$lead) {
                return null;
            }

            if ($crmLead && !$crmLead->visitor_lead_id) {
                $crmLead->forceFill(['visitor_lead_id' => $lead->id])->saveQuietly();
            }

            VisitorEvent::create([
                'visitor_lead_id' => $lead->id,
                'visitor_browser_id' => $this->browser($request)?->id,
                'type' => VisitorEvent::ENQUIRY,
                'property_id' => $crmLead?->property_id,
                'url' => Str::limit((string) $request->header('referer'), 990, '') ?: null,
                'title' => Str::limit((string) ($meta['form'] ?? 'Website form'), 250, ''),
                'meta' => array_filter($meta + ['crm_lead_id' => $crmLead?->id], fn ($v) => $v !== null && $v !== ''),
            ]);

            return $lead;
        });
    }

    /**
     * Auto-routing: a website lead stays in Super Admin's pool until it shows real interest in one
     * account — 2+ visits to the same listing, or to the same agent's listings, or to the same
     * agency's listings. Then it goes to that agency / agent (the listing's usual lead assignment
     * picks the agent) and stays there: a lead already routed is never moved or shared by this.
     * Views of one listing within VISIT_GAP_MINUTES of each other are one visit (refreshes, back/forward).
     */
    public function routeIfInterested(VisitorLead $lead, ?Property $property, ?string $url = null): ?Lead
    {
        if (!$property || (!$lead->email && !$lead->phone)) {
            return null;
        }

        // One person, one account: already an agency's / agent's lead → it stays there.
        if ($existing = $this->existingCrmLeads($lead)->first()) {
            $this->link($lead, $existing);

            return null;
        }

        $ownerId = (int) $this->assignment->resolveOwnerId($property, $property->portal_user_id);
        if (!$ownerId) {
            return null;
        }

        $visits = $this->propertyVisits($lead);
        $viewed = Property::whereIn('id', array_keys($visits))->get(['id', 'portal_user_id', 'agent_id']);
        $sum = fn (callable $matches) => $viewed->filter($matches)->sum(fn ($p) => $visits[$p->id]);

        $listingVisits = $visits[$property->id] ?? 0;
        $agentVisits = $property->agent_id ? $sum(fn ($p) => (int) $p->agent_id === (int) $property->agent_id) : 0;
        $accountVisits = $sum(fn ($p) => (int) $this->assignment->resolveOwnerId($p, $p->portal_user_id) === $ownerId);

        $reason = match (true) {
            $listingVisits >= self::ROUTE_AFTER_VISITS => "visited this listing {$listingVisits} times",
            $agentVisits >= self::ROUTE_AFTER_VISITS => "visited this agent's listings {$agentVisits} times",
            $accountVisits >= self::ROUTE_AFTER_VISITS => "visited this agency's listings {$accountVisits} times",
            default => null,
        };

        return $reason ? $this->routeToPropertyOwner($lead, $property, $ownerId, $reason, $url) : null;
    }

    /** property_id => visits, counting repeat views of one listing within VISIT_GAP_MINUTES as one. */
    private function propertyVisits(VisitorLead $lead): array
    {
        $visits = [];
        $lastSeen = [];
        $lead->events()->where('type', VisitorEvent::PROPERTY_VIEW)->whereNotNull('property_id')
            ->orderBy('created_at')->get(['property_id', 'created_at'])
            ->each(function ($event) use (&$visits, &$lastSeen) {
                $id = $event->property_id;
                if (!isset($lastSeen[$id]) || $lastSeen[$id]->diffInMinutes($event->created_at) >= self::VISIT_GAP_MINUTES) {
                    $visits[$id] = ($visits[$id] ?? 0) + 1;
                }
                $lastSeen[$id] = $event->created_at;
            });

        return $visits;
    }

    /**
     * Once identified, anything they viewed before (anonymously, or before this identity) may
     * already qualify — check their most recently viewed listings.
     */
    private function routeFromHistory(VisitorLead $lead): void
    {
        $this->safely(function () use ($lead) {
            $recent = $lead->events()->where('type', VisitorEvent::PROPERTY_VIEW)->whereNotNull('property_id')
                ->latest('id')->limit(50)->pluck('property_id')->unique()->take(10);
            foreach (Property::whereIn('id', $recent)->get() as $property) {
                if ($this->routeIfInterested($lead, $property)) {
                    return;
                }
            }
        });
    }

    /**
     * This person's CRM leads in agency / agent accounts — the one linked to their website lead,
     * or any sharing their email / phone (an earlier enquiry, Facebook, import…). Linked first,
     * then oldest. Normally one: a person belongs to a single account.
     */
    public function existingCrmLeads(VisitorLead $lead): \Illuminate\Support\Collection
    {
        return $this->crmLeadsFor($lead->email, $lead->phone_key, $lead->id);
    }

    /**
     * The account an enquiry from this person must go to because they already belong to it
     * (one person, one agency / agent), or null when they don't belong to any yet. Used by the
     * website's enquiry forms (LeadCaptureController) before the lead is created.
     */
    public function assignedOwnerIdFor(Request $request, ?string $email, ?string $phone): ?int
    {
        return $this->safely(function () use ($request, $email, $phone) {
            $visitorLeadId = $this->browser($request)?->visitor_lead_id;

            return $this->crmLeadsFor(LeadContact::emailKey($email), LeadContact::phoneKey($phone), $visitorLeadId)
                ->first()?->portal_user_id;
        });
    }

    private function crmLeadsFor(?string $emailKey, ?string $phoneKey, ?int $visitorLeadId): \Illuminate\Support\Collection
    {
        if (!$emailKey && !$phoneKey && !$visitorLeadId) {
            return collect();
        }

        return Lead::whereNotNull('portal_user_id')
            ->where(fn ($q) => $q
                ->when($visitorLeadId, fn ($w) => $w->orWhere('visitor_lead_id', $visitorLeadId))
                ->when($emailKey || $phoneKey, fn ($w) => $w->orWhereHas('contacts', fn ($c) => $c->where(fn ($match) => $match
                    ->when($emailKey, fn ($m) => $m->orWhere(fn ($e) => $e->where('type', LeadContact::TYPE_EMAIL)->where('match_key', $emailKey)))
                    ->when($phoneKey, fn ($m) => $m->orWhere(fn ($p) => $p->where('type', LeadContact::TYPE_PHONE)->where('match_key', $phoneKey)))))))
            ->orderByRaw('CASE WHEN visitor_lead_id = ? THEN 0 ELSE 1 END', [(int) $visitorLeadId])
            ->orderBy('id')
            ->get();
    }

    /** Hand the lead (in no account yet) to this account as a new CRM lead. */
    private function routeToPropertyOwner(VisitorLead $lead, Property $property, int $ownerId, string $reason, ?string $url = null): ?Lead
    {
        $title = $property->getTranslation('title') ?: $property->reference_no;
        $crmLead = $this->leadCreation->create([
            'property_id' => $property->id,
            'user_id' => $lead->user_id,
            'visitor_lead_id' => $lead->id,
            'name' => $lead->name ?: 'Website visitor',
            'email' => $lead->email,
            'phone' => $lead->phone,
            'phone_country_code' => $lead->phone_country_code,
            'message' => "Interested in \"{$title}\" — {$reason} on the website"
                . ($lead->source === VisitorLead::SOURCE_CHATBOT ? ' after chatting with the AI assistant' : '')
                . '. Their chat history and browsing activity are on the Insights tab.',
            'page_url' => $url,
            'page_source' => $lead->crmPageSource(),
            'status' => 'active',
        ], ownerId: $ownerId);

        $this->linkAndLog($lead, $crmLead, VisitorEvent::ROUTED, ['property' => $title, 'reason' => ucfirst($reason)], $property->id);

        return $crmLead;
    }

    /**
     * Super Admin hands the lead to an agency or agent — a MOVE, never a copy (one person, one
     * account):
     *   - already a lead in that account → that lead is updated (transfer logged on it);
     *   - a lead in another account → that lead moves to this one, history and notes included;
     *   - none yet → a new CRM lead is created there.
     * Any further copies of the person in other accounts are merged into the kept lead (and
     * removed from those accounts — restorable from Deleted Leads). An agent who works within an
     * agency gets it inside that agency (owner = agency, assigned to the agent).
     */
    public function transfer(VisitorLead $lead, PortalUser $target, ?string $note = null, ?int $adminId = null, ?string $adminName = null): Lead
    {
        $agency = $target->isAgent() && $target->company_id ? PortalUser::find($target->company_id) : null;
        $withinAgency = $agency?->hasEligibleAgent($target->id) ?? false;
        $ownerId = $withinAgency ? $agency->id : $target->id;

        $existing = $this->existingCrmLeads($lead);
        $previousOwners = $existing->map(fn ($l) => $l->owner?->displayName())->filter()->unique()->values();
        $summary = 'Transferred from Super Admin\'s Website Leads' . ($adminName ? " by {$adminName}" : '') . '.'
            . ($note ? "\n\n" . $note : '');

        $isNew = false;
        $crmLead = DB::transaction(function () use ($lead, $existing, $ownerId, $withinAgency, $summary, $adminName, $note, &$isNew) {
            $crmLead = $existing->firstWhere('portal_user_id', $ownerId) ?? $existing->first();
            $isNew = !$crmLead;

            if ($isNew) {
                $crmLead = $this->leadCreation->create([
                    'property_id' => $lead->events()->where('type', VisitorEvent::PROPERTY_VIEW)->latest('id')->value('property_id'),
                    'user_id' => $lead->user_id,
                    'visitor_lead_id' => $lead->id,
                    'name' => $lead->name ?: 'Website visitor',
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'phone_country_code' => $lead->phone_country_code,
                    'message' => $summary . "\n\nTheir chat history and browsing activity are on the Insights tab.",
                    'page_source' => $lead->crmPageSource(),
                    'status' => 'active',
                ], ownerId: $ownerId, autoAssign: !$withinAgency);
            } elseif ((int) $crmLead->portal_user_id !== $ownerId) {
                app(\App\Services\Crm\LeadService::class)->assignOwner($crmLead, $ownerId);
            }

            // Copies of this person in other accounts fold into the kept lead.
            foreach ($existing->reject(fn ($l) => $l->is($crmLead)) as $duplicate) {
                // Its agent works for the other account — never carried over into this one.
                $duplicate->forceFill(['agent_id' => null, 'assignment_type' => null, 'assigned_at' => null])->saveQuietly();
                $this->leadCreation->mergeDuplicate($crmLead, $duplicate);
            }

            if (!$isNew) {
                $crmLead->notesHistory()->create([
                    'type' => \App\Models\LeadNote::TYPE_ENQUIRY,
                    'body' => $summary,
                    'meta' => array_filter(['page_source' => $lead->crmPageSource(), 'message' => $note]),
                    'author_name' => $adminName,
                ]);
            }

            return $crmLead->fresh();
        });

        if ($withinAgency && (int) $crmLead->agent_id !== $target->id) {
            $this->assignment->assignManually($crmLead, $target->id, AssignmentActor::admin($adminId), 'Transferred from Website Leads');
        }

        // A moved / updated lead: the account hears about it like a new one (a new lead was
        // already announced by LeadCreationService). The assigned agent is told by assignManually.
        if (!$isNew && ($owner = $crmLead->owner)) {
            try {
                $owner->notify(new \App\Notifications\NewLeadNotification($crmLead));
                if ($owner->email) {
                    \Illuminate\Support\Facades\Mail::to($owner->email)->queue((new \App\Mail\NewLeadReceived($crmLead))->afterCommit());
                }
            } catch (\Throwable $e) {
                Log::warning('Website lead transfer notification failed: ' . $e->getMessage());
            }
        }

        $this->linkAndLog($lead, $crmLead, VisitorEvent::TRANSFERRED, array_filter([
            'by' => $adminName,
            'note' => $note,
            'from' => $previousOwners->reject(fn ($name) => $name === $crmLead->owner?->displayName())->implode(', ') ?: null,
        ]));

        return $crmLead;
    }

    /** Point a CRM lead at the website lead it belongs to (its Insights tab), if not linked yet. */
    private function link(VisitorLead $lead, Lead $crmLead): void
    {
        if (!$crmLead->visitor_lead_id) {
            $crmLead->forceFill(['visitor_lead_id' => $lead->id])->saveQuietly();
        }
    }

    private function linkAndLog(VisitorLead $lead, Lead $crmLead, string $type, array $meta = [], ?int $propertyId = null): void
    {
        $this->link($lead, $crmLead);

        $owner = $crmLead->owner;
        VisitorEvent::create([
            'visitor_lead_id' => $lead->id,
            'type' => $type,
            'property_id' => $propertyId,
            'title' => $owner?->displayName() ?? 'Unassigned',
            'meta' => $meta + array_filter([
                'crm_lead_id' => $crmLead->id,
                'owner' => $owner?->displayName(),
                'agent' => $crmLead->agent?->name,
            ]),
        ]);
    }

    /** Point the browser at this lead; anonymous history from this browser now belongs to them. */
    private function switchBrowser(Request $request, VisitorLead $lead, string $source): void
    {
        $browser = $this->browser($request);
        $switched = $browser && (int) $browser->visitor_lead_id !== $lead->id;

        if ($switched) {
            VisitorEvent::where('visitor_browser_id', $browser->id)->whereNull('visitor_lead_id')->update(['visitor_lead_id' => $lead->id]);
            // The AI chat they started before giving their details.
            \App\Models\Visitors\ChatConversation::where('visitor_browser_id', $browser->id)->whereNull('visitor_lead_id')->update(['visitor_lead_id' => $lead->id]);
            $browser->forceFill(['visitor_lead_id' => $lead->id])->save();
            $browser->setRelation('visitorLead', $lead);
        }

        if ($switched || $lead->wasRecentlyCreated) {
            VisitorEvent::create([
                'visitor_lead_id' => $lead->id,
                'visitor_browser_id' => $browser?->id,
                'type' => VisitorEvent::IDENTIFIED,
                'title' => VisitorLead::SOURCE_LABELS[$source] ?? $source,
                'meta' => ['source' => $source, 'new' => $lead->wasRecentlyCreated],
            ]);
        }
    }

    private function safely(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::warning('Visitor tracking failed: ' . $e->getMessage(), ['exception' => $e]);

            return null;
        }
    }
}
