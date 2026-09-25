@extends('cms-kit::layouts.cms')

@section('title', 'Dashboard')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Overview</li>
@endsection

@php
    // Period-over-period delta for the KPI cards: last 30 days vs the 30 before.
    $delta = function (array $t) {
        if ($t['previous'] == 0) {
            return $t['current'] > 0 ? ['dir' => 'up', 'text' => 'New'] : ['dir' => 'flat', 'text' => '0%'];
        }
        $pct = round((($t['current'] - $t['previous']) / $t['previous']) * 100);
        return ['dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'), 'text' => ($pct > 0 ? '+' : '') . $pct . '%'];
    };

    $partners = $accountStatus['approved'];
    $leadsTotal = $stats['crm_leads'];

    $wonLeads = $stagePipeline->filter(fn ($s) => $s->is_closed && !str_contains(strtolower($s->name), 'lost'))->sum('total');
    $lostLeads = $stagePipeline->filter(fn ($s) => $s->is_closed && str_contains(strtolower($s->name), 'lost'))->sum('total');
    $openLeads = $stagePipeline->where('is_closed', 0)->sum('total') + $unstagedLeads;
    $winRate = ($wonLeads + $lostLeads) > 0 ? round($wonLeads / ($wonLeads + $lostLeads) * 100) : null;
    $stageMax = max($stagePipeline->max('total') ?? 0, $unstagedLeads, 1);

    $sourceMax = max($leadSources->max() ?? 0, $unsourcedLeads, 1);
    $typeMax = max($propertyTypeBreakdown->max() ?? 0, 1);
    $saleCount = (int) ($listingTypeBreakdown['sale'] ?? 0);
    $rentCount = (int) ($listingTypeBreakdown['rent'] ?? 0);
    $listingSplitTotal = max($saleCount + $rentCount, 1);
    $planTotal = max($planDistribution->sum('subscribers_count'), 1);

    $attentionItems = collect([
        ['count' => $stats['pending_accounts'], 'label' => 'accounts awaiting approval', 'icon' => 'fa-user-clock', 'tone' => 'warning', 'url' => route('cms.portal-accounts.index', ['status' => 'pending']), 'cta' => 'Review'],
        ['count' => $attention['upgrade_requests'], 'label' => 'plan upgrade requests', 'icon' => 'fa-arrow-up-right-dots', 'tone' => 'info', 'url' => route('cms.portal-accounts.plan-upgrade-requests'), 'cta' => 'Decide'],
        ['count' => $attention['failed_payments'], 'label' => 'failed payments (30 days)', 'icon' => 'fa-credit-card', 'tone' => 'critical', 'url' => route('cms.payments.index', ['status' => 'failed']), 'cta' => 'Check'],
        ['count' => $stats['new_crm_leads'], 'label' => 'active leads to follow up', 'icon' => 'fa-bell', 'tone' => 'info', 'url' => route('portal.crm.leads.index', ['status' => 'active']), 'cta' => 'Open'],
        ['count' => $attention['recent_enquiries'], 'label' => 'website enquiries this week', 'icon' => 'fa-envelope-open-text', 'tone' => 'info', 'url' => route('cms.enquiries.index'), 'cta' => 'Read'],
        ['count' => $attention['inactive_properties'], 'label' => 'inactive listings', 'icon' => 'fa-eye-slash', 'tone' => 'muted', 'url' => route('portal.properties.index'), 'cta' => 'View'],
    ])->filter(fn ($i) => $i['count'] > 0)->values();
    $urgentCount = $stats['pending_accounts'] + $attention['upgrade_requests'] + $attention['failed_payments'];

    $accountsTotal = max(array_sum($accountStatus), 1);
    $kpis = [
        [
            'label' => 'Partners', 'icon' => 'fa-handshake', 'tone' => 'navy',
            'value' => number_format($partners), 'trend' => $trends['signups'], 'trendLabel' => 'sign-ups',
            'sub' => $stats['approved_agents'] . ' agents · ' . $stats['approved_companies'] . ' companies',
            'url' => route('cms.portal-accounts.index'), 'spark' => $activity['signups']['data'],
        ],
        [
            'label' => 'Awaiting Approval', 'icon' => 'fa-user-clock', 'tone' => 'amber',
            'value' => number_format($stats['pending_accounts']), 'badge' => $stats['pending_accounts'] > 0 ? 'Review' : null,
            'sub' => $stats['rejected_accounts'] . ' rejected · ' . $stats['approval_rate'] . '% approved',
            'url' => route('cms.portal-accounts.index', ['status' => 'pending']), 'meter' => round($stats['pending_accounts'] / $accountsTotal * 100),
        ],
        [
            'label' => 'Listings', 'icon' => 'fa-building', 'tone' => 'blue',
            'value' => number_format($stats['total_properties']), 'trend' => $trends['properties'], 'trendLabel' => 'new listings',
            'sub' => $stats['active_properties'] . ' active · ' . $stats['featured_properties'] . ' featured',
            'url' => route('portal.properties.index'), 'spark' => $activity['properties']['data'],
        ],
        [
            'label' => 'CRM Leads', 'icon' => 'fa-address-book', 'tone' => 'teal',
            'value' => number_format($leadsTotal), 'trend' => $trends['leads'], 'trendLabel' => 'new leads',
            'sub' => $stats['new_crm_leads'] . ' active · ' . ($winRate !== null ? $winRate . '% win rate' : 'no closed deals'),
            'url' => route('portal.crm.leads.index'), 'spark' => $activity['leads']['data'],
        ],
        [
            'label' => 'Collected · 30d', 'icon' => 'fa-coins', 'tone' => 'green',
            'value' => 'AED ' . number_format($trends['revenue']['current']), 'trend' => $trends['revenue'], 'trendLabel' => 'revenue', 'money' => true,
            'sub' => 'Est. MRR AED ' . number_format($stats['monthly_revenue']),
            'url' => route('cms.payments.index'), 'spark' => $collectedSeries['weekly']['data'],
        ],
        [
            'label' => 'Paid Subscribers', 'icon' => 'fa-crown', 'tone' => 'maroon',
            'value' => number_format($paidSubscribers),
            'sub' => $freeSubscribers . ' free · ' . $noPlan . ' no plan',
            'url' => route('cms.plans.index'), 'meter' => round($paidSubscribers / max($partners, 1) * 100),
        ],
    ];
