<?php

namespace App\Http\Controllers\Crm\Integrations;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Jobs\ImportFacebookPageLeads;
use App\Models\FacebookPageConnection;
use App\Models\PortalUser;
use App\Services\Crm\AdminOwnerResolver;
use App\Services\Integrations\FacebookConnectionHealth;
use App\Services\Integrations\FacebookLeadAds;
use App\Services\Integrations\FacebookLeadImporter;
use App\Services\Integrations\FacebookTokenException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @group CRM Integrations — Facebook Lead Ads
 *
 * Facebook Pages whose Lead Ads leads arrive in an agency's / agent's CRM.
 *
 * Connect (GET connect-url) → Facebook Login → the public callback (no CRM login needed — the
 * one-time state in the cache names who started it) lists the Pages the Facebook user manages →
 * the Super Admin links each Page to an agency or independent agent, or any agency / agent
 * connects Pages to their own account (an agency agent's become their personal leads) → each is
 * saved with its Page token and subscribed to the app's "leadgen" webhook (Api\FacebookWebhookController).
 * A Page belongs to one account only: the webhook names just the Page, so it must point to a single
 * CRM (never the admin's own). "Sync now" pulls recent leads directly (FacebookLeadImporter::sync).
 */
class FacebookController extends Controller
{
    use ScopesPortalOwner;

    private const STATE_CACHE = 'facebook_integration.state.';
    private const PAGES_CACHE = 'facebook_integration.pages.';
    /** The callback's outcome, shown by the next show() of whoever started the login. */
    private const NOTICE_CACHE = 'facebook_integration.notice.';
    private const PENDING_MINUTES = 15;

    public function __construct(private readonly FacebookLeadAds $facebook)
    {
    }

    /**
     * Facebook Lead Ads screen
     *
     * Connected Pages (Super Admin: every Page, searched and paged 20 at a time; otherwise the
     * account's own), the Pages waiting to be picked after a Facebook Login, and — once — the
     * outcome of that login (`notice`).
     *
     * @queryParam q string Super Admin: Page or account name. Example: marina
     * @queryParam page integer Example: 1
     */
    public function show(Request $request)
    {
        $isAdmin = $this->isAdmin();
        $canManage = $this->canManage();
        $pending = $canManage ? $this->pendingPages() : [];
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $connections = $isAdmin
            ? FacebookPageConnection::with('owner:id,type,name,company_name')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('page_name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($o) => $o->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"))))
                ->orderBy('page_name')->paginate(20)
            : FacebookPageConnection::where('portal_user_id', $this->ownerId() ?? 0)->orderBy('page_name')->get();
        $rows = collect($isAdmin ? $connections->items() : $connections);
        $rows->each(fn (FacebookPageConnection $c) => $c->failStaleImport());

        // Pending Pages already connected — to this account (reconnect refreshes) or another (blocked).
        $existing = $pending
            ? FacebookPageConnection::with('owner:id,type,name,company_name')->whereIn('page_id', array_column($pending, 'id'))->get()->keyBy('page_id')
            : collect();

        $notice = $canManage ? Cache::pull(self::NOTICE_CACHE . $this->actorKey()) : null;

        return response()->json([
            'is_admin' => $isAdmin,
            'can_manage' => $canManage,
            'is_agency_agent' => (bool) $this->owner()?->isAgencyAgent(),
            'configured' => $this->facebook->configured(),
            'search' => $search,
            'connected_count' => $isAdmin ? $connections->total() : $connections->count(),
            'connections' => $rows->map(fn (FacebookPageConnection $c) => $this->connectionJson($c))->values(),
            'meta' => $isAdmin ? ['current_page' => $connections->currentPage(), 'last_page' => $connections->lastPage(), 'total' => $connections->total(), 'from' => $connections->firstItem(), 'to' => $connections->lastItem()] : null,
            'pending_pages' => collect($pending)->map(function (array $page) use ($existing, $isAdmin) {
                $connection = $existing->get($page['id']);
                $mine = $connection && !$isAdmin && $connection->portal_user_id === $this->ownerId();

                return [
                    'id' => (string) $page['id'],
                    'name' => $page['name'],
                    'picture' => $page['picture'] ?? null,
                    'state' => match (true) {
                        $connection && !$mine => 'blocked',
                        (bool) $connection => 'refresh',
                        default => 'new',
                    },
                    'connected_to' => $connection && !$mine ? ($isAdmin ? ($connection->owner?->displayName() ?? 'another account') : 'another account') : null,
                ];
            })->values(),
            'notice' => $notice,
            'setup' => $isAdmin && !$this->facebook->configured() ? [
                'callback_url' => route('integrations.facebook.callback'),
                'webhook_url' => route('webhooks.facebook'),
                'scopes' => FacebookLeadAds::SCOPES,
            ] : null,
        ]);
    }

    private function connectionJson(FacebookPageConnection $c): array
    {
        $recent = fn () => $c->import_finished_at?->gt(now()->subDays(3)) ?? false;

        return [
            'id' => $c->id,
            'page_name' => $c->page_name,
            'owner' => $c->owner ? ['name' => $c->owner->displayName(), 'is_agency' => $c->owner->isAgency()] : null,
            'connected_by' => $c->connected_by,
            'created_at' => $c->created_at?->toIso8601String(),
            'subscribed_at' => $c->subscribed_at?->toIso8601String(),
            'needs_reconnect_at' => $c->needs_reconnect_at?->toIso8601String(),
            'reconnect_notified_at' => $c->reconnect_notified_at?->toIso8601String(),
            'last_error' => $c->last_error,
            'leads_count' => (int) $c->leads_count,
            'last_lead_at' => $c->last_lead_at?->toIso8601String(),
            'last_synced_at' => $c->last_synced_at?->toIso8601String(),
            'import' => [
                'in_progress' => $c->importInProgress(),
                'status' => $c->import_status,
                'added' => (int) $c->import_added,
                'skipped' => (int) $c->import_skipped,
                'error' => $c->import_error,
                // done / empty / failed results show for three days after they finish.
                'recent' => $recent(),
            ],
        ];
    }

    /**
     * Agencies + independent agents a Page can be linked to
     *
     * Super Admin only. Searched and paged on the server, 20 per request.
     *
     * @queryParam search string Example: prime
     * @queryParam page integer Example: 1
     */
    public function accounts(Request $request)
    {
        abort_unless($this->isAdmin(), 403);
        $term = mb_substr(trim((string) $request->input('search', $request->input('q', ''))), 0, 100);

        $page = $this->linkableAccounts()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('company_name', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->orderByRaw("COALESCE(NULLIF(company_name, ''), name)")
            ->paginate(20, ['id', 'type', 'name', 'company_name', 'company_id', 'email'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => collect($page->items())->map(fn (PortalUser $account) => [
                'id' => $account->id,
                'name' => $account->displayName() . ($account->isAgency() ? ' — Agency' : ' — Agent') . " ({$account->email})",
                'color' => null,
            ]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    /**
     * Start a Facebook Login
     *
     * Returns the Facebook Login address to open — in a popup over the screen (`popup=1`: the
     * callback closes it and tells the screen to reload), or the whole window / an in-app browser.
     *
     * @queryParam popup boolean Example: true
     * @response 200 {"url": "https://www.facebook.com/v19.0/dialog/oauth?..."}
     */
    public function connectUrl(Request $request)
    {
        $this->authorizeManage();
        abort_unless($this->facebook->configured(), 422, 'Facebook isn\'t set up yet — add FACEBOOK_APP_ID and FACEBOOK_APP_SECRET to the .env file.');

        $state = Str::random(40);
        Cache::put(self::STATE_CACHE . $state, ['actor' => $this->actorKey(), 'popup' => $request->boolean('popup')], now()->addMinutes(self::PENDING_MINUTES));

        return response()->json(['url' => $this->facebook->loginUrl(route('integrations.facebook.callback'), $state)]);
    }

    /**
     * Public web route (outside the CRM login): Facebook sends the browser back here. The one-time
     * state names who started the login; their Pages wait in the cache for them to pick.
     *
     * @hideFromAPIDocumentation
     */
    public function callback(Request $request)
    {
        $started = $request->filled('state') ? Cache::pull(self::STATE_CACHE . $request->input('state')) : null;
        $actor = $started['actor'] ?? null;
        $popup = (bool) ($started['popup'] ?? false);

        if ($request->filled('error')) {
            return $this->finishLogin($actor, $popup, 'error', 'Facebook connection cancelled' . ($request->filled('error_description') ? ': ' . $request->input('error_description') : '.'));
        }
        if (!$actor || !$request->filled('code')) {
            return $this->finishLogin($actor, $popup, 'error', 'The Facebook login could not be verified — please try connecting again.');
        }

        try {
            $userToken = $this->facebook->userToken((string) $request->input('code'), route('integrations.facebook.callback'));
            $pages = $this->facebook->pages($userToken);
        } catch (\Throwable $e) {
            Log::warning('Facebook connect failed: ' . $e->getMessage());

            return $this->finishLogin($actor, $popup, 'error', 'Facebook connection failed: ' . $e->getMessage());
        }

        if (!$pages) {
            return $this->finishLogin($actor, $popup, 'error', 'No Facebook Pages found. Log in with a Facebook account that manages the Page, and allow access to it.');
        }

        Cache::put(self::PAGES_CACHE . $actor, encrypt($pages), now()->addMinutes(self::PENDING_MINUTES));

        return $this->finishLogin($actor, $popup, 'success', str_starts_with($actor, 'admin.') ? 'Choose the agency or agent each Facebook Page belongs to.' : 'Choose the Facebook Pages to connect.');
    }

    /** Back to the Facebook screen with a message: a popup closes itself and tells the screen that opened it. */
    private function finishLogin(?string $actor, bool $popup, string $type, string $message)
    {
        if ($actor) {
            Cache::put(self::NOTICE_CACHE . $actor, ['type' => $type, 'message' => $message], now()->addMinutes(self::PENDING_MINUTES));
        }
        $screen = route('crm.app', 'integrations/facebook');

        return $popup
            ? response()->view('crm.integrations.facebook-popup-done', ['indexUrl' => $screen])
            : redirect()->to($screen);
    }

    /**
     * Connect the chosen Pages
     *
     * Saves them and subscribes each to the leadgen webhook. Super Admin sends owners[pageId] =
     * account id; an agency / independent agent sends page_ids[] for their own account.
     *
     * @bodyParam owners object Super Admin: page id => account id. Example: {"1029384756": 12}
     * @bodyParam page_ids string[] Agency / agent: the ticked Pages. Example: ["1029384756"]
     * @bodyParam import_existing boolean Also import the leads these Pages already have. Example: true
     * @bodyParam import_days string 7 | 30 | 90 | all. Example: all
     */
    public function storePages(Request $request)
    {
        $this->authorizeManage();
        $pending = collect($this->pendingPages())->keyBy('id');

        if ($this->isAdmin()) {
            $data = $request->validate(['owners' => 'required|array', 'owners.*' => 'nullable|integer']);
            $choices = array_filter($data['owners']);
            if (!$choices) {
                return response()->json(['success' => false, 'message' => 'Choose an agency or agent for at least one Page.'], 422);
            }
            $accounts = $this->linkableAccounts()->whereIn('id', $choices)->get()->keyBy('id');
        } else {
            $data = $request->validate(['page_ids' => 'required|array|min:1', 'page_ids.*' => 'string|max:64']);
            $choices = array_fill_keys($data['page_ids'], $this->ownerId());
            $accounts = collect([$this->ownerId() => $this->owner()]);
        }
        // "Import the leads these Pages already have?" — all of them (default) or the last N days.
        $import = $request->boolean('import_existing');
        $importDays = $import ? ($request->validate(['import_days' => 'nullable|in:7,30,90,all'])['import_days'] ?? 'all') : null;
        $importFrom = $import && $importDays !== 'all' ? now()->subDays((int) $importDays) : null;

        $connected = [];
        $importing = 0;
        $problems = [];
        foreach ($choices as $pageId => $ownerId) {
            $page = $pending->get((string) $pageId);
            $account = $accounts->get($ownerId);
            if (!$page) {
                continue;
            }
            if (!$account) {
                $problems[] = "{$page['name']}: choose an approved agency or independent agent";
                continue;
            }
            // One account per Page — a Page already connected elsewhere must be disconnected first.
            $existing = FacebookPageConnection::with('owner:id,type,name,company_name')->where('page_id', $page['id'])->first();
            if ($existing && $existing->portal_user_id !== $account->id) {
                $problems[] = "{$page['name']} is already connected to " . ($this->isAdmin() ? ($existing->owner?->displayName() ?? 'another account') : 'another MW Realty account');
                continue;
            }

            $connection = FacebookPageConnection::updateOrCreate(['page_id' => $page['id']], [
                'portal_user_id' => $account->id,
                'page_name' => $page['name'],
                'page_access_token' => $page['access_token'],
                'connected_by' => $this->actorName(),
                // A fresh token: a Page that needed reconnecting works again.
                'needs_reconnect_at' => null,
                'reconnect_notified_at' => null,
            ]);
            try {
                $this->facebook->subscribe($page['id'], $page['access_token']);
                $connection->forceFill(['subscribed_at' => now(), 'last_error' => null])->save();
                $connected[] = $this->isAdmin() ? "{$page['name']} → {$account->displayName()}" : $page['name'];
            } catch (\Throwable $e) {
                $connection->forceFill(['subscribed_at' => null, 'last_error' => $e->getMessage()])->save();
                $problems[] = "{$page['name']}: {$e->getMessage()}";
                continue;
            }

            // Fetch in the background: the leads missed while it needed reconnecting (from its last
            // good sync), and/or its existing leads if asked. Duplicates are skipped by the importer.
            // Either way it's a bulk fetch: one summary email at the end, not one per lead.
            $catchUpFrom = null;
            if ($existing?->needs_reconnect_at) {
                $catchUpFrom = $existing->last_synced_at && $existing->last_synced_at->lt($existing->needs_reconnect_at) ? $existing->last_synced_at : $existing->needs_reconnect_at->copy()->subDay();
            }
            if ($import) {
                // All leads (null), or the earlier of the chosen period and the reconnect gap.
                $from = $importFrom && $catchUpFrom && $catchUpFrom->lt($importFrom) ? $catchUpFrom : $importFrom;
                ImportFacebookPageLeads::start($connection, $from, 'import');
                $importing++;
            } elseif ($catchUpFrom) {
                ImportFacebookPageLeads::start($connection, $catchUpFrom, 'catch-up');
                $importing++;
            }
        }
        Cache::forget(self::PAGES_CACHE . $this->actorKey());

        return response()->json([
            'success' => (bool) $connected,
            'message' => $connected
                ? 'Connected: ' . implode(', ', $connected) . '.' . ($importing ? ' Importing their leads in the background — you can keep working.' : ' New Facebook leads will arrive in Leads.')
                : null,
            'error' => $problems ? implode('. ', $problems) . '.' : null,
        ]);
    }

    /**
     * Cancel picking Pages
     *
     * Forgets the Pages from the last Facebook Login.
     */
    public function cancelPages()
    {
        $this->authorizeManage();
        Cache::forget(self::PAGES_CACHE . $this->actorKey());

        return response()->json(['success' => true]);
    }

    /**
     * Lead import progress
     *
     * Live progress of background lead imports, polled while any is running.
     *
     * @response 200 {"importing": [{"id": 3, "status": "running", "added": 40, "skipped": 2}]}
     */
    public function importStatus()
    {
        $connections = FacebookPageConnection::when(!$this->isAdmin(), fn ($q) => $q->where('portal_user_id', $this->ownerId() ?? 0))
            ->whereIn('import_status', ['queued', 'running'])
            ->get(['id', 'import_status', 'import_added', 'import_skipped', 'updated_at'])
            ->each->failStaleImport()
            ->filter->importInProgress();

        return response()->json(['importing' => $connections->map(fn ($c) => [
            'id' => $c->id, 'status' => $c->import_status, 'added' => $c->import_added, 'skipped' => $c->import_skipped,
        ])->values()]);
    }

    /**
     * Sync a Page
     *
     * "Sync now": leads since the last sync (first time: all of them), right away. "All leads"
     * (all=1): every lead the Page's forms have, in the background with live progress. Both send
     * one summary email instead of an email per lead.
     *
     * @bodyParam all boolean Example: false
     */
    public function sync(Request $request, $id, FacebookLeadImporter $importer, FacebookConnectionHealth $health)
    {
        $connection = $this->findConnection($id);

        if ($request->boolean('all') || !$connection->last_synced_at) {
            if ($connection->importInProgress()) {
                return $this->done("{$connection->page_name} is already importing — see its progress below.");
            }
            ImportFacebookPageLeads::start($connection, null, 'sync');

            return $this->done("Fetching all leads from {$connection->page_name} in the background — you'll get one summary email when it's done.");
        }

        try {
            $added = $importer->sync($connection);
            $importer->emailSummary($connection, 'sync');
        } catch (FacebookTokenException $e) {
            $health->tokenFailed($connection, $e);

            return $this->failed("{$connection->page_name} needs reconnecting: {$e->reason()} Click Reconnect and log in with Facebook again.");
        } catch (\Throwable $e) {
            $connection->forceFill(['last_error' => $e->getMessage()])->save();

            return $this->failed("Sync failed for {$connection->page_name}: {$e->getMessage()}");
        }

        $summary = $importer->summary();
        if (!$added) {
            return $this->done("No new leads on {$connection->page_name}" . ($summary['skipped'] ? " ({$summary['skipped']} already in the CRM)." : '.'));
        }

        return $this->done("{$connection->page_name}: {$summary['new']} new lead" . ($summary['new'] === 1 ? '' : 's')
            . ($summary['merged'] ? ", {$summary['merged']} existing lead" . ($summary['merged'] === 1 ? '' : 's') . ' updated' : '')
            . ($summary['skipped'] ? ", {$summary['skipped']} already in the CRM" : '') . '. A summary email is on its way.');
    }

    /**
     * Disconnect a Page
     *
     * Its leads already in the CRM stay.
     */
    public function destroy($id)
    {
        $this->authorizeManage();
        $connection = $this->findConnection($id);
        $this->disconnect($connection);

        return $this->done("{$connection->page_name} disconnected — its leads already in the CRM stay.");
    }

    /**
     * Disconnect several Pages
     *
     * Only ones this user may manage.
     *
     * @bodyParam ids integer[] required Example: [3, 4]
     */
    public function bulkDestroy(Request $request)
    {
        $this->authorizeManage();
        $ids = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer'])['ids'];

        $connections = FacebookPageConnection::when(!$this->isAdmin(), fn ($q) => $q->where('portal_user_id', $this->ownerId() ?? 0))
            ->whereIn('id', $ids)->get();
        $connections->each(fn (FacebookPageConnection $connection) => $this->disconnect($connection));

        return $this->done($connections->count() . ' Page' . ($connections->count() === 1 ? '' : 's') . ' disconnected — their leads already in the CRM stay.');
    }

    private function done(string $message)
    {
        return response()->json(['success' => true, 'message' => $message]);
    }

    private function failed(string $message)
    {
        return response()->json(['success' => false, 'message' => $message], 422);
    }

    /** Stop the Page's webhook (best effort — the token may already be dead) and forget it. */
    private function disconnect(FacebookPageConnection $connection): void
    {
        try {
            $this->facebook->unsubscribe($connection->page_id, $connection->page_access_token);
        } catch (\Throwable $e) {
            Log::info("Facebook unsubscribe for page {$connection->page_id} failed: " . $e->getMessage());
        }
        $connection->delete();
    }

    /** Who is connecting: the Super Admin (cms user) or the portal account. Keys their pending Pages. */
    private function actorKey(): string
    {
        return $this->isAdmin() ? 'admin.' . Auth::guard('cms')->id() : 'owner.' . $this->ownerId();
    }

    /** Pages from the last Facebook Login, still waiting to be picked. */
    private function pendingPages(): array
    {
        $cached = Cache::get(self::PAGES_CACHE . $this->actorKey());

        return $cached ? decrypt($cached) : [];
    }

    /**
     * Accounts that own their CRM leads: approved, active agencies and independent agents. Agency
     * agents work their agency's leads, and the shared Admin owner row must never receive them.
     */
    private function linkableAccounts()
    {
        return PortalUser::approved()->where('is_active', true)
            ->where('email', '!=', AdminOwnerResolver::EMAIL)
            ->where(fn ($q) => $q->where('type', 'company')->orWhere(fn ($a) => $a->where('type', 'agent')->whereNull('company_id')));
    }

    /**
     * Super Admin and every portal account connect Pages. An agency agent's own Pages feed their
     * personal leads (owned by the agent, not the agency) — the agency's Pages stay the agency's.
     */
    private function canManage(): bool
    {
        return $this->isAdmin() || $this->owner() !== null;
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    /** The admin reaches every Page; an agency / agent only their own. */
    private function findConnection($id): FacebookPageConnection
    {
        return FacebookPageConnection::when(!$this->isAdmin(), fn ($q) => $q->where('portal_user_id', $this->ownerId() ?? 0))->findOrFail($id);
    }
}
