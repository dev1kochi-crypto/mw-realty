<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\PropertyFilterController;
use App\Http\Controllers\PortalUserController;
use App\Http\Controllers\CmsKit\PostPropertyStepController;
use App\Http\Controllers\CmsKit\PopularPlaceController;
use App\Http\Controllers\CmsKit\LuxuryProjectController;
use App\Http\Controllers\CmsKit\AboutUsController;
use App\Http\Controllers\CmsKit\MarketTrendController;
use App\Http\Controllers\CmsKit\WhyChooseUsController;
use App\Http\Controllers\CmsKit\OurBuilderController;
use App\Http\Controllers\CmsKit\ConnectUsController;
use App\Http\Controllers\CmsKit\CommunityController;
use App\Http\Controllers\CmsKit\ContactController;
use App\Http\Controllers\CmsKit\FindPropertyController;
use App\Http\Controllers\CmsKit\PlanController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalPropertyController;
use App\Http\Controllers\Portal\PortalEnquiryController;

Route::get('/', function () {
    return view('welcome');
});

// Public, read-only — consumed by the frontend (home banner + listing page) search filter bar.
Route::get('/api/property-filters', [PropertyFilterController::class, 'index']);

Route::middleware(['web'])->group(function () {
    Route::prefix(config('cms-kit.common.auth.prefix', 'admin'))->group(function () {
        Route::middleware(['cms.auth'])->group(function () {

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

            // Agents & Companies (portal account moderation)
            Route::middleware(['cms.permission:portal-accounts.view'])->group(function () {
                Route::get('/portal-accounts', [PortalUserController::class, 'index'])->name('cms.portal-accounts.index');
                Route::middleware(['cms.permission:portal-accounts.edit'])->group(function () {
                    Route::post('/portal-accounts/{id}/approve', [PortalUserController::class, 'approve'])->name('cms.portal-accounts.approve');
                    Route::post('/portal-accounts/{id}/reject', [PortalUserController::class, 'reject'])->name('cms.portal-accounts.reject');
                    Route::post('/portal-accounts/{id}/assign-plan', [PortalUserController::class, 'assignPlan'])->name('cms.portal-accounts.assign-plan');
                });
                Route::delete('/portal-accounts/{id}', [PortalUserController::class, 'destroy'])->name('cms.portal-accounts.destroy')->middleware('cms.permission:portal-accounts.delete');
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
                });
            });
        });
    });
});

// --- Agent/Company self-service portal (separate credentials from the CMS admin) ---
Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware(['guest:portal'])->group(function () {
        Route::get('/register', [PortalAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [PortalAuthController::class, 'register']);
        Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [PortalAuthController::class, 'login']);
    });

    Route::middleware(['auth:portal'])->group(function () {
        Route::post('/logout', [PortalAuthController::class, 'logout'])->name('logout');
    });

    // Shared by Super Admin (global view) and Agent/Company (own-data view) —
    // either session is accepted; controllers scope data per guard.
    Route::middleware(['portal.or.cms'])->group(function () {
        Route::get('/dashboard', [PortalDashboardController::class, 'index'])->name('dashboard');

        Route::get('/properties', [PortalPropertyController::class, 'index'])->name('properties.index');
        Route::get('/properties/create', [PortalPropertyController::class, 'create'])->name('properties.create');
        Route::post('/properties', [PortalPropertyController::class, 'store'])->name('properties.store');
        Route::get('/properties/{id}/edit', [PortalPropertyController::class, 'edit'])->name('properties.edit');
        Route::put('/properties/{id}', [PortalPropertyController::class, 'update'])->name('properties.update');
        Route::delete('/properties/{id}', [PortalPropertyController::class, 'destroy'])->name('properties.destroy');
        Route::delete('/properties/{propertyId}/images/{imageId}', [PortalPropertyController::class, 'destroyImage'])->name('properties.images.destroy');
        Route::post('/properties/{id}/toggle-status', [PortalPropertyController::class, 'toggleStatus'])->name('properties.toggle-status');

        Route::get('/crm', [PortalEnquiryController::class, 'index'])->name('crm.index');
        Route::get('/crm/{id}', [PortalEnquiryController::class, 'show'])->name('crm.show');
        Route::put('/crm/{id}', [PortalEnquiryController::class, 'update'])->name('crm.update');
    });
});
