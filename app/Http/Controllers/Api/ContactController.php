<?php

namespace App\Http\Controllers\Api;

use App\Mail\ContactEnquiryReceived;
use App\Models\CmsKit\Enquiry;
use App\Models\CmsKit\SiteInformation;
use App\Rules\RecaptchaRule;
use App\Services\HomePageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;

/** Single aggregate endpoint for the standalone /contact page (GET /api/contact) plus its form submission. */
class ContactController extends Controller
{
    public function __construct(private readonly HomePageService $homePage)
    {
    }

    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        return response()->json($this->homePage->getContactPageData($lang));
    }

    /** Public, unauthenticated — the /contact page form (POST /api/contact). Saves to Enquiries and emails admin. */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|max:2000',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);

        $enquiry = Enquiry::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'message' => $request->input('message'),
            'page_url' => $request->header('referer'),
            'page_source' => 'Contact Page',
        ]);

        $adminEmail = SiteInformation::notificationEmail();
        if ($adminEmail) {
            Mail::to($adminEmail)->queue((new ContactEnquiryReceived($enquiry))->afterCommit());
        }

        return response()->json(['message' => "Thanks — we've received your message and will be in touch soon."]);
    }
}