@endphp

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --dash-navy: #1e3a5f;
        --dash-accent: #2c4a73;
        --dash-ink: #1e293b;
        --dash-ink-2: #475569;
        --dash-muted: #64748b;
        --dash-bg: #f3f5f8;
        --dash-soft: #f5f7fa;
        --dash-border: rgba(15, 23, 42, 0.09);
        --dash-shadow: 0 1px 2px rgba(15,23,42,0.05), 0 4px 12px rgba(15,23,42,0.04);

        /* Categorical series (fixed order: leads, listings, sign-ups) */
        --series-1: #1e3a5f;
        --series-2: #6f8fb8;
        --series-3: #b08d57;

        /* Status — reserved for state, always paired with an icon + label */
        --st-good: #2f855a;
        --st-warning: #b7791f;
        --st-critical: #b83232;
        --st-info: #1e3a5f;
    }

    .main-content { background: var(--dash-bg); }
    .dash-num { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; letter-spacing: -0.01em; }

    /* ---------- Hero ---------- */
    .dash-hero {
        position: relative; overflow: hidden;
        background: linear-gradient(135deg, #1e3a5f 0%, #172e4d 100%);
        border-radius: 16px; padding: 1rem 1.3rem; color: #fff; height: 100%; display: flex; flex-direction: column; justify-content: center;
        box-shadow: 0 6px 18px rgba(15,23,42,0.14);
    }
    .dash-hero-eyebrow { font-size: 0.62rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.7; }
    .dash-hero h4 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.2rem; margin: 0.15rem 0 0.25rem; }
    .dash-hero-summary { font-size: 0.78rem; opacity: 0.85; margin: 0; max-width: 560px; line-height: 1.55; }
    .dash-hero-summary strong { color: #fff; }
    .hero-side { position: relative; z-index: 1; display: flex; gap: 0.75rem; flex-wrap: wrap; }
    .hero-metric {
        background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.18); border-radius: 14px;
        padding: 0.45rem 0.75rem; min-width: 104px; backdrop-filter: blur(6px);
    }
    .hero-metric-label { font-size: 0.56rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.75; }
    .hero-metric-value { font-size: 1.05rem; line-height: 1.2; }
    .hero-metric-note { font-size: 0.6rem; opacity: 0.75; }
    .hero-alert {
        display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 0.6rem;
        background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.28); color: #fff;
        border-radius: 8px; padding: 0.25rem 0.3rem 0.25rem 0.65rem; font-size: 0.72rem; font-weight: 600; text-decoration: none;
    }
    .hero-alert span.go { background: #fff; color: #1e3a5f; border-radius: 6px; padding: 0.15rem 0.6rem; font-weight: 800; font-size: 0.72rem; }
    .hero-alert:hover { color: #fff; background: rgba(255,255,255,0.14); }
    .hero-ok { display: inline-flex; align-items: center; gap: 0.45rem; margin-top: 0.6rem; font-size: 0.72rem; font-weight: 600; color: rgba(255,255,255,0.85); }

    /* ---------- Quick links (side panel beside the hero) ---------- */
    .ql-card { display: flex; flex-direction: column; }
    .ql-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); grid-auto-rows: 1fr; gap: 0.35rem; flex: 1; }
    .ql-tile { justify-content: center; }
    .ql-tile { display: flex; flex-direction: column; align-items: center; gap: 0.2rem; padding: 0.35rem 0.2rem; border-radius: 10px; border: 1px solid var(--dash-border); background: #fff; text-decoration: none; color: var(--dash-ink-2); font-size: 0.7rem; font-weight: 700; text-align: center; line-height: 1.2; transition: all 0.15s ease; }
    .ql-tile i { width: 24px; height: 24px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--tone-tint, var(--dash-soft)); color: var(--tone, var(--dash-navy)); font-size: 0.82rem; transition: all 0.15s ease; }
    .ql-tile:hover { border-color: rgba(30,58,95,0.35); color: var(--dash-ink); }
    .ql-tile:hover i { background: var(--tone, var(--dash-navy)); color: #fff; }
    .ql-tile:hover { background: var(--tone-tint, #fff); }

    /* ---------- Cards ---------- */
    .dash-card { background: #fff; border: 1px solid var(--dash-border); border-radius: 14px; box-shadow: var(--dash-shadow); padding: 0.8rem 0.95rem; height: 100%; }
    .dash-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.6rem; flex-wrap: wrap; }
    .dash-card-title { font-weight: 800; font-size: 0.84rem; color: var(--dash-ink); margin: 0; display: flex; align-items: center; gap: 0.55rem; }
    .dash-card-title .ico { width: 26px; height: 26px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; background: var(--dash-soft); color: var(--dash-navy); }
    .dash-card-sub { font-size: 0.66rem; color: var(--dash-muted); margin: 0.15rem 0 0 0; }
    .dash-link { font-size: 0.68rem; font-weight: 700; color: var(--dash-accent); text-decoration: none; white-space: nowrap; }
    .dash-link:hover { text-decoration: underline; }
    .dash-empty { color: var(--dash-muted); text-align: center; font-size: 0.84rem; padding: 1rem 0; }

    /* Formal tone set — each tone is a solid pair (card fill) plus a light tint (tiles, badges). */
    .tone-navy  { --tone: #1e3a5f; --tone-2: #2d5286; --tone-tint: rgba(30,58,95,0.08); }
    .tone-blue  { --tone: #2b5c9e; --tone-2: #3a74bd; --tone-tint: rgba(43,92,158,0.09); }
    .tone-teal  { --tone: #1f6f73; --tone-2: #2b8a8e; --tone-tint: rgba(31,111,115,0.09); }
    .tone-green { --tone: #276749; --tone-2: #34845d; --tone-tint: rgba(39,103,73,0.09); }
    .tone-amber { --tone: #9a6a1c; --tone-2: #b8842e; --tone-tint: rgba(154,106,28,0.1); }
    .tone-maroon{ --tone: #7b2d3b; --tone-2: #9a3e4f; --tone-tint: rgba(123,45,59,0.09); }

    .kpi-card {
        display: flex; flex-direction: column; text-decoration: none; position: relative; overflow: hidden;
        background: linear-gradient(135deg, var(--tone) 0%, var(--tone-2) 100%); border: 0; color: #fff;
        box-shadow: 0 6px 18px rgba(15,23,42,0.14); transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .kpi-card::after {
        content: ''; position: absolute; right: -30px; top: -30px; width: 90px; height: 90px; border-radius: 50%;
        background: rgba(255,255,255,0.06); pointer-events: none;
    }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(15,23,42,0.2); color: #fff; }
    .kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.45rem; }
    .kpi-icon { width: 30px; height: 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 0.78rem; color: #fff; background: rgba(255,255,255,0.16); }
    .kpi-label { font-size: 0.58rem; font-weight: 700; color: rgba(255,255,255,0.78); text-transform: uppercase; letter-spacing: 0.05em; }
    .kpi-value { font-size: 1.3rem; color: #fff; line-height: 1.1; margin: 0.1rem 0 0.1rem; }
    .kpi-sub { font-size: 0.64rem; color: rgba(255,255,255,0.88); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .kpi-card { padding-bottom: 2.1rem !important; }
    .kpi-spark { position: absolute; left: 0; right: 0; bottom: 0; height: 30px; }
    .kpi-meter { position: absolute; left: 0.95rem; right: 0.95rem; bottom: 0.8rem; height: 5px; border-radius: 50px; background: rgba(255,255,255,0.2); overflow: hidden; }
    .kpi-meter span { display: block; height: 100%; border-radius: 50px; background: #fff; }
    .delta { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6rem; font-weight: 800; border-radius: 50px; padding: 0.12rem 0.45rem; }
    .delta.up { color: var(--st-good); background: rgba(47,133,90,0.1); }
    .delta.down { color: var(--st-critical); background: rgba(184,50,50,0.1); }
    .delta.flat { color: var(--dash-muted); background: var(--dash-soft); }
    .kpi-card .delta { color: #fff; background: rgba(255,255,255,0.18); }

    /* Section cards: light shading of the section's tone only — no header band */
    .dash-card[class*="tone-"]:not(.kpi-card) {
        border: 1px solid var(--tone-line);
        background: linear-gradient(160deg, var(--tone-shade) 0%, var(--tone-shade-2) 100%);
    }
    .dash-card[class*="tone-"] .dash-card-title .ico { background: var(--tone); color: #fff; }
    .dash-card[class*="tone-"] .dash-link { color: var(--tone); }
    .tone-navy  { --tone-shade: #eef2f8; --tone-shade-2: #f7f9fc; --tone-line: rgba(30,58,95,0.12); }
    .tone-blue  { --tone-shade: #edf3fb; --tone-shade-2: #f7fafd; --tone-line: rgba(43,92,158,0.12); }
    .tone-teal  { --tone-shade: #ebf5f5; --tone-shade-2: #f6fafa; --tone-line: rgba(31,111,115,0.12); }
    .tone-green { --tone-shade: #edf6f1; --tone-shade-2: #f7fbf8; --tone-line: rgba(39,103,73,0.12); }
    .tone-amber { --tone-shade: #fbf4ea; --tone-shade-2: #fdfaf5; --tone-line: rgba(154,106,28,0.14); }
    .tone-maroon{ --tone-shade: #f9eef0; --tone-shade-2: #fcf7f8; --tone-line: rgba(123,45,59,0.12); }
    /* Inner surfaces sit on white so they read against the shaded card */
    .dash-card[class*="tone-"] .attn-item,
    .dash-card[class*="tone-"] .seg-toggle,
    .dash-card[class*="tone-"] .dash-tabs,
    .dash-card[class*="tone-"] .ql-tile,
    .dash-card[class*="tone-"] .hbar-track { background: rgba(255,255,255,0.85); }
    .dash-card[class*="tone-"] .attn-item:hover { background: #fff; }
    .dash-card[class*="tone-"] .tile-tint { background: #fff !important; }

    /* Tinted summary tiles */
    .tile-tint { background: var(--tone-tint) !important; border-left: 3px solid var(--tone); }
    .tile-tint .dash-num { color: var(--tone) !important; }
    /* ---------- KPI cards ---------- */

    /* ---------- Legend / chart ---------- */
    .chart-legend { display: flex; flex-wrap: wrap; gap: 1.1rem; }
    .legend-item { display: flex; align-items: center; gap: 0.45rem; font-size: 0.68rem; color: var(--dash-ink-2); font-weight: 600; }
    .legend-swatch { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
    .legend-item strong { color: var(--dash-ink); font-family: 'Plus Jakarta Sans', sans-serif; }
    .chart-wrap { position: relative; height: 200px; }
    .seg-toggle { display: inline-flex; background: var(--dash-soft); border-radius: 10px; padding: 3px; gap: 2px; }
    .seg-toggle button { border: 0; background: transparent; color: var(--dash-muted); font-weight: 700; font-size: 0.72rem; padding: 0.32rem 0.75rem; border-radius: 8px; }
    .seg-toggle button.active { background: #fff; color: var(--dash-ink); box-shadow: 0 1px 3px rgba(15,23,42,0.12); }

    /* ---------- Needs attention ---------- */
    .attn-list { display: flex; flex-direction: column; gap: 0.35rem; }
    .attn-item { display: flex; align-items: center; gap: 0.6rem; padding: 0.38rem 0.55rem; border-radius: 12px; background: var(--dash-soft); text-decoration: none; color: inherit; transition: background 0.15s ease; }
    .attn-item:hover { background: #eef1f8; color: inherit; }
    .attn-icon { width: 28px; height: 28px; border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 0.72rem; }
    .attn-warning .attn-icon  { background: rgba(183,121,31,0.13); color: var(--st-warning); }
    .attn-critical .attn-icon { background: rgba(184,50,50,0.12); color: var(--st-critical); }
    .attn-info .attn-icon     { background: rgba(30,58,95,0.1); color: var(--st-info); }
    .attn-muted .attn-icon    { background: rgba(100,116,139,0.14); color: var(--dash-muted); }
    .attn-text { flex: 1; font-size: 0.74rem; color: var(--dash-ink-2); line-height: 1.3; }
    .attn-text strong { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.85rem; color: var(--dash-ink); margin-right: 0.2rem; }
    .attn-cta { font-size: 0.72rem; font-weight: 800; color: var(--dash-accent); }
    .attn-clear { text-align: center; padding: 1.5rem 0.5rem; }
    .attn-clear i { font-size: 1.6rem; color: var(--st-good); margin-bottom: 0.5rem; }

    .pending-head { font-size: 0.68rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: var(--dash-muted); margin: 0.7rem 0 0.2rem; }
    .pending-row { display: flex; align-items: center; gap: 0.65rem; padding: 0.3rem 0; border-bottom: 1px solid #f1f3f8; }
    .pending-row:last-child { border-bottom: 0; }
    .pending-name { font-size: 0.76rem; font-weight: 700; color: var(--dash-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pending-meta { font-size: 0.7rem; color: var(--dash-muted); }

    /* ---------- Avatars ---------- */
    .avatar { width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 0.64rem; }
    .avatar-agent { background: #1e3a5f; }
    .avatar-company { background: #5b7294; }

    /* ---------- Horizontal bar rows (pipeline, sources, types, plans) ---------- */
    .hbar { display: grid; grid-template-columns: minmax(90px, 38%) 1fr auto; align-items: center; gap: 0.7rem; padding: 0.2rem 0; }
    .hbar-label { font-size: 0.72rem; font-weight: 600; color: var(--dash-ink-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 0.4rem; }
    .hbar-track { height: 8px; border-radius: 50px; background: var(--dash-soft); overflow: hidden; }
    .hbar-fill { height: 100%; border-radius: 50px; min-width: 4px; background: var(--bar, var(--tone, var(--dash-navy))); transition: width 0.8s cubic-bezier(0.22,1,0.36,1); }
    .hbar-value { font-size: 0.74rem; font-weight: 800; color: var(--dash-ink); min-width: 46px; text-align: right; font-family: 'Plus Jakarta Sans', sans-serif; }
    .hbar-value small { font-weight: 600; color: var(--dash-muted); font-size: 0.68rem; margin-left: 0.2rem; font-family: inherit; }
    .hbar.is-won  { --bar: var(--st-good); }
    .hbar.is-lost { --bar: #cbd5e1; }
    .stage-tag { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; padding: 0.08rem 0.4rem; border-radius: 4px; }
    .stage-tag.won  { background: rgba(47,133,90,0.12); color: var(--st-good); }
    .stage-tag.lost { background: rgba(100,116,139,0.15); color: var(--dash-muted); }

    .mini-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 0.6rem; }
    .mini-stat { background: var(--dash-soft); border-radius: 12px; padding: 0.4rem 0.6rem; }
    .mini-stat-value { font-size: 0.98rem; color: var(--dash-ink); line-height: 1.15; }
    .mini-stat-label { font-size: 0.66rem; font-weight: 700; color: var(--dash-muted); text-transform: uppercase; letter-spacing: 0.03em; }

    /* ---------- Account status donut ---------- */
    .donut-wrap { position: relative; width: 128px; height: 128px; margin: 0 auto 0.6rem; }
    .donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
    .donut-center .dash-num { font-size: 1.3rem; color: var(--dash-ink); line-height: 1; }
    .donut-center span { font-size: 0.68rem; color: var(--dash-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.2rem; }
    .status-legend { display: flex; flex-direction: column; gap: 0.45rem; }
    .status-legend .row-item { display: flex; align-items: center; justify-content: space-between; font-size: 0.74rem; color: var(--dash-ink-2); font-weight: 600; }
    .status-legend .row-item i { width: 16px; }
    .status-legend strong { color: var(--dash-ink); font-family: 'Plus Jakarta Sans', sans-serif; }

    /* ---------- Sale / rent split ---------- */
    .split-bar { display: flex; height: 10px; border-radius: 50px; overflow: hidden; gap: 2px; background: #fff; margin: 0.4rem 0 0.55rem; }
    .split-bar span { height: 100%; }
    .split-labels { display: flex; justify-content: space-between; font-size: 0.7rem; font-weight: 600; color: var(--dash-ink-2); }
    .split-labels strong { color: var(--dash-ink); font-family: 'Plus Jakarta Sans', sans-serif; }

    /* ---------- Revenue strip ---------- */
    .fin-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.6rem; margin-top: 0.6rem; }
    .fin-item { background: var(--dash-soft); border-radius: 12px; padding: 0.45rem 0.65rem; }
    .fin-value { font-size: 0.92rem; color: var(--dash-ink); }
    .fin-label { font-size: 0.66rem; font-weight: 700; color: var(--dash-muted); text-transform: uppercase; letter-spacing: 0.03em; }

    /* ---------- Tabs + tables ---------- */
    .dash-tabs { display: inline-flex; background: var(--dash-soft); border-radius: 10px; padding: 3px; gap: 2px; flex-wrap: wrap; }
    .dash-tabs .nav-link { border: 0; font-size: 0.7rem; font-weight: 700; color: var(--dash-muted); border-radius: 8px; padding: 0.26rem 0.7rem; background: transparent; }
    .dash-tabs .nav-link.active { background: #fff; color: var(--dash-ink); box-shadow: 0 1px 3px rgba(15,23,42,0.12); }
    .mini-table { width: 100%; font-size: 0.76rem; }
    .mini-table th, .mini-table td { padding: 0.36rem 0.4rem; vertical-align: middle; }
    .mini-table thead th { font-size: 0.66rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--dash-muted); font-weight: 800; border-bottom: 1px solid var(--dash-border); }
    .mini-table tbody tr { border-bottom: 1px solid #f1f3f8; }
    .mini-table tbody tr:last-child { border-bottom: 0; }
    .mini-table tbody tr:hover { background: #fafbfd; }
    .cell-main { font-weight: 700; color: var(--dash-ink); }
    .cell-sub { font-size: 0.7rem; color: var(--dash-muted); }
    .pill { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.68rem; font-weight: 800; border-radius: 50px; padding: 0.18rem 0.55rem; }
    .pill-good { background: rgba(47,133,90,0.1); color: var(--st-good); }
    .pill-muted { background: rgba(100,116,139,0.14); color: var(--dash-muted); }
    .pill-info { background: rgba(30,58,95,0.1); color: var(--st-info); }
    .count-chip { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.74rem; font-weight: 700; color: var(--dash-navy); background: var(--dash-soft); border-radius: 50px; padding: 0.2rem 0.55rem; }
    .count-chip i { font-size: 0.65rem; color: var(--dash-muted); }
    .rank { width: 22px; font-size: 0.72rem; font-weight: 800; color: var(--dash-muted); }


    @media (max-width: 575.98px) {
        .dash-hero { padding: 1.25rem; }
        .hero-metric { flex: 1; min-width: 0; }
        .mini-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
</style>
@endpush

@section('content')

{{-- ============ Hero + quick links ============ --}}
<div class="row g-2 mb-2">
    <div class="col-xl-8">
<div class="dash-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-4 position-relative" style="z-index:1;">
        <div>
            <div class="dash-hero-eyebrow">{{ now()->format('l, d M Y') }}</div>
            <h4>Welcome back, {{ $cmsUser->name ?? 'Super Admin' }} 👋</h4>
            <p class="dash-hero-summary">
                In the last 30 days MW Realty gained <strong>{{ $trends['signups']['current'] }}</strong> {{ \Illuminate\Support\Str::plural('sign-up', $trends['signups']['current']) }},
                <strong>{{ $trends['properties']['current'] }}</strong> new {{ \Illuminate\Support\Str::plural('listing', $trends['properties']['current']) }}
                and <strong>{{ $trends['leads']['current'] }}</strong> {{ \Illuminate\Support\Str::plural('lead', $trends['leads']['current']) }}.
            </p>
            @if($urgentCount > 0)
                <a href="#needs-attention" class="hero-alert">
                    <i class="fas fa-circle-exclamation"></i>
                    {{ $urgentCount }} {{ \Illuminate\Support\Str::plural('item', $urgentCount) }} need your decision
                    <span class="go">Review</span>
                </a>
            @else
                <div class="hero-ok"><i class="fas fa-circle-check"></i> Nothing waiting on you, all caught up.</div>
            @endif
        </div>
        <div class="hero-side">
            <div class="hero-metric">
                <div class="hero-metric-label">Est. MRR</div>
                <div class="hero-metric-value dash-num">AED {{ number_format($stats['monthly_revenue']) }}</div>
                <div class="hero-metric-note">{{ $paidSubscribers }} paid {{ \Illuminate\Support\Str::plural('subscriber', $paidSubscribers) }}</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Approval rate</div>
                <div class="hero-metric-value dash-num">{{ $stats['approval_rate'] }}%</div>
                <div class="hero-metric-note">{{ $partners }} of {{ array_sum($accountStatus) }} accounts</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Open leads</div>
                <div class="hero-metric-value dash-num">{{ $openLeads }}</div>
                <div class="hero-metric-note">in the pipeline</div>
            </div>
        </div>
    </div>
</div>
    </div>
    <div class="col-xl-4">
        <div class="dash-card tone-blue ql-card">
            <div class="dash-card-head">
                <h6 class="dash-card-title"><span class="ico"><i class="fas fa-bolt"></i></span> Quick Links</h6>
            </div>
            <div class="ql-grid">
                <a href="{{ route('portal.properties.create') }}" class="ql-tile tone-navy"><i class="fas fa-plus"></i> Add Property</a>
                @if($cmsUser->can('portal-accounts.view'))
                    <a href="{{ route('cms.portal-accounts.index') }}" class="ql-tile tone-blue"><i class="fas fa-user-check"></i> Clients</a>
                @endif
                <a href="{{ route('portal.crm.leads.index') }}" class="ql-tile tone-teal"><i class="fas fa-address-book"></i> CRM Leads</a>
                @if($cmsUser->can('plans.view'))
                    <a href="{{ route('cms.plans.index') }}" class="ql-tile tone-green"><i class="fas fa-layer-group"></i> Plans</a>
                    <a href="{{ route('cms.payments.index') }}" class="ql-tile tone-amber"><i class="fas fa-receipt"></i> Payments</a>
                @endif
                @if(config('cms-kit.common.modules.banners', true) && $cmsUser->can('banners.edit'))
                    <a href="{{ route('cms.banners.create') }}" class="ql-tile tone-maroon"><i class="fas fa-image"></i> New Banner</a>
                @endif
                @if($cmsUser->can('filters.view'))
                    <a href="{{ route('cms.filters.index') }}" class="ql-tile tone-blue"><i class="fas fa-sliders"></i> Filters</a>
                @endif
                @if($cmsUser->can('site-information.view'))
                    <a href="{{ route('cms.site-information.index') }}" class="ql-tile tone-navy"><i class="fas fa-gear"></i> Settings</a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ============ KPI cards ============ --}}
<div class="row g-2 mb-2">
    @foreach($kpis as $kpi)
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ $kpi['url'] }}" class="dash-card kpi-card tone-{{ $kpi['tone'] }}">
                <div class="kpi-top">
                    <span class="kpi-icon"><i class="fas {{ $kpi['icon'] }}"></i></span>
                    @isset($kpi['trend'])
                        @php $d = $delta($kpi['trend']); @endphp
                        <span class="delta {{ $d['dir'] }}" title="{{ !empty($kpi['money']) ? 'AED ' . number_format($kpi['trend']['current']) : $kpi['trend']['current'] }} {{ $kpi['trendLabel'] }} in the last 30 days vs {{ !empty($kpi['money']) ? 'AED ' . number_format($kpi['trend']['previous']) : $kpi['trend']['previous'] }} in the 30 before">
                            <i class="fas {{ $d['dir'] === 'up' ? 'fa-arrow-trend-up' : ($d['dir'] === 'down' ? 'fa-arrow-trend-down' : 'fa-minus') }}"></i>{{ $d['text'] }}
                        </span>
                    @elseif(!empty($kpi['badge']))
                        <span class="delta">{{ $kpi['badge'] }}</span>
                    @endisset
                </div>
                <div class="kpi-label">{{ $kpi['label'] }}</div>
                <div class="kpi-value dash-num">{{ $kpi['value'] }}</div>
                <div class="kpi-sub">{{ $kpi['sub'] }}</div>
                @isset($kpi['spark'])
                    <div class="kpi-spark"><canvas class="js-spark" data-values='@json($kpi['spark'])' data-color="#ffffff" data-money="{{ !empty($kpi['money']) ? 1 : 0 }}" aria-label="{{ $kpi['label'] }}, last 12 weeks"></canvas></div>
                @else
                    <div class="kpi-meter"><span style="width: {{ $kpi['meter'] }}%;"></span></div>
                @endisset
            </a>
        </div>
    @endforeach
</div>

{{-- ============ Activity + needs attention ============ --}}
<div class="row g-2 mb-2">
    <div class="col-xl-8">
        <div class="dash-card tone-blue">
            <div class="dash-card-head">
                <div>
                    <h6 class="dash-card-title"><span class="ico"><i class="fas fa-chart-column"></i></span> Platform Activity</h6>
                    <p class="dash-card-sub">New leads, listings and account sign-ups per week, last 12 weeks</p>
                </div>
                <div class="chart-legend">
                    <span class="legend-item"><span class="legend-swatch" style="background: var(--series-1);"></span> Leads <strong>{{ array_sum($activity['leads']['data']) }}</strong></span>
                    <span class="legend-item"><span class="legend-swatch" style="background: var(--series-2);"></span> Listings <strong>{{ array_sum($activity['properties']['data']) }}</strong></span>
                    <span class="legend-item"><span class="legend-swatch" style="background: var(--series-3);"></span> Sign-ups <strong>{{ array_sum($activity['signups']['data']) }}</strong></span>
                </div>
            </div>
            <div class="chart-wrap"><canvas id="activityChart" aria-label="Weekly activity chart"></canvas></div>
        </div>
    </div>

    <div class="col-xl-4" id="needs-attention">
        <div class="dash-card tone-amber">
            <div class="dash-card-head">
                <div>
                    <h6 class="dash-card-title"><span class="ico"><i class="fas fa-list-check"></i></span> Needs Attention</h6>
                    <p class="dash-card-sub">What's waiting on the team right now</p>
                </div>
            </div>
            @if($attentionItems->isEmpty())
                <div class="attn-clear">
                    <i class="fas fa-circle-check d-block"></i>
                    <div class="fw-bold" style="color: var(--dash-ink);">All caught up</div>
                    <div class="dash-card-sub">No approvals, requests or failed payments pending.</div>
                </div>
            @else
                <div class="attn-list">
                    @foreach($attentionItems as $item)
                        <a href="{{ $item['url'] }}" class="attn-item attn-{{ $item['tone'] }}">
                            <span class="attn-icon"><i class="fas {{ $item['icon'] }}"></i></span>
                            <span class="attn-text"><strong>{{ $item['count'] }}</strong> {{ $item['label'] }}</span>
                            <span class="attn-cta">{{ $item['cta'] }} <i class="fas fa-chevron-right" style="font-size: 0.6rem;"></i></span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if($pendingAccounts->isNotEmpty())
                <div class="pending-head">Latest awaiting approval</div>
                @foreach($pendingAccounts->take(3) as $acc)
                    @php
                        $accLabel = $acc->type === 'company' ? ($acc->company_name ?: $acc->name) : $acc->name;
                        $accInitials = collect(preg_split('/\s+/', trim((string) $accLabel)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
                    @endphp
                    <div class="pending-row">
                        <span class="avatar avatar-{{ $acc->type === 'company' ? 'company' : 'agent' }}">{{ strtoupper($accInitials) ?: '?' }}</span>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="pending-name">{{ $accLabel }}</div>
                            <div class="pending-meta">{{ ucfirst($acc->type) }} · signed up {{ $acc->created_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

{{-- ============ CRM: pipeline, sources, account status ============ --}}
<div class="row g-2 mb-2">
    <div class="col-xl-5">
        <div class="dash-card tone-teal">
            <div class="dash-card-head">
                <div>
                    <h6 class="dash-card-title"><span class="ico"><i class="fas fa-filter"></i></span> Lead Pipeline</h6>
                    <p class="dash-card-sub">All CRM leads by stage, across every agent &amp; company</p>
                </div>
                <a href="{{ route('portal.crm.leads.index') }}" class="dash-link">Open CRM <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="mini-stats">
                <div class="mini-stat tile-tint tone-blue"><div class="mini-stat-value dash-num">{{ $openLeads }}</div><div class="mini-stat-label">Open</div></div>
                <div class="mini-stat tile-tint tone-green"><div class="mini-stat-value dash-num">{{ $wonLeads }}</div><div class="mini-stat-label">Won</div></div>
                <div class="mini-stat tile-tint tone-teal"><div class="mini-stat-value dash-num">{{ $winRate !== null ? $winRate . '%' : '—' }}</div><div class="mini-stat-label">Win rate</div></div>
            </div>
            @if($stagePipeline->isEmpty() && $unstagedLeads === 0)
                <div class="dash-empty">No CRM leads yet.</div>
            @else
                @foreach($stagePipeline as $stage)
                    @php
                        $isLost = $stage->is_closed && str_contains(strtolower($stage->name), 'lost');
                        $isWon = $stage->is_closed && !$isLost;
                    @endphp
                    <div class="hbar {{ $isWon ? 'is-won' : ($isLost ? 'is-lost' : '') }}" title="{{ $stage->name }}: {{ $stage->total }} {{ \Illuminate\Support\Str::plural('lead', $stage->total) }}">
                        <div class="hbar-label">
                            {{ $stage->name }}
                            @if($isWon)<span class="stage-tag won"><i class="fas fa-check"></i> Won</span>@endif
                            @if($isLost)<span class="stage-tag lost">Lost</span>@endif
                        </div>
                        <div class="hbar-track"><div class="hbar-fill" style="width: {{ round($stage->total / $stageMax * 100) }}%;"></div></div>
                        <div class="hbar-value">{{ $stage->total }}<small>{{ $leadsTotal ? round($stage->total / $leadsTotal * 100) : 0 }}%</small></div>
                    </div>
                @endforeach
                @if($unstagedLeads > 0)
                    <div class="hbar is-lost" title="Leads without a stage">
                        <div class="hbar-label fst-italic">No stage</div>
                        <div class="hbar-track"><div class="hbar-fill" style="width: {{ round($unstagedLeads / $stageMax * 100) }}%;"></div></div>
                        <div class="hbar-value">{{ $unstagedLeads }}<small>{{ $leadsTotal ? round($unstagedLeads / $leadsTotal * 100) : 0 }}%</small></div>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="dash-card tone-blue">
            <div class="dash-card-head">
                <div>
                    <h6 class="dash-card-title"><span class="ico"><i class="fas fa-bullseye"></i></span> Lead Sources</h6>
                    <p class="dash-card-sub">Where leads come from</p>
                </div>
            </div>
            @forelse($leadSources as $name => $total)
                <div class="hbar" style="--bar: var(--tone); grid-template-columns: minmax(80px, 42%) 1fr auto;" title="{{ $name }}: {{ $total }}">
                    <div class="hbar-label">{{ $name }}</div>
                    <div class="hbar-track"><div class="hbar-fill" style="width: {{ round($total / $sourceMax * 100) }}%;"></div></div>
                    <div class="hbar-value">{{ $total }}</div>
                </div>
            @empty
                @if($unsourcedLeads === 0)<div class="dash-empty">No leads yet.</div>@endif
            @endforelse
            @if($unsourcedLeads > 0)
                <div class="hbar is-lost" style="grid-template-columns: minmax(80px, 42%) 1fr auto;" title="Leads without a source">
                    <div class="hbar-label fst-italic">Unknown</div>
                    <div class="hbar-track"><div class="hbar-fill" style="width: {{ round($unsourcedLeads / $sourceMax * 100) }}%;"></div></div>
                    <div class="hbar-value">{{ $unsourcedLeads }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="dash-card tone-green">
            <div class="dash-card-head">
                <div>
                    <h6 class="dash-card-title"><span class="ico"><i class="fas fa-user-shield"></i></span> Account Status</h6>
                    <p class="dash-card-sub">Every registered agent &amp; company</p>
                </div>
                <a href="{{ route('cms.portal-accounts.index') }}" class="dash-link">Manage <i class="fas fa-arrow-right"></i></a>
            </div>
            @if(array_sum($accountStatus) > 0)
                <div class="donut-wrap">
                    <canvas id="accountStatusChart" aria-label="Account status chart"></canvas>
                    <div class="donut-center"><div class="dash-num">{{ $stats['approval_rate'] }}%</div><span>approved</span></div>
                </div>
                <div class="status-legend">
                    <div class="row-item"><span><i class="fas fa-circle-check" style="color: var(--st-good);"></i> Approved</span><strong>{{ $accountStatus['approved'] }}</strong></div>
                    <div class="row-item"><span><i class="fas fa-clock" style="color: var(--st-warning);"></i> Pending</span><strong>{{ $accountStatus['pending'] }}</strong></div>
                    <div class="row-item"><span><i class="fas fa-circle-xmark" style="color: var(--st-critical);"></i> Rejected</span><strong>{{ $accountStatus['rejected'] }}</strong></div>
                </div>
            @else
                <div class="dash-empty">No accounts registered yet.</div>
            @endif
        </div>
    </div>
</div>

{{-- ============ Revenue · plans · inventory ============ --}}
<div class="row g-2 mb-2">
    <div class="col-xl-6">
        <div class="dash-card tone-green">
            <div class="dash-card-head">
                <div>
                    <h6 class="dash-card-title"><span class="ico"><i class="fas fa-wallet"></i></span> Collected Revenue</h6>
                    <p class="dash-card-sub">Paid plan invoices (AED), <span id="revenuePeriodLabel">last 30 days</span></p>
                </div>
                <div class="seg-toggle" id="revenuePeriodToggle" role="group" aria-label="Revenue period">
                    <button type="button" class="active" data-period="daily" data-label="last 30 days">Daily</button>
                    <button type="button" data-period="weekly" data-label="last 12 weeks">Weekly</button>
                    <button type="button" data-period="monthly" data-label="last 12 months">Monthly</button>
                </div>
            </div>
            <div class="chart-wrap"><canvas id="revenueChart" aria-label="Collected revenue chart"></canvas></div>
            <div class="fin-strip">
                <div class="fin-item tile-tint tone-green"><div class="fin-value dash-num" id="revenuePeriodTotal">AED {{ number_format(array_sum($collectedSeries['daily']['data'])) }}</div><div class="fin-label">Collected in period</div></div>
                <div class="fin-item tile-tint tone-navy"><div class="fin-value dash-num">AED {{ number_format($stats['monthly_revenue']) }}</div><div class="fin-label">Est. MRR</div></div>
                <div class="fin-item tile-tint tone-blue"><div class="fin-value dash-num">{{ $paidSubscribers }}</div><div class="fin-label">Paid subscribers</div></div>
                <div class="fin-item tile-tint tone-teal"><div class="fin-value dash-num">{{ $freeSubscribers }}</div><div class="fin-label">Free plan</div></div>
                <div class="fin-item tile-tint tone-amber"><div class="fin-value dash-num">{{ $noPlan }}</div><div class="fin-label">No plan</div></div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="dash-card tone-navy">
            <div class="dash-card-head">
                <div>
                    <h6 class="dash-card-title"><span class="ico"><i class="fas fa-layer-group"></i></span> Plan Distribution</h6>
                    <p class="dash-card-sub">Approved subscribers on each active plan</p>
                </div>
                <a href="{{ route('cms.plans.index') }}" class="dash-link">Plans <i class="fas fa-arrow-right"></i></a>
            </div>
            @forelse($planDistribution as $plan)
                <div class="hbar" style="--bar: var(--tone);" title="{{ $plan->getTranslation('name') }}: {{ $plan->subscribers_count }}">
                    <div class="hbar-label">
                        {{ $plan->getTranslation('name') }}
                        @if((float) $plan->price === 0.0 && !str_contains(strtolower($plan->getTranslation('name')), 'free'))<span class="stage-tag lost">Free</span>@endif
                    </div>
                    <div class="hbar-track"><div class="hbar-fill" style="width: {{ round($plan->subscribers_count / $planTotal * 100) }}%;"></div></div>
                    <div class="hbar-value">{{ $plan->subscribers_count }}<small>{{ round($plan->subscribers_count / $planTotal * 100) }}%</small></div>
                </div>
            @empty
                <div class="dash-empty">No active plans yet.</div>
            @endforelse
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="dash-card tone-teal">
            <div class="dash-card-head">
                <div>
                    <h6 class="dash-card-title"><span class="ico"><i class="fas fa-city"></i></span> Inventory Mix</h6>
                    <p class="dash-card-sub">{{ $stats['total_properties'] }} listings by purpose &amp; type</p>
                </div>
            </div>
            @if($stats['total_properties'] > 0)
                <div class="split-labels">
                    <span><i class="fas fa-tag me-1" style="color: var(--series-1);"></i> For sale <strong>{{ $saleCount }}</strong></span>
                    <span>For rent <strong>{{ $rentCount }}</strong> <i class="fas fa-key ms-1" style="color: var(--series-2);"></i></span>
                </div>
                <div class="split-bar" title="Sale {{ round($saleCount / $listingSplitTotal * 100) }}% · Rent {{ round($rentCount / $listingSplitTotal * 100) }}%">
                    <span style="width: {{ $saleCount / $listingSplitTotal * 100 }}%; background: var(--series-1);"></span>
                    <span style="width: {{ $rentCount / $listingSplitTotal * 100 }}%; background: var(--series-2);"></span>
                </div>
                <div class="split-labels mb-3" style="font-size: 0.7rem; color: var(--dash-muted);">
                    <span>{{ round($saleCount / $listingSplitTotal * 100) }}%</span><span>{{ round($rentCount / $listingSplitTotal * 100) }}%</span>
                </div>
                @foreach($propertyTypeBreakdown as $type => $total)
                    <div class="hbar" style="--bar: var(--tone);" title="{{ ucfirst(str_replace('_', ' ', $type)) }}: {{ $total }}">
                        <div class="hbar-label">{{ ucfirst(str_replace('_', ' ', $type)) }}</div>
                        <div class="hbar-track"><div class="hbar-fill" style="width: {{ round($total / $typeMax * 100) }}%;"></div></div>
                        <div class="hbar-value">{{ $total }}</div>
                    </div>
                @endforeach
            @else
                <div class="dash-empty">No properties yet.</div>
            @endif
        </div>
    </div>

</div>

{{-- ============ Top performers · recent leads · latest listings ============ --}}
<div class="row g-2 mb-2">
    <div class="col-12">
        <div class="dash-card tone-maroon">
            <div class="dash-card-head">
                <ul class="nav dash-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-owners" type="button">Top Performers</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-leads" type="button">Recent Leads</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-properties" type="button">Latest Listings</button></li>
                </ul>
            </div>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-owners">
                    <div class="table-responsive">
                        <table class="mini-table">
                            <thead><tr><th>#</th><th>Partner</th><th>Plan</th><th class="text-end">Listings</th><th class="text-end">Leads</th></tr></thead>
                            <tbody>
                                @forelse($topOwners as $i => $owner)
                                    @php
                                        $ownerLabel = $owner->type === 'company' ? ($owner->company_name ?: $owner->name) : $owner->name;
                                        $initials = collect(preg_split('/\s+/', trim((string) $ownerLabel)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
                                    @endphp
                                    <tr>
                                        <td class="rank">{{ $i + 1 }}</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="avatar avatar-{{ $owner->type === 'company' ? 'company' : 'agent' }}">{{ strtoupper($initials) ?: '?' }}</span>
                                                <div style="min-width: 0;">
                                                    <div class="cell-main text-truncate">{{ $ownerLabel }}</div>
                                                    <div class="cell-sub">{{ ucfirst($owner->type) }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="cell-sub">{{ $owner->plan?->getTranslation('name') ?? '—' }}</span></td>
                                        <td class="text-end"><span class="count-chip"><i class="fas fa-building"></i> {{ $owner->properties_count }}</span></td>
                                        <td class="text-end"><span class="count-chip"><i class="fas fa-address-book"></i> {{ $owner->leads_count }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="dash-empty">No approved accounts yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab-leads">
                    <div class="table-responsive">
                        <table class="mini-table">
                            <thead><tr><th>Lead</th><th>Property</th><th>Owner</th><th>Stage</th><th class="text-end">Received</th></tr></thead>
                            <tbody>
                                @forelse($recentLeads as $lead)
                                    <tr>
                                        <td>
                                            <div class="cell-main">{{ $lead->name ?: 'Unknown' }}</div>
                                            <div class="cell-sub">{{ $lead->source?->name ?? ($lead->email ?: '—') }}</div>
                                        </td>
                                        <td class="text-truncate" style="max-width: 200px;">{{ $lead->property?->getTranslation('title') ?? '—' }}</td>
                                        <td>{{ $lead->owner ? ($lead->owner->type === 'company' ? ($lead->owner->company_name ?: $lead->owner->name) : $lead->owner->name) : '—' }}</td>
                                        <td><span class="pill {{ $lead->status === 'active' ? 'pill-info' : 'pill-muted' }}">{{ $lead->stage?->name ?? ucfirst($lead->status) }}</span></td>
                                        <td class="text-end cell-sub">{{ $lead->created_at?->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="dash-empty">No CRM leads yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab-properties">
                    <div class="table-responsive">
                        <table class="mini-table">
                            <thead><tr><th>Listing</th><th>Owner</th><th class="text-end">Price</th><th>Status</th><th class="text-end">Added</th></tr></thead>
                            <tbody>
                                @forelse($latestProperties as $property)
                                    <tr>
                                        <td>
                                            <div class="cell-main text-truncate" style="max-width: 240px;">{{ $property->getTranslation('title') }}</div>
                                            <div class="cell-sub">{{ ucfirst($property->listing_type ?? '') }}{{ $property->property_type ? ' · ' . ucfirst(str_replace('_', ' ', $property->property_type)) : '' }}</div>
                                        </td>
                                        <td>{{ $property->owner ? ($property->owner->company_name ?: $property->owner->name) : 'MW Realty' }}</td>
                                        <td class="text-end fw-semibold">{{ $property->price ? number_format($property->price) . ' ' . $property->currency : '—' }}</td>
                                        <td>
                                            @if($property->status)
                                                <span class="pill pill-good"><i class="fas fa-circle" style="font-size: 0.4rem;"></i> Active</span>
                                            @else
                                                <span class="pill pill-muted"><i class="fas fa-circle" style="font-size: 0.4rem;"></i> Inactive</span>
                                            @endif
                                        </td>
                                        <td class="text-end cell-sub">{{ $property->created_at?->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="dash-empty">No properties yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const INK_MUTED = '#64748b';
    const GRID = 'rgba(15,23,42,0.06)';
    const aed = (v) => 'AED ' + Number(v).toLocaleString();
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
    Chart.defaults.color = INK_MUTED;

    const tooltip = {
        backgroundColor: '#1e293b', padding: 10, cornerRadius: 8, displayColors: true, boxPadding: 4,
        titleFont: { size: 11, weight: '700' }, bodyFont: { size: 12, weight: '600' },
    };

    // KPI sparklines: last 12 weeks, one series each, tooltip on hover.
    const weekLabels = @json($activity['leads']['labels']);
    document.querySelectorAll('.js-spark').forEach((el) => {
        const values = JSON.parse(el.dataset.values || '[]');
        const color = el.dataset.color;
        const money = el.dataset.money === '1';
        const g = el.getContext('2d').createLinearGradient(0, 0, 0, 38);
        g.addColorStop(0, color + '33');
        g.addColorStop(1, color + '00');
        new Chart(el, {
            type: 'line',
            data: { labels: weekLabels, datasets: [{ data: values, borderColor: color, backgroundColor: g, borderWidth: 2, fill: true, tension: 0.35, pointRadius: 0, pointHoverRadius: 3, pointBackgroundColor: color }] },
            options: {
                responsive: true, maintainAspectRatio: false, animation: { duration: 700 },
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { ...tooltip, displayColors: false, callbacks: { title: (i) => 'Week of ' + i[0].label, label: (i) => money ? aed(i.parsed.y) : i.parsed.y } } },
                scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
            }
        });
    });

    // Weekly activity — grouped bars, fixed series order.
    const activity = @json($activity);
    const actCtx = document.getElementById('activityChart');
    if (actCtx) {
        const bar = (label, data, color) => ({ label, data, backgroundColor: color, hoverBackgroundColor: color, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom', maxBarThickness: 14, borderColor: '#fff', borderWidth: { left: 1, right: 1 } });
        new Chart(actCtx, {
            type: 'bar',
            data: {
                labels: activity.leads.labels,
                datasets: [
                    bar('Leads', activity.leads.data, '#1e3a5f'),
                    bar('Listings', activity.properties.data, '#6f8fb8'),
                    bar('Sign-ups', activity.signups.data, '#b08d57'),
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { title: (i) => 'Week of ' + i[0].label } } },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                    y: { beginAtZero: true, grid: { color: GRID }, border: { display: false }, ticks: { font: { size: 10 }, precision: 0 } },
                }
            }
        });
    }

    // Account status donut — status colours, labelled in the legend beside it.
    const statusCtx = document.getElementById('accountStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{ data: @json(array_values($accountStatus)), backgroundColor: ['#2f855a', '#b7791f', '#b83232'], borderColor: '#fff', borderWidth: 2, hoverOffset: 4 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '74%', plugins: { legend: { display: false }, tooltip } }
        });
    }

    // Collected revenue — single series bars, period toggle.
    const revenue = @json($collectedSeries);
    const revCtx = document.getElementById('revenueChart');
    if (revCtx) {
        const revenueChart = new Chart(revCtx, {
            type: 'bar',
            data: { labels: revenue.daily.labels, datasets: [{ label: 'Collected', data: revenue.daily.data, backgroundColor: '#276749', hoverBackgroundColor: '#34845d', borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom', maxBarThickness: 26 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { ...tooltip, displayColors: false, callbacks: { label: (i) => aed(i.parsed.y) } } },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                    y: { beginAtZero: true, grid: { color: GRID }, border: { display: false }, ticks: { font: { size: 10 }, callback: (v) => aed(v) } },
                }
            }
        });

        const totalEl = document.getElementById('revenuePeriodTotal');
        const labelEl = document.getElementById('revenuePeriodLabel');
        document.querySelectorAll('#revenuePeriodToggle button').forEach((btn) => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#revenuePeriodToggle button').forEach((b) => b.classList.remove('active'));
                this.classList.add('active');
                const series = revenue[this.dataset.period];
                revenueChart.data.labels = series.labels;
                revenueChart.data.datasets[0].data = series.data;
                revenueChart.update();
                totalEl.textContent = aed(series.data.reduce((a, b) => a + b, 0));
                labelEl.textContent = this.dataset.label;
            });
        });
    }
});
</script>
@endpush
