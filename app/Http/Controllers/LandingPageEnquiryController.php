<?php

namespace App\Http\Controllers;

use App\Mail\LandingPageEnquiryReceived;
use App\Models\CmsKit\Enquiry;
use App\Models\CmsKit\LandingPage;
use App\Models\CmsKit\SiteInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Public, unauthenticated capture for any <form> on a landing page — see
 * LandingPageController::rewireForms(), which points every form on a rendered page here
 * and adds the reserved field names (enquiry_name/email/phone/company/country/message)
 * this controller knows how to map onto the Enquiry table. Anything else submitted goes
 * into extra_fields as a catch-all, so a form isn't required to use every reserved name.
 */
class LandingPageEnquiryController extends Controller
{
    private const RESERVED_FIELDS = [
        'enquiry_name' => 'name',
        'enquiry_email' => 'email',
        'enquiry_phone' => 'phone',
        'enquiry_company' => 'company',
        'enquiry_country' => 'country',
        'enquiry_message' => 'message',
    ];

    public function store(Request $request, string $slug)
    {
        $page = LandingPage::where('slug', $slug)->where('status', true)->firstOrFail();

        $request->validate([
            'enquiry_email' => 'nullable|email|max:255',
            'enquiry_phone' => 'nullable|string|max:50',
            'enquiry_name' => 'nullable|string|max:255',
            'enquiry_company' => 'nullable|string|max:255',
            'enquiry_country' => 'nullable|string|max:255',
            'enquiry_message' => 'nullable|string|max:5000',
        ]);

        $pageUrl = url('/' . $page->slug);

        if (!$request->filled('enquiry_email') && !$request->filled('enquiry_phone') && !$request->filled('enquiry_message')) {
            // The pasted HTML has no way to render Laravel's usual $errors/old() flash data, so the
            // outcome is signaled via a query flag instead — a themed "thank you" state is a frontend
            // concern for whenever a real site theme exists.
            return redirect($pageUrl . '?enquiry=error');
        }

        $data = [];
        foreach (self::RESERVED_FIELDS as $field => $column) {
            $data[$column] = $request->input($field);
        }

        $extra = $request->except([...array_keys(self::RESERVED_FIELDS), '_token']);
        $extra = array_filter($extra, fn ($value) => $value !== null && $value !== '');

        $title = $page->getTranslation('title', config('app.fallback_locale'));

        $enquiry = Enquiry::create([
            ...$data,
            'page_url' => url('/' . $page->slug),
            'page_source' => 'Landing Page: ' . ($title ?: $page->slug),
            'extra_fields' => $extra ?: null,
        ]);

        $adminEmail = SiteInformation::notificationEmail();
        if ($adminEmail) {
            Mail::to($adminEmail)->queue((new LandingPageEnquiryReceived($enquiry, $title ?: $page->slug))->afterCommit());
        }

        // A configured Thank You URL wins over the same-page banner — it can be another landing page,
        // a relative path, or a full external URL; url()->to() leaves an absolute URL alone.
        if ($page->thank_you_url) {
            return redirect(url()->to($page->thank_you_url));
        }

        return redirect($pageUrl . '?enquiry=success');
    }
}
