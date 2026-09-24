<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $siteInfo->company_name ?? config('cms-kit.common.name', 'CMS Kit') }} - @yield('title', 'Admin Dashboard')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ !empty($siteInfo->favicon) ? asset('storage/' . $siteInfo->favicon) : asset('frontend/assets/images/fav.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @php
        $theme = config('cms-kit.common.theme', []);
        $primaryColor = $theme['primary_color'] ?? '#dc3545';
        $primaryGradient = $theme['primary_gradient'] ?? null;
        $primaryFill = $primaryGradient ?: $primaryColor;
        $normalizedPrimary = ltrim($primaryColor, '#');
        if (strlen($normalizedPrimary) === 3) {
            $normalizedPrimary = collect(str_split($normalizedPrimary))->map(fn ($char) => $char . $char)->implode('');
        }
        [$primaryRed, $primaryGreen, $primaryBlue] = sscanf($normalizedPrimary, '%02x%02x%02x') ?: [220, 53, 69];
    @endphp
    <style>
        :root {
            --primary-color: {{ $primaryColor }};
            --primary-gradient: {{ $primaryGradient ?: $primaryColor }};
            --primary-fill: {{ $primaryFill }};
            --heading-gradient: {{ $primaryFill }};
            --theme-status-success-bg: rgba(25, 135, 84, 0.12);
            --theme-status-success-text: #198754;
            --theme-status-danger-bg: rgba(220, 53, 69, 0.12);
            --theme-status-danger-text: #dc3545;
            --primary-rgb: {{ $primaryRed }}, {{ $primaryGreen }}, {{ $primaryBlue }};
            --secondary-color: {{ $theme['secondary_color'] ?? '#212529' }};
            --bg-color: {{ $theme['background_color'] ?? '#f4f7f6' }};
            --sidebar-color: {{ $theme['sidebar_color'] ?? '#1a1d21' }};
            --text-color: {{ $theme['text_color'] ?? '#495057' }};
            --theme-border-color: rgba({{ $primaryRed }}, {{ $primaryGreen }}, {{ $primaryBlue }}, 0.24);
            --theme-border-strong: rgba({{ $primaryRed }}, {{ $primaryGreen }}, {{ $primaryBlue }}, 0.45);
            --theme-soft-bg: rgba({{ $primaryRed }}, {{ $primaryGreen }}, {{ $primaryBlue }}, 0.08);
            --theme-focus-ring: 0 0 0 0.2rem rgba({{ $primaryRed }}, {{ $primaryGreen }}, {{ $primaryBlue }}, 0.15);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --header-bg: rgba(255, 255, 255, 0.8);
            --sidebar-width: 280px;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('vendor/cms-kit/css/cms-premium.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/cms-kit/css/sitemap.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/cms-kit/css/cms-modules.css') }}">
    <style>
        .sidebar-section-label {
            font-size: 0.68rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase;
            color: rgba(255,255,255,0.32); padding: 1.1rem 1rem 0.4rem;
            border-top: 1px solid rgba(255,255,255,0.08); margin-top: 0.6rem;
        }

        /* Sidebar — standard navy gradient (tied to the configured brand colors), dim/professional */
        .sidebar {
            background: linear-gradient(165deg, var(--secondary-color, #264373) 0%, #16213d 45%, #0c1730 100%) !important;
            border-right: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar .brand {
            background: linear-gradient(135deg, rgba(var(--primary-rgb, 4,161,204), 0.18), transparent 70%);
            border-bottom: 1px solid rgba(255,255,255,0.08) !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, rgba(var(--primary-rgb, 4,161,204), 0.32), rgba(var(--primary-rgb, 4,161,204), 0.1)) !important;
            box-shadow: inset 0 0 0 1px rgba(var(--primary-rgb, 4,161,204), 0.24), 0 10px 22px rgba(0,0,0,0.22) !important;
        }
        .notif-bell-btn { position: relative; border: none; background: #f4f6fb; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #4b5065; font-size: 1.05rem; }
        .notif-bell-btn:hover { background: #e9ecf6; color: var(--secondary-color, #212529); }
        .notif-bell-badge { position: absolute; top: -3px; right: -3px; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 10px; background: #dc3545; border: 2px solid #fff; color: #fff; font-size: 0.62rem; font-weight: 800; display: flex; align-items: center; justify-content: center; line-height: 1; }
        .notif-offcanvas { width: 400px; max-width: 92vw; }
        .notif-offcanvas .offcanvas-header { border-bottom: 1px solid #f0f1f6; }
        .notif-offcanvas .offcanvas-title { font-weight: 800; font-size: 1.05rem; }
        .notif-offcanvas-actions { padding: 0.7rem 1.25rem; display: flex; justify-content: flex-end; border-bottom: 1px solid #f5f6fa; }
        .notif-offcanvas-actions .btn-link { font-size: 0.8rem; font-weight: 700; text-decoration: none; }
        .notif-list { overflow-y: auto; }
        .notif-item { display: flex; gap: 0.7rem; padding: 0.8rem 1.1rem; text-decoration: none; color: inherit; border-bottom: 1px solid #f5f6fa; }
        .notif-item:hover { background: #f9fafc; color: inherit; }
        .notif-item.is-unread { background: #f3f9ff; }
        .notif-icon { width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.8rem; margin-top: 2px; }
        .notif-icon.tone-teal { background: linear-gradient(135deg, var(--primary-color, #04a1cc), #2fc4e8); }
        .notif-icon.tone-amber { background: linear-gradient(135deg, #e08e0b, #f5a623); }
        .notif-icon.tone-green { background: linear-gradient(135deg, #0f9d58, #34c880); }
        .notif-icon.tone-red { background: linear-gradient(135deg, #dc3545, #ef6a76); }
        .notif-body { flex: 1; min-width: 0; }
        .notif-title { font-weight: 700; font-size: 0.83rem; color: #1c2340; }
        .notif-message { font-size: 0.78rem; color: #6b7185; line-height: 1.4; }
        .notif-time { font-size: 0.68rem; color: #a3a8bb; margin-top: 0.2rem; }
        .notif-empty { padding: 2.5rem 1rem; text-align: center; color: #a3a8bb; font-size: 0.85rem; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>

    {{-- TinyMCE initialized in specific views --}}
    @stack('styles')
</head>
<body>
    <div class="min-vh-100">
        <!-- Sidebar -->
        <div class="sidebar d-none d-md-block">
            <div class="brand">
                <h4 class="fw-bold mb-0 text-white">{{ $siteInfo->company_name ?? config('cms-kit.common.name', 'CMS Kit') }}</h4>
                <small class="text-white-50">Control Panel</small>
            </div>
            
            <div class="mt-4">
                <nav class="nav flex-column">
                    <a class="nav-link @if(Route::is('cms.dashboard')) active @endif" href="{{ route('cms.dashboard') }}">
                        <i class="fas fa-th-large"></i> Dashboard
                    </a>

                    {{-- Clients Group — portal-guard accounts (agents/companies, and future frontend/customer logins).
                         These are NOT admin users: no CMS roles/permissions, scoped to CRM + Properties portal only.
                         Keep this separate from User Management below; add new client-account types here, not there.
                         Placed right after Dashboard (with Plans) since these two are the core business — everything
                         below the "Website Content" label is CMS content for the public site, not the product itself. --}}
                    @if(config('cms-kit.common.modules.portal-accounts', true) && ($cmsUser->hasRole('superadmin') || $cmsUser->can('portal-accounts.view')))
                    <div class="nav-item sidebar-group">
                        <a class="nav-link d-flex align-items-center sidebar-group-toggle @if(request()->routeIs('cms.portal-accounts.*')) active @endif"
                           data-bs-toggle="collapse" href="#clientsMenu" role="button"
                           aria-expanded="@if(request()->routeIs('cms.portal-accounts.*')) true @else false @endif">
                            <i class="fas fa-address-card"></i>
                            <span>Clients</span>
                            <i class="fas fa-chevron-down ms-auto sidebar-chevron"></i>
                        </a>
                        <div class="collapse sidebar-submenu @if(request()->routeIs('cms.portal-accounts.*')) show @endif" id="clientsMenu">
                            <nav class="nav flex-column">
                               <a class="nav-link py-2 @if(request()->routeIs('cms.portal-accounts.*') && request()->query('type') === 'company') active @endif" href="{{ route('cms.portal-accounts.index', ['type' => 'company']) }}">
                                    Companies
                                </a>
                                <a class="nav-link py-2 @if(request()->routeIs('cms.portal-accounts.*') && request()->query('type') === 'agent') active @endif" href="{{ route('cms.portal-accounts.index', ['type' => 'agent']) }}">
                                    Agents
                                </a>
                                <a class="nav-link py-2 d-flex align-items-center @if(request()->routeIs('cms.portal-accounts.plan-upgrade-requests')) active @endif" href="{{ route('cms.portal-accounts.plan-upgrade-requests') }}">
                                    Plan Requests
                                    @if(($pendingUpgradeCount ?? 0) > 0)
                                    <span class="badge bg-danger ms-auto">{{ $pendingUpgradeCount }}</span>
                                    @endif
                                </a>

                                {{-- Frontend/customer user logins land here once frontend auth is integrated. --}}
                            </nav>
                        </div>
                    </div>
                    @endif

                    @if(config('cms-kit.common.modules.plans', true) && $cmsUser->can('plans.view'))
                    <a class="nav-link @if(Route::is('cms.plans.*')) active @endif" href="{{ route('cms.plans.index') }}">
                        <i class="fas fa-layer-group"></i> Plans
                    </a>
                    @endif

                    @if(config('cms-kit.common.modules.plans', true) && $cmsUser->can('plans.view'))
                    <a class="nav-link @if(Route::is('cms.payments.*')) active @endif" href="{{ route('cms.payments.index') }}">
                        <i class="fas fa-receipt"></i> Payments
                    </a>
                    @endif

                    @if(config('cms-kit.common.modules.coupons', true) && $cmsUser->can('coupons.view'))
                    <a class="nav-link @if(Route::is('cms.coupons.*')) active @endif" href="{{ route('cms.coupons.index') }}">
                        <i class="fas fa-ticket-alt"></i> Coupons
                    </a>
                    @endif

                    <div class="sidebar-section-label">Website Content</div>

                    {{-- Site Settings Group --}}
                    @if((config('cms-kit.common.modules.languages', true) && $cmsUser->can('languages.view')) || $cmsUser->can('site-information.view'))
                        <div class="nav-item sidebar-group">
                            <a class="nav-link d-flex align-items-center sidebar-group-toggle @if(request()->routeIs('cms.languages.*') || request()->routeIs('cms.site-information.*')) active @endif" 
                            data-bs-toggle="collapse" href="#settingsMenu" role="button" 
                            aria-expanded="@if(request()->routeIs('cms.languages.*') || request()->routeIs('cms.site-information.*')) true @else false @endif">
                                <i class="fas fa-cog"></i>
                                <span>General Settings</span>
                                <i class="fas fa-chevron-down ms-auto sidebar-chevron"></i>
                            </a>
                            <div class="collapse sidebar-submenu @if(request()->routeIs('cms.languages.*') || request()->routeIs('cms.site-information.*')) show @endif" id="settingsMenu">
                                <nav class="nav flex-column">
                                    @if(config('cms-kit.common.modules.languages', true) && $cmsUser->can('languages.view'))
                                    <a class="nav-link py-2 @if(request()->routeIs('cms.languages.index') || request()->routeIs('cms.languages.static-texts.*') || request()->routeIs('cms.languages.translations*')) active @endif" href="{{ route('cms.languages.index') }}">
                                        Languages
                                    </a>
                                    @endif
                                    @if($cmsUser->can('site-information.view'))
                                    <a class="nav-link py-2 @if(request()->routeIs('cms.site-information.*')) active @endif" href="{{ route('cms.site-information.index') }}">
                                        Site Information
                                    </a>
                                    @endif
                                </nav>
                            </div>
                        </div>
                    @endif

                    {{-- SEO Group --}}
                    @if((config('cms-kit.common.modules.metadata', true) && $cmsUser->can('metadata.view')) || $cmsUser->can('sitemap.view') || $cmsUser->can('robots-txt.view') || $cmsUser->can('llms-txt.view') || $cmsUser->can('url-redirects.view') || $cmsUser->can('url-miss-logs.view'))
                    <div class="nav-item sidebar-group">
                        <a class="nav-link d-flex align-items-center sidebar-group-toggle @if(request()->routeIs('cms.metadata.*') || request()->routeIs('cms.sitemap.*') || request()->routeIs('cms.robots-txt.*') || request()->routeIs('cms.llms-txt.*') || request()->routeIs('cms.url-redirects.*') || request()->routeIs('cms.url-miss-logs.*')) active @endif" 
                           data-bs-toggle="collapse" href="#seoMenu" role="button" 
                           aria-expanded="@if(request()->routeIs('cms.metadata.*') || request()->routeIs('cms.sitemap.*') || request()->routeIs('cms.robots-txt.*') || request()->routeIs('cms.llms-txt.*') || request()->routeIs('cms.url-redirects.*') || request()->routeIs('cms.url-miss-logs.*')) true @else false @endif">
                            <i class="fas fa-search"></i>
                            <span>SEO Management</span>
                            <i class="fas fa-chevron-down ms-auto sidebar-chevron"></i>
                        </a>
                        <div class="collapse sidebar-submenu @if(request()->routeIs('cms.metadata.*') || request()->routeIs('cms.sitemap.*') || request()->routeIs('cms.robots-txt.*') || request()->routeIs('cms.llms-txt.*') || request()->routeIs('cms.url-redirects.*') || request()->routeIs('cms.url-miss-logs.*')) show @endif" id="seoMenu">
                            <nav class="nav flex-column">
                                @if(config('cms-kit.common.modules.metadata', true) && $cmsUser->can('metadata.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.metadata.*')) active @endif" href="{{ route('cms.metadata.index') }}">
                                    Metadata
                                </a>
                                @endif
                                @if($cmsUser->can('sitemap.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.sitemap.*')) active @endif" href="{{ route('cms.sitemap.index') }}">
                                    Sitemap Generator
                                </a>
                                @endif
                                @if($cmsUser->can('robots-txt.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.robots-txt.*')) active @endif" href="{{ route('cms.robots-txt.index') }}">
                                    Robots.txt Editor
                                </a>
                                @endif
                                @if($cmsUser->can('llms-txt.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.llms-txt.*')) active @endif" href="{{ route('cms.llms-txt.index') }}">
                                    LLMs.txt Generator
                                </a>
                                @endif
                                @if($cmsUser->can('url-redirects.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.url-redirects.*')) active @endif" href="{{ route('cms.url-redirects.index') }}">
                                    URL redirects
                                </a>
                                @endif
                                @if($cmsUser->can('url-miss-logs.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.url-miss-logs.*')) active @endif" href="{{ route('cms.url-miss-logs.index') }}">
                                    404 log
                                </a>
                                @endif
                            </nav>
                        </div>
                    </div>
                    @endif
                    
                    {{-- Home Group --}}
                    @php
                        $homeRoutes = request()->routeIs('cms.banners.*') || request()->routeIs('cms.popular-places.*') || request()->routeIs('cms.section-headings.*');
                    @endphp
                    @if(($cmsUser->can('banners.view') && config('cms-kit.common.modules.banners', true))
                        || ($cmsUser->can('popular-places.view') && config('cms-kit.common.modules.popular-places', true))
                        || $cmsUser->can('section-headings.view'))
                    <div class="nav-item sidebar-group">
                        <a class="nav-link d-flex align-items-center sidebar-group-toggle @if($homeRoutes) active @endif"
                           data-bs-toggle="collapse" href="#homeMenu" role="button"
                           aria-expanded="@if($homeRoutes) true @else false @endif">
                            <i class="fas fa-home"></i>
                            <span>Home</span>
                            <i class="fas fa-chevron-down ms-auto sidebar-chevron"></i>
                        </a>
                        <div class="collapse sidebar-submenu @if($homeRoutes) show @endif" id="homeMenu">
                            <nav class="nav flex-column">
                                @if(config('cms-kit.common.modules.banners', true) && $cmsUser->can('banners.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.banners.*')) active @endif" href="{{ route('cms.banners.index') }}">
                                    Banner
                                </a>
                                @endif
                                @if(config('cms-kit.common.modules.popular-places', true) && $cmsUser->can('popular-places.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.popular-places.*')) active @endif" href="{{ route('cms.popular-places.index') }}">
                                    Popular Places
                                </a>
                                @endif
                                @if($cmsUser->can('section-headings.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.section-headings.*')) active @endif" href="{{ route('cms.section-headings.index') }}">
                                    Common Titles
                                </a>
                                @endif
                            </nav>
                        </div>
                    </div>
                    @endif

                    {{-- About Group --}}
                    @php
                        $aboutRoutes = request()->routeIs('cms.about-us.*') || request()->routeIs('cms.market-trends.*')
                            || request()->routeIs('cms.why-choose-us.*') || request()->routeIs('cms.our-builders.*')
                            || request()->routeIs('cms.connect-us.*') || request()->routeIs('cms.post-property-steps.*');
                    @endphp
                    @if(($cmsUser->can('about-us.view') && config('cms-kit.common.modules.about-us', true))
                        || ($cmsUser->can('market-trends.view') && config('cms-kit.common.modules.market-trends', true))
                        || ($cmsUser->can('why-choose-us.view') && config('cms-kit.common.modules.why-choose-us', true))
                        || ($cmsUser->can('our-builders.view') && config('cms-kit.common.modules.our-builders', true))
                        || ($cmsUser->can('connect-us.view') && config('cms-kit.common.modules.connect-us', true))
                        || ($cmsUser->can('post-property-steps.view') && config('cms-kit.common.modules.post-property-steps', true)))
                    <div class="nav-item sidebar-group">
                        <a class="nav-link d-flex align-items-center sidebar-group-toggle @if($aboutRoutes) active @endif"
                           data-bs-toggle="collapse" href="#aboutMenu" role="button"
                           aria-expanded="@if($aboutRoutes) true @else false @endif">
                            <i class="fas fa-info-circle"></i>
                            <span>About</span>
                            <i class="fas fa-chevron-down ms-auto sidebar-chevron"></i>
                        </a>
                        <div class="collapse sidebar-submenu @if($aboutRoutes) show @endif" id="aboutMenu">
                            <nav class="nav flex-column">
                                @if(config('cms-kit.common.modules.about-us', true) && $cmsUser->can('about-us.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.about-us.*')) active @endif" href="{{ route('cms.about-us.index') }}">
                                    About Us
                                </a>
                                @endif
                                @if(config('cms-kit.common.modules.market-trends', true) && $cmsUser->can('market-trends.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.market-trends.*')) active @endif" href="{{ route('cms.market-trends.index') }}">
                                    Market Trends
                                </a>
                                @endif
                                @if(config('cms-kit.common.modules.why-choose-us', true) && $cmsUser->can('why-choose-us.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.why-choose-us.*')) active @endif" href="{{ route('cms.why-choose-us.index') }}">
                                    Why Choose Us
                                </a>
                                @endif
                                @if(config('cms-kit.common.modules.our-builders', true) && $cmsUser->can('our-builders.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.our-builders.*')) active @endif" href="{{ route('cms.our-builders.index') }}">
                                    Our Builders
                                </a>
                                @endif
                                @if(config('cms-kit.common.modules.connect-us', true) && $cmsUser->can('connect-us.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.connect-us.*')) active @endif" href="{{ route('cms.connect-us.index') }}">
                                    Connect Us
                                </a>
                                @endif
                                @if(config('cms-kit.common.modules.post-property-steps', true) && $cmsUser->can('post-property-steps.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.post-property-steps.*')) active @endif" href="{{ route('cms.post-property-steps.index') }}">
                                    Property Posting Steps
                                </a>
                                @endif
                            </nav>
                        </div>
                    </div>
                    @endif

                    @if(config('cms-kit.common.modules.contact-us', true) && $cmsUser->can('contact-us.view'))
                    <a class="nav-link @if(Route::is('cms.contact-us.*')) active @endif" href="{{ route('cms.contact-us.index') }}">
                        <i class="fas fa-envelope-open-text"></i> Contact
                    </a>
                    @endif

                    {{-- Property listings themselves are managed in the separate Properties dashboard
                         (see the "Properties" button in the header) — this stays admin-only since
                         it's shared taxonomy, not content agents/companies should edit. --}}
                    @php
                        $filtersRoutes = request()->routeIs('cms.filters.*') || request()->routeIs('cms.communities.*') || request()->routeIs('cms.find-properties.*');
                    @endphp
                    @if(($cmsUser->can('filters.view') && config('cms-kit.common.modules.filters', true))
                        || ($cmsUser->can('communities.view') && config('cms-kit.common.modules.communities', true))
                        || ($cmsUser->can('find-properties.view') && config('cms-kit.common.modules.find-properties', true)))
                    <div class="nav-item sidebar-group">
                        <a class="nav-link d-flex align-items-center sidebar-group-toggle @if($filtersRoutes) active @endif"
                           data-bs-toggle="collapse" href="#filtersMenu" role="button"
                           aria-expanded="@if($filtersRoutes) true @else false @endif">
                            <i class="fas fa-sliders-h"></i>
                            <span>Filters</span>
                            <i class="fas fa-chevron-down ms-auto sidebar-chevron"></i>
                        </a>
                        <div class="collapse sidebar-submenu @if($filtersRoutes) show @endif" id="filtersMenu">
                            <nav class="nav flex-column">
                                @if(config('cms-kit.common.modules.filters', true) && $cmsUser->can('filters.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.filters.*')) active @endif" href="{{ route('cms.filters.index') }}">
                                    Manage Filters
                                </a>
                                @endif
                                @if(config('cms-kit.common.modules.communities', true) && $cmsUser->can('communities.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.communities.*')) active @endif" href="{{ route('cms.communities.index') }}">
                                    Communities
                                </a>
                                @endif
                                @if(config('cms-kit.common.modules.find-properties', true) && $cmsUser->can('find-properties.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.find-properties.*')) active @endif" href="{{ route('cms.find-properties.index') }}">
                                    Find Properties
                                </a>
                                @endif
                            </nav>
                        </div>
                    </div>
                    @endif
     
                    @if(config('cms-kit.common.modules.brands', true) && $cmsUser->can('brands.view'))
                    <a class="nav-link @if(Route::is('cms.brands.*')) active @endif" href="{{ route('cms.brands.index') }}">
                        <i class="fas fa-tag"></i> Brands
                    </a>
                    @endif

                    @if(config('cms-kit.common.modules.testimonials', true) && $cmsUser->can('testimonials.view'))
                    <a class="nav-link @if(Route::is('cms.testimonials.*')) active @endif" href="{{ route('cms.testimonials.index') }}">
                        <i class="fas fa-comment-dots"></i> Testimonials
                    </a>
                    @endif

                    @if(config('cms-kit.common.modules.faqs', true) && $cmsUser->can('faqs.view'))
                    <a class="nav-link @if(Route::is('cms.faqs.*')) active @endif" href="{{ route('cms.faqs.index') }}">
                        <i class="fas fa-question-circle"></i> FAQs
                    </a>
                    @endif


                    @if(config('cms-kit.common.modules.locations', true) && $cmsUser->can('locations.view'))
                    <a class="nav-link @if(Route::is('cms.locations.*')) active @endif" href="{{ route('cms.locations.index') }}">
                        <i class="fas fa-map-marker-alt"></i> Locations
                    </a>
                    @endif


                    @if(config('cms-kit.common.modules.enquiries', true) && $cmsUser->can('enquiries.view'))
                    <div class="nav-item sidebar-group">
                        <a class="nav-link d-flex align-items-center sidebar-group-toggle @if(request()->routeIs('cms.enquiries.*') || request()->routeIs('cms.newsletter-signups.*') || request()->routeIs('cms.unassigned-leads.*')) active @endif"
                           data-bs-toggle="collapse" href="#enquiryMenu" role="button"
                           aria-expanded="@if(request()->routeIs('cms.enquiries.*') || request()->routeIs('cms.newsletter-signups.*') || request()->routeIs('cms.unassigned-leads.*')) true @else false @endif">
                            <i class="fas fa-envelope"></i>
                            <span>Enquiries</span>
                            <i class="fas fa-chevron-down ms-auto sidebar-chevron"></i>
                        </a>
                        <div class="collapse sidebar-submenu @if(request()->routeIs('cms.enquiries.*') || request()->routeIs('cms.newsletter-signups.*') || request()->routeIs('cms.unassigned-leads.*')) show @endif" id="enquiryMenu">
                            <nav class="nav flex-column">
                                <a class="nav-link py-2 @if(request()->routeIs('cms.enquiries.*')) active @endif" href="{{ route('cms.enquiries.index') }}">
                                    Form Enquiries
                                </a>
                                @if(config('cms-kit.common.modules.newsletter-signups', true) && $cmsUser->can('newsletter.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.newsletter-signups.*')) active @endif" href="{{ route('cms.newsletter-signups.index') }}">
                                    Newsletter Signups
                                </a>
                                @endif
                                <a class="nav-link py-2 @if(request()->routeIs('cms.unassigned-leads.*')) active @endif" href="{{ route('cms.unassigned-leads.index') }}">
                                    Unassigned Property Leads
                                </a>
                            </nav>
                        </div>
                    </div>
                    @endif

                    @if(config('cms-kit.common.modules.blogs', true) && ($cmsUser->can('blogs.view') || $cmsUser->can('blog-categories.view')))
                    <div class="nav-item sidebar-group">
                        <a class="nav-link d-flex align-items-center sidebar-group-toggle @if(request()->routeIs('cms.blogs.*') || request()->routeIs('cms.blog-categories.*')) active @endif"
                           data-bs-toggle="collapse" href="#blogsMenu" role="button"
                           aria-expanded="@if(request()->routeIs('cms.blogs.*') || request()->routeIs('cms.blog-categories.*')) true @else false @endif">
                            <i class="fas fa-blog"></i>
                            <span>Blogs</span>
                            <i class="fas fa-chevron-down ms-auto sidebar-chevron"></i>
                        </a>
                        <div class="collapse sidebar-submenu @if(request()->routeIs('cms.blogs.*') || request()->routeIs('cms.blog-categories.*')) show @endif" id="blogsMenu">
                            <nav class="nav flex-column">
                                @if($cmsUser->can('blogs.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.blogs.*')) active @endif" href="{{ route('cms.blogs.index') }}">
                                    All Blogs
                                </a>
                                @endif
                                @if($cmsUser->can('blog-categories.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.blog-categories.*')) active @endif" href="{{ route('cms.blog-categories.index') }}">
                                    Categories
                                </a>
                                @endif
                            </nav>
                        </div>
                    </div>
                    @endif

                    @if(config('cms-kit.common.modules.landing-pages', true) && $cmsUser->can('landing-pages.view'))
                    <div class="nav-item sidebar-group">
                        <a class="nav-link d-flex align-items-center sidebar-group-toggle @if(request()->routeIs('cms.landing-pages.*')) active @endif"
                           data-bs-toggle="collapse" href="#landingPagesMenu" role="button"
                           aria-expanded="@if(request()->routeIs('cms.landing-pages.*')) true @else false @endif">
                            <i class="fas fa-file-alt"></i>
                            <span>Landing Pages</span>
                            <i class="fas fa-chevron-down ms-auto sidebar-chevron"></i>
                        </a>
                        <div class="collapse sidebar-submenu @if(request()->routeIs('cms.landing-pages.*')) show @endif" id="landingPagesMenu">
                            <nav class="nav flex-column">
                                <a class="nav-link py-2 @if(Route::is('cms.landing-pages.index') || Route::is('cms.landing-pages.create') || Route::is('cms.landing-pages.edit')) active @endif" href="{{ route('cms.landing-pages.index') }}">
                                    All Pages
                                </a>
                                <a class="nav-link py-2 @if(Route::is('cms.landing-pages.enquiries')) active @endif" href="{{ route('cms.landing-pages.enquiries') }}">
                                    Enquiries
                                </a>
                            </nav>
                        </div>
                    </div>
                    @endif

                    @if(config('cms-kit.common.modules.ads', true) && $cmsUser->can('ads.view'))
                    <a class="nav-link @if(Route::is('cms.ads.*')) active @endif" href="{{ route('cms.ads.index') }}">
                        <i class="fas fa-bullhorn"></i> Ad Management
                    </a>
                    @endif

                    @if(config('cms-kit.common.modules.careers', true) && $cmsUser->can('careers.view'))
                    <div class="nav-item sidebar-group">
                        <a class="nav-link d-flex align-items-center sidebar-group-toggle @if(request()->routeIs('cms.careers.*')) active @endif" 
                           data-bs-toggle="collapse" href="#careersMenu" role="button" 
                           aria-expanded="@if(request()->routeIs('cms.careers.*')) true @else false @endif">
                            <i class="fas fa-briefcase"></i>
                            <span>Careers</span>
                            <i class="fas fa-chevron-down ms-auto sidebar-chevron"></i>
                        </a>
                        <div class="collapse sidebar-submenu @if(request()->routeIs('cms.careers.*')) show @endif" id="careersMenu">
                            <nav class="nav flex-column">
                                @if(config('cms-kit.common.careers.common_section', true))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.careers.common')) active @endif" href="{{ route('cms.careers.common') }}">
                                    Common Section
                                </a>
                                @endif
                                @if(config('cms-kit.common.careers.vacancies', true))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.careers.vacancies.index') || request()->routeIs('cms.careers.create') || request()->routeIs('cms.careers.edit')) active @endif" href="{{ route('cms.careers.vacancies.index') }}">
                                    Vacancies
                                </a>
                                @endif
                                @if(config('cms-kit.common.careers.departments', true))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.careers.departments.*')) active @endif" href="{{ route('cms.careers.departments.index') }}">
                                    Departments
                                </a>
                                @endif
                                @if(config('cms-kit.common.careers.candidates', true))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.careers.candidates.*')) active @endif" href="{{ route('cms.careers.candidates.index') }}">
                                    Candidates
                                </a>
                                @endif
                            </nav>
                        </div>
                    </div>
                    @endif

                    {{-- User Management Group — CMS admin/staff accounts only (roles & permissions govern the admin panel itself) --}}
                    @if($cmsUser->hasRole('superadmin') || $cmsUser->can('users.view') || $cmsUser->can('roles.view'))
                    <div class="nav-item sidebar-group">
                        <a class="nav-link d-flex align-items-center sidebar-group-toggle @if(request()->routeIs('cms.admins.*') || request()->routeIs('cms.roles.*') || request()->routeIs('cms.permissions.*')) active @endif"
                           data-bs-toggle="collapse" href="#userMenu" role="button"
                           aria-expanded="@if(request()->routeIs('cms.admins.*') || request()->routeIs('cms.roles.*') || request()->routeIs('cms.permissions.*')) true @else false @endif">
                            <i class="fas fa-users-cog"></i>
                            <span>User Management</span>
                            <i class="fas fa-chevron-down ms-auto sidebar-chevron"></i>
                        </a>
                        <div class="collapse sidebar-submenu @if(request()->routeIs('cms.admins.*') || request()->routeIs('cms.roles.*') || request()->routeIs('cms.permissions.*')) show @endif" id="userMenu">
                            <nav class="nav flex-column">
                                @if($cmsUser->hasRole('superadmin') || $cmsUser->can('users.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.admins.*')) active @endif" href="{{ route('cms.admins.index') }}">
                                    Administrators
                                </a>
                                @endif
                                @if($cmsUser->hasRole('superadmin') || $cmsUser->can('roles.view'))
                                <a class="nav-link py-2 @if(request()->routeIs('cms.roles.*')) active @endif" href="{{ route('cms.roles.index') }}">
                                    Roles & Permissions
                                </a>
                                <a class="nav-link py-2 @if(request()->routeIs('cms.permissions.*')) active @endif" href="{{ route('cms.permissions.index') }}">
                                    Permissions List
                                </a>
                                @endif
                            </nav>
                        </div>
                    </div>
                    @endif

                </nav>
            </div>

            {{-- Logout moved to header --}}
        </div>

        <!-- Main Wrapper -->
        <div class="main-wrapper flex-grow-1">
            <!-- Top Header -->
            <header class="top-header">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#" class="text-decoration-none text-muted">Dashboard</a></li>
                        @yield('breadcrumbs')
                    </ol>
                </nav>

                <div class="d-flex align-items-center gap-3">
                    @if($cmsUser->hasRole('superadmin'))
                    <a href="{{ route('portal.crm.leads.index') }}" class="btn btn-premium btn-quicknav btn-sm">
                        <i class="fas fa-users"></i> CRM
                    </a>
                    @endif

                    <a href="/" target="_blank" class="btn btn-premium btn-view-site btn-sm">
                        <i class="fas fa-external-link-alt me-2"></i> View Site
                    </a>

                    @php
                        $recentNotifications = $cmsUser?->notifications()->latest()->take(20)->get() ?? collect();
                        $unreadNotifCount = $cmsUser?->unreadNotifications()->count() ?? 0;
                    @endphp
                    <button type="button" class="notif-bell-btn" id="notifBellBtn" data-bs-toggle="offcanvas" data-bs-target="#notifOffcanvas" aria-controls="notifOffcanvas">
                        <i class="fas fa-bell"></i>
                        @if($unreadNotifCount > 0)
                        <span class="notif-bell-badge">{{ $unreadNotifCount > 9 ? '9+' : $unreadNotifCount }}</span>
                        @endif
                    </button>

                    <div class="dropdown">
                        <div class="user-profile dropdown-toggle" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            @php
                                $adminName = $cmsUser->name ?? config('cms-kit.common.auth.admin_name', 'Admin');
                                $initials = collect(explode(' ', $adminName))->map(fn($n) => mb_substr($n, 0, 1))->take(2)->join('');
                            @endphp
                            <div class="user-avatar">{{ strtoupper($initials) }}</div>
                            <div class="d-none d-lg-block">
                                <div class="fw-bold small lh-1">{{ $adminName }}</div>
                                <small class="text-muted" style="font-size: 0.75rem;">{{ $cmsUser->email ?? config('cms-kit.common.auth.admin_email') }}</small>
                            </div>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2" aria-labelledby="userDropdown" style="border-radius: 12px; min-width: 180px;">
                            <li class="px-3 py-2 border-bottom d-lg-none">
                                <div class="fw-bold small lh-1 text-dark">{{ $adminName }}</div>
                                <small class="text-muted" style="font-size: 0.75rem;">{{ $cmsUser->email ?? config('cms-kit.common.auth.admin_email') }}</small>
                            </li>
                            <li>
                                <form action="{{ route('cms.logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2 py-2">
                                        <i class="fas fa-sign-out-alt small"></i>
                                        <span>Logout</span>
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <div class="offcanvas offcanvas-end notif-offcanvas" tabindex="-1" id="notifOffcanvas" aria-labelledby="notifOffcanvasLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="notifOffcanvasLabel">Notifications</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body p-0">
                    @if($unreadNotifCount > 0)
                    <div class="notif-offcanvas-actions">
                        <button type="button" id="notifMarkAllBtn" class="btn btn-sm btn-link p-0">Mark all read</button>
                    </div>
                    @endif
                    <div class="notif-list">
                        @forelse($recentNotifications as $notif)
                        <a href="{{ $notif->data['url'] ?? '#' }}" class="notif-item {{ $notif->read_at ? '' : 'is-unread' }} notif-mark-read" data-id="{{ $notif->id }}">
                            <span class="notif-icon tone-{{ $notif->data['tone'] ?? 'teal' }}"><i class="fas {{ $notif->data['icon'] ?? 'fa-bell' }}"></i></span>
                            <span class="notif-body">
                                <span class="notif-title d-block">{{ $notif->data['title'] ?? 'Notification' }}</span>
                                <span class="notif-message d-block">{{ $notif->data['message'] ?? '' }}</span>
                                <span class="notif-time">{{ $notif->created_at->diffForHumans() }}</span>
                            </span>
                        </a>
                        @empty
                        <div class="notif-empty"><i class="fas fa-bell-slash mb-2 d-block" style="font-size: 1.5rem;"></i>You're all caught up.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="main-content">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
            </div> <!-- end main-content -->
        </div> <!-- end main-wrapper -->
    </div> <!-- end d-flex -->

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof tinymce === 'undefined' || !document.querySelector('.tinymce-extra-field')) {
                return;
            }

            tinymce.init({
                selector: '.tinymce-extra-field',
                height: 350,
                plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
                toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
                branding: false,
                promotion: false
            });
        });
    </script>

    <script>
        // Site-wide fix for every admin form that splits per-language fields across Bootstrap
        // tabs (English/Arabic/...): a required field on a tab that ISN'T the active one is
        // invisible (display:none), so a validation failure there looks exactly like the
        // Save/Update button silently doing nothing. This runs on every CMS page, so any
        // module built with the same tab pattern gets it automatically — no per-page script
        // needed.
        document.addEventListener('DOMContentLoaded', function () {
            function activateTabFor(el) {
                const pane = el.closest('.tab-pane');
                if (!pane || !pane.id) return;
                const tabBtn = document.querySelector(`[data-bs-target="#${pane.id}"]`);
                if (tabBtn && !tabBtn.classList.contains('active')) {
                    bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                }
            }

            // Case 1: the page just reloaded after a server-side validation failure — Laravel's
            // error-bag markup already put .is-invalid on the offending field(s); jump to the
            // first one so it's actually visible instead of sitting on a hidden tab.
            const firstServerInvalid = document.querySelector('.tab-content .is-invalid');
            if (firstServerInvalid) {
                activateTabFor(firstServerInvalid);
            }

            // Case 2: native HTML5 "required" validation blocks the submit client-side, before
            // any request is sent — the browser can't show its tooltip on a hidden field, so
            // nothing visible happens at all. `invalid` doesn't bubble, so this must be a
            // capture-phase listener. One click fires this event for EVERY empty required field
            // at once (not just the first in DOM order) — the guard below only acts on the
            // first one per click, otherwise a still-empty English field would be skipped in
            // favor of jumping straight to Arabic.
            let handledThisAttempt = false;
            document.addEventListener('invalid', function (e) {
                if (handledThisAttempt) return;
                handledThisAttempt = true;
                setTimeout(() => { handledThisAttempt = false; }, 0);

                activateTabFor(e.target);
                setTimeout(() => e.target.focus(), 150);
            }, true);
        });
    </script>

    <script>
    (function () {
        // Show a "Processing..." spinner on the submit button the moment a form is actually
        // submitted (the browser only fires `submit` once native validation has passed),
        // and disable every submit button in it to prevent double-submits.
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!(form instanceof HTMLFormElement) || form.dataset.noSpinner) return;

            const submitBtns = form.querySelectorAll('button[type="submit"]');
            const clicked = (e.submitter && e.submitter.tagName === 'BUTTON') ? e.submitter : submitBtns[0];

            submitBtns.forEach(function (btn) { btn.disabled = true; });

            if (clicked) {
                if (clicked.dataset.originalHtml === undefined) {
                    clicked.dataset.originalHtml = clicked.innerHTML;
                }
                clicked.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
            }
        }, true);

        function restoreSubmitButtons(form) {
            form.querySelectorAll('button[type="submit"][data-original-html]').forEach(function (btn) {
                btn.innerHTML = btn.dataset.originalHtml;
                btn.disabled = false;
            });
        }
        window.restoreSubmitButtons = restoreSubmitButtons;

        // Guard against a stuck spinner if the page is restored from the back/forward cache.
        window.addEventListener('pageshow', function () {
            document.querySelectorAll('form').forEach(restoreSubmitButtons);
        });

        // After a failed server-side validation round trip, jump straight to the first
        // invalid field instead of leaving the admin to hunt for it in a long form. If that
        // field lives inside an inactive Bootstrap tab (e.g. the Arabic language tab), switch
        // to that tab first — otherwise scrollIntoView/focus silently land on a hidden element
        // and the admin never sees which tab the error is actually in.
        document.addEventListener('DOMContentLoaded', function () {
            const firstInvalid = document.querySelector('.is-invalid');
            if (!firstInvalid) return;

            const invalidTabPane = firstInvalid.closest('.tab-pane');
            if (invalidTabPane && !invalidTabPane.classList.contains('active') && window.bootstrap) {
                const tabBtn = document.querySelector('[data-bs-target="#' + invalidTabPane.id + '"], [href="#' + invalidTabPane.id + '"]');
                if (tabBtn) {
                    bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                }
            }

            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus({ preventScroll: true });
        });

        // Bell-icon notifications — mark as read on click (link navigation proceeds normally)
        document.querySelectorAll('.notif-mark-read').forEach(function (link) {
            link.addEventListener('click', function () {
                fetch("{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/notifications/" + this.dataset.id + "/read", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                });
            });
        });

        const notifMarkAllBtn = document.getElementById('notifMarkAllBtn');
        if (notifMarkAllBtn) {
            notifMarkAllBtn.addEventListener('click', function (e) {
                e.preventDefault();
                fetch("{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/notifications/read-all", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                }).then(function () { window.location.reload(); });
            });
        }
    })();
    </script>

    @stack('scripts')
</body>
</html>
