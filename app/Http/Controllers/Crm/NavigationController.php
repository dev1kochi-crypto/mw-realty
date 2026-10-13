<?php

namespace App\Http\Controllers\Crm;

use App\Services\Crm\CrmNavigation;
use Illuminate\Routing\Controller;

/**
 * @group CRM Auth
 */
class NavigationController extends Controller
{
    /**
     * Menu
     *
     * The side menu (`items`) and account menu (`account`) for the signed-in account: what it
     * can see, what is locked and why (`lock_reason`), and badge counts. `route` is set for
     * screens already in the new CRM; otherwise open `legacy_url`.
     *
     * @response 200 {"items": [{"key": "leads", "label": "Leads", "icon": "fa-address-book", "section": "Leads", "route": "leads.index", "legacy_url": "https://example.com/portal/crm/leads", "locked": false, "lock_reason": null, "badge": 0, "children": []}], "account": []}
     */
    public function __invoke(CrmNavigation $navigation)
    {
        return response()->json([
            'items' => $navigation->items(),
            'account' => $navigation->accountItems(),
            // Sidebar credits meter (null when it doesn't apply).
            'credits' => $navigation->credits(),
            // Credits can be bought (switched on in Admin › Credits); off → credits held can still be used.
            'credits_can_buy' => \App\Services\CreditService::canBuy(app(\App\Services\Crm\OwnerContext::class)->owner()),
        ]);
    }
}
