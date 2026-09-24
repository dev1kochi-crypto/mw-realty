@extends('portal.layouts.app')

@section('title', 'Agents')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="portal-section-title mb-0">{{ $isAdmin ? 'All Agents' : 'My Agents' }}</div>
    @if(!$isAdmin)
    <div class="d-flex align-items-center gap-2">
        @if($agentSlots)
        <span class="portal-muted small fw-semibold">
            <i class="fas fa-users me-1"></i>{{ $agentSlots['used'] }}{{ $agentSlots['remaining'] === null ? '' : ' of ' . (int) $agentSlots['limit'] }} team agents
        </span>
        @endif
        @if($agentSlots && $agentSlots['remaining'] === 0)
        <a href="{{ route('portal.plans.index') }}" class="btn btn-portal-primary btn-sm"><i class="fas fa-rocket me-1"></i> Upgrade to add agents</a>
        @else
        <a href="{{ route('portal.agents.create') }}" class="btn btn-portal-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Add Agent
        </a>
        @endif
    </div>
    @endif
</div>

@if(session('error'))
<div class="alert alert-warning"><i class="fas fa-lock me-2"></i>{{ session('error') }}</div>
@endif

<div class="portal-card p-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th>Agent</th>
                    @if($isAdmin)<th>Agency</th>@endif
                    <th>Contact</th>
                    <th>Experience</th>
                    <th>Preferred Areas</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($agents as $agent)
                <tr>
                    <td class="fw-semibold">{{ $agent->name }}</td>
                    @if($isAdmin)
                    <td>{{ $agent->company ? ($agent->company->company_name ?: $agent->company->name) : '—' }}</td>
                    @endif
                    <td>
                        <div>{{ $agent->email }}</div>
                        @if($agent->phone)<div class="text-muted small">{{ $agent->phone }}</div>@endif
                    </td>
                    <td>{{ $agent->years_of_experience !== null ? $agent->years_of_experience . ' yrs' : '—' }}</td>
                    <td>{{ !empty($agent->preferred_areas) ? implode(', ', $agent->preferred_areas) : '—' }}</td>
                    <td>
                        <span class="portal-badge-status {{ $agent->is_active ? 'portal-badge-active' : 'portal-badge-inactive' }}">
                            {{ $agent->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 6 : 5 }}" class="portal-empty">
                        No agents yet.
                        @if(!$isAdmin)
                            <a href="{{ route('portal.agents.create') }}">Add your first agent</a>.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $agents->links() }}</div>
@endsection
