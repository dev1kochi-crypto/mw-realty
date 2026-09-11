<?php

namespace App\Http\Controllers\Portal;

use App\Models\CmsKit\Enquiry;
use App\Models\CmsKit\SiteInformation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/** Lets an agent/company reach MW Realty support — writes to the general Enquiry table admin already reviews. */
class PortalContactController extends Controller
{
    public function index()
    {
        $siteInfo = SiteInformation::first();

        return view('portal.contact.index', compact('siteInfo'));
    }

    public function store(Request $request)
    {
        $owner = Auth::guard('portal')->user();

        $request->validate([
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:2000',
        ]);

        Enquiry::create([
            'name' => $owner->type === 'company' ? ($owner->company_name ?: $owner->name) : $owner->name,
            'email' => $owner->email,
            'phone' => $owner->phone,
            'company' => $owner->type === 'company' ? $owner->company_name : null,
            'page_url' => route('portal.contact.index'),
            'page_source' => 'portal-contact-us',
            'message' => $request->input('subject') . "\n\n" . $request->input('message'),
        ]);

        return back()->with('success', 'Your message has been sent — our team will get back to you shortly.');
    }
}
