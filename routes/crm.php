<?php

use App\Http\Controllers\Crm\Account\NotificationController;
use App\Http\Controllers\Crm\Account\PlanController;
use App\Http\Controllers\Crm\Account\ProfileController;
use App\Http\Controllers\Crm\Account\TwoFactorController;
use App\Http\Controllers\Crm\Agents\AgentController;
use App\Http\Controllers\Crm\Auth\AuthController;
use App\Http\Controllers\Crm\Dashboard\DashboardController;
use App\Http\Controllers\Crm\HelpController;
use App\Http\Controllers\Crm\Integrations\FacebookController;
use App\Http\Controllers\Crm\Integrations\IntegrationController;
use App\Http\Controllers\Crm\Integrations\PropertyFinderController;
use App\Http\Controllers\Crm\Leads\LeadController;
use App\Http\Controllers\Crm\Leads\LeadImportController;
use App\Http\Controllers\Crm\Leads\LeadInsightsController;
use App\Http\Controllers\Crm\Leads\LeadNoteController;
use App\Http\Controllers\Crm\ListingSettings\WatermarkController;
use App\Http\Controllers\Crm\Marketing\MarketingPropertyController;
use App\Http\Controllers\Crm\Masters\LinkedLeadsController;
use App\Http\Controllers\Crm\Masters\PropertyOptionController;
use App\Http\Controllers\Crm\Masters\SourceController;
use App\Http\Controllers\Crm\Masters\StageController;
use App\Http\Controllers\Crm\Masters\TagController;
use App\Http\Controllers\Crm\NavigationController;
use App\Http\Controllers\Crm\NearbyPlaces\NearbyPlaceController;
use App\Http\Controllers\Crm\Properties\ListingPermitController;
use App\Http\Controllers\Crm\Properties\PremiumController;
use App\Http\Controllers\Crm\Properties\PropertyController;
use App\Http\Controllers\Crm\Properties\SoldListingController;
use App\Http\Controllers\Crm\Properties\PropertyFeatureController;
use App\Http\Controllers\Crm\Properties\PropertyFormController;
use App\Http\Controllers\Crm\Properties\PropertyGalleryController;
use App\Http\Controllers\Crm\Properties\PropertyInsightsController;
use App\Http\Controllers\Crm\Properties\PropertySaleController;
use App\Http\Controllers\Crm\Reports\ReportController;
use App\Http\Controllers\Crm\Support\TicketController;
use App\Http\Controllers\Crm\WebsiteLeads\WebsiteLeadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent / Company CRM API — /api/crm/*, route names crm.api.*
|--------------------------------------------------------------------------
| Used by the CRM mobile app (Bearer token from POST /api/crm/auth/login) and the /crm web
| app (the browser's portal session, or a Super Admin's CMS session for the global view).
| Mounted from routes/api.php. Modules move here from routes/web.php's /portal group one at a
| time — once a module's Vue screens are done, its old Portal controller, Blade views and
| /portal routes are deleted.
*/

Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function () {
    Route::post('/login', 'login')->name('login')->middleware('throttle:admin-login');
    Route::post('/login/verify-email', 'verifyEmail')->name('login.verify-email')->middleware('throttle:otp-verify');
    Route::post('/login/resend-email', 'resendEmail')->name('login.resend-email')->middleware('throttle:otp-verify');
    Route::post('/login/two-factor', 'verifyTwoFactor')->name('login.two-factor')->middleware('throttle:otp-verify');
});

