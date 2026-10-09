<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\PortalUserController;
use App\Http\Controllers\CmsKit\PostPropertyStepController;
use App\Http\Controllers\CmsKit\PopularPlaceController;
use App\Http\Controllers\CmsKit\LuxuryProjectController;
use App\Http\Controllers\CmsKit\AboutUsController;
use App\Http\Controllers\CmsKit\BlogCategoryController;
use App\Http\Controllers\CmsKit\CurrencyController;
use App\Http\Controllers\CmsKit\BlogController;
use App\Http\Controllers\CmsKit\MarketInsightController;
use App\Http\Controllers\CmsKit\MarketInsightTermController;
use App\Http\Controllers\CmsKit\CareerController;
use App\Http\Controllers\CmsKit\CareerDepartmentController;
use App\Http\Controllers\CmsKit\CareerCandidateController;
use App\Http\Controllers\CmsKit\MarketTrendController;
use App\Http\Controllers\CmsKit\WhyChooseUsController;
use App\Http\Controllers\CmsKit\OurBuilderController;
use App\Http\Controllers\CmsKit\ConnectUsController;
use App\Http\Controllers\CmsKit\CommunityController;
use App\Http\Controllers\CmsKit\ContactController;
use App\Http\Controllers\CmsKit\FindPropertyController;
use App\Http\Controllers\CmsKit\PlanController;
use App\Http\Controllers\CmsKit\AdController;
use App\Http\Controllers\CmsKit\LandingPageController;
use App\Http\Controllers\CmsKit\NotificationController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalNotificationController;
use App\Http\Controllers\Crm\LeadCaptureController;
use App\Http\Controllers\Customer\CustomerAuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\SpaController;

Route::get('/', [SpaController::class, 'staticPage'])->defaults('pageKey', 'home');

// Media now lives on Cloudinary and the DB stores full URLs. Anything that still wraps one in
// "/storage/…" (an old template, a cached view, an external link) is redirected to the real file.
// Servers often collapse "https://" to "https:/" in paths, so both forms are accepted.
Route::get('/storage/{url}', function (string $url) {
    $url = preg_replace('#^(https?):/+#i', '$1://', $url);
    abort_unless(\App\Services\CloudinaryMedia::isCloudinaryUrl($url), 404);

    return redirect()->away($url, 301);
})->where('url', 'https?:/.+');

// Public, unauthenticated — any property-detail page can POST a lead here; it's
// routed to the property's owning company/agent (see LeadCaptureController).
Route::post('/leads/capture', [LeadCaptureController::class, 'store'])->name('leads.capture')->middleware('throttle:lead-capture');
Route::post('/leads/viewing', [LeadCaptureController::class, 'storeViewing'])->name('leads.viewing')->middleware('throttle:lead-capture');
Route::post('/leads/brochure-download', [LeadCaptureController::class, 'downloadBrochure'])->name('leads.brochure-download')->middleware('throttle:lead-capture');
Route::post('/leads/floor-plan-download', [LeadCaptureController::class, 'downloadFloorPlan'])->name('leads.floor-plan-download')->middleware('throttle:lead-capture');
// Signed, short-lived link handed out by the two routes above once the lead is saved.
Route::get('/downloads/property/{property}/{kind}', \App\Http\Controllers\PropertyFileDownloadController::class)->whereIn('kind', \App\Http\Controllers\PropertyFileDownloadController::KINDS)->name('property-files.download')->middleware(['signed', 'throttle:30,1']);

