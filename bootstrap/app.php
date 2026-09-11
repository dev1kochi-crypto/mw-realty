<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
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

        $middleware->alias([
            'portal.or.cms' => \App\Http\Middleware\PortalOrCmsAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
