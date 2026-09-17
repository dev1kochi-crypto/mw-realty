<?php

use App\Http\Controllers\PropertyFilterController;
use App\Http\Controllers\PublicAdController;
use App\Http\Controllers\PublicBannerController;
use App\Http\Controllers\PublicBrandController;
use App\Http\Controllers\PublicDevelopmentsController;
use App\Http\Controllers\PublicLanguageController;
use App\Http\Controllers\PublicPopularPlacesController;
use App\Http\Controllers\PublicPostPropertyStepsController;
use App\Http\Controllers\PublicPremiumPropertiesController;
use Illuminate\Support\Facades\Route;

// Public, read-only — consumed by the frontend (home banner + listing page) search filter bar.
Route::get('/property-filters', [PropertyFilterController::class, 'index']);

// Public, read-only — consumed by the frontend header's language dropdown.
Route::get('/languages', [PublicLanguageController::class, 'index']);

// Public, read-only — consumed by the frontend home hero section.
Route::get('/banner', [PublicBannerController::class, 'index']);

// Public, read-only — consumed by the frontend home partner-logo strip.
Route::get('/brands', [PublicBrandController::class, 'index']);

// Public, read-only — consumed by the frontend home "Browse New Projects" section.
Route::get('/developments', [PublicDevelopmentsController::class, 'index']);

// Public, read-only — consumed by the frontend home "Premium Properties" carousel.
Route::get('/premium-properties', [PublicPremiumPropertiesController::class, 'index']);

// Public, read-only — consumed by a page's ad banner section (?placement=home, etc.).
Route::get('/ads', [PublicAdController::class, 'index']);

// Public, read-only — consumed by the frontend home "Post your property" section.
Route::get('/post-property-steps', [PublicPostPropertyStepsController::class, 'index']);

// Public, read-only — consumed by the frontend home "Most Popular Properties Places" section.
Route::get('/popular-places', [PublicPopularPlacesController::class, 'index']);
