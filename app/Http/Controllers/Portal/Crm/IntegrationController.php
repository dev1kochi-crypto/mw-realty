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
 * Only the Super Admin connects Pages: Connect → Facebook Login (FacebookLeadAds::loginUrl) → the
 * public callback (no portal login needed — the one-time state in the cache identifies the admin)
 * lists the Pages the Facebook user manages → the admin links each Page to an agency or independent
 * agent → it is saved with its Page token and subscribed to the app's "leadgen" webhook
 * (Api\FacebookWebhookController), so its leads land in that account's CRM, never the admin's.
 * Agencies / agents see the Pages linked to them and can "Sync now" (FacebookLeadImporter::sync).
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
        $pending = $isAdmin ? $this->pendingPages() : [];
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $connections = $isAdmin
            ? FacebookPageConnection::with('owner:id,type,name,company_name')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('page_name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($o) => $o->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"))))
                ->orderBy('page_name')->paginate(20)->withQueryString()
            : FacebookPageConnection::where('portal_user_id', $this->ownerId())->orderBy('page_name')->get();

        return view('portal.crm.integrations.index', [
            'isAdmin' => $isAdmin,
            'isAgencyAgent' => (bool) $this->owner()?->isAgencyAgent(),
            'configured' => $this->facebook->configured(),
            'connections' => $connections,
            'search' => $search,
            'pendingPages' => $pending,
            'connectedOwners' => $pending
                ? FacebookPageConnection::with('owner:id,type,name,company_name')->whereIn('page_id', array_column($pending, 'id'))->get()->keyBy('page_id')
                : collect(),
            'callbackUrl' => route('integrations.facebook.callback'),
            'webhookUrl' => route('webhooks.facebook'),
        ]);
    }

    /** Agencies + independent agents a Page can be linked to (select2 format, 20 per page). */
    public function accounts(Request $request)
    {
        $this->authorizeAdmin();
        $term = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $page = $this->linkableAccounts()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('company_name', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->orderByRaw("COALESCE(NULLIF(company_name, ''), name)")
            ->paginate(20, ['id', 'type', 'name', 'company_name', 'company_id', 'email']);

        return response()->json([
            'results' => collect($page->items())->map(fn (PortalUser $account) => ['id' => $account->id, 'text' => $this->accountLabel($account)]),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }

    /** Off to Facebook Login (opened in a new tab). */
    public function connect()
    {
        $this->authorizeAdmin();
        if (!$this->facebook->configured()) {
            return redirect()->route('portal.crm.integrations.index')->with('error', 'Facebook isn\'t set up yet — add FACEBOOK_APP_ID and FACEBOOK_APP_SECRET to the .env file.');
        }

        $state = Str::random(40);
        Cache::put(self::STATE_CACHE . $state, Auth::guard('cms')->id(), now()->addMinutes(self::PENDING_MINUTES));

        return redirect()->away($this->facebook->loginUrl(route('integrations.facebook.callback'), $state));
    }

    /**
     * Public route (outside the portal's login): Facebook sends the browser back here. The one-time
     * state names the admin who started the login; their Pages wait in the cache for them to link.
     */
    public function callback(Request $request)
    {
        $index = redirect()->route('portal.crm.integrations.index');
        $adminId = $request->filled('state') ? Cache::pull(self::STATE_CACHE . $request->input('state')) : null;

        if ($request->filled('error')) {
            return $index->with('error', 'Facebook connection cancelled' . ($request->filled('error_description') ? ': ' . $request->input('error_description') : '.'));
        }
        if (!$adminId || !$request->filled('code')) {
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

        Cache::put(self::PAGES_CACHE . $adminId, encrypt($pages), now()->addMinutes(self::PENDING_MINUTES));

        return $index->with('toast', 'Choose the agency or agent each Facebook Page belongs to.');
    }

    /** Link the chosen Pages to their accounts and subscribe each to the leadgen webhook. */
    public function storePages(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['owners' => 'required|array', 'owners.*' => 'nullable|integer']);
        $pending = collect($this->pendingPages())->keyBy('id');
        $choices = array_filter($data['owners']);
        if (!$choices) {
            return back()->with('error', 'Choose an agency or agent for at least one Page.');
        }
        $accounts = $this->linkableAccounts()->whereIn('id', $choices)->get()->keyBy('id');

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

            $connection = FacebookPageConnection::updateOrCreate(['page_id' => $page['id']], [
                'portal_user_id' => $account->id,
                'page_name' => $page['name'],
                'page_access_token' => $page['access_token'],
                'connected_by' => $this->actorName(),
            ]);
            try {
                $this->facebook->subscribe($page['id'], $page['access_token']);
                $connection->forceFill(['subscribed_at' => now(), 'last_error' => null])->save();
                $connected[] = "{$page['name']} → {$account->displayName()}";
            } catch (\Throwable $e) {
                $connection->forceFill(['subscribed_at' => null, 'last_error' => $e->getMessage()])->save();
                $problems[] = "{$page['name']}: {$e->getMessage()}";
            }
        }
        Cache::forget(self::PAGES_CACHE . Auth::guard('cms')->id());

        $redirect = redirect()->route('portal.crm.integrations.index');
        if ($connected) {
            $redirect->with('toast', 'Connected: ' . implode(', ', $connected) . '. New Facebook leads go to their CRM.');
        }

        return $problems ? $redirect->with('error', implode('. ', $problems) . '.') : $redirect;
    }

    public function cancelPages()
    {
        $this->authorizeAdmin();
        Cache::forget(self::PAGES_CACHE . Auth::guard('cms')->id());

        return redirect()->route('portal.crm.integrations.index');
    }

    /** Move a connected Page to another agency / agent — its new leads go there; past leads stay put. */
    public function reassign(Request $request, $id)
    {
        $this->authorizeAdmin();
        $connection = FacebookPageConnection::findOrFail($id);
        $account = $this->linkableAccounts()->findOrFail($request->validate(['owner_id' => 'required|integer'])['owner_id']);

        $connection->forceFill(['portal_user_id' => $account->id])->save();

        return back()->with('toast', "{$connection->page_name} now sends its leads to {$account->displayName()}.");
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
        $this->authorizeAdmin();
        $connection = FacebookPageConnection::findOrFail($id);

        try {
            $this->facebook->unsubscribe($connection->page_id, $connection->page_access_token);
        } catch (\Throwable $e) {
            Log::info("Facebook unsubscribe for page {$connection->page_id} failed: " . $e->getMessage());
        }
        $connection->delete();

        return back()->with('toast', "{$connection->page_name} disconnected — its leads already in the CRM stay.");
    }

    /** Pages from the admin's last Facebook Login, still waiting to be linked. */
    private function pendingPages(): array
    {
        $cached = Cache::get(self::PAGES_CACHE . Auth::guard('cms')->id());

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

    private function accountLabel(PortalUser $account): string
    {
        return $account->displayName() . ($account->isAgency() ? ' — Agency' : ' — Agent') . " ({$account->email})";
    }

    private function authorizeAdmin(): void
    {
        abort_unless($this->isAdmin(), 403);
    }

    /** The admin reaches every Page; an agency / agent only the Pages linked to them. */
    private function findConnection($id): FacebookPageConnection
    {
        return FacebookPageConnection::when(!$this->isAdmin(), fn ($q) => $q->where('portal_user_id', $this->ownerId() ?? 0))->findOrFail($id);
    }
}
