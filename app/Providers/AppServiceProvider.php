<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Child views under an @extends('portal.layouts.*') render their content
        // before the parent layout's own @php block runs, so these have to be
        // shared this way rather than set inline in the layout. The Properties/CRM
        // dashboards are visited by two different guards (cms = Super Admin,
        // portal = Agent/Company), so both are exposed and the views pick.
        View::composer('portal.*', function ($view) {
            $view->with('owner', Auth::guard('portal')->user());
            $view->with('cmsActor', Auth::guard('cms')->user());
        });
    }
}
