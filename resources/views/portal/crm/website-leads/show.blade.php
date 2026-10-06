@extends('portal.crm._layout')

@section('title', $lead->displayName() . ' — Website Lead')

{{--
    Website lead profile (Super Admin) — same layout as the CRM lead page (header, stats, tabs,
    cards). Profile tab = who they are and where they were routed, plus Transfer; Insights tab =
    their tracked website activity (visitor-insights._panel).
--}}
@php
    $nameParts = preg_split('/\s+/', trim($lead->displayName()));
    $initials = strtoupper(mb_substr($nameParts[0], 0, 1) . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : ''));
    $phone = $lead->formattedPhone();
    $telHref = $phone ? 'tel:' . preg_replace('/[^\d+]/', '', $phone) : null;
    $waHref = $phone ? 'https://wa.me/' . preg_replace('/\D+/', '', $phone) : null;
    $routed = $crmLeads->whereNotNull('portal_user_id');
    $fmt = fn ($s) => \App\Services\Visitors\VisitorInsights::duration((int) $s);
    $transferFrom = 'profile';
@endphp

@section('crm-content')
<div class="portal-lead-page">
    @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', function () { window.portalToast('success', @json(session('success'))); });</script>
    @endif
    @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- Header --}}
    <div class="portal-card portal-lp-header mb-3">
        <div class="portal-lp-header-top">
            <a href="{{ route('portal.crm.website-leads.index', ['status' => 'all']) }}" class="portal-lp-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Website leads</a>
            <div class="portal-lp-nav" role="group" aria-label="Lead navigation">
                <a @if($previousLeadId) href="{{ route('portal.crm.website-leads.show', $previousLeadId) }}" @endif class="portal-lp-icon-btn {{ $previousLeadId ? '' : 'disabled' }}" title="Previous lead" aria-label="Previous lead"><i class="fas fa-chevron-left"></i></a>
                <a @if($nextLeadId) href="{{ route('portal.crm.website-leads.show', $nextLeadId) }}" @endif class="portal-lp-icon-btn {{ $nextLeadId ? '' : 'disabled' }}" title="Next lead" aria-label="Next lead"><i class="fas fa-chevron-right"></i></a>
                <a href="{{ route('portal.crm.website-leads.index') }}" class="portal-lp-icon-btn" title="Close — back to website leads" aria-label="Close"><i class="fas fa-xmark"></i></a>
            </div>
        </div>

        <div class="portal-lp-header-main">
            <span class="portal-lp-initials">{{ $initials }}</span>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h1 class="portal-lp-name">{{ $lead->displayName() }}</h1>
                    <span class="portal-lp-status {{ $routed->isNotEmpty() ? 'is-active' : 'is-inactive' }}">{{ $routed->isNotEmpty() ? 'Routed' : 'In pool' }}</span>
                </div>
                <div class="portal-lp-header-meta">
                    <span><i class="fas fa-hashtag" aria-hidden="true"></i>{{ $lead->id }}</span>
                    <span><i class="far fa-calendar" aria-hidden="true"></i>First seen {{ \Illuminate\Support\Carbon::parse($stats['first_seen'])->format('d M Y, H:i') }}</span>
                    <span><i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>via {{ $lead->sourceLabel() }}</span>
                </div>
                <div class="portal-lp-header-chips">
                    @if($lead->user_id)
                    <span class="portal-lp-badge portal-lp-badge-accent"><i class="fas fa-user-check" aria-hidden="true"></i>Customer account</span>
                    @endif
                    @forelse($routed as $crmLead)
                    <span class="portal-lp-badge" style="--badge: #1d7a3a;"><i class="fas fa-share" aria-hidden="true"></i>{{ $crmLead->owner?->displayName() }}</span>
                    @empty
                    <span class="portal-lp-badge portal-lp-badge-warning"><i class="fas fa-inbox" aria-hidden="true"></i>Not routed yet</span>
                    @endforelse
                </div>
            </div>
            <div class="portal-lp-quick">
                @if($phone)
                <a href="{{ $telHref }}" class="portal-lp-quick-btn" title="Call {{ $phone }}"><i class="fas fa-phone"></i><span>Call</span></a>
                <a href="{{ $waHref }}" target="_blank" rel="noopener" class="portal-lp-quick-btn is-whatsapp" title="WhatsApp"><i class="fab fa-whatsapp"></i><span>WhatsApp</span></a>
                @endif
                @if($lead->email)
                <a href="mailto:{{ $lead->email }}" class="portal-lp-quick-btn" title="Email {{ $lead->email }}"><i class="fas fa-envelope"></i><span>Email</span></a>
                @endif
                <button type="button" class="portal-lp-quick-btn" data-wl-transfer title="Transfer to an agency or agent"><i class="fas fa-share"></i><span>Transfer</span></button>
            </div>
        </div>

        <div class="portal-lp-stats">
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon"><i class="fas fa-house"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Property views</div><div class="portal-lp-stat-value">{{ $stats['property_views'] }} <small class="text-muted fw-normal">· {{ $stats['properties_viewed'] }} listings</small></div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-accent"><i class="fas fa-clock"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Time on site</div><div class="portal-lp-stat-value">{{ $fmt($stats['site_seconds']) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-warning"><i class="fas fa-robot"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">AI chats</div><div class="portal-lp-stat-value">{{ $stats['chats'] }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-success"><i class="fas fa-clock-rotate-left"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Last active</div><div class="portal-lp-stat-value">{{ $stats['last_seen']?->diffForHumans() ?? '—' }}</div></div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav portal-lp-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#wlPageProfile" type="button" role="tab"><i class="fas fa-id-card" aria-hidden="true"></i>Profile</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="wlPageInsightsTab" data-bs-toggle="tab" data-bs-target="#wlPageInsights" type="button" role="tab"><i class="fas fa-chart-line" aria-hidden="true"></i>Insights <span class="portal-lp-tab-count">{{ $stats['property_views'] }}</span></button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- ============ Profile ============ --}}
        <div class="tab-pane fade show active" id="wlPageProfile" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-7">
                    <section class="portal-lp-card">
                        <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon"><i class="fas fa-user"></i></span>Name</h2></header>
                        <div class="portal-lp-card-body">
                            <div class="portal-lp-field"><i class="far fa-user" aria-hidden="true"></i>{{ $lead->name ?: '—' }}</div>
                        </div>
                    </section>

                    <section class="portal-lp-card">
                        <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon"><i class="fas fa-address-card"></i></span>Contact</h2></header>
                        <div class="portal-lp-card-body">
                            <div class="d-grid gap-2">
                                @if($phone)
                                <div class="portal-lp-contact is-primary">
                                    <span class="portal-lp-contact-icon"><i class="fas fa-phone" aria-hidden="true"></i></span>
                                    <a class="portal-lp-contact-value" href="{{ $telHref }}">{{ $phone }}</a>
                                    <div class="portal-lp-contact-actions"><a href="{{ $waHref }}" target="_blank" rel="noopener" title="WhatsApp" class="is-whatsapp"><i class="fab fa-whatsapp"></i></a></div>
                                </div>
                                @endif
                                @if($lead->email)
                                <div class="portal-lp-contact is-primary">
                                    <span class="portal-lp-contact-icon is-accent"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                                    <a class="portal-lp-contact-value" href="mailto:{{ $lead->email }}">{{ $lead->email }}</a>
                                </div>
                                @endif
                                @if(!$phone && !$lead->email)
                                <div class="portal-lp-empty">No contact details.</div>
                                @endif
                            </div>
                        </div>
                    </section>

                    <section class="portal-lp-card">
                        <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon"><i class="fas fa-circle-info"></i></span>Lead Basic Details</h2></header>
                        <div class="portal-lp-card-body">
                            <dl class="portal-lp-grid">
                                <div class="portal-lp-fact"><dt><i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>Came in via</dt><dd>{{ $lead->sourceLabel() }}</dd></div>
                                <div class="portal-lp-fact"><dt><i class="fas fa-user-check" aria-hidden="true"></i>Customer account</dt><dd>{{ $lead->user_id ? 'Yes' : 'No' }}</dd></div>
                                <div class="portal-lp-fact"><dt><i class="far fa-calendar" aria-hidden="true"></i>First seen</dt><dd>{{ \Illuminate\Support\Carbon::parse($stats['first_seen'])->format('d M Y, H:i') }}</dd></div>
                                <div class="portal-lp-fact"><dt><i class="fas fa-clock-rotate-left" aria-hidden="true"></i>Last active</dt><dd>{{ $stats['last_seen']?->format('d M Y, H:i') ?? '—' }}</dd></div>
                                <div class="portal-lp-fact"><dt><i class="fas fa-magnifying-glass" aria-hidden="true"></i>Searches</dt><dd>{{ $stats['searches'] }}</dd></div>
                                <div class="portal-lp-fact"><dt><i class="fas fa-heart" aria-hidden="true"></i>Favorites / saved searches</dt><dd>{{ $stats['favorites'] }} / {{ $stats['saved_searches'] }}</dd></div>
                                <div class="portal-lp-fact"><dt><i class="fas fa-envelope-open-text" aria-hidden="true"></i>Form enquiries</dt><dd>{{ $stats['enquiries'] }}</dd></div>
                                <div class="portal-lp-fact"><dt><i class="fas fa-stopwatch" aria-hidden="true"></i>Time on listings</dt><dd>{{ $fmt($stats['property_seconds']) }}</dd></div>
                            </dl>
                        </div>
                    </section>
                </div>

                <div class="col-lg-5">
                    <section class="portal-lp-card">
                        <header class="portal-lp-card-head">
                            <h2><span class="portal-lp-card-icon is-success"><i class="fas fa-share"></i></span>Routed To @if($crmLeads->count() > 1)<span class="portal-lp-count">{{ $crmLeads->count() }}</span>@endif</h2>
                            <button type="button" class="portal-lp-card-add" data-wl-transfer title="Transfer to an agency or agent"><i class="fas fa-plus"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <div class="d-grid gap-2">
                                @forelse($crmLeads as $crmLead)
                                <a href="{{ route('portal.crm.leads.show', $crmLead->id) }}" class="portal-lp-contact text-decoration-none">
                                    <span class="portal-lp-contact-icon is-accent"><i class="fas fa-building-user" aria-hidden="true"></i></span>
                                    <span class="min-w-0 flex-grow-1">
                                        <span class="d-block fw-semibold text-truncate">{{ $crmLead->owner?->displayName() ?? 'Unassigned Leads' }}</span>
                                        <small class="text-muted d-block text-truncate">{{ $crmLead->agent ? 'Agent ' . $crmLead->agent->name . ' · ' : '' }}{{ $crmLead->created_at->format('d M Y') }}{{ $crmLead->property ? ' · ' . $crmLead->property->getTranslation('title') : '' }}</small>
                                    </span>
                                    <i class="fas fa-chevron-right text-muted small" aria-hidden="true"></i>
                                </a>
                                @empty
                                <div class="portal-lp-empty">Not routed yet — they haven't viewed a listing that belongs to an agency or agent.</div>
                                @endforelse
                            </div>
                            <button type="button" class="btn btn-portal-primary w-100 mt-3" data-wl-transfer><i class="fas fa-share me-1"></i>Transfer lead</button>
                        </div>
                    </section>

                    <section class="portal-lp-card">
                        <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon is-warning"><i class="fas fa-fire"></i></span>Most Interested In</h2></header>
                        <div class="portal-lp-card-body">
                            <div class="d-grid gap-2">
                                @forelse($topProperties->take(3) as $row)
                                <div class="portal-lp-property mb-0">
                                    <span class="portal-lp-property-icon"><i class="fas fa-building"></i></span>
                                    <div class="min-w-0 flex-grow-1">
                                        <a href="{{ url('/property-details/' . $row['property']->slug) }}" target="_blank" rel="noopener" class="portal-lp-property-title">{{ $row['property']->getTranslation('title') ?: $row['property']->reference_no }} <i class="fas fa-arrow-up-right-from-square"></i></a>
                                        <div class="portal-lp-property-ref">{{ $row['views'] }} {{ \Illuminate\Support\Str::plural('view', $row['views']) }} · {{ $fmt($row['seconds']) }} · {{ $row['property']->owner?->displayName() ?? 'MW Realty' }}</div>
                                    </div>
                                </div>
                                @empty
                                <div class="portal-lp-empty">No property detail pages viewed yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        {{-- ============ Insights ============ --}}
        <div class="tab-pane fade" id="wlPageInsights" role="tabpanel">
            @include('visitor-insights._panel')
        </div>
    </div>
</div>

@include('portal.crm.website-leads._transfer_modal')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // …/website-leads/{id}#insights opens straight on the Insights tab.
    if (window.location.hash === '#insights') {
        bootstrap.Tab.getOrCreateInstance(document.getElementById('wlPageInsightsTab')).show();
    }
    document.querySelectorAll('[data-wl-transfer]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.openWebsiteLeadTransfer({ ids: [{{ $lead->id }}], count: 1 });
        });
    });
});
</script>
@endpush
