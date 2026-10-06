<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Models\FacebookPageConnection;
use App\Services\Integrations\FacebookLeadAds;
use App\Services\Integrations\FacebookLeadImporter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CRM › Integrations: connect Facebook Pages so their Lead Ads leads arrive in this account's CRM.
 *
 * Connect → Facebook Login (FacebookLeadAds::loginUrl) → callback lists the Pages the user manages
 * (kept in the session for a few minutes) → the user picks Pages → each is saved with its Page token
 * and subscribed to the app's "leadgen" webhook (Api\FacebookWebhookController). "Sync now" pulls
 * recent leads directly (FacebookLeadImporter::sync). Agency agents' leads are their agency's, so
 * the agency manages integrations.
 */
class IntegrationController extends Controller
{
    use ScopesPortalOwner;

    private const PAGES_SESSION = 'facebook_integration.pages';
    private const STATE_SESSION = 'facebook_integration.state';

    public function __construct(private readonly FacebookLeadAds $facebook)
    {
    }

    public function index()
    {
        $pending = session(self::PAGES_SESSION);
        if ($pending && now()->timestamp > ($pending['expires'] ?? 0)) {
            session()->forget(self::PAGES_SESSION);
            $pending = null;
        }
        $connectedElsewhere = $pending
            ? FacebookPageConnection::whereIn('page_id', array_column($pending['pages'], 'id'))->where('portal_user_id', '!=', $this->effectiveOwnerId())->pluck('page_id')->all()
            : [];

        return view('portal.crm.integrations.index', [
            'canManage' => $this->canManage(),
            'configured' => $this->facebook->configured(),
            'connections' => $this->effectiveOwnerId()
                ? FacebookPageConnection::where('portal_user_id', $this->effectiveOwnerId())->orderBy('page_name')->get()
                : collect(),
            'pendingPages' => $pending['pages'] ?? [],
            'connectedPageIds' => FacebookPageConnection::where('portal_user_id', $this->effectiveOwnerId())->pluck('page_id')->all(),
            'connectedElsewhere' => $connectedElsewhere,
            'isAdmin' => $this->isAdmin(),
            'callbackUrl' => route('portal.crm.integrations.facebook.callback'),
            'webhookUrl' => route('webhooks.facebook'),
        ]);
    }

    /** Off to Facebook Login. */
    public function connect()
    {
        $this->authorizeManage();
        if (!$this->facebook->configured()) {
            return back()->with('error', 'Facebook isn\'t set up yet — add FACEBOOK_APP_ID and FACEBOOK_APP_SECRET to the .env file.');
        }

        $state = Str::random(40);
        session([self::STATE_SESSION => $state]);

        return redirect()->away($this->facebook->loginUrl(route('portal.crm.integrations.facebook.callback'), $state));
    }

    /** Back from Facebook Login: list the user's Pages to choose from. */
    public function callback(Request $request)
    {
        $this->authorizeManage();
        $expected = session()->pull(self::STATE_SESSION);
        $index = redirect()->route('portal.crm.integrations.index');

        if ($request->filled('error')) {
            return $index->with('error', 'Facebook connection cancelled' . ($request->filled('error_description') ? ': ' . $request->input('error_description') : '.'));
        }
        if (!$expected || !hash_equals($expected, (string) $request->input('state')) || !$request->filled('code')) {
            return $index->with('error', 'The Facebook login could not be verified — please try connecting again.');
        }

        try {
            $userToken = $this->facebook->userToken((string) $request->input('code'), route('portal.crm.integrations.facebook.callback'));
            $pages = $this->facebook->pages($userToken);
        } catch (\Throwable $e) {
            Log::warning('Facebook connect failed: ' . $e->getMessage());

            return $index->with('error', 'Facebook connection failed: ' . $e->getMessage());
        }

        if (!$pages) {
            return $index->with('error', 'No Facebook Pages found. Log in with a Facebook account that manages the Page, and allow access to it.');
        }

        session([self::PAGES_SESSION => ['pages' => $pages, 'expires' => now()->addMinutes(15)->timestamp]]);

        return $index->with('toast', 'Choose the Facebook Pages to connect.');
    }

    /** Save the chosen Pages and subscribe each to the leadgen webhook. */
    public function storePages(Request $request)
    {
        $this->authorizeManage();
        $data = $request->validate(['page_ids' => 'required|array|min:1', 'page_ids.*' => 'string|max:64']);
        $pending = collect(session(self::PAGES_SESSION)['pages'] ?? [])->keyBy('id');
        $ownerId = $this->effectiveOwnerId();

        $connected = [];
        $problems = [];
        foreach ($data['page_ids'] as $pageId) {
            $page = $pending->get($pageId);
            if (!$page) {
                continue;
            }
            $existing = FacebookPageConnection::where('page_id', $pageId)->first();
            if ($existing && $existing->portal_user_id !== $ownerId) {
                $problems[] = "{$page['name']} is already connected to another account";
                continue;
            }

            $connection = FacebookPageConnection::updateOrCreate(['page_id' => $pageId], [
                'portal_user_id' => $ownerId,
                'page_name' => $page['name'],
                'page_access_token' => $page['access_token'],
                'connected_by' => $this->actorName(),
            ]);
            try {
                $this->facebook->subscribe($pageId, $page['access_token']);
                $connection->forceFill(['subscribed_at' => now(), 'last_error' => null])->save();
                $connected[] = $page['name'];
            } catch (\Throwable $e) {
                $connection->forceFill(['subscribed_at' => null, 'last_error' => $e->getMessage()])->save();
                $problems[] = "{$page['name']}: {$e->getMessage()}";
            }
        }
        session()->forget(self::PAGES_SESSION);

        $redirect = redirect()->route('portal.crm.integrations.index');
        if ($connected) {
            $redirect->with('toast', 'Connected: ' . implode(', ', $connected) . '. New Facebook leads will arrive in Leads.');
        }

        return $problems ? $redirect->with('error', implode('. ', $problems) . '.') : $redirect;
    }

    public function cancelPages()
    {
        session()->forget(self::PAGES_SESSION);

        return redirect()->route('portal.crm.integrations.index');
    }

    /** Pull recent leads now (also catches up on leads the webhook missed). */
    public function sync($id, FacebookLeadImporter $importer)
    {
        $this->authorizeManage();
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

    /** Agency agents work their agency's leads, so the agency (or an independent agent / Super Admin) connects Pages. */
    private function canManage(): bool
    {
        $owner = $this->owner();

        return $this->isAdmin() || ($owner && !($owner->isAgent() && $owner->company_id));
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage() && $this->effectiveOwnerId(), 403);
    }

    private function findConnection($id): FacebookPageConnection
    {
        return FacebookPageConnection::where('portal_user_id', $this->effectiveOwnerId())->findOrFail($id);
    }
}
