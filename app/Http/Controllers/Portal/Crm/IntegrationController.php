<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Models\FacebookPageConnection;
use App\Models\PortalUser;
use App\Services\Crm\AdminOwnerResolver;
use App\Services\Integrations\FacebookConnectionHealth;
use App\Services\Integrations\FacebookLeadAds;
use App\Services\Integrations\FacebookTokenException;
use App\Services\Integrations\FacebookLeadImporter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CRM › Integrations: Facebook Pages whose Lead Ads leads arrive in an agency's / agent's CRM.
 *
 * Connect → Facebook Login (FacebookLeadAds::loginUrl) → the public callback (no portal login needed —
 * the one-time state in the cache names who started it) lists the Pages the Facebook user manages →
 * the Super Admin links each Page to an agency or independent agent, or any agency / agent
 * connects Pages to their own account (an agency agent's become their personal leads) → each is saved with its Page token and subscribed to
 * the app's "leadgen" webhook (Api\FacebookWebhookController). A Page belongs to one account only:
 * the webhook names just the Page, so it must point to a single CRM (never the admin's own).
 * "Sync now" pulls recent leads directly (FacebookLeadImporter::sync).
 */
class IntegrationController extends Controller
{
    use ScopesPortalOwner;

    private const STATE_CACHE = 'facebook_integration.state.';
    private const PAGES_CACHE = 'facebook_integration.pages.';
    private const PENDING_MINUTES = 15;

    public function __construct(private readonly FacebookLeadAds $facebook)
    {
    }

    /**
     * The Integrations hub: one card per integration with its status; each opens its own page.
     * A new integration is a new entry here plus its page.
     */
    public function index()
    {
        $isAdmin = $this->isAdmin();
        $facebookCount = FacebookPageConnection::when(!$isAdmin, fn ($q) => $q->where('portal_user_id', $this->ownerId() ?? 0))->count();
        $pf = $this->propertyFinderData();

        return view('portal.crm.integrations.index', [
            'isAdmin' => $isAdmin,
            'integrations' => [
                [
                    'key' => 'facebook',
                    'name' => 'Facebook Lead Ads',
                    'category' => 'Leads',
                    'icon' => 'fab fa-facebook-f',
                    'color' => '#1877f2',
                    'description' => $isAdmin
                        ? 'Connect Facebook Pages and send each one\'s lead form leads to an agency or agent.'
                        : 'Leads from your Facebook & Instagram lead forms arrive in Leads automatically.',
                    'url' => route('portal.crm.integrations.facebook'),
                    'status' => match (true) {
                        !$this->facebook->configured() => ['off', 'Not set up'],
                        $facebookCount > 0 => ['ok', $facebookCount . ' page' . ($facebookCount === 1 ? '' : 's') . ' connected'],
                        default => ['off', 'Not connected'],
                    },
                ],
                [
                    'key' => 'property-finder',
                    'name' => 'Property Finder',
                    'category' => 'Listings',
                    'icon' => 'fas fa-house-chimney',
                    'color' => '#ef5e4e',
                    'description' => $isAdmin
                        ? 'Agencies and agents import their Property Finder listings — you review them before they go live.'
                        : 'Import your Property Finder listings as properties — all of them once, then just the new ones.',
                    'url' => route('portal.crm.integrations.property-finder.show'),
                    'status' => match (true) {
                        $isAdmin && $pf['pfPending'] > 0 => ['warn', $pf['pfPending'] . ' to review'],
                        $isAdmin => ['off', $pf['pfAccounts'] . ' account' . ($pf['pfAccounts'] === 1 ? '' : 's') . ' connected'],
                        $pf['pfConnection']?->syncInProgress() => ['warn', 'Syncing…'],
                        $pf['pfConnection'] !== null => ['ok', 'Connected'],
                        default => ['off', 'Not connected'],
                    },
                ],
            ],
        ]);
    }

    /** Integrations › Property Finder (actions: PropertyFinderController). */
    public function propertyFinder()
    {
        return view('portal.crm.integrations.property-finder', ['isAdmin' => $this->isAdmin(), ...$this->propertyFinderData()]);
    }

    /** Integrations › Facebook Lead Ads. */
    public function facebook(Request $request)
    {
        $isAdmin = $this->isAdmin();
        $pending = $this->canManage() ? $this->pendingPages() : [];
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $connections = $isAdmin
            ? FacebookPageConnection::with('owner:id,type,name,company_name')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('page_name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($o) => $o->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"))))
                ->orderBy('page_name')->paginate(20)->withQueryString()
            : FacebookPageConnection::where('portal_user_id', $this->ownerId() ?? 0)->orderBy('page_name')->get();
        $connections->each(fn (FacebookPageConnection $c) => $c->failStaleImport());

        return view('portal.crm.integrations.facebook', [
            'isAdmin' => $isAdmin,
            'canManage' => $this->canManage(),
            'isAgencyAgent' => (bool) $this->owner()?->isAgencyAgent(),
            'configured' => $this->facebook->configured(),
            'connections' => $connections,
            'search' => $search,
            'pendingPages' => $pending,
            // Pending Pages already connected — to this account (reconnect refreshes) or another (blocked).
            'existingConnections' => $pending
                ? FacebookPageConnection::with('owner:id,type,name,company_name')->whereIn('page_id', array_column($pending, 'id'))->get()->keyBy('page_id')
                : collect(),
            'ownerId' => $this->ownerId(),
            'callbackUrl' => route('integrations.facebook.callback'),
            'webhookUrl' => route('webhooks.facebook'),
        ]);
    }

    /**
     * The Property Finder card (portal.crm.integrations._property_finder): the viewer's connection —
     * an agency agent sees their agency's — or, for Super Admin, totals and the review queue.
     */
    private function propertyFinderData(): array
    {
        if ($this->isAdmin()) {
            return [
                'pfConnection' => null,
                'pfCanManage' => false,
                'pfAccounts' => \App\Models\PropertyFinderConnection::count(),
                'pfPending' => \App\Models\PropertyFinderImport::where('review_status', \App\Models\PropertyFinderImport::PENDING)->whereNotNull('property_id')->count(),
                'pfImported' => 0,
            ];
        }
        $owner = $this->owner();
        $accountId = $owner?->isAgencyAgent() ? $owner->company_id : $owner?->id;
        $connection = $accountId ? \App\Models\PropertyFinderConnection::with('owner:id,type,name,company_name')->where('portal_user_id', $accountId)->first() : null;
        $connection?->failStaleSync();

        return [
            'pfConnection' => $connection,
            'pfCanManage' => $owner && !$owner->isAgencyAgent(),
            'pfAccounts' => 0,
            'pfPending' => $accountId ? \App\Models\PropertyFinderImport::where('portal_user_id', $accountId)->where('review_status', \App\Models\PropertyFinderImport::PENDING)->whereNotNull('property_id')->count() : 0,
            // Imported listings that still exist here (a deleted one may be imported again).
            'pfImported' => $accountId ? \App\Models\PropertyFinderImport::where('portal_user_id', $accountId)->whereNotNull('property_id')->count() : 0,
        ];
    }

    /** Agencies + independent agents a Page can be linked to (select2 format, 20 per page). */
    public function accounts(Request $request)
    {
        abort_unless($this->isAdmin(), 403);
        $term = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $page = $this->linkableAccounts()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('company_name', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->orderByRaw("COALESCE(NULLIF(company_name, ''), name)")
            ->paginate(20, ['id', 'type', 'name', 'company_name', 'company_id', 'email']);

        return response()->json([
            'results' => collect($page->items())->map(fn (PortalUser $account) => [
                'id' => $account->id,
                'text' => $account->displayName() . ($account->isAgency() ? ' — Agency' : ' — Agent') . " ({$account->email})",
            ]),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }

    /** Off to Facebook Login — in a popup window over Integrations (?popup=1), or this tab as a fallback. */
    public function connect(Request $request)
    {
        $this->authorizeManage();
        if (!$this->facebook->configured()) {
            return $this->finishLogin($request->boolean('popup'), 'error', 'Facebook isn\'t set up yet — add FACEBOOK_APP_ID and FACEBOOK_APP_SECRET to the .env file.');
        }

        $state = Str::random(40);
        Cache::put(self::STATE_CACHE . $state, ['actor' => $this->actorKey(), 'popup' => $request->boolean('popup')], now()->addMinutes(self::PENDING_MINUTES));

        return redirect()->away($this->facebook->loginUrl(route('integrations.facebook.callback'), $state));
    }

    /**
     * Public route (outside the portal's login): Facebook sends the browser back here. The one-time
     * state names who started the login; their Pages wait in the cache for them to pick.
     */
    public function callback(Request $request)
    {
        $started = $request->filled('state') ? Cache::pull(self::STATE_CACHE . $request->input('state')) : null;
        $actor = $started['actor'] ?? null;
        $popup = (bool) ($started['popup'] ?? false);

        if ($request->filled('error')) {
            return $this->finishLogin($popup, 'error', 'Facebook connection cancelled' . ($request->filled('error_description') ? ': ' . $request->input('error_description') : '.'));
        }
        if (!$actor || !$request->filled('code')) {
            return $this->finishLogin($popup, 'error', 'The Facebook login could not be verified — please try connecting again.');
        }

        try {
            $userToken = $this->facebook->userToken((string) $request->input('code'), route('integrations.facebook.callback'));
            $pages = $this->facebook->pages($userToken);
        } catch (\Throwable $e) {
            Log::warning('Facebook connect failed: ' . $e->getMessage());

            return $this->finishLogin($popup, 'error', 'Facebook connection failed: ' . $e->getMessage());
        }

        if (!$pages) {
            return $this->finishLogin($popup, 'error', 'No Facebook Pages found. Log in with a Facebook account that manages the Page, and allow access to it.');
        }

        Cache::put(self::PAGES_CACHE . $actor, encrypt($pages), now()->addMinutes(self::PENDING_MINUTES));

        return $this->finishLogin($popup, 'toast', str_starts_with($actor, 'admin.') ? 'Choose the agency or agent each Facebook Page belongs to.' : 'Choose the Facebook Pages to connect.');
    }

    /** Back to Integrations with a message: a popup closes itself and reloads the page that opened it. */
    private function finishLogin(bool $popup, string $type, string $message)
    {
        session()->flash($type, $message);

        return $popup
            ? response()->view('portal.crm.integrations.popup-done', ['indexUrl' => route('portal.crm.integrations.facebook')])
            : redirect()->route('portal.crm.integrations.facebook');
    }

    /**
     * Save the chosen Pages and subscribe each to the leadgen webhook. Super Admin sends
     * owners[pageId] = account id; an agency / independent agent sends page_ids[] for their own account.
     */
    public function storePages(Request $request)
    {
        $this->authorizeManage();
        $pending = collect($this->pendingPages())->keyBy('id');

        if ($this->isAdmin()) {
            $data = $request->validate(['owners' => 'required|array', 'owners.*' => 'nullable|integer']);
            $choices = array_filter($data['owners']);
            if (!$choices) {
                return back()->with('error', 'Choose an agency or agent for at least one Page.');
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
                \App\Jobs\ImportFacebookPageLeads::start($connection, $from, 'import');
                $importing++;
            } elseif ($catchUpFrom) {
                \App\Jobs\ImportFacebookPageLeads::start($connection, $catchUpFrom, 'catch-up');
                $importing++;
            }
        }
        Cache::forget(self::PAGES_CACHE . $this->actorKey());

        $redirect = redirect()->route('portal.crm.integrations.facebook');
        if ($connected) {
            $redirect->with('toast', 'Connected: ' . implode(', ', $connected) . '.' . ($importing ? ' Importing their leads in the background — you can keep working.' : ' New Facebook leads will arrive in Leads.'));
        }

        return $problems ? $redirect->with('error', implode('. ', $problems) . '.') : $redirect;
    }

    /** Live progress of background lead imports, polled by the Integrations page. */
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

    public function cancelPages()
    {
        $this->authorizeManage();
        Cache::forget(self::PAGES_CACHE . $this->actorKey());

        return redirect()->route('portal.crm.integrations.facebook');
    }

    /**
     * "Sync now": leads since the last sync (first time: all of them), right away. "Sync all leads"
     * (?all=1): every lead the Page's forms have, in the background with live progress. Both send
     * one summary email instead of an email per lead.
     */
    public function sync(Request $request, $id, FacebookLeadImporter $importer, FacebookConnectionHealth $health)
    {
        $connection = $this->findConnection($id);

        if ($request->boolean('all') || !$connection->last_synced_at) {
            if ($connection->importInProgress()) {
                return back()->with('toast', "{$connection->page_name} is already importing — see its progress below.");
            }
            \App\Jobs\ImportFacebookPageLeads::start($connection, null, 'sync');

            return back()->with('toast', "Fetching all leads from {$connection->page_name} in the background — you'll get one summary email when it's done.");
        }

        try {
            $added = $importer->sync($connection);
            $importer->emailSummary($connection, 'sync');
        } catch (FacebookTokenException $e) {
            $health->tokenFailed($connection, $e);

            return back()->with('error', "{$connection->page_name} needs reconnecting: {$e->reason()} Click Reconnect and log in with Facebook again.");
        } catch (\Throwable $e) {
            $connection->forceFill(['last_error' => $e->getMessage()])->save();

            return back()->with('error', "Sync failed for {$connection->page_name}: {$e->getMessage()}");
        }

        $summary = $importer->summary();
        if (!$added) {
            return back()->with('toast', "No new leads on {$connection->page_name}" . ($summary['skipped'] ? " ({$summary['skipped']} already in the CRM)." : '.'));
        }

        return back()->with('toast', "{$connection->page_name}: {$summary['new']} new lead" . ($summary['new'] === 1 ? '' : 's')
            . ($summary['merged'] ? ", {$summary['merged']} existing lead" . ($summary['merged'] === 1 ? '' : 's') . ' updated' : '')
            . ($summary['skipped'] ? ", {$summary['skipped']} already in the CRM" : '') . '. A summary email is on its way.');
    }

    public function destroy($id)
    {
        $this->authorizeManage();
        $connection = $this->findConnection($id);
        $this->disconnect($connection);

        return back()->with('toast', "{$connection->page_name} disconnected — its leads already in the CRM stay.");
    }

    /** Disconnect the ticked Pages (only ones this user may manage). */
    public function bulkDestroy(Request $request)
    {
        $this->authorizeManage();
        $ids = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer'])['ids'];

        $connections = FacebookPageConnection::when(!$this->isAdmin(), fn ($q) => $q->where('portal_user_id', $this->ownerId() ?? 0))
            ->whereIn('id', $ids)->get();
        $connections->each(fn (FacebookPageConnection $connection) => $this->disconnect($connection));

        return back()->with('toast', $connections->count() . ' Page' . ($connections->count() === 1 ? '' : 's') . ' disconnected — their leads already in the CRM stay.');
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
