<?php

namespace App\Http\Controllers\Crm;

use Illuminate\Routing\Controller;

/**
 * Serves the CRM web app (Vue, resources/js/crm) for every /crm/* URL — its own router takes
 * it from there, and all data comes from /api/crm/* with this browser's session.
 */
class CrmAppController extends Controller
{
    public function __invoke()
    {
        return view('crm.app');
    }
}
