@extends('portal.layouts.app')

@section('title', 'Agents')

@section('content')
@php
    $slotsFull = $agentSlots['remaining'] === 0;
    $agentsLocked = $slotsFull && !$agentSlots['limit'];
    $slotPct = $agentSlots['remaining'] === null || !$agentSlots['limit'] ? 0 : min(100, round($agentSlots['used'] / $agentSlots['limit'] * 100));
@endphp
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <div class="portal-section-title mb-1">My Agents</div>
        <p class="text-muted small mb-0">
            Enquiries on a listing with an assigned agent go to that agent. Listings without one rotate enquiries across your active agents in turn.
        </p>
    </div>
    <div class="d-flex align-items-center flex-wrap gap-2">
        <span class="portal-usage-chip {{ $slotsFull ? 'is-danger' : '' }}"
              title="{{ $agentSlots['remaining'] === null ? 'Unlimited agent slots' : $agentSlots['used'] . ' of ' . (int) $agentSlots['limit'] . ' agent slots used' }}{{ $slotsFull ? '. Upgrade to add more.' : '' }}">
            <i class="fas fa-users"></i>
            <span class="portal-usage-chip__label">Agent slots</span>
            <strong>{{ $agentSlots['used'] }} / {{ $agentSlots['remaining'] === null ? '∞' : (int) $agentSlots['limit'] }}</strong>
            @if($agentSlots['remaining'] !== null)
            <span class="portal-usage-chip__meter"><span style="width: {{ $slotPct }}%"></span></span>
            @endif
        </span>
        @unless($slotsFull)
        <a href="{{ route('portal.agents.create') }}" class="btn btn-portal-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Add / Invite Agent
        </a>
        @endunless
    </div>
</div>

@if($slotsFull)
<div class="alert alert-info d-flex align-items-center gap-2 small py-2 mb-3">
    <i class="fas fa-circle-info"></i>
    <span>
        @if($agentsLocked)
            Team agent accounts are available after you upgrade your plan.
        @else
            You've used all {{ (int) $agentSlots['limit'] }} agent slots on your plan. Upgrade your plan to add more agents.
        @endif
    </span>
</div>
@endif

