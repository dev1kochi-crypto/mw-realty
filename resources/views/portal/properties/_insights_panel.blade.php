{{--
    Listing Performance panel — loaded into the #listingPerformance offcanvas (index.blade.php) from
    PortalPropertyController::insights(). Photo banner, then Insights (KPI tiles, conversion steps,
    30-day chart, quality gauge) / Overview / Leads. Data from ListingPerformanceService.
--}}
@php
    $thumb = $property->galleryImages()[0]['url'] ?? null;
    $title = $property->getTranslation('title') ?: $property->reference_no;
    $fmtNum = fn ($n) => $n >= 10000 ? number_format($n / 1000, 1) . 'k' : number_format($n);
    $pct = fn ($part, $whole) => $whole ? round(100 * $part / $whole, 1) . '%' : '—';

    // Conversion steps — bar widths relative to the biggest step (impressions, normally).
    $steps = [
        ['Impressions', $funnel['impressions'], 'fa-eye', 'navy', null],
        ['Listing clicks', $funnel['clicks'], 'fa-arrow-pointer', 'red', $pct($funnel['clicks'], $funnel['impressions'])],
        ['Lead clicks', $funnel['lead_clicks'], 'fa-phone-volume', 'amber', $pct($funnel['lead_clicks'], $funnel['clicks'])],
        ['Leads', $funnel['leads'], 'fa-user-check', 'green', $pct($funnel['leads'], $funnel['clicks'])],
    ];
    $stepMax = max(1, ...array_column($steps, 1));

    // 30-day chart as SVG polylines (impressions area + clicks line).
    $w = 320; $h = 84; $n = max(1, $series->count() - 1);
    $chartMax = max(1, $seriesMax);
    $point = fn ($i, $v) => round($i * $w / $n, 1) . ',' . round($h - 4 - ($v / $chartMax) * ($h - 12), 1);
    $impLine = $series->values()->map(fn ($d, $i) => $point($i, $d['impressions']))->implode(' ');
    $clickLine = $series->values()->map(fn ($d, $i) => $point($i, $d['clicks']))->implode(' ');
    $hasActivity = $last30['impressions'] + $last30['clicks'] + $last30['lead_clicks'] > 0;

    $grade = match ($quality['tone']) { 'good' => 'Excellent', 'fair' => 'Good — room to improve', default => 'Needs work' };
    $fixes = collect($quality['groups'])->flatten(1)->filter(fn ($c) => $c['tip'])->sortByDesc(fn ($c) => $c['max'] - $c['points'])->take(3);
    $statusLabel = $property->isSold() ? ucfirst($property->sold_type ?? 'sold') : ($property->status ? 'Active' : 'Inactive');
@endphp

{{-- Banner --}}
<div class="lp-banner" style="background-image: url('{{ $thumb ?: '' }}');">
    <div class="lp-banner__shade"></div>
    <div class="lp-banner__chips">
        <span class="lp-chip {{ $property->status ? 'is-live' : '' }}"><i class="fas fa-circle"></i>{{ $statusLabel }}</span>
        @if($property->featured)<span class="lp-chip is-premium"><i class="fas fa-star"></i>Premium</span>@endif
    </div>
    <div class="lp-banner__text">
        <div class="lp-banner__title" title="{{ $title }}">{{ $title }}</div>
        <div class="lp-banner__meta">{{ $property->reference_no ? $property->reference_no . ' · ' : '' }}{{ $property->price ? number_format($property->price) . ' ' . $property->currency : 'Price on request' }}</div>
    </div>
</div>

<div class="lp-seg" role="tablist">
    <button class="active" data-bs-toggle="tab" data-bs-target="#lpInsights" type="button" role="tab"><i class="fas fa-chart-simple"></i>Insights</button>
    <button data-bs-toggle="tab" data-bs-target="#lpOverview" type="button" role="tab"><i class="fas fa-circle-info"></i>Overview</button>
    <button data-bs-toggle="tab" data-bs-target="#lpLeads" type="button" role="tab"><i class="fas fa-user-group"></i>Leads<span class="lp-seg__count">{{ $leads->count() }}</span></button>
</div>

