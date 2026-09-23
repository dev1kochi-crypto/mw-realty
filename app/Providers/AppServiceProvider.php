<?php

namespace App\Providers;

use App\Services\Chatbot\ChatbotProvider;
use App\Services\Chatbot\GeminiChatbotProvider;
use App\Services\Chatbot\GroqChatbotProvider;
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
        $this->app->scoped(\App\Services\ManagedFiles::class);
        $this->app->scoped(\App\Services\PropertyLabels::class);

        // Which concrete AI provider backs the chat widget — see config/chatbot.php's
        // 'provider' (CHATBOT_PROVIDER env). Both implementations ship regardless of which is
        // active, so switching providers (or back) is a one-line env change, no code change.
        $this->app->bind(ChatbotProvider::class, function () {
            return match (config('chatbot.provider')) {
                'gemini' => new GeminiChatbotProvider(config('services.gemini.api_key') ?? '', config('services.gemini.model')),
                default => new GroqChatbotProvider(config('services.groq.api_key') ?? '', config('services.groq.model')),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('admin-login', fn ($request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($request->ip()),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('portal-registration', fn ($request) =>
            \Illuminate\Cache\RateLimiting\Limit::perHour(10)->by($request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('lead-capture', fn ($request) =>
            \Illuminate\Cache\RateLimiting\Limit::perHour(20)->by($request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('landing-page-enquiry', fn ($request) =>
            \Illuminate\Cache\RateLimiting\Limit::perHour(20)->by($request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('form-submit', fn ($request) =>
            \Illuminate\Cache\RateLimiting\Limit::perHour(20)->by($request->ip()));
        // Tighter than form-submit — every message is a paid Gemini API call, not just a DB write.
        \Illuminate\Support\Facades\RateLimiter::for('ai-chatbot', fn ($request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(8)->by($request->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perDay(150)->by($request->ip()),
        ]);
        // Covers both verify (a 4-digit code is brute-forceable) and resend (don't let one
        // signup spam its own inbox) — keyed by IP, same as the other auth limiters above.
        \Illuminate\Support\Facades\RateLimiter::for('otp-verify', fn ($request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(8)->by($request->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perDay(30)->by($request->ip()),
        ]);

        $this->app->booted(function () {
            foreach (app('router')->getRoutes() as $route) {
                if (in_array($route->getName(), ['cms.sitemap.generate', 'cms.llms-txt.generate']) && in_array('GET', $route->methods())) {
                    $action = $route->getAction(); unset($action['as']); $route->setAction($action);
                }
                if ($route->uri() === config('cms-kit.common.auth.prefix', 'admin').'/login' && in_array('POST', $route->methods())) {
                    $route->middleware('throttle:admin-login');
                }
                if (str_starts_with($route->getName() ?? '', 'cms.permissions.')) {
                    $action = $route->getAction();
                    $action['middleware'] = array_map(fn ($m) => $m === 'cms.permission:roles.view' ? 'cms.permission:permissions.view' : $m, $action['middleware']);
                    $route->setAction($action);
                }
            }
        });
        // Child views under an @extends('portal.layouts.*') render their content
        // before the parent layout's own @php block runs, so these have to be
        // shared this way rather than set inline in the layout. The Properties/CRM
        // dashboards are visited by two different guards (cms = Super Admin,
        // portal = Agent/Company), so both are exposed and the views pick.
        View::composer('portal.*', function ($view) {
            $view->with('owner', Auth::guard('portal')->user());
            $view->with('cmsActor', Auth::guard('cms')->user());
        });

        // Pending-plan-request badge shown in the admin sidebar's Clients group.
        View::composer('cms-kit::layouts.cms', function ($view) {
            $view->with('pendingUpgradeCount', Auth::guard('cms')->check()
                ? \App\Models\PlanUpgradeRequest::pending()->count()
                : 0);
        });
    }
}
