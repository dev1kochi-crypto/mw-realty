<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('landing-pages:cleanup-temp-images')->daily();
        $schedule->command('portal:notify-expiring-documents')->daily();
        $schedule->command('facebook:check-page-tokens')->dailyAt('08:00')->withoutOverlapping();
        // Morning-before reminder for subscriptions that auto-renew tomorrow (UAE time).
        $schedule->command('portal:remind-subscription-renewals')->dailyAt('09:00')->timezone(\App\Console\Commands\RemindSubscriptionRenewals::TIMEZONE)->withoutOverlapping();
        $schedule->command('properties:expire-featured')->everyFifteenMinutes();
        $schedule->command('visitors:prune')->dailyAt('03:30')->withoutOverlapping();
        // DLD permits: unpublish listings whose permit expired, remind 7 days before (UAE midnight).
        $schedule->command('properties:expire-permits')->dailyAt('00:05')->timezone(\App\Console\Commands\RemindSubscriptionRenewals::TIMEZONE)->withoutOverlapping();
        $schedule->call(fn () => app(\App\Services\NewsletterCampaignService::class)->sendDueContent())->everyFifteenMinutes();
        // Every email (KYC, leads, enquiries…) is queued. On hosting without a supervisor-managed
        // `queue:work`, this drains the queue once a minute off the existing schedule:run cron.
        $schedule->command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->withoutOverlapping();
        // Long imports (Property Finder sync) on their own worker, so they never hold up the emails above.
        $schedule->command('queue:work imports --queue=imports --stop-when-empty --tries=1 --timeout=3600 --max-time=50')->everyMinute()->withoutOverlapping(90);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Only the portal routes use Laravel's built-in auth/guest middleware
        // (the CMS admin uses its own cms.auth/cms.permission middleware), so
        // these redirects only ever apply to /portal/* requests.
        $middleware->redirectGuestsTo(fn ($request) => route('portal.login'));
        $middleware->redirectUsersTo(fn ($request) => route('portal.dashboard'));

        $middleware->web(append: [
            \App\Http\Middleware\EnforceAccountSecurity::class,
            \App\Http\Middleware\AuthorizeCmsAction::class,
            \App\Http\Middleware\ValidateAdminInput::class,
            \App\Http\Middleware\AtomicAdminChanges::class,
        ]);

        // The visitor-tracking cookie is a random id, not a secret — left unencrypted so the
        // stateless API forms (POST /api/contact) can read it too. The page tracker's beacons
        // (sent as the visitor leaves a page) can't carry the CSRF header.
        $middleware->encryptCookies(except: [\App\Services\Visitors\VisitorTracker::COOKIE]);
        $middleware->validateCsrfTokens(except: ['track/*']);

        $middleware->alias([
            'portal.or.cms' => \App\Http\Middleware\PortalOrCmsAuth::class,
            'customer.auth' => \App\Http\Middleware\EnsureCustomerAuthenticated::class,
            'portal.approved' => \App\Http\Middleware\EnsurePortalAccountApproved::class,
            'portal.2fa' => \App\Http\Middleware\EnsurePortalTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // /api/* always answers in JSON (401/404/422/429…), even when the client — e.g. the mobile
        // app — doesn't send `Accept: application/json`; otherwise a validation error would
        // redirect and an unauthenticated request would bounce to the portal login page.
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
    })->create();
