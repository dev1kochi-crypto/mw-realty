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

/**
 * Single aggregate endpoint for the standalone /contact page (GET /api/contact) plus its form submission.
 *
 * @group Contact & Newsletter
 */
class ContactController extends Controller
{
    /** The home page contact form's "I'm interested in" options. */
    public const INTERESTS = [
        'buy' => 'Buying',
        'rent' => 'Renting',
        'sell' => 'Selling',
        'management' => 'Property Management',
    ];

    public function __construct(private readonly HomePageService $homePage)
    {
    }

    /**
     * Contact page
     *
     * Office details, map embed, hero image and social links.
     *
     * @queryParam lang string Example: en
     */
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());

        return response()->json($this->homePage->getContactPageData($lang));
    }

    /**
     * Send a contact message
     *
     * General enquiry (not about a specific property — for that use `POST /api/leads/capture`).
     * Saves to Enquiries and emails admin.
     *
     * Public, unauthenticated — the /contact page form and the home page's contact section
     * (`source=home`, which also sends `interest`; its message is then optional).
     *
     * @bodyParam name string required Example: Sara Ahmed
     * @bodyParam email string required Example: buyer@example.com
     * @bodyParam phone string Local number. Example: 501234567
     * @bodyParam phone_country_code string Example: +971
     * @bodyParam interest string buy, rent, sell or management. Example: buy
     * @bodyParam message string Required unless `interest` is sent. Example: I'd like a call about 2BR apartments in Marina.
     * @bodyParam source string contact or home. Example: contact
     * @bodyParam recaptcha_token string See "Forms & reCAPTCHA" in the introduction. No-example
     *
     * @response 200 {"message": "Thanks — we've received your message and will be in touch soon."}
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => \App\Rules\PhoneNumber::emailRules(),
            'phone' => \App\Rules\PhoneNumber::rules(),
            'phone_country_code' => \App\Rules\PhoneNumber::countryCodeRules(),
            'interest' => 'nullable|in:' . implode(',', array_keys(self::INTERESTS)),
            'message' => 'required_without:interest|nullable|string|max:2000',
            'source' => 'nullable|in:contact,home',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);

        $interest = self::INTERESTS[$request->input('interest')] ?? null;
        $message = trim((string) $request->input('message'));

        $enquiry = Enquiry::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            // Enquiries keep one phone column — stored with its dial code ("+971 50 123 4567").
            'phone' => $request->filled('phone') ? trim($request->input('phone_country_code') . ' ' . $request->input('phone')) : null,
            // Interest leads the message too, so it's visible in the admin list and the notification email.
            'message' => $interest ? trim("Interested in: {$interest}\n\n{$message}") : $message,
            'extra_fields' => $interest ? ['interest' => $interest] : null,
            'page_url' => $request->header('referer'),
            'page_source' => $request->input('source') === 'home' ? 'Home Page' : 'Contact Page',
        ]);

        $adminEmail = SiteInformation::notificationEmail();
        if ($adminEmail) {
            Mail::to($adminEmail)->queue((new ContactEnquiryReceived($enquiry))->afterCommit());
        }

        // The enquirer becomes a Website Lead (Super Admin's pool) with their browsing history.
        app(\App\Services\Visitors\VisitorTracker::class)->captureEnquiry(
            $request,
            $request->only(['name', 'email', 'phone', 'phone_country_code']),
            \App\Models\Visitors\VisitorLead::SOURCE_CONTACT_FORM,
            ['form' => $enquiry->page_source . ' form', 'enquiry_id' => $enquiry->id, 'message' => \Illuminate\Support\Str::limit((string) $enquiry->message, 500)],
        );

        return response()->json(['message' => "Thanks — we've received your message and will be in touch soon."]);
    }
}