Route::middleware('crm.auth')->group(function () {
    // Reachable before 2FA set-up / KYC approval, so the app can tell the user what's missing.
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/navigation', NavigationController::class)->name('navigation');
    Route::post('/help/seen', [HelpController::class, 'seen'])->name('help.seen');
    Route::get('/help/{topic}', HelpController::class)->name('help')->where('topic', '[a-z0-9-]+');

    // Bell icon.
    Route::prefix('notifications')->name('notifications.')->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/read-all', 'markAllRead')->name('read-all');
        Route::post('/{id}/read', 'markRead')->name('read');
    });

    // Two-factor set-up has to be reachable while portal.2fa is still holding the account back.
    Route::prefix('account/two-factor')->name('account.two-factor.')->controller(TwoFactorController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/setup', 'setup')->name('setup')->middleware('throttle:otp-verify');
        Route::post('/confirm', 'confirm')->name('confirm')->middleware('throttle:otp-verify');
        Route::post('/skip', 'skip')->name('skip');
        Route::post('/recovery-codes', 'regenerateRecoveryCodes')->name('recovery-codes')->middleware('throttle:otp-verify');
        Route::post('/disable', 'disable')->name('disable')->middleware('throttle:otp-verify');
        Route::post('/enforce', 'enforce')->name('enforce');
        Route::post('/forget-devices', 'forgetDevices')->name('forget-devices');
    });

    Route::middleware('portal.2fa')->group(function () {
        // My Profile — reachable before KYC approval: completing / fixing it is what unblocks approval.
        Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/', 'update')->name('update');
            Route::post('/avatar', 'uploadAvatar')->name('avatar.upload');
            Route::delete('/avatar', 'removeAvatar')->name('avatar.remove');
            Route::get('/brokerages', 'brokerages')->name('brokerages')->middleware('throttle:120,1');
            Route::get('/documents/{field}', 'document')->name('document');
            Route::post('/documents/{field}', 'uploadDocument')->name('document.upload');
            Route::delete('/documents/{field}', 'removeDocument')->name('document.remove');
            Route::post('/submit', 'submitForApproval')->name('submit');
            Route::post('/email/request', 'requestEmailChange')->name('email.request')->middleware('throttle:otp-verify');
            Route::post('/email/verify', 'verifyEmailChange')->name('email.verify')->middleware('throttle:otp-verify');
        });

        // Plans & billing. Choosing / buying a plan unlocks with KYC approval; payment history,
        // invoices and managing an existing subscription stay reachable regardless.
        Route::prefix('plans')->name('plans.')->controller(PlanController::class)->group(function () {
            Route::middleware('portal.approved')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('/coupon-check', 'checkCoupon')->name('coupon-check')->middleware('throttle:20,1');
                Route::post('/change-preview', 'previewChange')->name('change-preview');
                Route::post('/subscription/keep-plan', 'keepCurrentPlan')->name('subscription.keep-plan');
                Route::get('/checkout', 'checkout')->name('checkout');
                Route::post('/checkout/intent', 'checkoutIntent')->name('checkout.intent')->middleware('throttle:10,1');
                Route::post('/checkout/complete', 'checkoutComplete')->name('checkout.complete');
            });
            Route::post('/checkout/session', 'checkoutSession')->name('checkout.session');
            Route::post('/subscription/cancel', 'cancelSubscription')->name('subscription.cancel');
            Route::post('/subscription/resume', 'resumeSubscription')->name('subscription.resume');
            Route::post('/billing-portal', 'billingPortal')->name('billing-portal');
            Route::get('/payments', 'payments')->name('payments');
            Route::get('/payments/{id}/invoice', 'invoice')->name('invoice')->whereNumber('id');
            Route::get('/payments/{id}/invoice.pdf', 'invoicePdf')->name('invoice.pdf')->whereNumber('id');
        });

        // Dashboard — reachable before KYC approval, like the portal's.
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Leads — approved accounts only, like the portal's CRM menu.
        Route::prefix('leads')->name('leads.')->middleware('portal.approved')->group(function () {
            Route::controller(LeadController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/meta', 'meta')->name('meta');
                Route::put('/table-columns', 'updateTableColumns')->name('table-columns');
                Route::get('/options/{type}', 'masterOptions')->name('options')->whereIn('type', ['stages', 'sources', 'tags']);
                Route::get('/owner-options', 'ownerOptions')->name('owner-options');
                Route::post('/bulk/stage', 'bulkStage')->name('bulk.stage');
                Route::post('/bulk/tags', 'bulkTags')->name('bulk.tags');
                Route::post('/bulk/delete', 'bulkDestroy')->name('bulk.delete');
                Route::post('/export', 'export')->name('export');
                Route::post('/distribute', 'distributeUnassigned')->name('distribute');
                Route::get('/assignment-settings', 'assignmentSettings')->name('assignment-settings');
                Route::put('/assignment-settings', 'updateAssignmentSettings')->name('assignment-settings.update');
                Route::get('/trashed', 'trashed')->name('trashed');
                Route::post('/trashed/bulk', 'bulkTrashed')->name('trashed.bulk');
                Route::post('/trashed/{id}/restore', 'restore')->name('restore')->whereNumber('id');
                Route::delete('/trashed/{id}', 'forceDestroy')->name('force-delete')->whereNumber('id');

                Route::get('/{id}', 'show')->name('show')->whereNumber('id');
                Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                Route::patch('/{id}', 'updateFields')->name('fields')->whereNumber('id');
                Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
                Route::put('/{id}/stage', 'updateStage')->name('stage')->whereNumber('id');
                Route::put('/{id}/tags', 'syncTags')->name('tags')->whereNumber('id');
                Route::post('/{id}/assign', 'assign')->name('assign')->whereNumber('id');
                Route::get('/{id}/insights/summary', 'insightsSummary')->name('insights.summary')->whereNumber('id');
                Route::get('/{id}/insights', 'insights')->name('insights')->whereNumber('id')->middleware('throttle:120,1');
                Route::post('/{id}/contacts', 'addContact')->name('contacts.store')->whereNumber('id');
                Route::put('/{id}/contacts/{contactId}/primary', 'setPrimaryContact')->name('contacts.primary')->whereNumber(['id', 'contactId']);
                Route::delete('/{id}/contacts/{contactId}', 'removeContact')->name('contacts.destroy')->whereNumber(['id', 'contactId']);
            });
            Route::post('/{id}/notes', [LeadNoteController::class, 'store'])->name('notes.store')->whereNumber('id');

            Route::controller(LeadImportController::class)->prefix('imports')->name('imports.')->group(function () {
                Route::post('/', 'store')->name('store');
                Route::get('/template', 'template')->name('template');
                Route::get('/facebook-sample', 'facebookSample')->name('facebook-sample');
                Route::get('/{leadImport}', 'status')->name('status')->middleware('throttle:120,1');
                Route::get('/{leadImport}/result', 'result')->name('result');
            });
        });

        Route::get('/lead-insights', [LeadInsightsController::class, 'index'])->name('lead-insights.index')->middleware('portal.approved');

        // Website Leads — AI chat / form / customer-account visitors and the pool not routed to an
        // agency / agent yet. Super Admin only (checked in the controller).
        Route::prefix('website-leads')->name('website-leads.')->controller(WebsiteLeadController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/transfer-targets', 'targets')->name('targets')->middleware('throttle:120,1');
            Route::post('/transfer', 'transfer')->name('transfer');
            Route::get('/{websiteLead}', 'show')->name('show')->whereNumber('websiteLead');
            Route::get('/{websiteLead}/insights/summary', 'insightsSummary')->name('insights.summary')->whereNumber('websiteLead');
            Route::get('/{websiteLead}/insights', 'insights')->name('insights')->whereNumber('websiteLead')->middleware('throttle:120,1');
        });

        // Properties + Commercial — one set of endpoints; index / create / store / reorder take
        // `segment` (residential | commercial), every per-listing action works on either.
        Route::prefix('properties')->name('properties.')->middleware('portal.approved')->group(function () {
            Route::controller(PropertyController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::post('/bulk-action', 'bulkAction')->name('bulk-action');
                Route::post('/reorder', 'reorder')->name('reorder');
                Route::get('/{id}', 'show')->name('show')->whereNumber('id');
                Route::get('/{id}/edit', 'edit')->name('edit')->whereNumber('id');
                Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
                Route::post('/{id}/toggle-status', 'toggleStatus')->name('toggle-status')->whereNumber('id');
                Route::post('/{id}/move', 'move')->name('move')->whereNumber('id');
            });
            Route::controller(PropertyFormController::class)->group(function () {
                Route::get('/agent-options', 'agentOptions')->name('agent-options')->middleware('throttle:120,1');
                Route::get('/nearby-places', 'nearbyPlaces')->name('nearby-places');
                Route::post('/translate', 'translate')->name('translate')->middleware('throttle:60,1');
                Route::post('/permit/validate', 'validatePermit')->name('permit.validate')->middleware('throttle:30,1');
            });
            Route::get('/{id}/insights', PropertyInsightsController::class)->name('insights')->whereNumber('id')->middleware('throttle:120,1');
            Route::controller(PropertyFeatureController::class)->prefix('/{id}/feature')->name('feature.')->whereNumber('id')->group(function () {
                Route::post('/', 'store')->name('store');
                Route::put('/', 'update')->name('update');
                Route::delete('/', 'destroy')->name('destroy');
            });
            Route::controller(PropertyGalleryController::class)->prefix('/{id}/images')->name('images.')->whereNumber('id')->group(function () {
                Route::delete('/', 'destroyAll')->name('destroy-all');
                Route::post('/reorder', 'reorder')->name('reorder');
                Route::delete('/{number}', 'destroy')->name('destroy')->whereNumber('number');
            });
            Route::controller(PropertySaleController::class)->group(function () {
                Route::get('/{id}/sale-leads', 'leads')->name('sale-leads')->whereNumber('id');
                Route::post('/{id}/mark-sold', 'markSold')->name('mark-sold')->whereNumber('id');
            });
        });

        // Premium — every live / scheduled premium listing, and booking several at once.
        Route::prefix('premium')->name('premium.')->middleware('portal.approved')->controller(PremiumController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/eligible', 'eligible')->name('eligible')->middleware('throttle:120,1');
        });

        // Sold Listings — sold / rented listings, their proof documents, putting one back on the market.
        Route::prefix('sold-listings')->name('sold-listings.')->middleware('portal.approved')->controller(SoldListingController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{id}/documents/{kind}', 'document')->name('document')->whereNumber('id')->whereIn('kind', ['ownership', 'contract']);
            Route::post('/{id}/revert', 'revert')->name('revert')->whereNumber('id');
        });

        // Marketing Properties — the home "Realty Property" list. Super Admin only (checked in the controller).
        Route::prefix('marketing-properties')->name('marketing.')->controller(MarketingPropertyController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/accounts', 'accounts')->name('accounts')->middleware('throttle:120,1');
            Route::get('/properties', 'properties')->name('properties')->middleware('throttle:120,1');
            Route::post('/', 'store')->name('store');
            Route::post('/reorder', 'reorder')->name('reorder');
            Route::delete('/{propertyId}', 'destroy')->name('destroy')->whereNumber('propertyId');
        });

        // Agents — a Company's own roster; Super Admin sees every agent (scoped in the controller).
        Route::prefix('agents')->name('agents.')->middleware('portal.approved')->controller(AgentController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/invite', 'invite')->name('invite');
            Route::get('/{id}', 'show')->name('show')->whereNumber('id');
            Route::post('/{id}/accept-request', 'acceptRequest')->name('accept-request')->whereNumber('id');
            Route::post('/{id}/decline-request', 'declineRequest')->name('decline-request')->whereNumber('id');
            Route::post('/{id}/cancel', 'cancel')->name('cancel')->whereNumber('id');
            Route::post('/{id}/suspend', 'suspend')->name('suspend')->whereNumber('id');
            Route::post('/{id}/reactivate', 'reactivate')->name('reactivate')->whereNumber('id');
            Route::post('/{id}/remove', 'remove')->name('remove')->whereNumber('id');
        });

        // Nearby Places — shared list managed by Super Admin, plus each Agent/Company's own places.
        Route::prefix('nearby-places')->name('nearby-places.')->middleware('portal.approved')->controller(NearbyPlaceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/form-options', 'formOptions')->name('form-options');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->name('show')->whereNumber('id');
            Route::put('/{id}', 'update')->name('update')->whereNumber('id');
            Route::post('/{id}/toggle-status', 'toggleStatus')->name('toggle-status')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
        });

        // Listing Settings → Watermark stamped on uploaded listing photos.
        Route::prefix('listing-settings/watermark')->name('watermark.')->middleware('portal.approved')->controller(WatermarkController::class)->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/', 'update')->name('update');
            Route::get('/accounts', 'accounts')->name('accounts');
            Route::get('/image', 'image')->name('image');
        });

        // Reports — a plan entitlement (checked in the controller: `locked` without it).
        Route::prefix('reports')->name('reports.')->middleware('portal.approved')->controller(ReportController::class)->group(function () {
            Route::get('/sales/export', 'exportSales')->name('sales.export');
            Route::get('/{report?}', 'show')->name('show')->whereIn('report', array_keys(ReportController::REPORTS));
        });

        // Contact Us — the account's support tickets. Reachable before KYC approval too, since getting
        // unstuck is often exactly what they need help with. Agent / company accounts only.
        Route::prefix('support')->name('support.')->controller(TicketController::class)->group(function () {
            Route::get('/meta', 'meta')->name('meta');
            Route::get('/tickets', 'index')->name('index');
            Route::post('/tickets', 'store')->name('store')->middleware('throttle:10,1');
            Route::get('/tickets/{ticket}', 'show')->name('show')->whereNumber('ticket');
            Route::post('/tickets/{ticket}/reply', 'reply')->name('reply')->whereNumber('ticket')->middleware('throttle:20,1');
            Route::post('/tickets/{ticket}/resolve', 'resolve')->name('resolve')->whereNumber('ticket');
            Route::post('/tickets/{ticket}/reopen', 'reopen')->name('reopen')->whereNumber('ticket');
            Route::get('/tickets/{ticket}/attachments/{message}', 'attachment')->name('attachment')->whereNumber(['ticket', 'message']);
        });

        // Listing Permits — Super Admin only (checked in the controller).
        Route::prefix('listing-permits')->name('listing-permits.')->controller(ListingPermitController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{id}', 'show')->name('show')->whereNumber('id');
            Route::post('/{id}/approve', 'approve')->name('approve')->whereNumber('id');
            Route::post('/{id}/take-down', 'takeDown')->name('take-down')->whereNumber('id');
        });

        // Integrations — hub, Facebook Lead Ads (Pages' leads arrive via /api/webhooks/facebook) and Property Finder.
        Route::prefix('integrations')->name('integrations.')->middleware('portal.approved')->group(function () {
            Route::get('/', [IntegrationController::class, 'index'])->name('index');

            Route::controller(FacebookController::class)->prefix('facebook')->name('facebook.')->group(function () {
                Route::get('/', 'show')->name('show');
                Route::get('/accounts', 'accounts')->name('accounts')->middleware('throttle:120,1');
                Route::get('/connect-url', 'connectUrl')->name('connect-url');
                Route::post('/pages', 'storePages')->name('pages.store');
                Route::post('/pages/cancel', 'cancelPages')->name('pages.cancel');
                Route::get('/import-status', 'importStatus')->name('import-status')->middleware('throttle:120,1');
                Route::post('/bulk-disconnect', 'bulkDestroy')->name('bulk-destroy');
                Route::post('/{id}/sync', 'sync')->name('sync')->whereNumber('id')->middleware('throttle:10,1');
                Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
            });

            Route::controller(PropertyFinderController::class)->prefix('property-finder')->name('property-finder.')->group(function () {
                Route::get('/', 'show')->name('show');
                Route::post('/', 'connect')->name('connect')->middleware('throttle:10,1');
                Route::post('/sync', 'sync')->name('sync')->middleware('throttle:10,1');
                Route::get('/status', 'status')->name('status')->middleware('throttle:120,1');
                Route::delete('/', 'destroy')->name('destroy');
                Route::get('/review', 'review')->name('review');
                Route::post('/review', 'reviewAction')->name('review.action');
            });
        });

        // Master — the stages / tags / sources used on leads.
        Route::prefix('master')->name('master.')->middleware('portal.approved')->group(function () {
            Route::controller(StageController::class)->prefix('stages')->name('stages.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('/reorder', 'reorder')->name('reorder');
                Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
                Route::post('/{id}/default', 'setDefault')->name('default')->whereNumber('id');
            });
            Route::controller(TagController::class)->prefix('tags')->name('tags.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
            });
            Route::controller(SourceController::class)->prefix('sources')->name('sources.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('/reorder', 'reorder')->name('reorder');
                Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
            });
            // Property form dropdown options — Super Admin only (checked in the controller).
            Route::controller(PropertyOptionController::class)->prefix('property-options')->name('property-options.')->group(function () {
                $lists = implode('|', array_keys(PropertyOptionController::LISTS));
                Route::get('/', 'index')->name('index');
                Route::post('/{key}', 'store')->name('store')->where('key', $lists);
                Route::post('/{key}/reorder', 'reorder')->name('reorder')->where('key', $lists);
                Route::put('/{key}/{id}', 'update')->name('update')->where('key', $lists)->whereNumber('id');
                Route::post('/{key}/{id}/toggle', 'toggle')->name('toggle')->where('key', $lists)->whereNumber('id');
                Route::delete('/{key}/{id}', 'destroy')->name('destroy')->where('key', $lists)->whereNumber('id');
            });
            Route::controller(LinkedLeadsController::class)->group(function () {
                Route::get('/{type}/{id}/leads', 'index')->name('linked-leads.index')->whereIn('type', ['stages', 'tags', 'sources'])->whereNumber('id');
                Route::post('/{type}/{id}/leads/remove', 'remove')->name('linked-leads.remove')->whereIn('type', ['stages', 'tags', 'sources'])->whereNumber('id');
            });
        });
    });
});
