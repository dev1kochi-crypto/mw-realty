<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - Partner Portal</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('portal/css/portal.css') }}">
    @stack('styles')
</head>
<body class="portal-body">
    <div class="portal-shell">
        <aside class="portal-sidebar">
            <div class="portal-brand">
                <span class="badge-dot"></span> MW Realty
            </div>

            <nav class="nav flex-column">
                <a href="{{ route('portal.dashboard') }}" class="nav-link @if(request()->routeIs('portal.dashboard')) active @endif">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>

                <div class="nav-section-label">Listings</div>
                <a href="{{ route('portal.properties.index') }}" class="nav-link @if(request()->routeIs('portal.properties.*')) active @endif">
                    <i class="fas fa-building"></i> Properties
                </a>

                <div class="nav-section-label">Leads</div>
                <a href="{{ route('portal.crm.index') }}" class="nav-link @if(request()->routeIs('portal.crm.*')) active @endif">
                    <i class="fas fa-address-book"></i> CRM
                </a>
            </nav>
        </aside>

        <div class="portal-main">
            <header class="portal-topbar">
                <div>
                    @if($owner)
                        <div class="fw-bold">{{ $owner->type === 'company' ? ($owner->company_name ?: $owner->name) : $owner->name }}</div>
                        <span class="portal-account-type"><i class="fas fa-circle" style="font-size:6px;"></i> {{ ucfirst($owner->type) }} Account</span>
                    @else
                        <div class="fw-bold">{{ $cmsActor->name ?? 'Super Admin' }}</div>
                        <span class="portal-account-type"><i class="fas fa-circle" style="font-size:6px;"></i> Super Admin &middot; Global View</span>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="portal-app-switcher">
                        @if($cmsActor)
                        <a href="{{ route('cms.dashboard') }}" class="portal-switch-pill">
                            <i class="fas fa-shield-alt"></i> Admin
                        </a>
                        @endif
                        <a href="{{ route('portal.properties.index') }}" class="portal-switch-pill @if(request()->routeIs('portal.properties.*')) active @endif">
                            <i class="fas fa-building"></i> Properties
                        </a>
                        <a href="{{ route('portal.crm.index') }}" class="portal-switch-pill @if(request()->routeIs('portal.crm.*')) active @endif">
                            <i class="fas fa-address-book"></i> CRM
                        </a>
                    </div>
                    <a href="/" target="_blank" class="portal-btn-ghost btn btn-sm">
                        <i class="fas fa-external-link-alt me-1"></i> View Site
                    </a>
                    @if($owner)
                    <form action="{{ route('portal.logout') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="portal-btn-ghost btn btn-sm">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </button>
                    </form>
                    @else
                    <form action="{{ route('cms.logout') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="portal-btn-ghost btn btn-sm">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </button>
                    </form>
                    @endif
                </div>
            </header>

            <main class="portal-content">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
