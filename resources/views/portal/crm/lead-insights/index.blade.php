@extends('portal.crm._layout')

@section('title', 'Lead Insights')

{{-- Lead Insights — website activity of the viewer's leads (LeadInsightsController), All Leads layout. --}}
@php
    $fmt = fn ($s) => \App\Services\Visitors\VisitorInsights::duration((int) $s);
@endphp

@push('styles')
<style>
    .li-row { cursor: pointer; }
    .li-metric { white-space: nowrap; font-weight: 600; color: #1f2340; }
    .li-metric small { display: block; font-weight: 400; color: #8a8fae; font-size: 11.5px; }
    .li-heat { display: inline-block; width: 64px; height: 6px; border-radius: 999px; background: #eef0f6; overflow: hidden; vertical-align: middle; margin-left: 6px; }
    .li-heat span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #f2a33a, #b9233c); }
</style>
@endpush

@section('crm-content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <div class="portal-section-title mb-0">Lead Insights</div>
        <p class="text-muted mb-0" style="font-size: 0.88rem; max-width: 760px;">How {{ $isAdmin ? 'website' : 'your website' }} leads behave on the site — listings they view, time spent, searches and AI chats. Open one to see the full activity on its Insights tab.</p>
    </div>
</div>

<div class="portal-lp-stats portal-card p-3 mb-3">
    <div class="portal-lp-stat">
        <span class="portal-lp-stat-icon"><i class="fas fa-users"></i></span>
        <div class="min-w-0"><div class="portal-lp-stat-label">Leads with website activity</div><div class="portal-lp-stat-value">{{ number_format($totals['leads']) }}</div></div>
    </div>
    <div class="portal-lp-stat">
        <span class="portal-lp-stat-icon is-accent"><i class="fas fa-house"></i></span>
        <div class="min-w-0"><div class="portal-lp-stat-label">Property views</div><div class="portal-lp-stat-value">{{ number_format($totals['property_views']) }}</div></div>
    </div>
    <div class="portal-lp-stat">
        <span class="portal-lp-stat-icon is-warning"><i class="fas fa-clock"></i></span>
        <div class="min-w-0"><div class="portal-lp-stat-label">Total time on site</div><div class="portal-lp-stat-value">{{ $fmt($totals['seconds']) }}</div></div>
    </div>
    <div class="portal-lp-stat">
        <span class="portal-lp-stat-icon is-success"><i class="fas fa-bolt"></i></span>
        <div class="min-w-0"><div class="portal-lp-stat-label">Active this week</div><div class="portal-lp-stat-value">{{ number_format($totals['active_week']) }} <small class="text-muted fw-normal">· {{ $totals['chats'] }} AI chats</small></div></div>
    </div>
</div>

<div class="portal-card portal-filter-bar mb-3">
    <form method="GET" class="portal-filter-bar__form">
        <div class="portal-filter-field portal-filter-field--search {{ $search !== '' ? 'is-set' : '' }}">
            <label class="visually-hidden" for="liSearch">Search</label>
            <div class="portal-filter-control">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="liSearch" name="search" value="{{ $search }}" class="form-control" placeholder="Name, email or phone" autocomplete="off" maxlength="100">
            </div>
        </div>
        <div class="portal-filter-field {{ $sort !== 'active' ? 'is-set' : '' }}">
            <label class="visually-hidden" for="liSort">Sort</label>
            <div class="portal-filter-control">
                <i class="fas fa-arrow-down-wide-short" aria-hidden="true"></i>
                <select id="liSort" name="sort" class="form-select" onchange="this.form.submit()">
                    @foreach($sorts as $key => $label)
                    <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="portal-filter-actions">
            <button type="submit" class="portal-filter-submit" title="Apply" aria-label="Apply"><i class="fas fa-filter" aria-hidden="true"></i></button>
            @if($search !== '' || $sort !== 'active')
            <a href="{{ route('portal.crm.lead-insights.index') }}" class="portal-filter-bar__clear" title="Clear"><i class="fas fa-xmark" aria-hidden="true"></i> Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="portal-card p-3 p-md-4">
    @php $maxSeconds = max(1, (int) $leads->max('visitor_seconds')); @endphp
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th>Lead</th>
                    @if($isAdmin)<th>Owner</th>@endif
                    <th>Stage</th>
                    <th>Property views</th>
                    <th>Time on site</th>
                    <th>Searches</th>
                    <th>AI chats</th>
                    <th>Last active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr class="portal-lead-row li-row" data-href="{{ route('portal.crm.leads.show', $lead->id) }}#insights" tabindex="0" aria-label="View insights for {{ $lead->name ?: 'lead' }}">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="portal-lead-avatar">{{ strtoupper(mb_substr($lead->name ?: '?', 0, 1)) }}</span>
                            <div class="min-w-0">
                                <a href="{{ route('portal.crm.leads.show', $lead->id) }}#insights" class="portal-lead-name-button text-decoration-none text-truncate d-block" style="max-width: 200px;" title="{{ $lead->name }}">{{ $lead->name ?: 'Unknown' }}</a>
                                <div class="text-muted text-truncate" style="font-size: 0.78rem; max-width: 200px;">{{ $lead->email ?: $lead->formatted_phone ?: '-' }}</div>
                            </div>
                        </div>
                    </td>
                    @if($isAdmin)<td>{{ $lead->owner?->displayName() ?? 'Unassigned' }}</td>@endif
                    <td>
                        @if($lead->stage)
                        <span class="portal-lp-badge" style="--badge: {{ $lead->stage->color }};"><span class="portal-lp-badge-dot"></span>{{ $lead->stage->name }}</span>
                        @else
                        <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td><span class="li-metric">{{ (int) $lead->visitor_property_views }}<small>{{ (int) $lead->visitor_properties }} {{ \Illuminate\Support\Str::plural('listing', (int) $lead->visitor_properties) }}</small></span></td>
                    <td class="text-nowrap">
                        <span class="li-metric">{{ $fmt($lead->visitor_seconds) }}</span>
                        <span class="li-heat" aria-hidden="true"><span style="width: {{ round(100 * (int) $lead->visitor_seconds / $maxSeconds) }}%;"></span></span>
                    </td>
                    <td><span class="li-metric">{{ (int) $lead->visitor_searches }}</span></td>
                    <td><span class="li-metric">{{ (int) $lead->visitor_chats }}</span></td>
                    <td class="text-muted text-nowrap">{{ $lead->visitor_last_seen ? \Illuminate\Support\Carbon::parse($lead->visitor_last_seen)->diffForHumans() : '—' }}</td>
                    <td class="text-end"><a href="{{ route('portal.crm.leads.show', $lead->id) }}#insights" class="portal-lead-insights"><i class="fas fa-chart-line" aria-hidden="true"></i>Insights</a></td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 9 : 8 }}" class="portal-empty text-center text-muted py-5">
                        <i class="fas fa-chart-line fa-2x mb-2 d-block opacity-50"></i>
                        {{ $search !== '' ? 'No leads match your search.' : 'No leads with website activity yet — they appear here once website visitors (AI chat, enquiry forms, customer accounts) become your leads.' }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($leads->hasPages())
    <div class="mt-3">{{ $leads->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.li-row').forEach(function (row) {
    const open = function (e) {
        if (e.target.closest('a, button, input')) return;
        window.location = row.dataset.href;
    };
    row.addEventListener('click', open);
    row.addEventListener('keydown', function (e) { if (e.key === 'Enter') open(e); });
});
</script>
@endpush