{{-- Lead distribution: who got the last round-robin lead, whose turn is next. --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="portal-stat-card h-100">
            <div class="portal-stat-icon indigo"><i class="fas fa-user-check"></i></div>
            <div>
                <div class="portal-stat-value">{{ $eligibleAgents->count() }}</div>
                <div class="portal-stat-label">Active agents in rotation</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="portal-stat-card h-100">
            <div class="portal-stat-icon teal"><i class="fas fa-rotate"></i></div>
            <div class="min-w-0">
                <div class="fw-bold text-truncate">{{ $roundRobin['last']?->name ?? '—' }}</div>
                <div class="portal-stat-label">Last round-robin lead{{ $roundRobin['last_at'] ? ' · ' . $roundRobin['last_at']->diffForHumans() : '' }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="portal-stat-card h-100">
            <div class="portal-stat-icon amber"><i class="fas fa-forward"></i></div>
            <div class="min-w-0">
                <div class="fw-bold">{{ $roundRobin['next']?->name ?? 'No active agents' }}</div>
                <div class="portal-stat-label">{{ $roundRobin['next'] ? 'Next in line' : 'Leads stay with the agency' }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="portal-stat-card h-100">
            <div class="portal-stat-icon rose"><i class="fas fa-inbox"></i></div>
            <div class="flex-grow-1">
                <div class="portal-stat-value">{{ $roundRobin['unassigned'] }}</div>
                <div class="portal-stat-label">Unassigned agency leads</div>
            </div>
            @if($roundRobin['unassigned'] > 0 && $eligibleAgents->isNotEmpty())
            <form method="POST" action="{{ route('portal.crm.leads.distribute') }}" onsubmit="return confirm('Round-robin all unassigned leads across your active agents?');">
                @csrf
                <button type="submit" class="btn btn-sm portal-btn-ghost" title="Distribute across active agents"><i class="fas fa-shuffle"></i></button>
            </form>
            @endif
        </div>
    </div>
</div>

<ul class="nav portal-lang-tabs mb-3">
    @foreach(['active' => 'Active', 'pending' => 'Pending & Invited', 'requests' => 'Join Requests', 'history' => 'History'] as $key => $label)
    <li class="nav-item">
        <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('portal.agents.index', ['tab' => $key]) }}">
            {{ $label }}<span class="portal-tab-count">{{ $tabCounts[$key] }}</span>
        </a>
    </li>
    @endforeach
</ul>

<div class="portal-card p-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th>Agent</th>
                    <th>Contact</th>
                    <th>Status</th>
                    @if($tab === 'active')
                    <th class="text-center">Listings</th>
                    <th class="text-center">Leads</th>
                    @endif
                    <th>{{ $tab === 'history' ? 'Ended' : 'Since' }}</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($memberships as $membership)
                @php($agent = $membership->agent)
                <tr>
                    <td class="fw-semibold">
                        @if(in_array($membership->status, \App\Models\AgencyAgent::MEMBER_STATUSES, true))
                        <a href="{{ route('portal.agents.show', $membership->id) }}" class="text-decoration-none">{{ $agent->name }}</a>
                        @else
                        {{ $agent->name }}
                        @endif
                        <div class="portal-muted small">Agent ID {{ $agent->id }}</div>
                    </td>
                    <td>
                        <div>{{ $agent->email }}</div>
                        @if($agent->phone)<div class="text-muted small">{{ $agent->phone }}</div>@endif
                    </td>
                    <td>
                        <span class="badge bg-{{ $membership->statusTone() }}-subtle text-{{ $membership->statusTone() }}-emphasis border">{{ $membership->statusLabel() }}</span>
                        @if($membership->rejection_reason)<div class="text-muted small">{{ $membership->rejection_reason }}</div>@endif
                    </td>
                    @if($tab === 'active')
                    <td class="text-center">{{ $propertyCounts[$agent->id] ?? 0 }}</td>
                    <td class="text-center">{{ $leadCounts[$agent->id] ?? 0 }}</td>
                    @endif
                    <td class="small">{{ ($tab === 'history' ? ($membership->left_at ?? $membership->responded_at ?? $membership->updated_at) : ($membership->joined_at ?? $membership->created_at))?->format('d M Y') }}</td>
                    <td class="text-end text-nowrap">
                        @switch($membership->status)
                            @case(\App\Models\AgencyAgent::APPROVED)
                                <form method="POST" action="{{ route('portal.agents.suspend', $membership->id) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-outline-secondary">Suspend</button>
                                </form>
                                <a href="{{ route('portal.agents.show', $membership->id) }}#remove" class="btn btn-sm btn-outline-danger">Remove</a>
                                @break
                            @case(\App\Models\AgencyAgent::SUSPENDED)
                                <form method="POST" action="{{ route('portal.agents.reactivate', $membership->id) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-outline-success">Reactivate</button>
                                </form>
                                <a href="{{ route('portal.agents.show', $membership->id) }}#remove" class="btn btn-sm btn-outline-danger">Remove</a>
                                @break
                            @case(\App\Models\AgencyAgent::REQUESTED)
                                <form method="POST" action="{{ route('portal.agents.accept-request', $membership->id) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-success">Accept</button>
                                </form>
                                <form method="POST" action="{{ route('portal.agents.decline-request', $membership->id) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-outline-danger">Decline</button>
                                </form>
                                @break
                            @case(\App\Models\AgencyAgent::INVITED)
                            @case(\App\Models\AgencyAgent::PENDING)
                                <form method="POST" action="{{ route('portal.agents.cancel', $membership->id) }}" class="d-inline" onsubmit="return confirm('Cancel this {{ $membership->status === 'invited' ? 'invitation' : 'request' }}?');">@csrf
                                    <button class="btn btn-sm btn-outline-secondary">Cancel</button>
                                </form>
                                @break
                        @endswitch
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="portal-empty">
                        @if($tab === 'active' && $agentsLocked)
                            No agents yet — team agent accounts are available after you upgrade your plan.
                        @elseif($tab === 'active')
                            No active agents yet — your listings and enquiries still work without them.
                            @unless($slotsFull)<a href="{{ route('portal.agents.create') }}">Add or invite an agent</a>.@endunless
                        @else
                            Nothing here.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $memberships->links('pagination::bootstrap-5') }}</div>
@endsection
