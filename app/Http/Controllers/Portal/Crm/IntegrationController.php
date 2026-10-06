<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Models\FacebookPageConnection;
use App\Models\PortalUser;
use App\Services\Crm\AdminOwnerResolver;
use App\Services\Integrations\FacebookLeadAds;
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
 * the Super Admin links each Page to an agency or independent agent, or an agency / independent
 * agent connects Pages to their own account → each is saved with its Page token and subscribed to
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

    public function index(Request $request)
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

        return view('portal.crm.integrations.index', [
            'isAdmin' => $isAdmin,
            'canManage' => $this->canManage(),
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

    /** Off to Facebook Login (opened in a new tab). */
    public function connect()
    {
        $this->authorizeManage();
        if (!$this->facebook->configured()) {
            return redirect()->route('portal.crm.integrations.index')->with('error', 'Facebook isn\'t set up yet — add FACEBOOK_APP_ID and FACEBOOK_APP_SECRET to the .env file.');
        }

        $state = Str::random(40);
        Cache::put(self::STATE_CACHE . $state, $this->actorKey(), now()->addMinutes(self::PENDING_MINUTES));

        return redirect()->away($this->facebook->loginUrl(route('integrations.facebook.callback'), $state));
    }

    /**
     * Public route (outside the portal's login): Facebook sends the browser back here. The one-time
     * state names who started the login; their Pages wait in the cache for them to pick.
     */
    public function callback(Request $request)
    {
        $index = redirect()->route('portal.crm.integrations.index');
        $actor = $request->filled('state') ? Cache::pull(self::STATE_CACHE . $request->input('state')) : null;

        if ($request->filled('error')) {
            return $index->with('error', 'Facebook connection cancelled' . ($request->filled('error_description') ? ': ' . $request->input('error_description') : '.'));
        }
        if (!$actor || !$request->filled('code')) {
            return $index->with('error', 'The Facebook login could not be verified — please try connecting again.');
        }

        try {
            $userToken = $this->facebook->userToken((string) $request->input('code'), route('integrations.facebook.callback'));
            $pages = $this->facebook->pages($userToken);
        } catch (\Throwable $e) {
            Log::warning('Facebook connect failed: ' . $e->getMessage());

            return $index->with('error', 'Facebook connection failed: ' . $e->getMessage());
        }

        if (!$pages) {
            return $index->with('error', 'No Facebook Pages found. Log in with a Facebook account that manages the Page, and allow access to it.');
        }

        Cache::put(self::PAGES_CACHE . $actor, encrypt($pages), now()->addMinutes(self::PENDING_MINUTES));

        return $index->with('toast', str_starts_with($actor, 'admin.') ? 'Choose the agency or agent each Facebook Page belongs to.' : 'Choose the Facebook Pages to connect.');
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

        $connected = [];
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
            ]);
            try {
                $this->facebook->subscribe($page['id'], $page['access_token']);
                $connection->forceFill(['subscribed_at' => now(), 'last_error' => null])->save();
                $connected[] = $this->isAdmin() ? "{$page['name']} → {$account->displayName()}" : $page['name'];
            } catch (\Throwable $e) {
                $connection->forceFill(['subscribed_at' => null, 'last_error' => $e->getMessage()])->save();
                $problems[] = "{$page['name']}: {$e->getMessage()}";
            }
        }
        Cache::forget(self::PAGES_CACHE . $this->actorKey());

        $redirect = redirect()->route('portal.crm.integrations.index');
        if ($connected) {
            $redirect->with('toast', 'Connected: ' . implode(', ', $connected) . '. New Facebook leads will arrive in Leads.');
        }

        return $problems ? $redirect->with('error', implode('. ', $problems) . '.') : $redirect;
    }

    public function cancelPages()
    {
        $this->authorizeManage();
        Cache::forget(self::PAGES_CACHE . $this->actorKey());

        return redirect()->route('portal.crm.integrations.index');
    }

    /** Pull recent leads now (also catches up on leads the webhook missed). */
    public function sync($id, FacebookLeadImporter $importer)
    {
        $connection = $this->findConnection($id);

        try {
            $added = $importer->sync($connection);
        } catch (\Throwable $e) {
            $connection->forceFill(['last_error' => $e->getMessage()])->save();

            return back()->with('error', "Sync failed for {$connection->page_name}: {$e->getMessage()}");
        }

        return back()->with('toast', $added ? "{$added} new lead" . ($added === 1 ? '' : 's') . " added from {$connection->page_name}." : "No new leads on {$connection->page_name}.");
    }

    public function destroy($id)
    {
        $this->authorizeManage();
        $connection = $this->findConnection($id);

        try {
            $this->facebook->unsubscribe($connection->page_id, $connection->page_access_token);
        } catch (\Throwable $e) {
            Log::info("Facebook unsubscribe for page {$connection->page_id} failed: " . $e->getMessage());
        }
        $connection->delete();

        return back()->with('toast', "{$connection->page_name} disconnected — its leads already in the CRM stay.");
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

    /** Super Admin, agencies and independent agents connect Pages; agency agents work their agency's leads. */
    private function canManage(): bool
    {
        $owner = $this->owner();

        return $this->isAdmin() || ($owner && !$owner->isAgencyAgent());
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