<div class="tab-content">
    {{-- ============ Insights ============ --}}
    <div class="tab-pane fade show active" id="lpInsights" role="tabpanel">
        <div class="lp-kpis">
            @foreach($steps as [$label, $value, $icon, $tone])
            <div class="lp-kpi tone-{{ $tone }}">
                <span class="lp-kpi__icon"><i class="fas {{ $icon }}"></i></span>
                <div class="lp-kpi__value">{{ $label === 'Leads' ? number_format($value) : $fmtNum($value) }}</div>
                <div class="lp-kpi__label">{{ $label }}</div>
                @if($label !== 'Leads')
                <div class="lp-kpi__sub">{{ number_format($last30[['Impressions' => 'impressions', 'Listing clicks' => 'clicks', 'Lead clicks' => 'lead_clicks'][$label]]) }} in 30 days</div>
                @else
                <div class="lp-kpi__sub">all time</div>
                @endif
            </div>
            @endforeach
        </div>

        <section class="lp-block">
            <div class="lp-block__head"><span>Conversion</span><small>{{ $updatedAt ? 'Updated ' . $updatedAt->diffForHumans() : 'No activity yet' }}</small></div>
            @foreach($steps as [$label, $value, $icon, $tone, $rate])
            @if($rate !== null)<div class="lp-step-rate"><i class="fas fa-arrow-down"></i>{{ $rate }}</div>@endif
            <div class="lp-step tone-{{ $tone }}">
                <span class="lp-step__label">{{ $label }}</span>
                <span class="lp-step__track"><span style="width: {{ $value ? max(3, round(100 * $value / $stepMax)) : 0 }}%;"></span></span>
                <span class="lp-step__value">{{ number_format($value) }}</span>
            </div>
            @endforeach
        </section>

        <section class="lp-block">
            <div class="lp-block__head"><span>Last {{ \App\Services\ListingPerformanceService::DAYS }} days</span>
                <small><i class="lp-key is-imp"></i>Impressions <i class="lp-key is-click ms-2"></i>Clicks</small></div>
            @if($hasActivity)
            <svg class="lp-chart" viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" role="img" aria-label="Impressions and clicks over the last 30 days">
                <polygon points="0,{{ $h }} {{ $impLine }} {{ $w }},{{ $h }}" class="lp-chart__area"/>
                <polyline points="{{ $impLine }}" class="lp-chart__imp"/>
                <polyline points="{{ $clickLine }}" class="lp-chart__click"/>
            </svg>
            <div class="lp-chart__axis"><span>{{ $series->first()['date']->format('d M') }}</span><span>Today</span></div>
            @else
            <div class="lp-chart-empty"><i class="fas fa-chart-line"></i>No views yet in the last {{ \App\Services\ListingPerformanceService::DAYS }} days — activity shows here as visitors see and open this listing.</div>
            @endif
            @if($interestedVisitors)
            <div class="lp-hint"><i class="fas fa-user-clock"></i>{{ $interestedVisitors }} identified website {{ \Illuminate\Support\Str::plural('visitor', $interestedVisitors) }} opened this listing.</div>
            @endif
        </section>

        <section class="lp-block">
            <div class="lp-quality">
                <div class="lp-gauge is-{{ $quality['tone'] }}" style="--pq: {{ $quality['score'] }};"><span><strong>{{ $quality['score'] }}</strong><small>/100</small></span></div>
                <div class="min-w-0">
                    <div class="lp-quality__title">Listing quality</div>
                    <div class="lp-quality__grade is-{{ $quality['tone'] }}">{{ $grade }}</div>
                    <div class="lp-quality__hint">Higher quality listings rank better and get more clicks.</div>
                </div>
            </div>
            @if($fixes->isNotEmpty())
            <div class="lp-fixes">
                <div class="lp-fixes__title"><i class="fas fa-wand-magic-sparkles"></i>Improve next</div>
                @foreach($fixes as $fix)
                <div class="lp-fix"><span>{{ $fix['tip'] }}</span><b>+{{ $fix['max'] - $fix['points'] }}</b></div>
                @endforeach
            </div>
            @endif
            <div class="lp-checks">
                @foreach(collect($quality['groups'])->flatten(1) as $check)
                <div class="lp-check">
                    <span class="lp-check__label">{{ $check['label'] }}</span>
                    <span class="lp-check__bar {{ $check['points'] >= $check['max'] ? 'is-full' : ($check['points'] > 0 ? 'is-part' : 'is-none') }}"><span style="width: {{ round(100 * $check['points'] / $check['max']) }}%;"></span></span>
                    <span class="lp-check__pts">{{ $check['points'] }}<small>/{{ $check['max'] }}</small></span>
                </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- ============ Overview ============ --}}
    <div class="tab-pane fade" id="lpOverview" role="tabpanel">
        {{-- Who works / changed the listing --}}
        @php
            $assignedTo = $property->agent?->name ?? ($property->owner?->type === 'agent' ? $property->owner->name : ($property->owner?->displayName() ?? 'MW Realty'));
            $people = [
                ['Assigned to', 'fa-user-tie', $assignedTo],
                ['Created by', 'fa-user-pen', $property->createdByName() ?? '—'],
                ['Updated by', 'fa-user-gear', $property->updatedByName() ?? '—'],
                ['Last updated', 'fa-calendar-check', $property->updated_at->format('M d, Y, h:i A')],
            ];
        @endphp
        <div class="lp-people">
            @foreach($people as [$label, $icon, $value])
            <div class="lp-people__row">
                <span class="lp-people__label">{{ $label }}</span>
                <span class="lp-people__value" title="{{ $value }}"><i class="fas {{ $icon }}"></i>{{ $value }}</span>
            </div>
            @endforeach
        </div>

        <div class="lp-tiles">
            <div class="lp-tile"><i class="fas fa-toggle-on"></i><span>Status</span><strong>{{ $statusLabel }}</strong></div>
            <div class="lp-tile"><i class="fas fa-file-shield"></i><span>Permit</span><strong>{{ $property->complianceLabel() }}</strong></div>
            <div class="lp-tile"><i class="fas fa-star"></i><span>Exposure</span><strong>{{ $property->featured ? 'Premium' . ($property->featured_until ? ' · ' . $property->featured_until->format('d M') : '') : 'Standard' }}</strong></div>
            <div class="lp-tile"><i class="fas fa-user-tie"></i><span>Agent</span><strong>{{ $property->agent?->name ?? ($property->owner?->type === 'agent' ? $property->owner->name : 'Agency listing') }}</strong></div>
            @if($isAdmin)<div class="lp-tile"><i class="fas fa-building"></i><span>Owner</span><strong>{{ $property->owner?->displayName() ?? 'MW Realty' }}</strong></div>@endif
            <div class="lp-tile"><i class="far fa-calendar"></i><span>Listed</span><strong>{{ $property->created_at->format('d M Y') }}</strong></div>
            @if($property->permit_number)<div class="lp-tile"><i class="fas fa-hashtag"></i><span>Permit no.</span><strong>{{ $property->permit_number }}</strong></div>@endif
            <div class="lp-tile"><i class="fas fa-gauge-high"></i><span>Quality</span><strong>{{ $quality['score'] }}/100</strong></div>
        </div>
        <div class="lp-actions">
            <a href="{{ route($routePrefix . '.edit', $property->id) }}" class="btn btn-portal-primary"><i class="fas fa-edit me-1"></i>Edit listing</a>
            @if($property->slug)<a href="{{ url('/property-details/' . $property->slug) }}" target="_blank" rel="noopener" class="btn portal-btn-ghost"><i class="fas fa-arrow-up-right-from-square me-1"></i>View on website</a>@endif
        </div>
    </div>

    {{-- ============ Leads ============ --}}
    <div class="tab-pane fade" id="lpLeads" role="tabpanel">
        @forelse($leads as $lead)
        <a href="{{ route('portal.crm.leads.show', $lead->id) }}" class="lp-lead">
            <span class="lp-lead__avatar">{{ strtoupper(mb_substr($lead->name ?: '?', 0, 1)) }}</span>
            <span class="min-w-0 flex-grow-1">
                <span class="lp-lead__name">{{ $lead->name ?: 'Unknown' }}</span>
                <span class="lp-lead__meta"><i class="fas fa-globe"></i>{{ $lead->source?->name ?? '—' }} · {{ $lead->created_at->diffForHumans() }}{{ $lead->agent ? ' · ' . $lead->agent->name : '' }}</span>
            </span>
            @if($lead->stage)<span class="lp-stage" style="--stage: {{ $lead->stage->color ?: '#6b7094' }};">{{ $lead->stage->name }}</span>@endif
            <i class="fas fa-chevron-right lp-lead__go"></i>
        </a>
        @empty
        <div class="lp-empty">
            <span class="lp-empty__icon"><i class="fas fa-user-group"></i></span>
            <strong>No leads yet</strong>
            <span>Enquiries, viewing requests and website visitors routed to you for this listing will show here.</span>
        </div>
        @endforelse
    </div>
</div>