// Properties listing "Custom Request" — no property to attach to, always an unassigned lead
// (see LeadCaptureController::storeCustomRequest).
Route::post('/leads/custom-request', [LeadCaptureController::class, 'storeCustomRequest'])->name('leads.custom-request')->middleware('throttle:lead-capture');
Route::post('/leads/profile-request', [LeadCaptureController::class, 'storeProfileRequest'])->name('leads.profile-request')->middleware('throttle:lead-capture');
Route::get('/newsletter/unsubscribe/{token}', [\App\Http\Controllers\Api\NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

// Public — the AI chat widget. Web (not API) routes: the visitor is identified by the mw_vid
// cookie and every conversation is saved against them (see Api\ChatbotController / VisitorTracker).
Route::prefix('chatbot')->name('chatbot.')->controller(\App\Http\Controllers\Api\ChatbotController::class)->group(function () {
    Route::get('/session', 'session')->name('session')->middleware('throttle:60,1');
    Route::post('/start', 'start')->name('start')->middleware('throttle:lead-capture');
    Route::post('/reset', 'reset')->name('reset')->middleware('throttle:30,1');
    Route::post('/message', 'send')->name('message')->middleware('throttle:ai-chatbot');
});

// Public — the site's page tracker (useVisitorTracking.js): page / property views and time spent.
Route::post('/track/page', [\App\Http\Controllers\VisitorTrackingController::class, 'page'])->name('track.page')->middleware('throttle:120,1');
Route::post('/track/page/{event}/time', [\App\Http\Controllers\VisitorTrackingController::class, 'time'])->whereNumber('event')->name('track.time')->middleware('throttle:240,1');
// Listing performance: cards seen on screen (useListingImpressions.js) and contact clicks on a listing.
Route::post('/track/impressions', [\App\Http\Controllers\VisitorTrackingController::class, 'impressions'])->name('track.impressions')->middleware('throttle:120,1');
Route::post('/track/lead-click', [\App\Http\Controllers\VisitorTrackingController::class, 'leadClick'])->name('track.lead-click')->middleware('throttle:60,1');

// The public-site "customer" (buyer/visitor) account — guard 'web', separate from the
// agent/company portal above. Real <form> POSTs (CustomerLogin.vue / CustomerSignup.vue),
// same pattern as the portal's own login form.
Route::post('/customer/register', [CustomerAuthController::class, 'register'])->name('customer.register')->middleware('throttle:portal-registration');
Route::post('/customer/login', [CustomerAuthController::class, 'login'])->name('customer.login')->middleware('throttle:admin-login');
Route::post('/customer/verify-otp', [CustomerAuthController::class, 'verifyOtp'])->name('customer.verify-otp')->middleware('throttle:otp-verify');
Route::post('/customer/resend-otp', [CustomerAuthController::class, 'resendOtp'])->name('customer.resend-otp')->middleware('throttle:otp-verify');
Route::post('/customer/forgot-password', [CustomerAuthController::class, 'forgotPassword'])->name('customer.forgot-password')->middleware('throttle:form-submit');
Route::post('/customer/reset-password', [CustomerAuthController::class, 'resetPassword'])->name('customer.reset-password')->middleware('throttle:form-submit');
Route::post('/customer/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');

// "Continue with Google" — customer accounts only (see CustomerAuthController).
Route::get('/customer/auth/google', [CustomerAuthController::class, 'redirectToGoogle'])->name('customer.auth.google');
Route::get('/customer/auth/google/callback', [CustomerAuthController::class, 'handleGoogleCallback'])->name('customer.auth.google.callback');

// Public — every page's shared wishlist-heart state reads this (see useWishlist.js).
Route::get('/customer/session', [CustomerController::class, 'session'])->name('customer.session');

// JSON API for the logged-in customer's own dashboard (Profile.vue) — guarded by
// customer.auth (401 JSON on a guest, not a redirect; see EnsureCustomerAuthenticated).
Route::prefix('customer')->middleware('customer.auth')->group(function () {
    Route::get('/me', [CustomerController::class, 'me'])->name('customer.me');
    Route::put('/settings', [CustomerController::class, 'updateSettings'])->name('customer.settings');
    Route::post('/wishlist/{property}', [CustomerController::class, 'toggleWishlist'])->name('customer.wishlist.toggle');
    Route::post('/saved-searches', [CustomerController::class, 'storeSavedSearch'])->name('customer.saved-searches.store');
    Route::delete('/saved-searches/{savedSearch}', [CustomerController::class, 'destroySavedSearch'])->name('customer.saved-searches.destroy');
});

Route::middleware(['web'])->group(function () {
    Route::prefix(config('cms-kit.common.auth.prefix', 'admin'))->group(function () {
        Route::middleware(['cms.auth'])->group(function () {

            // Currencies (website currency switcher; prices are stored in AED)
            Route::middleware(['cms.permission:currencies.view'])->group(function () {
                Route::get('/currencies', [CurrencyController::class, 'index'])->name('cms.currencies.index');
                Route::post('/currencies', [CurrencyController::class, 'store'])->middleware('cms.permission:currencies.create')->name('cms.currencies.store');
                Route::middleware(['cms.permission:currencies.edit'])->group(function () {
                    Route::put('/currencies/{id}', [CurrencyController::class, 'update'])->name('cms.currencies.update');
                    Route::post('/currencies/{id}/toggle-status', [CurrencyController::class, 'toggleStatus'])->name('cms.currencies.toggle-status');
                    Route::post('/currencies/{id}/set-default', [CurrencyController::class, 'setDefault'])->name('cms.currencies.set-default');
                });
                Route::delete('/currencies/{id}', [CurrencyController::class, 'destroy'])->middleware('cms.permission:currencies.delete')->name('cms.currencies.destroy');
            });

            // Filters
            Route::middleware(['cms.permission:filters.view'])->group(function () {
                Route::get('/filters', [FilterController::class, 'index'])->name('cms.filters.index');

                Route::middleware(['cms.permission:filters.create'])->group(function () {
                    Route::get('/filters/create', [FilterController::class, 'create'])->name('cms.filters.create');
                    Route::post('/filters', [FilterController::class, 'store'])->name('cms.filters.store');
                    Route::post('/filters/{filter}/values', [FilterController::class, 'storeValue'])->name('cms.filters.values.store');
                });

                Route::middleware(['cms.permission:filters.edit'])->group(function () {
                    Route::get('/filters/{id}/edit', [FilterController::class, 'edit'])->name('cms.filters.edit');
                    Route::put('/filters/{id}', [FilterController::class, 'update'])->name('cms.filters.update');
                    Route::post('/filters/{id}/toggle-status', [FilterController::class, 'toggleStatus'])->name('cms.filters.toggle-status');
                    Route::post('/filters/reorder', [FilterController::class, 'reorder'])->name('cms.filters.reorder');
                    Route::put('/filters/{filter}/values/{value}', [FilterController::class, 'updateValue'])->name('cms.filters.values.update');
                    Route::post('/filters/{filter}/values/{value}/toggle-status', [FilterController::class, 'toggleValueStatus'])->name('cms.filters.values.toggle-status');
                });

                Route::middleware(['cms.permission:filters.delete'])->group(function () {
                    Route::delete('/filters/{id}', [FilterController::class, 'destroy'])->name('cms.filters.destroy');
                    Route::delete('/filters/{filter}/values/{value}', [FilterController::class, 'destroyValue'])->name('cms.filters.values.destroy');
                });
            });

            // Communities (under Filters — section + items, each linked to a "location" filter value)
            Route::middleware(['cms.permission:communities.view'])->group(function () {
                Route::get('/communities', [CommunityController::class, 'index'])->name('cms.communities.index');
                Route::post('/communities/section', [CommunityController::class, 'updateSection'])->name('cms.communities.update-section')->middleware('cms.permission:communities.edit');

                Route::middleware(['cms.permission:communities.create'])->group(function () {
                    Route::get('/communities/create', [CommunityController::class, 'create'])->name('cms.communities.create');
                    Route::post('/communities', [CommunityController::class, 'store'])->name('cms.communities.store');
                });

                Route::middleware(['cms.permission:communities.edit'])->group(function () {
                    Route::get('/communities/{id}/edit', [CommunityController::class, 'edit'])->name('cms.communities.edit');
                    Route::put('/communities/{id}', [CommunityController::class, 'update'])->name('cms.communities.update');
                    Route::post('/communities/{id}/toggle-status', [CommunityController::class, 'toggleStatus'])->name('cms.communities.toggle-status');
                    Route::post('/communities/reorder', [CommunityController::class, 'reorder'])->name('cms.communities.reorder');
                });

                Route::middleware(['cms.permission:communities.delete'])->group(function () {
                    Route::delete('/communities/{id}', [CommunityController::class, 'destroy'])->name('cms.communities.destroy');
                    Route::post('/communities/bulk-action', [CommunityController::class, 'bulkAction'])->name('cms.communities.bulk-action');
                });
            });

            // Find Properties (under Filters — section + items, each linked to a "property_type" filter value)
            Route::middleware(['cms.permission:find-properties.view'])->group(function () {
                Route::get('/find-properties', [FindPropertyController::class, 'index'])->name('cms.find-properties.index');
                Route::post('/find-properties/section', [FindPropertyController::class, 'updateSection'])->name('cms.find-properties.update-section')->middleware('cms.permission:find-properties.edit');

                Route::middleware(['cms.permission:find-properties.create'])->group(function () {
                    Route::get('/find-properties/create', [FindPropertyController::class, 'create'])->name('cms.find-properties.create');
                    Route::post('/find-properties', [FindPropertyController::class, 'store'])->name('cms.find-properties.store');
                });

                Route::middleware(['cms.permission:find-properties.edit'])->group(function () {
                    Route::get('/find-properties/{id}/edit', [FindPropertyController::class, 'edit'])->name('cms.find-properties.edit');
                    Route::put('/find-properties/{id}', [FindPropertyController::class, 'update'])->name('cms.find-properties.update');
                    Route::post('/find-properties/{id}/toggle-status', [FindPropertyController::class, 'toggleStatus'])->name('cms.find-properties.toggle-status');
                    Route::post('/find-properties/reorder', [FindPropertyController::class, 'reorder'])->name('cms.find-properties.reorder');
                });

                Route::middleware(['cms.permission:find-properties.delete'])->group(function () {
                    Route::delete('/find-properties/{id}', [FindPropertyController::class, 'destroy'])->name('cms.find-properties.destroy');
                    Route::post('/find-properties/bulk-action', [FindPropertyController::class, 'bulkAction'])->name('cms.find-properties.bulk-action');
                });
            });

            // Post Property Steps ("Post Your Property in 3 Simple Steps" home section)
            Route::middleware(['cms.permission:post-property-steps.view'])->group(function () {
                Route::get('/post-property-steps', [PostPropertyStepController::class, 'index'])->name('cms.post-property-steps.index');
                Route::post('/post-property-steps/section', [PostPropertyStepController::class, 'updateSection'])->name('cms.post-property-steps.update-section')->middleware('cms.permission:post-property-steps.edit');

                Route::middleware(['cms.permission:post-property-steps.create'])->group(function () {
                    Route::get('/post-property-steps/create', [PostPropertyStepController::class, 'create'])->name('cms.post-property-steps.create');
                    Route::post('/post-property-steps', [PostPropertyStepController::class, 'store'])->name('cms.post-property-steps.store');
                });

                Route::middleware(['cms.permission:post-property-steps.edit'])->group(function () {
                    Route::get('/post-property-steps/{id}/edit', [PostPropertyStepController::class, 'edit'])->name('cms.post-property-steps.edit');
                    Route::put('/post-property-steps/{id}', [PostPropertyStepController::class, 'update'])->name('cms.post-property-steps.update');
                    Route::post('/post-property-steps/{id}/toggle-status', [PostPropertyStepController::class, 'toggleStatus'])->name('cms.post-property-steps.toggle-status');
                    Route::post('/post-property-steps/reorder', [PostPropertyStepController::class, 'reorder'])->name('cms.post-property-steps.reorder');
                });

                Route::middleware(['cms.permission:post-property-steps.delete'])->group(function () {
                    Route::delete('/post-property-steps/{id}', [PostPropertyStepController::class, 'destroy'])->name('cms.post-property-steps.destroy');
                    Route::post('/post-property-steps/bulk-action', [PostPropertyStepController::class, 'bulkAction'])->name('cms.post-property-steps.bulk-action');
                });
            });

            // About Us (section-only)
            Route::middleware(['cms.permission:about-us.view'])->group(function () {
                Route::get('/about-us', [AboutUsController::class, 'index'])->name('cms.about-us.index');
                Route::post('/about-us', [AboutUsController::class, 'update'])->name('cms.about-us.update')->middleware('cms.permission:about-us.edit');
            });

            // Common Titles — heading/description/button content for home-page sections
            // that have no admin screen of their own (Developments, Premium Property,
            // Popular Places, Luxury Project, Realty Property).
            Route::middleware(['cms.permission:section-headings.view'])->group(function () {
                Route::get('/section-headings', [\App\Http\Controllers\CmsKit\SectionHeadingController::class, 'index'])->name('cms.section-headings.index');
                Route::put('/section-headings/{section}', [\App\Http\Controllers\CmsKit\SectionHeadingController::class, 'update'])->name('cms.section-headings.update')->middleware('cms.permission:section-headings.edit');
            });

            // Market Trends (section-only)
            Route::middleware(['cms.permission:market-trends.view'])->group(function () {
                Route::get('/market-trends', [MarketTrendController::class, 'index'])->name('cms.market-trends.index');
                Route::post('/market-trends', [MarketTrendController::class, 'update'])->name('cms.market-trends.update')->middleware('cms.permission:market-trends.edit');
            });

            // Why Choose Us (section + items)
            Route::middleware(['cms.permission:why-choose-us.view'])->group(function () {
                Route::get('/why-choose-us', [WhyChooseUsController::class, 'index'])->name('cms.why-choose-us.index');
                Route::post('/why-choose-us/section', [WhyChooseUsController::class, 'updateSection'])->name('cms.why-choose-us.update-section')->middleware('cms.permission:why-choose-us.edit');

                Route::middleware(['cms.permission:why-choose-us.create'])->group(function () {
                    Route::get('/why-choose-us/create', [WhyChooseUsController::class, 'create'])->name('cms.why-choose-us.create');
                    Route::post('/why-choose-us', [WhyChooseUsController::class, 'store'])->name('cms.why-choose-us.store');
                });

                Route::middleware(['cms.permission:why-choose-us.edit'])->group(function () {
                    Route::get('/why-choose-us/{id}/edit', [WhyChooseUsController::class, 'edit'])->name('cms.why-choose-us.edit');
                    Route::put('/why-choose-us/{id}', [WhyChooseUsController::class, 'update'])->name('cms.why-choose-us.update');
                    Route::post('/why-choose-us/{id}/toggle-status', [WhyChooseUsController::class, 'toggleStatus'])->name('cms.why-choose-us.toggle-status');
                    Route::post('/why-choose-us/reorder', [WhyChooseUsController::class, 'reorder'])->name('cms.why-choose-us.reorder');
                });

                Route::middleware(['cms.permission:why-choose-us.delete'])->group(function () {
                    Route::delete('/why-choose-us/{id}', [WhyChooseUsController::class, 'destroy'])->name('cms.why-choose-us.destroy');
                    Route::post('/why-choose-us/bulk-action', [WhyChooseUsController::class, 'bulkAction'])->name('cms.why-choose-us.bulk-action');
                });
            });

            // Blogs (section + items) — overrides the vendor package's own /blogs routes (registered
            // in vendor/mightywarnerskochi/cms/src/routes/web.php) so this app's BlogController, with
            // its extra_fields file-upload handling and dynamic category list, is what actually runs.
            // Laravel matches routes in registration order, and this file's routes load before the
            // package's own, so these take priority for the same URIs.
            Route::middleware(['cms.permission:blogs.view'])->group(function () {
                Route::get('/blogs', [BlogController::class, 'index'])->name('cms.blogs.index');
                Route::post('/blogs/update-section', [BlogController::class, 'updateSection'])->name('cms.blogs.update-section')->middleware('cms.permission:blogs.edit');

                Route::middleware(['cms.permission:blogs.create'])->group(function () {
                    Route::get('/blogs/create', [BlogController::class, 'create'])->name('cms.blogs.create');
                    Route::post('/blogs', [BlogController::class, 'store'])->name('cms.blogs.store');
                });

                Route::middleware(['cms.permission:blogs.edit'])->group(function () {
                    Route::get('/blogs/{id}/edit', [BlogController::class, 'edit'])->name('cms.blogs.edit');
                    Route::put('/blogs/{id}', [BlogController::class, 'update'])->name('cms.blogs.update');
                    Route::post('/blogs/{id}/toggle-status', [BlogController::class, 'toggleStatus'])->name('cms.blogs.toggle-status');
                    Route::post('/blogs/reorder', [BlogController::class, 'reorder'])->name('cms.blogs.reorder');
                });

                Route::middleware(['cms.permission:blogs.delete'])->group(function () {
                    Route::delete('/blogs/{id}', [BlogController::class, 'destroy'])->name('cms.blogs.destroy');
                    Route::post('/blogs/bulk-action', [BlogController::class, 'bulkAction'])->name('cms.blogs.bulk-action');
                });
            });

            // Blog Categories
            Route::middleware(['cms.permission:blog-categories.view'])->group(function () {
                Route::get('/blog-categories', [BlogCategoryController::class, 'index'])->name('cms.blog-categories.index');

                Route::middleware(['cms.permission:blog-categories.create'])->group(function () {
                    Route::get('/blog-categories/create', [BlogCategoryController::class, 'create'])->name('cms.blog-categories.create');
                    Route::post('/blog-categories', [BlogCategoryController::class, 'store'])->name('cms.blog-categories.store');
                });

                Route::middleware(['cms.permission:blog-categories.edit'])->group(function () {
                    Route::get('/blog-categories/{id}/edit', [BlogCategoryController::class, 'edit'])->name('cms.blog-categories.edit');
                    Route::put('/blog-categories/{id}', [BlogCategoryController::class, 'update'])->name('cms.blog-categories.update');
                    Route::post('/blog-categories/{id}/toggle-status', [BlogCategoryController::class, 'toggleStatus'])->name('cms.blog-categories.toggle-status');
                    Route::post('/blog-categories/reorder', [BlogCategoryController::class, 'reorder'])->name('cms.blog-categories.reorder');
                });

                Route::middleware(['cms.permission:blog-categories.delete'])->group(function () {
                    Route::delete('/blog-categories/{id}', [BlogCategoryController::class, 'destroy'])->name('cms.blog-categories.destroy');
                    Route::post('/blog-categories/bulk-action', [BlogCategoryController::class, 'bulkAction'])->name('cms.blog-categories.bulk-action');
                });
            });

            // Market Insights (page header + posts with topic, region, headline figures and report PDF)
            Route::middleware(['cms.permission:market-insights.view'])->group(function () {
                Route::get('/market-insights', [MarketInsightController::class, 'index'])->name('cms.market-insights.index');
                Route::post('/market-insights/update-section', [MarketInsightController::class, 'updateSection'])->name('cms.market-insights.update-section')->middleware('cms.permission:market-insights.edit');

                Route::middleware(['cms.permission:market-insights.create'])->group(function () {
                    Route::get('/market-insights/create', [MarketInsightController::class, 'create'])->name('cms.market-insights.create');
                    Route::post('/market-insights', [MarketInsightController::class, 'store'])->name('cms.market-insights.store');
                });

                Route::middleware(['cms.permission:market-insights.edit'])->group(function () {
                    Route::get('/market-insights/{id}/edit', [MarketInsightController::class, 'edit'])->name('cms.market-insights.edit');
                    Route::put('/market-insights/{id}', [MarketInsightController::class, 'update'])->name('cms.market-insights.update');
                    Route::post('/market-insights/{id}/toggle-status', [MarketInsightController::class, 'toggleStatus'])->name('cms.market-insights.toggle-status');
                    Route::post('/market-insights/{id}/toggle-featured', [MarketInsightController::class, 'toggleFeatured'])->name('cms.market-insights.toggle-featured');
                    Route::post('/market-insights/reorder', [MarketInsightController::class, 'reorder'])->name('cms.market-insights.reorder');
                });

                Route::middleware(['cms.permission:market-insights.delete'])->group(function () {
                    Route::delete('/market-insights/{id}', [MarketInsightController::class, 'destroy'])->name('cms.market-insights.destroy');
                    Route::post('/market-insights/bulk-action', [MarketInsightController::class, 'bulkAction'])->name('cms.market-insights.bulk-action');
                });

                // Topics / Regions — admin-managed, translatable lists the insight form picks from.
                // One controller for both; the route's 'type' default says which list.
                foreach (['topic' => 'topics', 'region' => 'regions'] as $termType => $termPath) {
                    $termName = "cms.market-insight-{$termPath}";
                    $termUrl = "/market-insights/{$termPath}";
                    Route::get($termUrl, [MarketInsightTermController::class, 'index'])->defaults('type', $termType)->name("{$termName}.index");
                    Route::middleware(['cms.permission:market-insights.create'])->group(function () use ($termType, $termName, $termUrl) {
                        Route::get("{$termUrl}/create", [MarketInsightTermController::class, 'create'])->defaults('type', $termType)->name("{$termName}.create");
                        Route::post($termUrl, [MarketInsightTermController::class, 'store'])->defaults('type', $termType)->name("{$termName}.store");
                    });
                    Route::middleware(['cms.permission:market-insights.edit'])->group(function () use ($termType, $termName, $termUrl) {
                        Route::get("{$termUrl}/{id}/edit", [MarketInsightTermController::class, 'edit'])->whereNumber('id')->defaults('type', $termType)->name("{$termName}.edit");
                        Route::put("{$termUrl}/{id}", [MarketInsightTermController::class, 'update'])->whereNumber('id')->defaults('type', $termType)->name("{$termName}.update");
                        Route::post("{$termUrl}/{id}/toggle-status", [MarketInsightTermController::class, 'toggleStatus'])->whereNumber('id')->defaults('type', $termType)->name("{$termName}.toggle-status");
                        Route::post("{$termUrl}/reorder", [MarketInsightTermController::class, 'reorder'])->defaults('type', $termType)->name("{$termName}.reorder");
                    });
                    Route::middleware(['cms.permission:market-insights.delete'])->group(function () use ($termType, $termName, $termUrl) {
                        Route::delete("{$termUrl}/{id}", [MarketInsightTermController::class, 'destroy'])->whereNumber('id')->defaults('type', $termType)->name("{$termName}.destroy");
                        Route::post("{$termUrl}/bulk-action", [MarketInsightTermController::class, 'bulkAction'])->defaults('type', $termType)->name("{$termName}.bulk-action");
                    });
                }
            });

            // Careers — overrides the vendor package's own /careers routes (same URIs, registered
            // first, see the Blogs note above) so this app's copies of the controllers run: they
            // store uploads through ManagedFiles (Cloudinary) instead of the local public disk.
            Route::middleware(['cms.permission:careers.view'])->group(function () {
                Route::get('/careers/common', [CareerController::class, 'common'])->name('cms.careers.common');
                Route::post('/careers/common', [CareerController::class, 'updateSection'])->name('cms.careers.update-section')->middleware('cms.permission:careers.edit');

                Route::get('/careers/vacancies', [CareerController::class, 'vacancies'])->name('cms.careers.vacancies.index');
                Route::get('/careers/create', [CareerController::class, 'create'])->name('cms.careers.create')->middleware('cms.permission:careers.create');
                Route::post('/careers', [CareerController::class, 'store'])->name('cms.careers.store')->middleware('cms.permission:careers.create');
                Route::get('/careers/{id}/edit', [CareerController::class, 'edit'])->whereNumber('id')->name('cms.careers.edit')->middleware('cms.permission:careers.edit');
                Route::put('/careers/{id}', [CareerController::class, 'update'])->whereNumber('id')->name('cms.careers.update')->middleware('cms.permission:careers.edit');
                Route::delete('/careers/{id}', [CareerController::class, 'destroy'])->whereNumber('id')->name('cms.careers.destroy')->middleware('cms.permission:careers.delete');
                Route::post('/careers/{id}/toggle-status', [CareerController::class, 'toggleStatus'])->whereNumber('id')->name('cms.careers.toggle-status')->middleware('cms.permission:careers.edit');
                Route::post('/careers/reorder', [CareerController::class, 'reorder'])->name('cms.careers.reorder')->middleware('cms.permission:careers.edit');
                Route::post('/careers/bulk-action', [CareerController::class, 'bulkAction'])->name('cms.careers.bulk-action')->middleware('cms.permission:careers.edit');

                Route::get('/careers/departments', [CareerDepartmentController::class, 'index'])->name('cms.careers.departments.index');
                Route::get('/careers/departments/create', [CareerDepartmentController::class, 'create'])->name('cms.careers.departments.create')->middleware('cms.permission:careers.create');
                Route::post('/careers/departments', [CareerDepartmentController::class, 'store'])->name('cms.careers.departments.store')->middleware('cms.permission:careers.create');
                Route::get('/careers/departments/{id}/edit', [CareerDepartmentController::class, 'edit'])->name('cms.careers.departments.edit')->middleware('cms.permission:careers.edit');
                Route::put('/careers/departments/{id}', [CareerDepartmentController::class, 'update'])->name('cms.careers.departments.update')->middleware('cms.permission:careers.edit');
                Route::delete('/careers/departments/{id}', [CareerDepartmentController::class, 'destroy'])->name('cms.careers.departments.destroy')->middleware('cms.permission:careers.delete');
                Route::post('/careers/departments/{id}/toggle-status', [CareerDepartmentController::class, 'toggleStatus'])->name('cms.careers.departments.toggle-status')->middleware('cms.permission:careers.edit');
                Route::post('/careers/departments/reorder', [CareerDepartmentController::class, 'reorder'])->name('cms.careers.departments.reorder')->middleware('cms.permission:careers.edit');
                Route::post('/careers/departments/bulk-action', [CareerDepartmentController::class, 'bulkAction'])->name('cms.careers.departments.bulk-action')->middleware('cms.permission:careers.edit');

                Route::get('/careers/candidates', [CareerCandidateController::class, 'index'])->name('cms.careers.candidates.index');
                Route::get('/careers/candidates/export', [CareerCandidateController::class, 'export'])->name('cms.careers.candidates.export')->middleware('cms.permission:careers.export');
                Route::get('/careers/candidates/{id}', [CareerCandidateController::class, 'show'])->name('cms.careers.candidates.show')->middleware('cms.permission:careers.show');
                Route::delete('/careers/candidates/{id}', [CareerCandidateController::class, 'destroy'])->name('cms.careers.candidates.destroy')->middleware('cms.permission:careers.delete');
                Route::post('/careers/candidates/bulk-action', [CareerCandidateController::class, 'bulkAction'])->name('cms.careers.candidates.bulk-action')->middleware('cms.permission:careers.delete');
            });

            // Our Builders (section + items)
            Route::middleware(['cms.permission:our-builders.view'])->group(function () {
                Route::get('/our-builders', [OurBuilderController::class, 'index'])->name('cms.our-builders.index');
                Route::post('/our-builders/section', [OurBuilderController::class, 'updateSection'])->name('cms.our-builders.update-section')->middleware('cms.permission:our-builders.edit');

                Route::middleware(['cms.permission:our-builders.create'])->group(function () {
                    Route::get('/our-builders/create', [OurBuilderController::class, 'create'])->name('cms.our-builders.create');
                    Route::post('/our-builders', [OurBuilderController::class, 'store'])->name('cms.our-builders.store');
                });

                Route::middleware(['cms.permission:our-builders.edit'])->group(function () {
                    Route::get('/our-builders/{id}/edit', [OurBuilderController::class, 'edit'])->name('cms.our-builders.edit');
                    Route::put('/our-builders/{id}', [OurBuilderController::class, 'update'])->name('cms.our-builders.update');
                    Route::post('/our-builders/{id}/toggle-status', [OurBuilderController::class, 'toggleStatus'])->name('cms.our-builders.toggle-status');
                    Route::post('/our-builders/reorder', [OurBuilderController::class, 'reorder'])->name('cms.our-builders.reorder');
                });

                Route::middleware(['cms.permission:our-builders.delete'])->group(function () {
                    Route::delete('/our-builders/{id}', [OurBuilderController::class, 'destroy'])->name('cms.our-builders.destroy');
                    Route::post('/our-builders/bulk-action', [OurBuilderController::class, 'bulkAction'])->name('cms.our-builders.bulk-action');
                });
            });

            // Connect Us (section-only, incl. video)
            Route::middleware(['cms.permission:connect-us.view'])->group(function () {
                Route::get('/connect-us', [ConnectUsController::class, 'index'])->name('cms.connect-us.index');
                Route::post('/connect-us', [ConnectUsController::class, 'update'])->name('cms.connect-us.update')->middleware('cms.permission:connect-us.edit');
            });

            // Contact (section-only — home vs. contact-page title/description)
            Route::middleware(['cms.permission:contact-us.view'])->group(function () {
                Route::get('/contact-us', [ContactController::class, 'index'])->name('cms.contact-us.index');
                Route::post('/contact-us', [ContactController::class, 'update'])->name('cms.contact-us.update')->middleware('cms.permission:contact-us.edit');
            });

            // Luxury Projects ("Luxury Project" home section — header/CTA only, cards are real listings)
            Route::middleware(['cms.permission:luxury-projects.view'])->group(function () {
                Route::get('/luxury-projects', [LuxuryProjectController::class, 'index'])->name('cms.luxury-projects.index');
                Route::post('/luxury-projects', [LuxuryProjectController::class, 'update'])->name('cms.luxury-projects.update')->middleware('cms.permission:luxury-projects.edit');
            });

            // Popular Places ("Most Popular Properties Places" home section)
            Route::middleware(['cms.permission:popular-places.view'])->group(function () {
                Route::get('/popular-places', [PopularPlaceController::class, 'index'])->name('cms.popular-places.index');
                Route::post('/popular-places/section', [PopularPlaceController::class, 'updateSection'])->name('cms.popular-places.update-section')->middleware('cms.permission:popular-places.edit');

                Route::middleware(['cms.permission:popular-places.create'])->group(function () {
                    Route::get('/popular-places/create', [PopularPlaceController::class, 'create'])->name('cms.popular-places.create');
                    Route::post('/popular-places', [PopularPlaceController::class, 'store'])->name('cms.popular-places.store');
                });

                Route::middleware(['cms.permission:popular-places.edit'])->group(function () {
                    Route::get('/popular-places/{id}/edit', [PopularPlaceController::class, 'edit'])->name('cms.popular-places.edit');
                    Route::put('/popular-places/{id}', [PopularPlaceController::class, 'update'])->name('cms.popular-places.update');
                    Route::post('/popular-places/{id}/toggle-status', [PopularPlaceController::class, 'toggleStatus'])->name('cms.popular-places.toggle-status');
                    Route::post('/popular-places/reorder', [PopularPlaceController::class, 'reorder'])->name('cms.popular-places.reorder');
                });

                Route::middleware(['cms.permission:popular-places.delete'])->group(function () {
                    Route::delete('/popular-places/{id}', [PopularPlaceController::class, 'destroy'])->name('cms.popular-places.destroy');
                    Route::post('/popular-places/bulk-action', [PopularPlaceController::class, 'bulkAction'])->name('cms.popular-places.bulk-action');
                });
            });

            // Bell-icon notifications — every logged-in admin can read/mark their own
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('cms.notifications.read');
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('cms.notifications.read-all');

            Route::get('/portal-accounts/{portalUser}/documents/{field}', [\App\Http\Controllers\DocumentController::class, 'admin'])
                ->name('cms.portal-accounts.document')->middleware('cms.permission:portal-accounts.view');

            // Agents & Companies (portal account moderation)
            Route::middleware(['cms.permission:portal-accounts.view'])->group(function () {
                Route::get('/portal-accounts', [PortalUserController::class, 'index'])->name('cms.portal-accounts.index');
                Route::get('/portal-accounts-export', [PortalUserController::class, 'export'])->name('cms.portal-accounts.export');
                Route::get('/portal-accounts/{id}', [PortalUserController::class, 'show'])->name('cms.portal-accounts.show');
                Route::middleware(['cms.permission:portal-accounts.edit'])->group(function () {
                    Route::get('/portal-accounts-create', [PortalUserController::class, 'create'])->name('cms.portal-accounts.create');
                    Route::post('/portal-accounts', [PortalUserController::class, 'store'])->name('cms.portal-accounts.store');
                    Route::post('/portal-accounts/{id}/approve', [PortalUserController::class, 'approve'])->name('cms.portal-accounts.approve');
                    Route::post('/portal-accounts/{id}/reject', [PortalUserController::class, 'reject'])->name('cms.portal-accounts.reject');
                    Route::post('/portal-accounts/{id}/status', [PortalUserController::class, 'updateStatus'])->name('cms.portal-accounts.update-status');
                    Route::post('/portal-accounts/{id}/assign-plan', [PortalUserController::class, 'assignPlan'])->name('cms.portal-accounts.assign-plan');
                    Route::post('/portal-accounts/{id}/payment-status', [PortalUserController::class, 'updatePaymentStatus'])->name('cms.portal-accounts.update-payment-status');
                    Route::post('/portal-accounts/{id}/update', [PortalUserController::class, 'update'])->name('cms.portal-accounts.update');
                    Route::post('/portal-accounts/{id}/retranslate-bio', [PortalUserController::class, 'retranslateBio'])->name('cms.portal-accounts.retranslate-bio');
                    Route::post('/portal-accounts/{id}/reset-password', [PortalUserController::class, 'resetPassword'])->name('cms.portal-accounts.reset-password');
                    Route::post('/portal-accounts/{id}/toggle-active', [PortalUserController::class, 'toggleActive'])->name('cms.portal-accounts.toggle-active');
                    Route::post('/portal-accounts/{id}/reset-two-factor', [PortalUserController::class, 'resetTwoFactor'])->name('cms.portal-accounts.reset-two-factor');
                    Route::post('/portal-accounts/{id}/documents/{field}', [PortalUserController::class, 'uploadDocument'])->name('cms.portal-accounts.upload-document');
                    Route::delete('/portal-accounts/{id}/documents/{field}', [PortalUserController::class, 'removeDocument'])->name('cms.portal-accounts.remove-document');
                    Route::post('/portal-accounts/{id}/documents/{field}/status', [PortalUserController::class, 'updateDocumentStatus'])->name('cms.portal-accounts.update-document-status');
                    Route::post('/portal-accounts/{id}/request-info', [PortalUserController::class, 'requestInfo'])->name('cms.portal-accounts.request-info');
                    Route::post('/portal-accounts/bulk-approve', [PortalUserController::class, 'bulkApprove'])->name('cms.portal-accounts.bulk-approve');
                });
                Route::middleware(['cms.permission:portal-accounts.delete'])->group(function () {
                    Route::delete('/portal-accounts/{id}', [PortalUserController::class, 'destroy'])->name('cms.portal-accounts.destroy');
                    Route::post('/portal-accounts/bulk-delete', [PortalUserController::class, 'bulkDelete'])->name('cms.portal-accounts.bulk-delete');
                });

                // Agency ⇄ agent memberships awaiting approval, and active ones (suspend / remove).
                Route::get('/agency-agents', [\App\Http\Controllers\CmsKit\AgencyAgentController::class, 'index'])->name('cms.agency-agents.index');
                Route::middleware(['cms.permission:portal-accounts.edit'])->group(function () {
                    Route::post('/agency-agents/{id}/approve', [\App\Http\Controllers\CmsKit\AgencyAgentController::class, 'approve'])->name('cms.agency-agents.approve');
                    Route::post('/agency-agents/{id}/reject', [\App\Http\Controllers\CmsKit\AgencyAgentController::class, 'reject'])->name('cms.agency-agents.reject');
                    Route::post('/agency-agents/{id}/suspend', [\App\Http\Controllers\CmsKit\AgencyAgentController::class, 'suspend'])->name('cms.agency-agents.suspend');
                    Route::post('/agency-agents/{id}/reactivate', [\App\Http\Controllers\CmsKit\AgencyAgentController::class, 'reactivate'])->name('cms.agency-agents.reactivate');
                    Route::post('/agency-agents/{id}/remove', [\App\Http\Controllers\CmsKit\AgencyAgentController::class, 'remove'])->name('cms.agency-agents.remove');
                });

                Route::get('/plan-upgrade-requests', [PortalUserController::class, 'planUpgradeRequests'])->name('cms.portal-accounts.plan-upgrade-requests');
                Route::middleware(['cms.permission:portal-accounts.edit'])->group(function () {
                    Route::post('/plan-upgrade-requests/{id}/approve', [PortalUserController::class, 'approvePlanUpgradeRequest'])->name('cms.portal-accounts.plan-upgrade-requests.approve');
                    Route::post('/plan-upgrade-requests/{id}/reject', [PortalUserController::class, 'rejectPlanUpgradeRequest'])->name('cms.portal-accounts.plan-upgrade-requests.reject');
                });
            });

            // Plans (packages / pricing tiers assignable to Agents & Companies)
            Route::middleware(['cms.permission:plans.view'])->group(function () {
                Route::get('/plans', [PlanController::class, 'index'])->name('cms.plans.index');

                Route::middleware(['cms.permission:plans.create'])->group(function () {
                    Route::get('/plans/create', [PlanController::class, 'create'])->name('cms.plans.create');
                    Route::post('/plans', [PlanController::class, 'store'])->name('cms.plans.store');
                });

                Route::middleware(['cms.permission:plans.edit'])->group(function () {
                    Route::get('/plans/{id}/edit', [PlanController::class, 'edit'])->name('cms.plans.edit');
                    Route::put('/plans/{id}', [PlanController::class, 'update'])->name('cms.plans.update');
                    Route::post('/plans/{id}/toggle-status', [PlanController::class, 'toggleStatus'])->name('cms.plans.toggle-status');
                    Route::post('/plans/reorder', [PlanController::class, 'reorder'])->name('cms.plans.reorder');
                });

                Route::middleware(['cms.permission:plans.delete'])->group(function () {
                    Route::delete('/plans/{id}', [PlanController::class, 'destroy'])->name('cms.plans.destroy');
                    Route::post('/plans/bulk-action', [PlanController::class, 'bulkAction'])->name('cms.plans.bulk-action');
                });

                Route::get('/plans/{id}', [PlanController::class, 'show'])->name('cms.plans.show');
            });

            // Payments (every plan payment — Stripe card payments + manually recorded), under plans.view
            Route::middleware(['cms.permission:plans.view'])->group(function () {
                Route::get('/payments', [\App\Http\Controllers\CmsKit\PaymentController::class, 'index'])->name('cms.payments.index');
                Route::get('/payments/export', [\App\Http\Controllers\CmsKit\PaymentController::class, 'export'])->name('cms.payments.export');
                Route::get('/payments/{id}/invoice', [\App\Http\Controllers\CmsKit\PaymentController::class, 'show'])->name('cms.payments.show');
                Route::get('/payments/{id}/invoice.pdf', [\App\Http\Controllers\CmsKit\PaymentController::class, 'pdf'])->name('cms.payments.pdf');
            });

            // Support Tickets — raised by Agents/Companies from CRM Contact Us (see Crm\Support\TicketController)
            Route::middleware(['cms.permission:support-tickets.view'])->controller(\App\Http\Controllers\CmsKit\SupportTicketController::class)->group(function () {
                Route::get('/support-tickets', 'index')->name('cms.support-tickets.index');
                Route::get('/support-tickets/{ticket}', 'show')->name('cms.support-tickets.show');
                Route::get('/support-tickets/{ticket}/attachments/{message}', 'attachment')->name('cms.support-tickets.attachment')->whereNumber('message');
                Route::middleware(['cms.permission:support-tickets.edit'])->group(function () {
                    Route::post('/support-tickets/{ticket}/reply', 'reply')->name('cms.support-tickets.reply');
                    Route::put('/support-tickets/{ticket}', 'update')->name('cms.support-tickets.update');
                });
            });

            // Coupons (discount codes Agents/Companies can apply when requesting a paid plan)
            Route::middleware(['cms.permission:coupons.view'])->group(function () {
                Route::get('/coupons', [\App\Http\Controllers\CmsKit\CouponController::class, 'index'])->name('cms.coupons.index');

                Route::middleware(['cms.permission:coupons.create'])->group(function () {
                    Route::get('/coupons/create', [\App\Http\Controllers\CmsKit\CouponController::class, 'create'])->name('cms.coupons.create');
                    Route::post('/coupons', [\App\Http\Controllers\CmsKit\CouponController::class, 'store'])->name('cms.coupons.store');
                });

                Route::middleware(['cms.permission:coupons.edit'])->group(function () {
                    Route::get('/coupons/{id}/edit', [\App\Http\Controllers\CmsKit\CouponController::class, 'edit'])->name('cms.coupons.edit');
                    Route::put('/coupons/{id}', [\App\Http\Controllers\CmsKit\CouponController::class, 'update'])->name('cms.coupons.update');
                    Route::post('/coupons/{id}/toggle-status', [\App\Http\Controllers\CmsKit\CouponController::class, 'toggleStatus'])->name('cms.coupons.toggle-status');
                });

                Route::middleware(['cms.permission:coupons.delete'])->group(function () {
                    Route::delete('/coupons/{id}', [\App\Http\Controllers\CmsKit\CouponController::class, 'destroy'])->name('cms.coupons.destroy');
                });

                Route::get('/coupons/{id}', [\App\Http\Controllers\CmsKit\CouponController::class, 'show'])->name('cms.coupons.show');
            });

            // Ad Management (image/GIF banners shown at a named placement — placement is free-text until the frontend design is final)
            Route::middleware(['cms.permission:ads.view'])->group(function () {
                Route::get('/ads', [AdController::class, 'index'])->name('cms.ads.index');

                Route::middleware(['cms.permission:ads.create'])->group(function () {
                    Route::get('/ads/create', [AdController::class, 'create'])->name('cms.ads.create');
                    Route::post('/ads', [AdController::class, 'store'])->name('cms.ads.store');
                });

                Route::middleware(['cms.permission:ads.edit'])->group(function () {
                    Route::get('/ads/{id}/edit', [AdController::class, 'edit'])->name('cms.ads.edit');
                    Route::put('/ads/{id}', [AdController::class, 'update'])->name('cms.ads.update');
                    Route::post('/ads/{id}/toggle-status', [AdController::class, 'toggleStatus'])->name('cms.ads.toggle-status');
                    Route::post('/ads/reorder', [AdController::class, 'reorder'])->name('cms.ads.reorder');
                });

                Route::middleware(['cms.permission:ads.delete'])->group(function () {
                    Route::delete('/ads/{id}', [AdController::class, 'destroy'])->name('cms.ads.destroy');
                    Route::post('/ads/bulk-action', [AdController::class, 'bulkAction'])->name('cms.ads.bulk-action');
                });
            });

            // Landing Pages (template pages sharing the Blog field shape, or fully custom HTML/CSS)
            Route::middleware(['cms.permission:landing-pages.view'])->group(function () {
                Route::get('/landing-pages', [LandingPageController::class, 'index'])->name('cms.landing-pages.index');
                Route::get('/landing-pages/enquiries', [LandingPageController::class, 'enquiries'])->name('cms.landing-pages.enquiries');

                Route::middleware(['cms.permission:landing-pages.create'])->group(function () {
                    Route::get('/landing-pages/create', [LandingPageController::class, 'create'])->name('cms.landing-pages.create');
                    Route::post('/landing-pages', [LandingPageController::class, 'store'])->name('cms.landing-pages.store');
                });

                Route::middleware(['cms.permission:landing-pages.edit'])->group(function () {
                    Route::get('/landing-pages/{id}/edit', [LandingPageController::class, 'edit'])->name('cms.landing-pages.edit');
                    Route::put('/landing-pages/{id}', [LandingPageController::class, 'update'])->name('cms.landing-pages.update');
                    Route::post('/landing-pages/{id}/toggle-status', [LandingPageController::class, 'toggleStatus'])->name('cms.landing-pages.toggle-status');
                    Route::post('/landing-pages/reorder', [LandingPageController::class, 'reorder'])->name('cms.landing-pages.reorder');
                    Route::post('/landing-pages/upload-image', [LandingPageController::class, 'uploadContentImage'])->name('cms.landing-pages.upload-image');
                    Route::post('/landing-pages/discard-temp-images', [LandingPageController::class, 'discardTempImages'])->name('cms.landing-pages.discard-temp-images');
                    Route::post('/landing-pages/extract-text', [LandingPageController::class, 'extractText'])->name('cms.landing-pages.extract-text');
                    Route::post('/landing-pages/extract-images', [LandingPageController::class, 'extractImages'])->name('cms.landing-pages.extract-images');
                    Route::post('/landing-pages/preview', [LandingPageController::class, 'preview'])->name('cms.landing-pages.preview');
                });

                Route::middleware(['cms.permission:landing-pages.delete'])->group(function () {
                    Route::delete('/landing-pages/{id}', [LandingPageController::class, 'destroy'])->name('cms.landing-pages.destroy');
                    Route::post('/landing-pages/bulk-action', [LandingPageController::class, 'bulkAction'])->name('cms.landing-pages.bulk-action');
                });
            });

            // Overrides the vendor package's own /enquiries routes (same names/permissions, so nothing
            // else that calls route('cms.enquiries.*') needs to change) — the only difference is that
            // this app-level EnquiryController excludes landing-page submissions from the listing/export,
            // since those now live under their own Landing Pages > Enquiries page instead.
            Route::middleware(['cms.permission:enquiries.view'])->group(function () {
                if (config('cms-kit.common.modules.enquiries', true)) {
                    Route::get('/enquiries', [\App\Http\Controllers\CmsKit\EnquiryController::class, 'index'])->name('cms.enquiries.index');
                    Route::get('/enquiries/export', [\App\Http\Controllers\CmsKit\EnquiryController::class, 'export'])->name('cms.enquiries.export')->middleware('cms.permission:enquiries.export');
                    Route::get('/enquiries/{id}', [\App\Http\Controllers\CmsKit\EnquiryController::class, 'show'])->name('cms.enquiries.show')->middleware('cms.permission:enquiries.show');
                    Route::delete('/enquiries/{id}', [\App\Http\Controllers\CmsKit\EnquiryController::class, 'destroy'])->name('cms.enquiries.destroy')->middleware('cms.permission:enquiries.delete');
                    Route::post('/enquiries/bulk-action', [\App\Http\Controllers\CmsKit\EnquiryController::class, 'bulkAction'])->name('cms.enquiries.bulk-action')->middleware('cms.permission:enquiries.delete');
                }
            });

            // Property leads that came in with no agent/company assigned to the property (see
            // Crm\LeadCaptureController) — superadmin gets notified and picks them up here to
            // hand off to the right agent/company, since the portal CRM is otherwise scoped
            // entirely to each agent/company's own leads.
            Route::middleware(['cms.permission:enquiries.view'])->group(function () {
                Route::get('/unassigned-leads', [\App\Http\Controllers\CmsKit\LeadController::class, 'index'])->name('cms.unassigned-leads.index');
                Route::post('/unassigned-leads/{lead}/assign', [\App\Http\Controllers\CmsKit\LeadController::class, 'assign'])->name('cms.unassigned-leads.assign');
            });
        });
    });
});

// --- Agent/Company CRM web app (Vue, resources/js/crm) — replaces the /portal screens module by
// module; its data comes from /api/crm/* (routes/crm.php). Same sign-in as the portal: an
// agent/company portal session, or a Super Admin's CMS session for the global view.
// The two-factor set-up screen is where portal.2fa sends an account that has to set it up (or skip
// it, after sign-up) — so it's the one CRM page served without that check.
Route::get('/crm/security/two-factor', \App\Http\Controllers\Crm\CrmAppController::class)
    ->name('crm.two-factor')->middleware('portal.or.cms');
Route::get('/crm/{any?}', \App\Http\Controllers\Crm\CrmAppController::class)
    ->where('any', '.*')->name('crm.app')->middleware(['portal.or.cms', 'portal.2fa']);

// --- Agent/Company self-service portal (separate credentials from the CMS admin) ---
// Facebook Login comes back here (public — the one-time OAuth state identifies the Super Admin who started it).
Route::get('/integrations/facebook/callback', [\App\Http\Controllers\Crm\Integrations\FacebookController::class, 'callback'])
    ->name('integrations.facebook.callback')->middleware('throttle:30,1');

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware(['guest:portal'])->group(function () {
        Route::get('/register', [PortalAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [PortalAuthController::class, 'register'])->name('register.store')->middleware('throttle:portal-registration');
        Route::post('/verify-otp', [PortalAuthController::class, 'verifyOtp'])->name('verify-otp')->middleware('throttle:otp-verify');
        Route::post('/resend-otp', [PortalAuthController::class, 'resendOtp'])->name('resend-otp')->middleware('throttle:otp-verify');
        Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [PortalAuthController::class, 'login'])->name('login.store')->middleware('throttle:admin-login');
        // Login steps after the password: emailed code for a new device, then the authenticator-app code.
        Route::post('/login/verify-email', [PortalAuthController::class, 'verifyLoginEmail'])->name('login.verify-email')->middleware('throttle:otp-verify');
        Route::post('/login/resend-email', [PortalAuthController::class, 'resendLoginEmail'])->name('login.resend-email')->middleware('throttle:otp-verify');
        Route::post('/login/two-factor', [PortalAuthController::class, 'verifyLoginTwoFactor'])->name('login.two-factor')->middleware('throttle:otp-verify');

        // Set-password link for an agent account an agency created (emailed on admin approval).
        Route::get('/agent-setup/{agent}', [\App\Http\Controllers\Portal\AgentAccountSetupController::class, 'show'])->name('agent-setup.show')->middleware('signed');
        Route::post('/agent-setup/{agent}', [\App\Http\Controllers\Portal\AgentAccountSetupController::class, 'store'])->name('agent-setup.store')->middleware(['signed', 'throttle:otp-verify']);
    });

    Route::middleware(['auth:portal', 'portal.2fa'])->group(function () {
        Route::post('/logout', [PortalAuthController::class, 'logout'])->name('logout');

        // My Profile, Security (two-factor) and Plans moved to the CRM app (/crm/profile, /crm/security,
        // /crm/plans — data from routes/crm.php). These names stay as redirects for links already sent
        // (KYC / document / renewal emails, notifications, invoice emails, Stripe return URLs).
        Route::controller(\App\Http\Controllers\Crm\LegacyRedirectController::class)->group(function () {
            Route::get('/security', 'security')->name('security');
            // Reachable while portal.2fa holds the account back (EnsurePortalTwoFactor::ALLOWED_ROUTES).
            Route::get('/two-factor/setup', 'twoFactorSetup')->name('two-factor.setup');
            Route::get('/profile', 'profile')->name('profile.edit');
        });

        // Bell-icon notifications
        Route::post('/notifications/{id}/read', [PortalNotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [PortalNotificationController::class, 'markAllRead'])->name('notifications.read-all');

        // Module help guides (top bar "!" icon) — remember which ones opened by themselves already.
        Route::post('/help/{topic}/seen', [\App\Http\Controllers\Portal\PortalHelpController::class, 'seen'])->name('help.seen');

        Route::controller(\App\Http\Controllers\Crm\LegacyRedirectController::class)->group(function () {
            Route::get('/plans', 'plans')->name('plans.index');
            Route::get('/plans/checkout', 'planCheckout')->name('plans.checkout');
            // Stripe-hosted checkouts started before the move still come back here with ?session_id=.
            Route::get('/plans/checkout/success', 'plans')->name('plans.checkout.success');
            Route::get('/plans/payments', 'planPayments')->name('plans.payments');
            Route::get('/plans/payments/{id}/invoice', 'planInvoice')->name('plans.payments.show')->whereNumber('id');
            Route::get('/plans/payments/{id}/invoice.pdf', 'planInvoice')->name('plans.payments.pdf')->whereNumber('id');
        });

        // My Agency — an agent's own agency membership: invitations, join requests, leaving.
        Route::prefix('agency')->name('agency.')->controller(\App\Http\Controllers\Portal\AgencyController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/search', 'searchAgencies')->name('search')->middleware('throttle:120,1');
            Route::post('/request', 'requestToJoin')->name('request')->middleware('portal.approved');
            Route::post('/requests/{id}/cancel', 'cancelRequest')->name('requests.cancel');
            Route::post('/invitations/{id}/accept', 'acceptInvitation')->name('invitations.accept')->middleware('portal.approved');
            Route::post('/invitations/{id}/decline', 'declineInvitation')->name('invitations.decline');
            Route::post('/leave', 'leave')->name('leave');
            Route::post('/properties/{id}/transfer', 'transferProperty')->name('properties.transfer')->middleware('portal.approved');
        });

        // Contact Us moved to the CRM app (/crm/contact — data from routes/crm.php, admin side is
        // cms.support-tickets.*). These names stay as redirects for links already sent (ticket notifications).
        Route::prefix('contact')->name('contact.')->controller(\App\Http\Controllers\Crm\LegacyRedirectController::class)->group(function () {
            Route::get('/', 'contact')->name('index');
            Route::get('/tickets/create', 'contactCreate')->name('create');
            Route::get('/tickets/{ticket}', 'contactTicket')->name('show')->whereNumber('ticket');
        });
    });

    // Shared by Super Admin (global view) and Agent/Company (own-data view) —
    // either session is accepted; controllers scope data per guard.
    Route::middleware(['portal.or.cms', 'portal.2fa'])->group(function () {
        // Dashboard moved to the CRM app (/crm/dashboard); the name stays as a redirect for existing links.
        Route::get('/dashboard', [\App\Http\Controllers\Crm\LegacyRedirectController::class, 'dashboard'])->name('dashboard');

        // Properties / Commercial moved to the CRM app (/crm/properties, /crm/commercial — data from
        // routes/crm.php). These names stay as redirects so existing links (emails, notifications,
        // CMS dashboard, other portal screens) open the new screens; the query string is carried over.
        Route::controller(\App\Http\Controllers\Crm\LegacyRedirectController::class)->group(function () {
            Route::get('/properties', 'properties')->name('properties.index');
            Route::get('/properties/create', 'propertyCreate')->name('properties.create');
            Route::get('/properties/{id}', 'property')->name('properties.show')->whereNumber('id');
            Route::get('/properties/{id}/edit', 'propertyEdit')->name('properties.edit')->whereNumber('id');
            Route::get('/commercial', 'commercial')->name('commercial.index');
            Route::get('/commercial/create', 'commercialCreate')->name('commercial.create');
            Route::get('/commercial/{id}', 'property')->name('commercial.show')->whereNumber('id');
            Route::get('/commercial/{id}/edit', 'propertyEdit')->name('commercial.edit')->whereNumber('id');
        });

        // Sold Listings, Listing Permits and Premium moved to the CRM app (/crm/sold-listings,
        // /crm/listing-permits, /crm/premium — data from routes/crm.php). These names stay as redirects
        // for links already sent (listing-review emails and notifications) and other screens.
        Route::controller(\App\Http\Controllers\Crm\LegacyRedirectController::class)->group(function () {
            Route::get('/sold-listings', 'soldListings')->name('sold.index');
            Route::get('/listing-approvals', 'listingPermits')->name('listing-approvals.index');
            Route::get('/listing-approvals/{id}', 'listingPermit')->name('listing-approvals.show')->whereNumber('id');
            Route::get('/featured', 'premium')->name('featured.index');
        });

        // Marketing Properties, Agents, Nearby Places and Listing Settings (watermark) moved to the CRM
        // app (/crm/marketing-properties, /crm/agents, /crm/nearby-places, /crm/listing-settings — data
        // from routes/crm.php). These names stay as redirects for links already sent (agency membership
        // emails and notifications) and other screens.
        Route::controller(\App\Http\Controllers\Crm\LegacyRedirectController::class)->group(function () {
            Route::get('/marketing-properties', 'marketingProperties')->name('marketing.index');
            Route::get('/agents', 'agents')->name('agents.index');
            Route::get('/agents/create', 'agentCreate')->name('agents.create');
            Route::get('/agents/{id}', 'agent')->name('agents.show')->whereNumber('id');
            Route::get('/nearby-places', 'nearbyPlaces')->name('nearby-places.index');
            Route::get('/nearby-places/create', 'nearbyPlaceCreate')->name('nearby-places.create');
            Route::get('/nearby-places/{id}/edit', 'nearbyPlaceEdit')->name('nearby-places.edit')->whereNumber('id');
            Route::get('/listing-settings/watermark', 'watermark')->name('watermark.edit');
        });

        // Real (server-side) gating for Leads/Master/Reports — previously only cosmetically
        // "locked" in the sidebar with no enforcement at all.
        Route::prefix('crm')->name('crm.')->middleware('portal.approved')->group(function () {
            // Leads moved to the CRM app (/crm/leads, data from routes/crm.php). These names stay as
            // redirects so existing links (emails, notifications, other screens) open the new screens.
            Route::get('/leads', [\App\Http\Controllers\Crm\LegacyRedirectController::class, 'leads'])->name('leads.index');
            Route::get('/leads/trashed', [\App\Http\Controllers\Crm\LegacyRedirectController::class, 'trashedLeads'])->name('leads.trashed');
            Route::get('/leads/{id}', [\App\Http\Controllers\Crm\LegacyRedirectController::class, 'lead'])->name('leads.show')->whereNumber('id');

            // Website Leads moved to the CRM app (/crm/website-leads, data from routes/crm.php).
            Route::get('/website-leads', [\App\Http\Controllers\Crm\LegacyRedirectController::class, 'websiteLeads'])->name('website-leads.index');
            Route::get('/website-leads/{id}', [\App\Http\Controllers\Crm\LegacyRedirectController::class, 'websiteLead'])->name('website-leads.show')->whereNumber('id');

            // Lead Insights — website activity of the viewer's own leads (property views, time spent, AI chats…).
            Route::get('/lead-insights', [\App\Http\Controllers\Crm\LegacyRedirectController::class, 'leadInsights'])->name('lead-insights.index');

            // Integrations moved to the CRM app (/crm/integrations/*). These names stay as redirects for
            // links already sent (Facebook reconnect emails, Property Finder notifications).
            Route::controller(\App\Http\Controllers\Crm\LegacyRedirectController::class)->prefix('integrations')->name('integrations.')->group(function () {
                Route::get('/', 'integrations')->name('index');
                Route::get('/facebook', 'facebookIntegration')->name('facebook');
                Route::get('/property-finder', 'propertyFinder')->name('property-finder.show');
                Route::get('/property-finder/review', 'propertyFinderReview')->name('property-finder.review');
            });

            // Master (Stage / Tag / Source / Property Options) moved to the CRM app (routes/crm.php,
            // /crm/master/*). This name stays as a redirect (?list=… carried over) for existing links.
            Route::get('/master/property-options', [\App\Http\Controllers\Crm\LegacyRedirectController::class, 'propertyOptions'])->name('master.property-options.index');

            // Reports moved to the CRM app (/crm/reports/*); the name stays as a redirect for existing links.
            Route::get('/reports/{report?}', [\App\Http\Controllers\Crm\LegacyRedirectController::class, 'reports'])->name('reports.index')->whereIn('report', ['leads', 'properties', 'sales', 'revenue', 'agents']);
        });
    });
});

