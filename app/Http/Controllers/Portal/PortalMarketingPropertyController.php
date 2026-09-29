<?php

namespace App\Http\Controllers\Portal;

use App\Services\MarketingPropertyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Portal › Listings › Marketing Properties (Super Admin only): pick agencies / agents, add their
 * listings to the home "Realty Property" list and order it. Logic: MarketingPropertyService.
 */
class PortalMarketingPropertyController extends Controller
{
    public function __construct(private readonly MarketingPropertyService $marketing)
    {
    }

    private function authorizeAdmin(): void
    {
        abort_unless(!Auth::guard('portal')->check() && Auth::guard('cms')->user()?->hasRole('superadmin'), 403);
    }

    public function index()
    {
        $this->authorizeAdmin();

        return view('portal.marketing-properties.index', [
            'items' => $this->marketing->list(app()->getLocale()),
            'homeLimit' => MarketingPropertyService::HOME_LIMIT,
        ]);
    }

    public function accounts(Request $request)
    {
        $this->authorizeAdmin();

        return response()->json($this->marketing->accounts(trim((string) $request->input('search')), max(1, (int) $request->input('page', 1))));
    }

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

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['property_ids' => ['required', 'array', 'min:1', 'max:500'], 'property_ids.*' => ['integer']]);

        $added = $this->marketing->add(array_map('intval', $data['property_ids']));
        $this->flushHome();

        return response()->json([
            'success' => true,
            'message' => $added ? ($added === 1 ? '1 property added' : "{$added} properties added") . ' to the top of the list.' : 'Those properties are already on the list.',
            'items' => $this->marketing->list(app()->getLocale()),
        ]);
    }

    public function destroy(int $propertyId)
    {
        $this->authorizeAdmin();
        $this->marketing->remove($propertyId);
        $this->flushHome();

        return response()->json(['success' => true, 'message' => 'Removed from the list.', 'items' => $this->marketing->list(app()->getLocale())]);
    }

    public function reorder(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['property_ids' => ['required', 'array'], 'property_ids.*' => ['integer']]);

        $this->marketing->reorder(array_map('intval', $data['property_ids']));
        $this->flushHome();

        return response()->json(['success' => true, 'message' => 'Order saved.']);
    }

    /** The home page is cached for a few minutes — show the change now. (/marketing-properties
     *  keys its own cache on the list, see PropertiesPageService.) */
    private function flushHome(): void
    {
        \App\Services\HomePageService::flushCache();
    }
}
