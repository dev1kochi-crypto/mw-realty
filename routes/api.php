<?php

use App\Http\Controllers\Api\AboutController;
use App\Http\Controllers\Api\AgencyController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\CareerController;
use App\Http\Controllers\Api\CommercialController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\MarketInsightController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\PropertiesController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\SiteInformationController;
use App\Http\Controllers\Api\StaticTranslationController;
use App\Http\Controllers\PropertyFilterController;
use App\Http\Controllers\PublicAdController;
use App\Http\Controllers\PublicLanguageController;
use Illuminate\Support\Facades\Route;

// Agent/Company CRM API (mobile app + the /crm web app) — /api/crm/*, see routes/crm.php.
// Mounted here, not in bootstrap/app.php's `then:`, because those load after routes/web.php,
// whose /{slug} landing-page catch-all would answer every /api/crm/* GET first.
Route::prefix('crm')->name('crm.api.')->middleware([
    \App\Http\Middleware\Crm\ForceJsonResponse::class,
    // The /crm web app signs in with the browser's session; the app sends a Bearer token instead.
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    \App\Http\Middleware\EnforceAccountSecurity::class,
    // Writes are all-or-nothing (DB + uploaded files) and audit-logged, as they were on the /portal routes.
    \App\Http\Middleware\AtomicAdminChanges::class,
])->group(base_path('routes/crm.php'));

// Public, read-only — consumed by the frontend (home banner + listing page) search filter bar
// AND the properties-dubai listing page, so it stays its own endpoint rather than folding
// into /api/home below.
Route::get('/property-filters', [PropertyFilterController::class, 'index']);

// Public, read-only — location autocomplete (cities, communities, addresses) for the Home search,
// the Properties listing and the Commercial page. Throttled: it runs on every few keystrokes.
Route::get('/location-suggestions', [\App\Http\Controllers\Api\LocationSuggestionController::class, 'index'])
    ->middleware('throttle:120,1');

// Public, read-only — every page's header language dropdown, not just home.
Route::get('/languages', [PublicLanguageController::class, 'index']);

// Public, read-only — the header currency switcher (prices are stored in AED; the site converts for display).
Route::get('/currencies', [\App\Http\Controllers\Api\CurrencyController::class, 'index']);

// Public, read-only — static UI copy (nav/footer/button labels etc.), keyed by ?lang=.
// Same JSON files the admin's Languages > Translations screen edits.
Route::get('/static-translations', [StaticTranslationController::class, 'index']);

// Public, read-only — any page's ad slot (?placement=home, ?placement=property-details, ...).
// Home's own ad is also included in /api/home for convenience, so the home page itself never
// needs to call this separately.
Route::get('/ads', [PublicAdController::class, 'index']);

// Public, read-only — the entire home page in one request (banner, brands, ad, developments,
// premium properties, luxury, why-choose-us, realty, communities, find-properties,
// post-property-steps, popular-places, testimonials, contact). See HomePageService.
Route::get('/home', [HomeController::class, 'index']);

// Public, read-only — the home "Browse New Projects" section's city tabs (?city=Ajman), fetched on tab click.
Route::get('/home/new-projects', [HomeController::class, 'newProjects']);

// Public, read-only — the standalone /contact page in one request (title, description,
// map embed url, hero image, office address/phone/email/whatsapp/hours, social links).
Route::get('/contact', [ContactController::class, 'index']);

// Public — the /contact page form submission. Saves to Enquiries and emails admin.
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:form-submit');

// Public — the footer newsletter signup form. Saves to Newsletter Signups and emails admin.
Route::post('/newsletter/subscribe', [NewsletterController::class, 'store'])->middleware('throttle:form-submit');

// Public, read-only — the standalone /about page in one request (overview, director quote,
// post-property steps, market trends, why-choose-us icons, connect-us video, builders).
Route::get('/about', [AboutController::class, 'index']);

// Public, read-only — the /blogs listing page (paginated) and /blog-details/{slug} page.
Route::get('/blogs', [BlogController::class, 'index']);
Route::get('/blogs/{slug}', [BlogController::class, 'show']);
// Public — a "Blog design" landing page (served at /{slug}, rendered by BlogDetails.vue).
Route::get('/landing-pages/{slug}', [BlogController::class, 'landingPage']);

// Public, read-only — the /market-insights listing page (paginated) and /market-insights/{slug} page.
Route::get('/market-insights', [MarketInsightController::class, 'index']);
Route::get('/market-insights/{slug}', [MarketInsightController::class, 'show']);

// Public — the /careers listing page, /careers/{slug} vacancy page, and the application form
// on both (saves to Careers > Candidates and emails admin).
Route::get('/careers', [CareerController::class, 'index']);
Route::post('/careers/apply', [CareerController::class, 'apply'])->middleware('throttle:form-submit');
Route::get('/careers/{slug}', [CareerController::class, 'show']);

// Public, read-only — the /agents listing page and /agent-details/{slug} page.
Route::get('/agents', [AgentController::class, 'index']);
Route::get('/agents/{slug}', [AgentController::class, 'show']);

