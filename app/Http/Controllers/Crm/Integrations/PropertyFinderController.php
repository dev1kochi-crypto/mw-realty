<?php

namespace App\Http\Controllers\Crm\Integrations;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Jobs\SyncPropertyFinderListings;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyFinderConnection;
use App\Models\PropertyFinderImport;
use App\Services\PropertyFinder\PropertyFinderClient;
use App\Services\PropertyFinder\PropertyFinderException;
use App\Services\PropertyFinder\PropertyFinderReview;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group CRM Integrations — Property Finder
 *
 *   Agency / independent agent: connect with their API key + secret (verified with Property Finder
 *   first), sync — "Import all listings" the first time, then "Sync new listings" — and disconnect.
 *   Agency agent: sees the agency's connection (key masked) and may sync it; can't change it.
 *   Super Admin: reviews every imported listing (approve / reject) before it can go live.
 */
class PropertyFinderController extends Controller
{
    use ScopesPortalOwner;

    /**
     * The viewer's connection — an agency agent sees their agency's — or, for Super Admin, totals
     * and the review queue. Also the hub card's status (IntegrationController).
     */
    public function summary(): array
    {
        if ($this->isAdmin()) {
            return [
                'connection' => null,
                'can_manage' => false,
                'accounts' => PropertyFinderConnection::count(),
                'pending' => PropertyFinderImport::where('review_status', PropertyFinderImport::PENDING)->whereNotNull('property_id')->count(),
                'imported' => 0,
            ];
        }
        $owner = $this->owner();
        $accountId = $owner?->isAgencyAgent() ? $owner->company_id : $owner?->id;
        $connection = $accountId ? PropertyFinderConnection::with('owner:id,type,name,company_name')->where('portal_user_id', $accountId)->first() : null;
        $connection?->failStaleSync();

        return [
            'connection' => $connection,
            'can_manage' => $owner && !$owner->isAgencyAgent(),
            'accounts' => 0,
            'pending' => $accountId ? PropertyFinderImport::where('portal_user_id', $accountId)->where('review_status', PropertyFinderImport::PENDING)->whereNotNull('property_id')->count() : 0,
            // Imported listings that still exist here (a deleted one may be imported again).
            'imported' => $accountId ? PropertyFinderImport::where('portal_user_id', $accountId)->whereNotNull('property_id')->count() : 0,
        ];
    }

    /**
     * Property Finder screen
     *
     * Super Admin: connected accounts + listings waiting for review. Otherwise the account's
     * connection (key masked) with its last / running sync, or null when not connected.
     */
    public function show()
    {
        $summary = $this->summary();
        $pf = $summary['connection'];

        return response()->json([
            'is_admin' => $this->isAdmin(),
            'can_manage' => $summary['can_manage'],
            'accounts' => $summary['accounts'],
            'pending' => $summary['pending'],
            'imported' => $summary['imported'],
            'connection' => $pf ? [
                'masked_key' => $pf->maskedKey(),
                'owner' => $pf->owner?->displayName(),
                'connected_by' => $pf->connected_by,
                'created_at' => $pf->created_at?->toIso8601String(),
                'last_synced_at' => $pf->last_synced_at?->toIso8601String(),
                'last_full_sync_at' => $pf->last_full_sync_at?->toIso8601String(),
                'syncing' => $pf->syncInProgress(),
                'sync' => $this->syncJson($pf),
            ] : null,
        ]);
    }

    private function syncJson(PropertyFinderConnection $pf): array
    {
        return [
            'status' => $pf->sync_status,
            'mode' => $pf->sync_mode,
            'added' => (int) $pf->sync_added,
            'skipped' => (int) $pf->sync_skipped,
            'failed' => (int) $pf->sync_failed,
            'error' => $pf->sync_error,
            'finished_at' => $pf->sync_finished_at?->toIso8601String(),
            'waiting' => $pf->waitingForWorker(),
        ];
    }

