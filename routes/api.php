<?php

use App\Http\Controllers\Api\AboutController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\HomeController;
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

// Public, read-only — the standalone /about page in one request (overview, director quote,
// post-property steps, market trends, why-choose-us icons, connect-us video, builders).
Route::get('/about', [AboutController::class, 'index']);

// Public, read-only — the /blogs listing page (paginated) and /blog-details/{slug} page.
Route::get('/blogs', [BlogController::class, 'index']);
Route::get('/blogs/{slug}', [BlogController::class, 'show']);
