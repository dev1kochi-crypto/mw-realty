<?php

namespace App\Http\Controllers\Crm\Marketing;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Services\HomePageService;
use App\Services\MarketingPropertyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group CRM Marketing Properties
 *
 * Super Admin only: pick agencies / agents, add their listings to the home "Realty Property" list
 * and order it. The first `home_limit` show on the home page, all of them on /marketing-properties.
 * Logic: MarketingPropertyService.
 */
class MarketingPropertyController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(private readonly MarketingPropertyService $marketing)
    {
    }

    /**
     * The marketing list
     *
     * In order, with how many of them show on the home page.
     */
    public function index()
    {
        $this->authorizeAdmin();

        return response()->json([
            'items' => $this->marketing->list(app()->getLocale())->values(),
            'home_limit' => MarketingPropertyService::HOME_LIMIT,
            'website_url' => url('/marketing-properties'),
        ]);
    }

    /**
     * Agencies & agents to pick from
     *
     * Searched and paged on the server, 20 per page (`next_page` null on the last one).
     *
     * @queryParam search string Example: marina
     * @queryParam page integer Example: 1
     */
    public function accounts(Request $request)
    {
        $this->authorizeAdmin();

        return response()->json($this->marketing->accounts(trim((string) $request->input('search')), max(1, (int) $request->input('page', 1))));
    }

    /**
     * Listings of the chosen accounts
     *
     * @queryParam accounts integer[] required The agency / agent ids. Example: [12, 40]
     * @queryParam search string Title or reference. Example: villa
     * @queryParam page integer Example: 1
     */
    public function properties(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['accounts' => ['required', 'array', 'min:1', 'max:50'], 'accounts.*' => ['integer']]);

        return response()->json($this->marketing->propertiesOf(
            $data['accounts'],
            trim((string) $request->input('search')),
            max(1, (int) $request->input('page', 1)),
            app()->getLocale(),
        ));
    }

    /**
     * Add listings
     *
     * They land at the top of the list. Returns the whole list again.
     *
     * @bodyParam property_ids integer[] required Example: [101, 102]
     */
    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['property_ids' => ['required', 'array', 'min:1', 'max:500'], 'property_ids.*' => ['integer']]);

        $added = $this->marketing->add(array_map('intval', $data['property_ids']));
        HomePageService::flushCache();

        return response()->json([
            'success' => true,
            'message' => $added ? ($added === 1 ? '1 property added' : "{$added} properties added") . ' to the top of the list.' : 'Those properties are already on the list.',
            'items' => $this->marketing->list(app()->getLocale())->values(),
        ]);
    }

    /** Remove a listing from the list */
    public function destroy(int $propertyId)
    {
        $this->authorizeAdmin();
        $this->marketing->remove($propertyId);
        HomePageService::flushCache();

        return response()->json(['success' => true, 'message' => 'Removed from the list.', 'items' => $this->marketing->list(app()->getLocale())->values()]);
    }

    /**
     * Save the order
     *
     * @bodyParam property_ids integer[] required Every listing on the list, in the new order. Example: [102, 101]
     */
    public function reorder(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['property_ids' => ['required', 'array'], 'property_ids.*' => ['integer']]);

        $this->marketing->reorder(array_map('intval', $data['property_ids']));
        HomePageService::flushCache();

        return response()->json(['success' => true, 'message' => 'Order saved.']);
    }

    private function authorizeAdmin(): void
    {
        abort_unless($this->isAdmin(), 403);
    }
}