Route::prefix(config('cms-kit.common.auth.prefix', 'admin'))->middleware(['web', 'cms.auth'])->group(function () {
    Route::post('/sitemap/generate', [\App\Http\Controllers\CmsKit\SitemapController::class, 'generate'])->name('cms.sitemap.generate')->middleware('cms.permission:sitemap.edit');
    Route::post('/seo/llms-txt/generate', [\App\Http\Controllers\CmsKit\LlmsTxtController::class, 'generate'])->name('cms.llms-txt.generate')->middleware('cms.permission:llms-txt.edit');
    // Editing a redirect goes through the app controller's loop check (adding already does, via
    // the SafeUrlRedirectService binding) — the package version writes the row unchecked.
    Route::put('/seo/url-redirects/{url_redirect}', [\App\Http\Controllers\CmsKit\UrlRedirectController::class, 'update'])->name('cms.url-redirects.update')->middleware(['cms.permission:url-redirects.view', 'cms.permission:url-redirects.edit']);
});

// Public — static marketing/front-end pages, served by the Vue SPA (resources/js/router).
// Registered before the /{slug} landing-page catch-all below so these always win.
// Pages with real SEO value are routed through SpaController, which injects a resolved
// <title>/meta tags into the same welcome.blade.php shell before returning it — everything
// else (auth/profile/thank-you) stays a bare Route::view since there's nothing to index.
Route::get('/about', [SpaController::class, 'staticPage'])->defaults('pageKey', 'about');
Route::get('/commercial', [SpaController::class, 'staticPage'])->defaults('pageKey', 'commercial');
Route::get('/commercial/map', [SpaController::class, 'staticPage'])->defaults('pageKey', 'commercial');
Route::get('/agents', [SpaController::class, 'staticPage'])->defaults('pageKey', 'agents');
Route::get('/agent-details/{slug}', [SpaController::class, 'agentDetails']);
Route::view('/agent-login', 'welcome');
Route::view('/agent-signup', 'welcome');
Route::get('/agencies', [SpaController::class, 'staticPage'])->defaults('pageKey', 'agencies');
Route::get('/agency-details/{slug}', [SpaController::class, 'agencyDetails']);
Route::view('/agency-login', 'welcome');
Route::view('/agency-signup', 'welcome');
Route::get('/blogs', [SpaController::class, 'staticPage'])->defaults('pageKey', 'blog');
Route::get('/blog-details/{slug}', [SpaController::class, 'blogDetails']);
Route::get('/market-insights', [SpaController::class, 'staticPage'])->defaults('pageKey', 'market-insights');
Route::get('/market-insights/{slug}', [SpaController::class, 'marketInsightDetails']);
Route::get('/careers', [SpaController::class, 'staticPage'])->defaults('pageKey', 'careers');
Route::get('/careers/{slug}', [SpaController::class, 'careerDetails']);
Route::get('/contact', [SpaController::class, 'staticPage'])->defaults('pageKey', 'contact');
Route::view('/login', 'welcome');
Route::view('/signup', 'welcome');
Route::view('/verify-email', 'welcome');
Route::view('/forgot-password', 'welcome');
Route::view('/reset-password', 'welcome');
Route::view('/profile', 'welcome');
Route::get('/properties', [SpaController::class, 'staticPage'])->defaults('pageKey', 'properties');
// Map view of the same listings (same filters, same SEO page) — see PropertyMap.vue.
Route::get('/properties/map', [SpaController::class, 'staticPage'])->defaults('pageKey', 'properties');
Route::get('/premium-properties', [SpaController::class, 'staticPage'])->defaults('pageKey', 'premium-properties');
Route::get('/premium-properties/map', [SpaController::class, 'staticPage'])->defaults('pageKey', 'premium-properties');
// Super Admin's Marketing Properties list — the home "Realty Property" section's view-all page.
Route::get('/marketing-properties', [SpaController::class, 'staticPage'])->defaults('pageKey', 'marketing-properties');
Route::get('/marketing-properties/map', [SpaController::class, 'staticPage'])->defaults('pageKey', 'marketing-properties');
Route::get('/property-details/{slug}', [SpaController::class, 'propertyDetails']);
Route::get('/terms-and-conditions', [SpaController::class, 'staticPage'])->defaults('pageKey', 'terms');
Route::get('/privacy-policy', [SpaController::class, 'staticPage'])->defaults('pageKey', 'privacy');
Route::get('/security-policy', [SpaController::class, 'staticPage'])->defaults('pageKey', 'security');
Route::get('/cookie-settings', [SpaController::class, 'staticPage'])->defaults('pageKey', 'cookie');
Route::view('/thank-you', 'welcome');

