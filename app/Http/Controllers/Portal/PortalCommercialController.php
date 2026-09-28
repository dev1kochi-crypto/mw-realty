<?php

namespace App\Http\Controllers\Portal;

use App\Models\Property;

/**
 * CRM "Commercial" menu — the same screens, form and `properties` table as Properties, limited to
 * listings with segment = commercial (these are what the website's /commercial page shows). A
 * listing created here counts toward the owner's plan property limit exactly like any other.
 *
 * Only index/create/store/edit/update/show/reorder/move get their own routes; per-listing AJAX
 * actions (feature, status, delete, gallery) are shared with the Properties routes.
 */
class PortalCommercialController extends PortalPropertyController
{
    protected function segment(): string
    {
        return Property::SEGMENT_COMMERCIAL;
    }
}
