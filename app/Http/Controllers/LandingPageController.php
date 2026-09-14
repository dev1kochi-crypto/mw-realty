<?php

namespace App\Http\Controllers;

use App\Models\CmsKit\LandingPage;
use App\Models\CmsKit\Language;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated rendering of a published Landing Page by its slug (see routes/web.php —
 * registered last since it's a single-segment catch-all). There's no shared site theme/header-footer
 * in this repo yet, so every page is rendered as a self-contained document for now; the "use site
 * header & footer" toggle in the admin is reserved for once that shared layout exists.
 */
class LandingPageController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $page = LandingPage::where('slug', $slug)->where('status', true)->firstOrFail();

        $activeLangCodes = Language::where('status', true)->pluck('code')->all();
        $lang = $request->query('lang');
        if (!$lang || !in_array($lang, $activeLangCodes, true)) {
            $lang = config('app.fallback_locale');
        }

        if ($page->isCustom()) {
            $document = $page->renderDocument($lang, $lang);
            $document = $this->rewireForms($document, $page);
            $document = $this->injectEnquiryBanner($document, $request);

            return response($document)->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $title = $page->getTranslation('title', $lang);
        $content = $page->getTranslation('content', $lang);
        $isRtl = in_array($lang, LandingPage::RTL_LANGUAGE_CODES, true);
        $metaTags = $page->metaTagsHtml(includeTitle: false);

        return response()->view('landing-pages.show-template', compact('page', 'lang', 'title', 'content', 'metaTags', 'isRtl'));
    }

    /**
     * Points every <form> on the page at the enquiry-capture endpoint, replacing whatever action/method
     * it was pasted with (usually action="", which submits nowhere) and adding a CSRF field — so a form
     * works out of the box with no HTML editing required. See LandingPageEnquiryController for how
     * fields are mapped (enquiry_name/email/phone/company/country/message are recognized by name;
     * everything else on the form is still captured, just goes into the enquiry's extra_fields).
     */
    private function rewireForms(string $html, LandingPage $page): string
    {
        $actionUrl = e(route('landing-pages.enquiry.store', $page->slug));
        $csrfField = '<input type="hidden" name="_token" value="' . csrf_token() . '">';

        return preg_replace_callback('/<form\b([^>]*)>/i', function ($matches) use ($actionUrl, $csrfField) {
            $attrs = $matches[1];
            $attrs = preg_replace('/\s+action\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $attrs);
            $attrs = preg_replace('/\s+method\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $attrs);

            return '<form' . $attrs . ' action="' . $actionUrl . '" method="POST">' . $csrfField;
        }, $html);
    }

    /**
     * Shows a floating "thank you"/error banner after a form submission redirects back here with
     * ?enquiry=success|error — styled inline so it looks reasonable on any theme, no admin setup
     * needed. Only used when the page has no dedicated Thank You URL (see the "After a Form Submits"
     * field in the admin) — a configured URL redirects elsewhere entirely instead of showing this.
     */
    private function injectEnquiryBanner(string $html, Request $request): string
    {
        $state = $request->query('enquiry');
        if (!in_array($state, ['success', 'error'], true)) {
            return $html;
        }

        $isSuccess = $state === 'success';
        $message = $isSuccess
            ? 'Thank you — your enquiry has been received.'
            : 'Please fill in at least an email, phone, or message before submitting.';
        $colors = $isSuccess
            ? 'background:#e6f7ee;color:#0d5c34;border:1px solid #a7e3c4;'
            : 'background:#fdeaea;color:#b91c1c;border:1px solid #f3b4b4;';

        $banner = '<div id="lp-enquiry-banner" style="position:fixed;top:16px;left:50%;transform:translateX(-50%);'
            . 'z-index:2147483647;max-width:92vw;padding:14px 20px;border-radius:10px;'
            . 'font-family:system-ui,-apple-system,sans-serif;font-size:14px;line-height:1.4;'
            . 'box-shadow:0 4px 16px rgba(0,0,0,.15);display:flex;align-items:center;gap:14px;' . $colors . '">'
            . '<span>' . e($message) . '</span>'
            . '<button type="button" onclick="document.getElementById(\'lp-enquiry-banner\').remove()" '
            . 'style="background:none;border:none;font-size:18px;line-height:1;cursor:pointer;color:inherit;opacity:.6;padding:0;">&times;</button>'
            . '</div>'
            . '<script>setTimeout(function(){var b=document.getElementById("lp-enquiry-banner");if(b)b.remove();},6000);</script>';

        $pos = stripos($html, '<body');
        if ($pos === false) {
            return $banner . $html;
        }
        $tagEnd = strpos($html, '>', $pos);

        return substr($html, 0, $tagEnd + 1) . $banner . substr($html, $tagEnd + 1);
    }
}