// Public, unauthenticated — captures any <form> submission on a landing page (see
// LandingPageController::rewireForms(), which points every form at this URL automatically).
Route::post('/{slug}/enquiry', [\App\Http\Controllers\LandingPageEnquiryController::class, 'store'])
    ->name('landing-pages.enquiry.store')
    ->middleware('throttle:landing-page-enquiry');

// Public — renders a published Landing Page by its slug. Registered LAST: it's a single-segment
// catch-all, so every more specific route above (/, /api/*, /leads/capture, /admin/*, /portal/*)
// must always get first chance to match. The (?!...) guard is a belt-and-braces exclusion of the
// app's other top-level path segments, in case any of them is ever reached without a deeper segment.
Route::get('/{slug}', [\App\Http\Controllers\LandingPageController::class, 'show'])
    ->where('slug', '^(?!(admin|portal|crm|api|storage|about|commercial|agents|agent-details|agent-login|agent-signup|agencies|agency-details|agency-login|agency-signup|blogs|blog-details|market-insights|careers|contact|login|signup|verify-email|forgot-password|reset-password|profile|properties|premium-properties|marketing-properties|property-details|terms-and-conditions|privacy-policy|security-policy|cookie-settings|thank-you)$).+$')
    ->name('landing-pages.show');

// Anything no route above claims (e.g. /some/unknown/page) — the site's own 404 page, served with a
// real 404 status and the normal web middleware, instead of Laravel's bare "404 | Not Found" screen.
Route::fallback([SpaController::class, 'missing']);
