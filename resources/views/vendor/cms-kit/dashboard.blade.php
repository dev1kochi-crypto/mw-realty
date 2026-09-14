@extends('cms-kit::layouts.cms')

@section('title', 'Dashboard')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Overview</li>
@endsection

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --dash-navy: var(--secondary-color, #264373);
        --dash-teal: var(--primary-color, #04a1cc);
        --dash-green: #0f9d58;
        --dash-green-2: #34c880;
        --dash-amber: #e08e0b;
        --dash-amber-2: #f5a623;
        --dash-slate: #5b6478;
        --dash-slate-2: #8891a8;
        --dash-ink: #1c2340;
        --dash-muted: #838aa3;
        --dash-bg-soft: #f7f8fc;
        --dash-border: rgba(28, 35, 64, 0.07);

        /* Muted accent palette — one dim tone per section, kept subtle (icon badges + thin borders
           only), not painted across whole cards. Standard admin-dashboard look, not a rainbow. */
        --v-blue: #5b7fb0;    --v-blue-2: #8098bf;
        --v-purple: #8078ab;  --v-purple-2: #9d97bf;
        --v-pink: #ae7e93;    --v-pink-2: #bf9aab;
        --v-orange: #c08a54;  --v-orange-2: #cfa578;
        --v-cyan: #4c8994;    --v-cyan-2: #74a3ac;
        --v-emerald: #5a9a7c; --v-emerald-2: #7fb097;
        --v-amber: #b99049;   --v-amber-2: #c9ab74;
        --v-rose: #a9636c;    --v-rose-2: #bd868d;
    }

    .main-content {
        background: var(--dash-bg-soft);
    }

    .dash-hero {
        background: linear-gradient(120deg, var(--dash-navy) 0%, #16294f 55%, var(--dash-teal) 145%);
        border-radius: 16px;
        padding: 1.5rem 1.85rem;
        color: #fff;
        margin-bottom: 1.5rem;
    }
    .dash-hero h4 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; margin-bottom: 0.2rem; font-size: 1.3rem; }
    .dash-hero p { opacity: 0.78; margin-bottom: 0; font-size: 0.85rem; }
    .dash-hero-chips { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .hero-chip {
        background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.22);
        border-radius: 12px; padding: 0.55rem 0.9rem; min-width: 92px; backdrop-filter: blur(6px);
    }
    .hero-chip-value { display: block; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.05rem; line-height: 1.15; }
    .hero-chip-label { display: block; font-size: 0.65rem; opacity: 0.8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }

    .dash-section-label {
        font-weight: 800; font-size: 0.72rem; letter-spacing: 0.06em; text-transform: uppercase;
        color: var(--dash-navy); margin-bottom: 0.9rem; padding-bottom: 0.5rem;
        border-bottom: 2px solid var(--dash-border);
    }

    /* KPI strip — standard white card with a colored icon badge; the fill-* class some of these
       carry (for buttons elsewhere) is deliberately overridden back to white here so the card
       itself stays calm and only the icon carries color. */
    .stat-mini {
        border-radius: 14px; border: 1px solid var(--dash-border); background: #fff !important;
        box-shadow: 0 4px 14px rgba(28,35,64,0.05);
        padding: 0.9rem 1rem; display: flex; align-items: center; gap: 0.75rem;
        text-decoration: none; color: inherit !important; height: 100%;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .stat-mini:hover { transform: translateY(-3px); box-shadow: 0 10px 22px rgba(28,35,64,0.09); color: inherit !important; }
    .stat-mini-icon { width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; }
    .stat-mini-value { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.2rem; line-height: 1.1; color: var(--dash-ink) !important; }
    .stat-mini-label { font-size: 0.68rem; font-weight: 700; color: var(--dash-muted) !important; text-transform: uppercase; letter-spacing: 0.02em; }

    .fill-teal    { background: linear-gradient(135deg, var(--dash-teal), #2fc4e8); }
    .fill-navy    { background: linear-gradient(135deg, var(--dash-navy), #3a5794); }
    .fill-green   { background: linear-gradient(135deg, var(--dash-green), var(--dash-green-2)); }
    .fill-amber   { background: linear-gradient(135deg, var(--v-amber), var(--v-amber-2)); }
    .fill-slate   { background: linear-gradient(135deg, var(--dash-slate), var(--dash-slate-2)); }
    .fill-blue    { background: linear-gradient(135deg, var(--v-blue), var(--v-blue-2)); }
    .fill-purple  { background: linear-gradient(135deg, var(--v-purple), var(--v-purple-2)); }
    .fill-pink    { background: linear-gradient(135deg, var(--v-pink), var(--v-pink-2)); }
    .fill-orange  { background: linear-gradient(135deg, var(--v-orange), var(--v-orange-2)); }
    .fill-cyan    { background: linear-gradient(135deg, var(--v-cyan), var(--v-cyan-2)); }
    .fill-emerald { background: linear-gradient(135deg, var(--v-emerald), var(--v-emerald-2)); }
    .fill-rose    { background: linear-gradient(135deg, var(--v-rose), var(--v-rose-2)); }

    .dash-card { border-radius: 16px; border: 1px solid var(--dash-border); border-top: 3px solid transparent; background: #fff; box-shadow: 0 4px 14px rgba(28,35,64,0.05); padding: 1.25rem 1.4rem; height: 100%; }
    .dash-card h6 { font-weight: 800; font-size: 0.85rem; margin-bottom: 1rem; color: var(--dash-ink); display: flex; align-items: center; gap: 0.5rem; }
    .dash-card h6 i { color: var(--dash-muted); font-size: 0.85rem; }
    .dash-card.accent-blue    { border-top-color: var(--v-blue); }
    .dash-card.accent-purple  { border-top-color: var(--v-purple); }
    .dash-card.accent-pink    { border-top-color: var(--v-pink); }
    .dash-card.accent-orange  { border-top-color: var(--v-orange); }
    .dash-card.accent-cyan    { border-top-color: var(--v-cyan); }
    .dash-card.accent-emerald { border-top-color: var(--v-emerald); }
    .dash-card.accent-rose    { border-top-color: var(--v-rose); }
    .dash-card.accent-blue h6 i    { color: var(--v-blue); }
    .dash-card.accent-purple h6 i  { color: var(--v-purple); }
    .dash-card.accent-pink h6 i    { color: var(--v-pink); }
    .dash-card.accent-orange h6 i  { color: var(--v-orange); }
    .dash-card.accent-cyan h6 i    { color: var(--v-cyan); }
    .dash-card.accent-emerald h6 i { color: var(--v-emerald); }
    .dash-card.accent-rose h6 i    { color: var(--v-rose); }

    /* Pipeline v2 — soft-tint stage cards linked by arrows */
    .pipeline-v2 { display: flex; align-items: stretch; gap: 0.5rem; }
    .pipeline-card { flex: 1; border-radius: 14px; padding: 1rem 1.15rem; }
    .pipeline-card.tone-blue    { background: rgba(91,127,176,0.09); }
    .pipeline-card.tone-orange  { background: rgba(192,138,84,0.09); }
    .pipeline-card.tone-emerald { background: rgba(90,154,124,0.09); }
    .pipeline-card .pc-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.7rem; }
    .pipeline-card .pc-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.82rem; }
    .pipeline-card .pc-pct { font-size: 0.72rem; font-weight: 800; color: var(--dash-muted); }
    .pipeline-card .pc-num { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.6rem; color: var(--dash-ink); line-height: 1; }
    .pipeline-card .pc-label { font-size: 0.78rem; font-weight: 700; color: #4b5065; margin-top: 0.2rem; }
    .pipeline-card .pc-track { height: 6px; border-radius: 50px; background: rgba(28,35,64,0.08); margin-top: 0.8rem; overflow: hidden; }
    .pipeline-card .pc-fill { height: 100%; border-radius: 50px; }
    .pipeline-arrow { display: flex; align-items: center; justify-content: center; color: var(--dash-slate-2); font-size: 0.95rem; flex: 0 0 24px; }

    .breakdown-row { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 0.75rem; }
    .breakdown-row:last-child { margin-bottom: 0; }
    .breakdown-label { flex: 0 0 105px; font-size: 0.78rem; font-weight: 700; color: #4b5065; }
    .breakdown-bar-track { flex: 1; height: 9px; border-radius: 50px; background: var(--dash-bg-soft); overflow: hidden; }
    .breakdown-bar-fill { height: 100%; border-radius: 50px; }
    .breakdown-count { flex: 0 0 30px; text-align: right; font-weight: 800; font-size: 0.82rem; color: var(--dash-ink); }

    .mini-table { width: 100%; font-size: 0.83rem; }
    .mini-table td, .mini-table th { padding: 0.55rem 0.4rem; vertical-align: middle; }
    .mini-table thead th { font-size: 0.66rem; text-transform: uppercase; letter-spacing: 0.02em; color: var(--dash-muted); border-bottom: 1.5px solid var(--dash-border); font-weight: 800; }
    .mini-table tbody tr { border-bottom: 1px solid #f5f6fa; }
    .mini-table tbody tr:last-child { border-bottom: none; }

    .dash-tabs .nav-link { font-size: 0.78rem; font-weight: 700; color: var(--dash-muted); border-radius: 50px; padding: 0.35rem 0.9rem; }
    .dash-tabs .nav-link.active { background: linear-gradient(135deg, var(--v-purple), var(--v-blue)); color: #fff; }

    .action-btn-stack .btn { border: none; border-radius: 12px; padding: 0.75rem 1rem; font-weight: 700; font-size: 0.85rem; text-align: left; color: #fff; display: flex; align-items: center; }
    .action-btn-stack .btn i { width: 22px; }

    .pending-row, .action-row { display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px solid #f5f6fa; font-size: 0.85rem; }
    .pending-row:last-child, .action-row:last-child { border-bottom: none; }

    .fin-strip { display: flex; flex-wrap: wrap; gap: 0.6rem; }
    .fin-item { flex: 1; min-width: 135px; border-radius: 12px; background: var(--dash-bg-soft); padding: 0.85rem 1rem; }
    .fin-item .fin-value { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.1rem; color: var(--dash-ink); }
    .fin-item .fin-label { font-size: 0.68rem; color: var(--dash-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }

    .approval-big { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 2rem; color: var(--dash-ink); line-height: 1; }

    /* Revenue trend toggle */
    .btn-period {
        border: 1px solid var(--dash-border); background: #fff; color: var(--dash-muted);
        font-weight: 700; font-size: 0.72rem; padding: 0.32rem 0.75rem;
    }
    .btn-period.active { background: var(--v-emerald); border-color: var(--v-emerald); color: #fff; }
    .btn-period:hover { color: var(--v-emerald); }
    .revenue-chart-wrap { position: relative; height: 210px; }

    /* Top performers — card list */
    .performer-list { display: flex; flex-direction: column; }
    .performer-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.15rem; border-bottom: 1px solid #f5f6fa; }
    .performer-row:last-child { border-bottom: none; }
    .performer-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 0.76rem; flex-shrink: 0; }
    .performer-info { flex: 1; min-width: 0; }
    .performer-name { font-weight: 700; font-size: 0.85rem; color: var(--dash-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .performer-type { font-size: 0.7rem; color: var(--dash-muted); font-weight: 600; }
    .performer-stats { display: flex; gap: 0.4rem; flex-shrink: 0; }
    .performer-stat { font-size: 0.74rem; font-weight: 700; color: var(--dash-navy); background: var(--dash-bg-soft); border-radius: 50px; padding: 0.25rem 0.55rem; display: inline-flex; align-items: center; gap: 0.3rem; }
    .performer-stat i { font-size: 0.66rem; color: var(--dash-muted); }
</style>
@endpush

@section('content')
<div class="dash-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
        <div>
            <h4>Welcome back, {{ $cmsUser->name ?? 'Super Admin' }} 👋</h4>
            <p>{{ now()->format('l, d M Y') }} &middot; here's today's snapshot across MW Realty.</p>
        </div>
        <div class="dash-hero-chips">
            <div class="hero-chip">
                <span class="hero-chip-value">{{ $stats['total_properties'] }}</span>
                <span class="hero-chip-label">Listings</span>
            </div>
            <div class="hero-chip">
                <span class="hero-chip-value">{{ $stats['approved_agents'] + $stats['approved_companies'] }}</span>
                <span class="hero-chip-label">Partners</span>
            </div>
            <div class="hero-chip">
                <span class="hero-chip-value">{{ $stats['new_crm_leads'] }}</span>
                <span class="hero-chip-label">New Leads</span>
            </div>
            <div class="hero-chip">
                <span class="hero-chip-value">{{ $stats['pending_accounts'] }}</span>
                <span class="hero-chip-label">Awaiting Approval</span>
            </div>
            <div class="hero-chip">
                <span class="hero-chip-value">AED {{ number_format($stats['monthly_revenue']) }}</span>
                <span class="hero-chip-label">Est. MRR</span>
            </div>
        </div>
    </div>
</div>

@if($stats['pending_accounts'] > 0 && $cmsUser->can('portal-accounts.view'))
<div class="alert alert-warning d-flex justify-content-between align-items-center shadow-sm mb-3" style="border-radius: 14px;">
    <div><i class="fas fa-exclamation-triangle me-2"></i><strong>{{ $stats['pending_accounts'] }}</strong> agent/company {{ \Illuminate\Support\Str::plural('account', $stats['pending_accounts']) }} awaiting approval.</div>
    <a href="{{ route('cms.portal-accounts.index') }}" class="btn btn-sm btn-warning">Review Now</a>
</div>
@endif

<!-- KPI strip — Clients & Plans lead since they're the core product; Properties/CRM
     (driven by the public website) follow as secondary, day-to-day operational stats. -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3 col-xl">
        <a href="{{ route('cms.portal-accounts.index', ['type' => 'agent']) }}" class="stat-mini fill-blue">
            <span class="stat-mini-icon fill-navy"><i class="fas fa-user-tie"></i></span>
            <div><div class="stat-mini-value">{{ $stats['approved_agents'] }}</div><div class="stat-mini-label">Agents</div></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <a href="{{ route('cms.portal-accounts.index', ['type' => 'company']) }}" class="stat-mini fill-purple">
            <span class="stat-mini-icon fill-navy"><i class="fas fa-building-user"></i></span>
            <div><div class="stat-mini-value">{{ $stats['approved_companies'] }}</div><div class="stat-mini-label">Companies</div></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <a href="{{ route('cms.portal-accounts.index', ['status' => 'pending']) }}" class="stat-mini fill-orange">
            <span class="stat-mini-icon fill-amber"><i class="fas fa-user-clock"></i></span>
            <div><div class="stat-mini-value">{{ $stats['pending_accounts'] }}</div><div class="stat-mini-label">Awaiting Approval</div></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <a href="{{ route('cms.plans.index') }}" class="stat-mini fill-emerald">
            <span class="stat-mini-icon fill-green"><i class="fas fa-coins"></i></span>
            <div><div class="stat-mini-value">{{ number_format($stats['monthly_revenue']) }}</div><div class="stat-mini-label">AED / mo</div></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <a href="{{ route('portal.properties.index') }}" class="stat-mini fill-cyan">
            <span class="stat-mini-icon fill-teal"><i class="fas fa-building"></i></span>
            <div><div class="stat-mini-value">{{ $stats['total_properties'] }}</div><div class="stat-mini-label">Properties</div></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <a href="{{ route('portal.properties.index') }}" class="stat-mini fill-rose">
            <span class="stat-mini-icon fill-green"><i class="fas fa-check-circle"></i></span>
            <div><div class="stat-mini-value">{{ $stats['active_properties'] }}</div><div class="stat-mini-label">Active</div></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <a href="{{ route('portal.crm.leads.index') }}" class="stat-mini fill-pink">
            <span class="stat-mini-icon fill-teal"><i class="fas fa-address-book"></i></span>
            <div><div class="stat-mini-value">{{ $stats['crm_leads'] }}</div><div class="stat-mini-label">CRM Leads</div></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <a href="{{ route('portal.crm.leads.index', ['status' => 'active']) }}" class="stat-mini fill-amber">
            <span class="stat-mini-icon fill-amber"><i class="fas fa-bell"></i></span>
            <div><div class="stat-mini-value">{{ $stats['new_crm_leads'] }}</div><div class="stat-mini-label">New Leads</div></div>
        </a>
    </div>
</div>

<!-- CRM pipeline -->
<div class="dash-card accent-cyan mb-3">
    <h6><i class="fas fa-stream"></i> CRM Lead Pipeline</h6>
    @php
        $newCount = $leadStatusBreakdown['new'] ?? 0;
        $contactedCount = $leadStatusBreakdown['contacted'] ?? 0;
        $closedCount = $leadStatusBreakdown['closed'] ?? 0;
        $pipelineTotal = max($newCount + $contactedCount + $closedCount, 1);
    @endphp
    <div class="pipeline-v2">
        <div class="pipeline-card tone-blue">
            <div class="pc-top">
                <span class="pc-icon fill-blue"><i class="fas fa-inbox"></i></span>
                <span class="pc-pct">{{ round($newCount / $pipelineTotal * 100) }}%</span>
            </div>
            <div class="pc-num">{{ $newCount }}</div>
            <div class="pc-label">New</div>
            <div class="pc-track"><div class="pc-fill fill-blue" style="width: {{ round($newCount / $pipelineTotal * 100) }}%;"></div></div>
        </div>
        <div class="pipeline-arrow"><i class="fas fa-chevron-right"></i></div>
        <div class="pipeline-card tone-orange">
            <div class="pc-top">
                <span class="pc-icon fill-orange"><i class="fas fa-comments"></i></span>
                <span class="pc-pct">{{ round($contactedCount / $pipelineTotal * 100) }}%</span>
            </div>
            <div class="pc-num">{{ $contactedCount }}</div>
            <div class="pc-label">Contacted</div>
            <div class="pc-track"><div class="pc-fill fill-orange" style="width: {{ round($contactedCount / $pipelineTotal * 100) }}%;"></div></div>
        </div>
        <div class="pipeline-arrow"><i class="fas fa-chevron-right"></i></div>
        <div class="pipeline-card tone-emerald">
            <div class="pc-top">
                <span class="pc-icon fill-emerald"><i class="fas fa-check-double"></i></span>
                <span class="pc-pct">{{ round($closedCount / $pipelineTotal * 100) }}%</span>
            </div>
            <div class="pc-num">{{ $closedCount }}</div>
            <div class="pc-label">Closed</div>
            <div class="pc-track"><div class="pc-fill fill-emerald" style="width: {{ round($closedCount / $pipelineTotal * 100) }}%;"></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Property type donut -->
    <div class="col-lg-3">
        <div class="dash-card accent-blue">
            <h6><i class="fas fa-chart-pie"></i> Properties by Type</h6>
            @if($propertyTypeBreakdown->sum())
                <canvas id="propertyTypeChart" width="220" height="200"></canvas>
            @else
                <div class="text-muted text-center py-5" style="font-size: 0.85rem;">No properties yet.</div>
            @endif
        </div>
    </div>

    <!-- Tabbed table -->
    <div class="col-lg-5">
        <div class="dash-card accent-purple">
            <ul class="nav dash-tabs mb-3" role="tablist" style="gap: 0.4rem;">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-owners" type="button">Top Performers</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-leads" type="button">Recent Leads</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-properties" type="button">Latest Properties</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-owners">
                    <div class="performer-list">
                        @forelse($topOwners as $owner)
                        @php
                            $ownerLabel = $owner->type === 'company' ? ($owner->company_name ?: $owner->name) : $owner->name;
                            $initials = collect(preg_split('/\s+/', trim($ownerLabel)))->filter()->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('');
                        @endphp
                        <div class="performer-row">
                            <div class="performer-avatar {{ $owner->type === 'company' ? 'fill-purple' : 'fill-blue' }}">{{ strtoupper($initials) ?: '?' }}</div>
                            <div class="performer-info">
                                <div class="performer-name">{{ $ownerLabel }}</div>
                                <div class="performer-type">{{ ucfirst($owner->type) }}</div>
                            </div>
                            <div class="performer-stats">
                                <span class="performer-stat"><i class="fas fa-building"></i> {{ $owner->properties_count }}</span>
                                <span class="performer-stat"><i class="fas fa-address-book"></i> {{ $owner->leads_count }}</span>
                            </div>
                        </div>
                        @empty
                        <div class="text-center text-muted py-3" style="font-size: 0.85rem;">No approved accounts yet.</div>
                        @endforelse
                    </div>
                </div>
                <div class="tab-pane fade" id="tab-leads">
                    <table class="mini-table">
                        <thead><tr><th>Lead</th><th>Property</th><th>Owner</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($recentLeads as $lead)
                            <tr>
                                <td class="fw-semibold">{{ $lead->name ?: 'Unknown' }}</td>
                                <td>{{ $lead->property?->getTranslation('title') ?? '-' }}</td>
                                <td>{{ $lead->owner ? ($lead->owner->type === 'company' ? ($lead->owner->company_name ?: $lead->owner->name) : $lead->owner->name) : '-' }}</td>
                                <td><span class="badge bg-{{ $lead->status === 'active' ? 'primary' : 'secondary' }}">{{ ucfirst($lead->status) }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No CRM leads yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade" id="tab-properties">
                    <table class="mini-table">
                        <thead><tr><th>Title</th><th>Owner</th><th>Price</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($latestProperties as $property)
                            <tr>
                                <td class="fw-semibold">{{ $property->getTranslation('title') }}</td>
                                <td>{{ $property->owner ? ($property->owner->company_name ?: $property->owner->name) : 'MW Realty' }}</td>
                                <td>{{ $property->price ? number_format($property->price) . ' ' . $property->currency : '-' }}</td>
                                <td><span class="badge bg-{{ $property->status ? 'success' : 'secondary' }}">{{ $property->status ? 'Active' : 'Inactive' }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No properties yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending actions + approval rate -->
    <div class="col-lg-4 d-flex flex-column gap-3">
        <div class="dash-card accent-orange">
            <h6><i class="fas fa-tasks"></i> Pending Actions</h6>
            @if($stats['pending_accounts'] > 0)
            <div class="action-row">
                <span><i class="fas fa-user-clock me-2" style="color: var(--dash-amber);"></i>{{ $stats['pending_accounts'] }} account approvals</span>
                <a href="{{ route('cms.portal-accounts.index') }}" class="btn btn-sm btn-outline-secondary py-0">Review</a>
            </div>
            @endif
            @if($stats['new_crm_leads'] > 0)
            <div class="action-row">
                <span><i class="fas fa-bell me-2" style="color: var(--dash-teal);"></i>{{ $stats['new_crm_leads'] }} new leads to contact</span>
                <a href="{{ route('portal.crm.leads.index', ['status' => 'active']) }}" class="btn btn-sm btn-outline-secondary py-0">View</a>
            </div>
            @endif
            @if($stats['pending_accounts'] === 0 && $stats['new_crm_leads'] === 0)
            <div class="text-muted text-center py-3" style="font-size: 0.85rem;"><i class="fas fa-check-circle me-1" style="color: var(--dash-green);"></i> All caught up.</div>
            @endif
        </div>
        <div class="dash-card accent-rose">
            <h6><i class="fas fa-user-check"></i> Account Approval Rate</h6>
            <div class="d-flex align-items-baseline gap-2 mb-2">
                <span class="approval-big">{{ $stats['approval_rate'] }}%</span>
                <span class="text-muted" style="font-size: 0.78rem;">of registered accounts</span>
            </div>
            <div class="breakdown-bar-track" style="height: 12px;">
                <div class="breakdown-bar-fill fill-rose" style="width: {{ $stats['approval_rate'] }}%;"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7 d-flex flex-column gap-3">
        <div class="dash-card accent-emerald">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="mb-0"><i class="fas fa-wallet"></i> Revenue Trend</h6>
                <div class="btn-group btn-group-sm" role="group" id="revenuePeriodToggle">
                    <button type="button" class="btn btn-period active" data-period="daily">Daily</button>
                    <button type="button" class="btn btn-period" data-period="weekly">Weekly</button>
                    <button type="button" class="btn btn-period" data-period="monthly">Monthly</button>
                </div>
            </div>
            <div class="revenue-chart-wrap">
                <canvas id="revenueChart"></canvas>
            </div>
            <div class="fin-strip mt-3">
                <div class="fin-item"><div class="fin-value">AED {{ number_format($stats['monthly_revenue']) }}</div><div class="fin-label">Est. MRR</div></div>
                <div class="fin-item"><div class="fin-value">{{ $paidSubscribers }}</div><div class="fin-label">Paid Subscribers</div></div>
                <div class="fin-item"><div class="fin-value">{{ $freeSubscribers }}</div><div class="fin-label">Free Plan</div></div>
                <div class="fin-item"><div class="fin-value">{{ $noPlan }}</div><div class="fin-label">No Plan Assigned</div></div>
            </div>
        </div>
        <div class="dash-card accent-pink">
            <h6><i class="fas fa-layer-group"></i> Plan Distribution</h6>
            @php $planTotal = max($planDistribution->sum('subscribers_count'), 1); @endphp
            @forelse($planDistribution as $plan)
                <div class="breakdown-row">
                    <div class="breakdown-label text-truncate">{{ $plan->getTranslation('name') }}</div>
                    <div class="breakdown-bar-track"><div class="breakdown-bar-fill fill-pink" style="width: {{ round($plan->subscribers_count / $planTotal * 100) }}%;"></div></div>
                    <div class="breakdown-count">{{ $plan->subscribers_count }}</div>
                </div>
            @empty
                <div class="text-muted text-center py-3" style="font-size: 0.85rem;">No plans yet.</div>
            @endforelse
        </div>
    </div>

    <div class="col-lg-5">
        <div class="dash-card accent-blue h-100">
            <h6><i class="fas fa-bolt"></i> Quick Actions</h6>
            <div class="action-btn-stack d-grid gap-2">
                <a href="{{ route('portal.properties.create') }}" class="btn fill-cyan"><i class="fas fa-plus-circle"></i> Add New Property</a>
                @if(config('cms-kit.common.modules.banners', true) && $cmsUser->can('banners.edit'))
                <a href="{{ route('cms.banners.create') }}" class="btn fill-purple"><i class="fas fa-image"></i> Add New Banner</a>
                @endif
                @if($cmsUser->can('portal-accounts.view'))
                <a href="{{ route('cms.portal-accounts.index') }}" class="btn fill-orange"><i class="fas fa-user-check"></i> Review Agents & Companies</a>
                @endif
                @if($cmsUser->can('plans.view'))
                <a href="{{ route('cms.plans.index') }}" class="btn fill-pink"><i class="fas fa-layer-group"></i> Manage Plans</a>
                @endif
                @if($cmsUser->can('filters.view'))
                <a href="{{ route('cms.filters.index') }}" class="btn fill-blue"><i class="fas fa-sliders-h"></i> Manage Filters</a>
                @endif
                @if($cmsUser->can('site-information.view'))
                <a href="{{ route('cms.site-information.index') }}" class="btn fill-slate"><i class="fas fa-cog"></i> Site Settings</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('propertyTypeChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($propertyTypeBreakdown->keys()->map(fn($k) => ucfirst(str_replace('_',' ',$k)))->values()) !!},
                datasets: [{
                    data: {!! json_encode($propertyTypeBreakdown->values()) !!},
                    backgroundColor: ['#04a1cc', '#264373', '#0f9d58', '#e08e0b', '#5b6478', '#8891a8'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
                cutout: '65%',
            }
        });
    }

    const revenueSeries = {!! json_encode($revenueSeries) !!};
    const revCtx = document.getElementById('revenueChart');
    if (revCtx) {
        const gradient = revCtx.getContext('2d').createLinearGradient(0, 0, 0, 210);
        gradient.addColorStop(0, 'rgba(4,161,204,0.28)');
        gradient.addColorStop(1, 'rgba(4,161,204,0.02)');

        const revenueChart = new Chart(revCtx, {
            type: 'line',
            data: {
                labels: revenueSeries.daily.labels,
                datasets: [{
                    data: revenueSeries.daily.data,
                    borderColor: '#04a1cc',
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    tension: 0.35,
                    fill: true,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: '#04a1cc',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: (item) => 'AED ' + Number(item.parsed.y).toLocaleString() }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#838aa3', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                    y: { grid: { color: 'rgba(28,35,64,0.06)' }, ticks: { font: { size: 10 }, color: '#838aa3', callback: (v) => 'AED ' + Number(v).toLocaleString() } }
                }
            }
        });

        document.querySelectorAll('#revenuePeriodToggle .btn-period').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#revenuePeriodToggle .btn-period').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const period = this.dataset.period;
                revenueChart.data.labels = revenueSeries[period].labels;
                revenueChart.data.datasets[0].data = revenueSeries[period].data;
                revenueChart.update();
            });
        });
    }
});
</script>
@endpush