    /**
     * Connect / replace API keys
     *
     * Saves (or replaces) the account's API key + secret, after Property Finder accepts them.
     * Connecting only saves the keys — importing is always started by hand.
     *
     * @bodyParam api_key string required Example: pf_live_abc123
     * @bodyParam api_secret string required Example: s3cr3t
     */
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
            return $this->failed('This Property Finder API key is already connected to another MW Realty account.');
        }

        try {
            (new PropertyFinderClient($key, $secret))->verify();
        } catch (PropertyFinderException $e) {
            return $this->failed($e->getMessage());
        }

        $existing = PropertyFinderConnection::where('portal_user_id', $owner->id)->exists();
        PropertyFinderConnection::updateOrCreate(['portal_user_id' => $owner->id], [
            'api_key' => $key,
            'api_secret' => $secret,
            'api_key_hash' => PropertyFinderConnection::hashKey($key),
            'connected_by' => $this->actorName(),
            'verified_at' => now(),
        ]);

        return $this->done($existing ? 'Property Finder keys updated.' : 'Property Finder connected — click Import all listings when you\'re ready.');
    }

    /**
     * Sync listings
     *
     * mode=all: every live listing (first import / full re-check). mode=new: just the ones added since.
     * Runs in the background — poll `/integrations/property-finder/status`.
     *
     * @bodyParam mode string all | new. Example: new
     */
    public function sync(Request $request)
    {
        $connection = $this->visibleConnection();
        abort_unless($connection, 404);
        $mode = $request->input('mode') === 'new' && $connection->last_full_sync_at ? 'new' : 'all';

        if ($connection->syncInProgress()) {
            return $this->done('A Property Finder sync is already running — see its progress below.');
        }
        SyncPropertyFinderListings::start($connection, $mode);

        return $this->done($mode === 'new'
            ? 'Checking Property Finder for new listings…'
            : 'Importing your Property Finder listings in the background — you can keep working.');
    }

    /**
     * Sync progress
     *
     * @response 200 {"status": "running", "added": 12, "skipped": 3, "failed": 0, "waiting": false}
     */
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

    /**
     * Disconnect
     *
     * Forgets the key. Imported properties stay, and are still never imported twice.
     */
    public function destroy()
    {
        $owner = $this->manager();
        PropertyFinderConnection::where('portal_user_id', $owner->id)->delete();

        return $this->done('Property Finder disconnected — imported listings stay in your properties.');
    }

    /**
     * Review queue (Super Admin)
     *
     * Imported listings by review status, newest first, 20 per page.
     *
     * @queryParam tab string pending | approved | rejected. Example: pending
     * @queryParam q string Title, reference or account. Example: marina
     * @queryParam page integer Example: 1
     */
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
            ->latest('id')->paginate(20);

        return response()->json([
            'data' => collect($imports->items())->map(fn (PropertyFinderImport $import) => $this->importJson($import)),
            'meta' => ['current_page' => $imports->currentPage(), 'last_page' => $imports->lastPage(), 'total' => $imports->total(), 'from' => $imports->firstItem(), 'to' => $imports->lastItem()],
            'tab' => $tab,
            'search' => $search,
            'counts' => PropertyFinderImport::selectRaw('review_status, count(*) as total')->groupBy('review_status')->pluck('total', 'review_status'),
        ]);
    }

    private function importJson(PropertyFinderImport $import): array
    {
        $p = $import->property;

        return [
            'id' => $import->id,
            'pf_reference' => $import->pf_reference ?: $import->pf_listing_id,
            'owner' => $import->owner ? ['name' => $import->owner->displayName(), 'is_agency' => $import->owner->isAgency()] : null,
            'property' => $p ? [
                'id' => $p->id,
                'title' => $p->getTranslation('title') ?: $p->reference_no,
                'reference_no' => $p->reference_no,
                'cover' => $p->galleryImages()[0]['url'] ?? null,
                'url' => route('crm.app', ($p->segment === Property::SEGMENT_COMMERCIAL ? 'commercial/' : 'properties/') . $p->id),
                'meta' => $p->reference_no . ' · ' . ucfirst((string) $p->listing_type)
                    . ($p->property_type ? ' · ' . $p->filterLabel('property_type') : '')
                    . ($p->bedrooms !== null ? ' · ' . ($p->bedrooms ? $p->bedrooms . ' bed' : 'Studio') : ''),
                'address' => $p->getTranslation('address'),
                'price' => $p->price ? (float) $p->price : null,
                'permit_number' => $p->permit_number,
            ] : null,
            'created_at' => $import->created_at?->toIso8601String(),
            'reviewed_at' => $import->reviewed_at?->toIso8601String(),
            'review_note' => $import->review_note,
        ];
    }

    /**
     * Approve / reject imports (Super Admin)
     *
     * Approve → the listing joins the normal permit flow; reject → the property is removed for good.
     *
     * @bodyParam action string required approve | reject. Example: approve
     * @bodyParam ids integer[] required Example: [10, 11]
     * @bodyParam note string Sent with a rejection. Example: Duplicate listing
     */
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

        return $this->done($data['action'] === 'approve' ? "{$listings} approved." : "{$listings} rejected and removed.");
    }

    private function done(string $message)
    {
        return response()->json(['success' => true, 'message' => $message]);
    }

    private function failed(string $message)
    {
        return response()->json(['success' => false, 'message' => $message], 422);
    }

    /** The agency / independent agent who owns the connection — agency agents and the admin can't change it. */
    private function manager(): PortalUser
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
