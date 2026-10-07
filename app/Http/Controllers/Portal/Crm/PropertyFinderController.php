<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Jobs\SyncPropertyFinderListings;
use App\Models\PropertyFinderConnection;
use App\Models\PropertyFinderImport;
use App\Services\PropertyFinder\PropertyFinderClient;
use App\Services\PropertyFinder\PropertyFinderException;
use App\Services\PropertyFinder\PropertyFinderReview;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CRM › Integrations › Property Finder.
 *
 *   Agency / independent agent: connect with their API key + secret (verified with Property Finder
 *   first), sync — "Import all listings" the first time, then "Sync new listings" — and disconnect.
 *   Agency agent: sees the agency's connection (key masked) and may sync it; can't change it.
 *   Super Admin: reviews every imported listing (approve / reject) before it can go live.
 */
class PropertyFinderController extends Controller
{
    use ScopesPortalOwner;

    /** Save (or replace) the account's API key + secret, after Property Finder accepts them. */
    public function connect(Request $request)
    {
        $owner = $this->manager();
        $data = $request->validate([
            'api_key' => 'required|string|max:255',
            'api_secret' => 'required|string|max:255',
        ]);
        $key = trim($data['api_key']);
        $secret = trim($data['api_secret']);

        $taken = PropertyFinderConnection::where('api_key_hash', PropertyFinderConnection::hashKey($key))
            ->where('portal_user_id', '!=', $owner->id)->exists();
        if ($taken) {
            return back()->withInput($request->except('api_secret'))->with('error', 'This Property Finder API key is already connected to another MW Realty account.');
        }

        try {
            (new PropertyFinderClient($key, $secret))->verify();
        } catch (PropertyFinderException $e) {
            return back()->withInput($request->except('api_secret'))->with('error', $e->getMessage());
        }

        // Connecting only saves the keys — importing is always started by hand (Import all listings).
        $existing = PropertyFinderConnection::where('portal_user_id', $owner->id)->exists();
        PropertyFinderConnection::updateOrCreate(['portal_user_id' => $owner->id], [
            'api_key' => $key,
            'api_secret' => $secret,
            'api_key_hash' => PropertyFinderConnection::hashKey($key),
            'connected_by' => $this->actorName(),
            'verified_at' => now(),
        ]);

        return back()->with('toast', $existing ? 'Property Finder keys updated.' : 'Property Finder connected — click Import all listings when you\'re ready.');
    }

    /** mode=all: every live listing (first import / full re-check). mode=new: just the ones added since. */
    public function sync(Request $request)
    {
        $connection = $this->visibleConnection();
        abort_unless($connection, 404);
        $mode = $request->input('mode') === 'new' && $connection->last_full_sync_at ? 'new' : 'all';

        if ($connection->syncInProgress()) {
            return back()->with('toast', 'A Property Finder sync is already running — see its progress below.');
        }
        SyncPropertyFinderListings::start($connection, $mode);

        return back()->with('toast', $mode === 'new'
            ? 'Checking Property Finder for new listings…'
            : 'Importing your Property Finder listings in the background — you can keep working.');
    }

    /** Live sync progress, polled by the Integrations page. */
    public function status()
    {
        $connection = $this->visibleConnection();
        $connection?->failStaleSync();

        return response()->json($connection ? [
            'status' => $connection->sync_status,
            'added' => $connection->sync_added,
            'skipped' => $connection->sync_skipped,
            'failed' => $connection->sync_failed,
            'waiting' => $connection->waitingForWorker(),
        ] : ['status' => null]);
    }

    /** Forget the key. Imported properties stay, and are still never imported twice. */
    public function destroy()
    {
        $owner = $this->manager();
        PropertyFinderConnection::where('portal_user_id', $owner->id)->delete();

        return back()->with('toast', 'Property Finder disconnected — imported listings stay in your properties.');
    }

    /** Super Admin: imported listings by review status, newest first. */
    public function review(Request $request)
    {
        abort_unless($this->isAdmin(), 403);
        $tab = in_array($request->query('tab'), [PropertyFinderImport::APPROVED, PropertyFinderImport::REJECTED], true) ? $request->query('tab') : PropertyFinderImport::PENDING;
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        // A pending import whose property the account has deleted meanwhile has nothing left to review.
        PropertyFinderImport::where('review_status', PropertyFinderImport::PENDING)->whereNull('property_id')->delete();

        $imports = PropertyFinderImport::with(['owner:id,type,name,company_name', 'property'])
            ->where('review_status', $tab)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('pf_reference', 'like', "%{$search}%")
                ->orWhereHas('owner', fn ($o) => $o->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"))
                ->orWhereHas('property', fn ($p) => $p->where('translations', 'like', "%{$search}%")->orWhere('reference_no', 'like', "%{$search}%"))))
            ->latest('id')->paginate(20)->withQueryString();

        return view('portal.crm.integrations.property-finder-review', [
            'imports' => $imports,
            'tab' => $tab,
            'search' => $search,
            'counts' => PropertyFinderImport::selectRaw('review_status, count(*) as total')->groupBy('review_status')->pluck('total', 'review_status'),
        ]);
    }

    /** Super Admin: approve / reject the ticked imports. */
    public function reviewAction(Request $request, PropertyFinderReview $review)
    {
        abort_unless($this->isAdmin(), 403);
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer',
            'note' => 'nullable|string|max:500',
        ]);
        $imports = PropertyFinderImport::with('property')->where('review_status', PropertyFinderImport::PENDING)->whereIn('id', $data['ids'])->get();

        $count = $data['action'] === 'approve' ? $review->approve($imports) : $review->reject($imports, $data['note'] ?? null);
        $listings = $count . ' listing' . ($count === 1 ? '' : 's');

        return back()->with('toast', $data['action'] === 'approve' ? "{$listings} approved." : "{$listings} rejected and removed.");
    }

    /** The agency / independent agent who owns the connection — agency agents and the admin can't change it. */
    private function manager(): \App\Models\PortalUser
    {
        $owner = $this->owner();
        abort_unless($owner && !$owner->isAgencyAgent(), 403, 'Your agency manages the Property Finder connection.');

        return $owner;
    }

    /** The connection the viewer works with: their own, or their agency's for an agency agent. */
    private function visibleConnection(): ?PropertyFinderConnection
    {
        $owner = $this->owner();
        if (!$owner) {
            return null;
        }
        $accountId = $owner->isAgencyAgent() ? $owner->company_id : $owner->id;

        return PropertyFinderConnection::where('portal_user_id', $accountId)->first();
    }
}
