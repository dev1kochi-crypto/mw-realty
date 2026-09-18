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
    <link rel="stylesheet" href="{{ asset('portal/css/portal.css') }}?v={{ filemtime(public_path('portal/css/portal.css')) }}">
    <style>
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
    @stack('styles')
</head>
<body class="portal-body">
    <div class="portal-shell">
        <aside class="portal-sidebar">
            <div class="portal-brand portal-brand--sidebar">
                <img src="{{ asset('frontend/assets/images/logo.png') }}" alt="MW Realty" class="portal-brand__icon portal-brand__icon--only">
            </div>

            @php
                $notApproved = $owner && $owner->status !== 'approved';
            @endphp
            <nav class="nav flex-column">
                <a href="{{ route('portal.dashboard') }}" class="nav-link @if(request()->routeIs('portal.dashboard')) active @endif">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>

                <div class="nav-section-label">Leads</div>
                <a href="{{ route('portal.crm.leads.index') }}" class="nav-link @if(request()->routeIs('portal.crm.leads.*')) active @endif">
                    <i class="fas fa-address-book"></i> Leads
                    @if($notApproved)<span class="portal-nav-lock" title="Unlocks after KYC approval"><i class="fas fa-lock"></i></span>@endif
                </a>

                <div class="nav-item portal-nav-group">
                    <a class="nav-link portal-nav-group-toggle @if(request()->routeIs('portal.crm.master.*')) active @endif"
                       data-bs-toggle="collapse" href="#masterMenu" role="button"
                       aria-expanded="@if(request()->routeIs('portal.crm.master.*')) true @else false @endif">
                        <i class="fas fa-layer-group"></i> <span>Master</span>
                        <i class="fas fa-chevron-down ms-auto portal-nav-chevron"></i>
                    </a>
                    <div class="collapse portal-nav-submenu @if(request()->routeIs('portal.crm.master.*')) show @endif" id="masterMenu">
                        <nav class="nav flex-column">
                            <a class="nav-link py-2 @if(request()->routeIs('portal.crm.master.stages.*')) active @endif" href="{{ route('portal.crm.master.stages.index') }}">Stage</a>
                            <a class="nav-link py-2 @if(request()->routeIs('portal.crm.master.tags.*')) active @endif" href="{{ route('portal.crm.master.tags.index') }}">Tag</a>
                            <a class="nav-link py-2 @if(request()->routeIs('portal.crm.master.sources.*')) active @endif" href="{{ route('portal.crm.master.sources.index') }}">Source</a>
                        </nav>
                    </div>
                </div>

                @if($owner)
                <div class="nav-section-label">Account</div>
                <a href="{{ route('portal.profile.edit') }}" class="nav-link @if(request()->routeIs('portal.profile.*')) active @endif">
                    <i class="fas fa-user-edit"></i> My Profile
                    @if($notApproved)
                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.6rem;">!</span>
                    @endif
                </a>
                @endif

                <div class="nav-section-label">Listings</div>
                <a href="{{ route('portal.properties.index') }}" class="nav-link @if(request()->routeIs('portal.properties.*')) active @endif">
                    <i class="fas fa-building"></i> Properties
                    @if($notApproved)<span class="portal-nav-lock" title="Unlocks after KYC approval"><i class="fas fa-lock"></i></span>@endif
                </a>
                @if($cmsActor || $owner?->type === 'company')
                <a href="{{ route('portal.agents.index') }}" class="nav-link @if(request()->routeIs('portal.agents.*')) active @endif">
                    <i class="fas fa-user-tie"></i> Agents
                </a>
                @endif
                @if($cmsActor)
                <a href="{{ route('portal.nearby-places.index') }}" class="nav-link @if(request()->routeIs('portal.nearby-places.*')) active @endif">
                    <i class="fas fa-map-marker-alt"></i> Nearby Places
                </a>
                @endif

                <div class="nav-section-label">Insights</div>
                <a href="{{ route('portal.crm.reports.index') }}" class="nav-link @if(request()->routeIs('portal.crm.reports.*')) active @endif">
                    <i class="fas fa-chart-pie"></i> Reports
                    @if($notApproved)<span class="portal-nav-lock" title="Unlocks after KYC approval"><i class="fas fa-lock"></i></span>@endif
                </a>

                @if($owner)
                <div class="nav-section-label">Support</div>
                <a href="{{ route('portal.contact.index') }}" class="nav-link @if(request()->routeIs('portal.contact.*')) active @endif">
                    <i class="fas fa-headset"></i> Contact Us
                </a>

                <div class="nav-section-label">Billing</div>
                <a href="{{ route('portal.plans.index') }}" class="nav-link @if(request()->routeIs('portal.plans.*')) active @endif">
                    <i class="fas fa-layer-group"></i> Plans
                </a>
                @endif
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
                    @if($cmsActor)
                    <a href="{{ route('cms.dashboard') }}" class="portal-admin-pill">
                        <i class="fas fa-shield-alt"></i> Back to Admin
                    </a>
                    @endif
                    @if($owner && !$owner->plan?->isTopTier())
                    <a href="{{ route('portal.plans.index') }}" class="btn-portal-upgrade-cta">
                        <span class="btn-portal-upgrade-shine"></span>
                        <i class="fas fa-rocket"></i> Upgrade Plan
                    </a>
                    @endif
                    <a href="/" target="_blank" class="portal-btn-ghost btn btn-sm">
                        <i class="fas fa-external-link-alt me-1"></i> View Site
                    </a>

                    @php
                        $notifiable = $owner ?? $cmsActor;
                        $recentNotifications = $notifiable?->notifications()->latest()->take(20)->get();
                        $unreadNotifCount = $notifiable?->unreadNotifications()->count() ?? 0;
                    @endphp
                    <button type="button" class="notif-bell-btn" id="notifBellBtn" data-bs-toggle="offcanvas" data-bs-target="#notifOffcanvas" aria-controls="notifOffcanvas">
                        <i class="fas fa-bell"></i>
                        @if($unreadNotifCount > 0)
                        <span class="notif-bell-badge">{{ $unreadNotifCount > 9 ? '9+' : $unreadNotifCount }}</span>
                        @endif
                    </button>

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
                                @forelse($recentNotifications ?? [] as $notif)
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

                    @php
                        $profileName = $owner ? ($owner->type === 'company' ? ($owner->company_name ?: $owner->name) : $owner->name) : ($cmsActor->name ?? 'Super Admin');
                        $profileInitials = strtoupper(collect(preg_split('/\s+/', trim($profileName)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '?';
                    @endphp
                    <div class="dropdown">
                        <button type="button" class="portal-profile-btn" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="portal-avatar-circle">{{ $profileInitials }}</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end portal-profile-menu">
                            <li class="portal-profile-menu-header">
                                <div class="fw-bold">{{ $profileName }}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">{{ $owner ? ucfirst($owner->type) . ' Account' : 'Super Admin' }}</div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            @if($owner)
                            <li><a class="dropdown-item" href="{{ route('portal.profile.edit') }}"><i class="fas fa-user-edit me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="{{ route('portal.plans.index') }}"><i class="fas fa-layer-group me-2"></i>Plans</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('portal.logout') }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</button>
                                </form>
                            </li>
                            @else
                            <li>
                                <form action="{{ route('cms.logout') }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</button>
                                </form>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </header>

            <main class="portal-content">
                @if($owner && $owner->status !== 'approved')
                    <div class="alert @if($owner->status === 'rejected') alert-danger @else alert-warning @endif d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            @if($owner->status === 'pending')
                                <i class="fas fa-clock me-2"></i>
                                <strong>Your account is pending Super Admin approval.</strong>
                                Leads, Reports and property listing unlock once your KYC documents are reviewed and your account is approved — complete your profile and upload documents now.
                            @else
                                <i class="fas fa-ban me-2"></i>
                                <strong>Your account application was rejected.</strong>
                                @if($owner->rejection_reason)
                                    Reason: {{ $owner->rejection_reason }}.
                                @endif
                                Update your profile and resubmit for review — CRM access unlocks once you're approved.
                            @endif
                        </div>
                        <a href="{{ route('portal.profile.edit') }}" class="btn btn-sm btn-dark">
                            <i class="fas fa-user-edit me-1"></i> {{ $owner->status === 'rejected' ? 'Fix & Resubmit' : 'Complete Profile' }}
                        </a>
                    </div>
                @endif

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('info'))
                    <div class="alert alert-info">{{ session('info') }}</div>
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
            // Every submit button in the form was disabled above, but only the one actually
            // clicked got a spinner (and `data-original-html`) — so all of them need re-enabling
            // here, not just that one, or a second button (e.g. a duplicate top/bottom Save) stays
            // stuck disabled when a submit is cancelled (client-side validation, for instance)
            // instead of proceeding.
            form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                if (btn.dataset.originalHtml !== undefined) btn.innerHTML = btn.dataset.originalHtml;
                btn.disabled = false;
            });
        }
        window.restoreSubmitButtons = restoreSubmitButtons;

        // Guard against a stuck spinner if the page is restored from the back/forward cache.
        window.addEventListener('pageshow', function () {
            document.querySelectorAll('form').forEach(restoreSubmitButtons);
        });

        // After a failed server-side validation round trip, jump straight to the first
        // invalid field instead of leaving the user to hunt for it in a long form.
        document.addEventListener('DOMContentLoaded', function () {
            const firstInvalid = document.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus({ preventScroll: true });
            }
        });

        // Bell-icon notifications — mark as read on click (link navigation proceeds normally)
        document.querySelectorAll('.notif-mark-read').forEach(function (link) {
            link.addEventListener('click', function () {
                fetch("{{ url('/portal/notifications') }}/" + this.dataset.id + "/read", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                });
            });
        });

        const notifMarkAllBtn = document.getElementById('notifMarkAllBtn');
        if (notifMarkAllBtn) {
            notifMarkAllBtn.addEventListener('click', function (e) {
                e.preventDefault();
                fetch("{{ url('/portal/notifications/read-all') }}", {
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