// Public, read-only — the /agencies listing page and /agency-details/{slug} page.
Route::get('/agencies', [AgencyController::class, 'index']);
Route::get('/agencies/{slug}', [AgencyController::class, 'show']);

// Public, read-only — the /commercial listing page (properties where category=commercial).
Route::get('/commercial', [CommercialController::class, 'index']);

// Public, read-only — the /properties listing page.
Route::get('/properties', [PropertiesController::class, 'index']);

// Public, read-only — map pins for /properties/map, /commercial/map and /premium-properties/map
// (same filters as the listings, limited to the visible map area). Before {slug} so "map" isn't a slug.
Route::get('/properties/map', [\App\Http\Controllers\Api\PropertyMapController::class, 'index'])->middleware('throttle:120,1');

// Public, read-only — the /property-details/{slug} page.
Route::get('/properties/{slug}', [PropertyController::class, 'show']);

// Public, read-only — site-wide footer content (address/phone/email/social links).
Route::get('/site-information', [SiteInformationController::class, 'index']);

// Public, read-only — one endpoint shared by all 4 legal pages, keyed by {key} = terms|privacy|security|cookie.
Route::get('/legal/{key}', [LegalController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Mobile app — customer account, leads, AI chat, tracking
|--------------------------------------------------------------------------
| The website reaches these through web.php (session cookie + CSRF); the app uses the same
| controllers here, stateless: a Sanctum Bearer token for the account, and an X-Device-Id
| header in place of the mw_vid visitor cookie (see VisitorTracker::browser()).
| Docs: /docs (generated by `php artisan scribe:generate`).
*/
Route::prefix('auth')->controller(\App\Http\Controllers\Api\CustomerTokenAuthController::class)->group(function () {
    Route::post('/register', 'register')->middleware('throttle:portal-registration');
    Route::post('/verify-otp', 'verifyOtp')->middleware('throttle:otp-verify');
    Route::post('/resend-otp', 'resendOtp')->middleware('throttle:otp-verify');
    Route::post('/login', 'login')->middleware('throttle:admin-login');
    Route::post('/google', 'google')->middleware('throttle:admin-login');
    Route::post('/forgot-password', 'forgotPassword')->middleware('throttle:form-submit');
    Route::post('/reset-password', 'resetPassword')->middleware('throttle:form-submit');
    Route::post('/logout', 'logout')->middleware('auth:sanctum');
});

// Guests get {authenticated: false} rather than a 401, as on the website.
Route::get('/customer/session', [\App\Http\Controllers\Api\CustomerController::class, 'session']);

Route::prefix('customer')->middleware('auth:sanctum')->controller(\App\Http\Controllers\Api\CustomerController::class)->group(function () {
    Route::get('/me', 'me');
    // POST as well as PUT: PHP only parses multipart file uploads (the avatar) on POST.
    Route::match(['put', 'post'], '/settings', 'updateSettings');
    Route::delete('/account', 'destroyAccount');
    Route::get('/wishlist', 'wishlist');
    Route::post('/wishlist/{property}', 'toggleWishlist');
    Route::post('/saved-searches', 'storeSavedSearch');
    Route::delete('/saved-searches/{savedSearch}', 'destroySavedSearch');
});

Route::prefix('leads')->controller(\App\Http\Controllers\Crm\LeadCaptureController::class)->middleware('throttle:lead-capture')->group(function () {
    Route::post('/capture', 'store');
    Route::post('/viewing', 'storeViewing');
    Route::post('/brochure-download', 'downloadBrochure');
    Route::post('/floor-plan-download', 'downloadFloorPlan');
    Route::post('/custom-request', 'storeCustomRequest');
    Route::post('/profile-request', 'storeProfileRequest');
});

Route::prefix('chatbot')->controller(\App\Http\Controllers\Api\ChatbotController::class)->group(function () {
    Route::get('/session', 'session')->middleware('throttle:60,1');
    Route::post('/start', 'start')->middleware('throttle:lead-capture');
    Route::post('/reset', 'reset')->middleware('throttle:30,1');
    Route::post('/message', 'send')->middleware('throttle:ai-chatbot');
});

Route::prefix('track')->controller(\App\Http\Controllers\VisitorTrackingController::class)->group(function () {
    Route::post('/page', 'page')->middleware('throttle:120,1');
    Route::post('/page/{event}/time', 'time')->whereNumber('event')->middleware('throttle:240,1');
    Route::post('/impressions', 'impressions')->middleware('throttle:120,1');
    Route::post('/lead-click', 'leadClick')->middleware('throttle:60,1');
});

// Stripe plan-subscription events (signature-verified inside the controller) — /api/stripe/webhook
Route::post('/stripe/webhook', \App\Http\Controllers\StripeWebhookController::class)->name('stripe.webhook');

// Facebook Lead Ads "leadgen" webhook (verify handshake + signature-checked events) — /api/webhooks/facebook
Route::get('/webhooks/facebook', [\App\Http\Controllers\Api\FacebookWebhookController::class, 'verify'])->name('webhooks.facebook.verify');
Route::post('/webhooks/facebook', [\App\Http\Controllers\Api\FacebookWebhookController::class, 'receive'])->name('webhooks.facebook')->middleware('throttle:600,1');
