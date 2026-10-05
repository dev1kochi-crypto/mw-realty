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

        // SEO files built from the database (the package crawls the site, which can't see the
        // Vue SPA's links) — used by the CMS buttons, `sitemap:generate` and the package jobs.
        $this->app->bind(\CMS\SiteManager\Services\SitemapService::class, \App\Services\Seo\SiteSitemapService::class);
        $this->app->bind(\CMS\SiteManager\Services\LlmsTxtService::class, \App\Services\Seo\SiteLlmsTxtService::class);
        // Loop/chain-safe URL redirects (slug renames, deletions, manual rules).
        $this->app->bind(\CMS\SiteManager\Services\UrlRedirectService::class, \App\Services\Seo\SafeUrlRedirectService::class);

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
        $this->regenerateSeoFilesOnChange();
        $this->redirectOnSlugChange();

        // The admin and portal are Bootstrap 5 — Laravel's default Tailwind pagination rendered
        // unstyled there (giant arrow icons). ->links() now produces Bootstrap markup everywhere.
        \Illuminate\Pagination\Paginator::useBootstrapFive();

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
            // KYC submitted and waiting for review, per client type (Clients > Companies / Agents badges).
            $view->with('pendingKycCounts', Auth::guard('cms')->check()
                ? \App\Models\PortalUser::where('kyc_review_status', 'submitted')->whereNot('status', 'approved')
                    ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')->all()
                : []);
            $view->with('pendingAgencyAgentCount', Auth::guard('cms')->check()
                ? \App\Models\AgencyAgent::where('status', \App\Models\AgencyAgent::PENDING)->count()
                : 0);
            $view->with('unassignedLeadCount', Auth::guard('cms')->check()
                ? \App\Models\Lead::whereNull('portal_user_id')->count()
                : 0);
            $view->with('supportTicketsNeedingReply', Auth::guard('cms')->check()
                ? \App\Models\SupportTicket::needsAdminReply()->count()
                : 0);
        });
    }

    /**
     * Listing / agent / agency slug renamed → 301 from the old detail URL to the new one; listing
     * deleted → 301 to /properties (config/cms/url_redirects.php). Blogs, careers and market
     * insights already record theirs in their CMS controllers. SafeUrlRedirectService keeps the
     * rules loop- and chain-free.
     */
    private function redirectOnSlugChange(): void
    {
        $redirects = fn () => app(\CMS\SiteManager\Services\UrlRedirectService::class);
        $actor = fn () => \Illuminate\Support\Facades\Auth::guard('cms')->id();

        \App\Models\Property::updated(function ($property) use ($redirects, $actor) {
            if ($property->wasChanged('slug') && $property->getOriginal('slug')) {
                $redirects()->recordSlugChange('property', $property->getOriginal('slug'), (string) $property->slug, $actor());
            }
        });
        \App\Models\Property::deleted(function ($property) use ($redirects, $actor) {
            if ($property->slug) {
                $redirects()->recordDeletion('property', $property->slug, $actor());
            }
        });
        \App\Models\PortalUser::updated(function ($user) use ($redirects, $actor) {
            if ($user->wasChanged('slug') && $user->getOriginal('slug')) {
                $redirects()->recordSlugChange($user->type === 'company' ? 'agency' : 'agent', $user->getOriginal('slug'), (string) $user->slug, $actor());
            }
        });
    }

    /**
     * Saving/deleting a model listed in config/cms/sitemap.php 'regenerate_on_change' queues one
     * sitemap.xml + llms.txt rebuild. Saves that only touch bookkeeping columns (login codes,
     * timestamps…) are ignored so routine activity doesn't keep rebuilding the files.
     */
    private function regenerateSeoFilesOnChange(): void
    {
        $ignored = ['updated_at', 'remember_token', 'password', 'otp_code', 'otp_expires_at', 'two_factor_last_step', 'two_factor_recovery_codes', 'image_sequence'];
        $queue = function ($model) use ($ignored) {
            if (!config('cms.sitemap.auto_regenerate', true)) {
                return;
            }
            if ($model->wasRecentlyCreated || !$model->exists || array_diff(array_keys($model->getChanges()), $ignored)) {
                \App\Jobs\RegenerateSeoFiles::dispatch();
            }
        };

        foreach (config('cms.sitemap.regenerate_on_change', []) as $class) {
            if (class_exists($class)) {
                $class::saved($queue);
                $class::deleted($queue);
            }
        }
    }
}
