<?php

use App\Http\Controllers\Api\AboutController;
use App\Http\Controllers\Api\AgencyController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\CommercialController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\PropertiesController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\SiteInformationController;
use App\Http\Controllers\Api\StaticTranslationController;
use App\Http\Controllers\PropertyFilterController;
use App\Http\Controllers\PublicAdController;
use App\Http\Controllers\PublicLanguageController;
use Illuminate\Support\Facades\Route;

// Public, read-only — consumed by the frontend (home banner + listing page) search filter bar
// AND the properties-dubai listing page, so it stays its own endpoint rather than folding
// into /api/home below.
Route::get('/property-filters', [PropertyFilterController::class, 'index']);

// Public, read-only — every page's header language dropdown, not just home.
Route::get('/languages', [PublicLanguageController::class, 'index']);

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

// Public, read-only — the /property-details/{slug} page.
Route::get('/properties/{slug}', [PropertyController::class, 'show']);

// Public, read-only — site-wide footer content (address/phone/email/social links).
Route::get('/site-information', [SiteInformationController::class, 'index']);

// Public, read-only — one endpoint shared by all 4 legal pages, keyed by {key} = terms|privacy|security|cookie.
Route::get('/legal/{key}', [LegalController::class, 'show']);

// Public — the floating AI chat widget's message endpoint. Grounded via Gemini function-calling
// against PropertiesPageService; never returns free-hallucinated listings.
Route::post('/chatbot/message', [ChatbotController::class, 'send'])->middleware('throttle:ai-chatbot');

// Stripe plan-subscription events (signature-verified inside the controller) — /api/stripe/webhook
Route::post('/stripe/webhook', \App\Http\Controllers\StripeWebhookController::class)->name('stripe.webhook');
