{{--
    Compact plan-usage chips (listings used + featured quota) for the Properties / Commercial / Featured
    toolbars, instead of full-width banners. Details sit in each chip's tooltip.

    Expects: $planUsage (null for Super Admin), $featuredQuota (null for Super Admin); optional
    $showManageFeatured (link to the Featured menu, default true).
--}}
@php $showManageFeatured = $showManageFeatured ?? true; @endphp
@if(!empty($planUsage) && $planUsage['plan'])
    @php
        $limit = $planUsage['plan']->property_limit;
        $full = $planUsage['remaining'] === 0;
        $pct = $planUsage['remaining'] === null || !$limit ? 0 : min(100, round($planUsage['used'] / $limit * 100));
    @endphp
    <span class="portal-usage-chip {{ $full ? 'is-danger' : '' }}"
          title="{{ $planUsage['plan']->getTranslation('name') }} plan — {{ $planUsage['remaining'] === null ? 'unlimited listings' : $planUsage['used'] . ' of ' . $limit . ' listings used' }} (Properties and Commercial combined){{ $full ? '. Upgrade to add more.' : '' }}">
        <i class="fas fa-layer-group"></i>
        <span class="portal-usage-chip__label">{{ $planUsage['plan']->getTranslation('name') }}</span>
        <strong>{{ $planUsage['remaining'] === null ? $planUsage['used'] . ' / ∞' : $planUsage['used'] . ' / ' . $limit }}</strong>
        @if($planUsage['remaining'] !== null)
        <span class="portal-usage-chip__meter"><span style="width: {{ $pct }}%"></span></span>
        @endif
        @if($full)<a href="{{ route('portal.plans.index') }}">Upgrade</a>@endif
    </span>
@endif

@if(!empty($featuredQuota))
    @if($featuredQuota['limit'] > 0)
    <span class="portal-usage-chip is-featured {{ $featuredQuota['remaining'] === 0 ? 'is-full' : '' }}"
          title="Premium: {{ $featuredQuota['used'] }} of {{ $featuredQuota['limit'] }} {{ $featuredQuota['per_month'] ? 'used this month (resets ' . now()->addMonthNoOverflow()->startOfMonth()->format('d M') . ')' : 'live or scheduled — a slot frees up when one ends or you stop it' }}. {{ $featuredQuota['max_days'] ? 'Up to ' . $featuredQuota['max_days'] . ' days each.' : 'No limit on length.' }}">
        <i class="fas fa-star"></i>
        <span class="portal-usage-chip__label">Premium</span>
        <strong>{{ $featuredQuota['used'] }} / {{ $featuredQuota['limit'] }}</strong>
        <span class="portal-usage-chip__muted">{{ $featuredQuota['per_month'] ? '/ month' : '' }}{{ $featuredQuota['max_days'] ? ' · ' . $featuredQuota['max_days'] . 'd max' : '' }}</span>
        @if($showManageFeatured)<a href="{{ route('portal.featured.index') }}">Manage</a>@endif
    </span>
    @else
    <span class="portal-usage-chip is-featured" title="Premium listings appear in the Premium section on the website. Not included in your plan.">
        <i class="fas fa-star"></i>
        <span class="portal-usage-chip__label">Premium</span>
        <span class="portal-usage-chip__muted">not in plan</span>
        <a href="{{ route('portal.plans.index') }}">Upgrade</a>
    </span>
    @endif
@endif
