@extends('portal.layouts.app')

@section('title', 'Agents')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="portal-section-title mb-0">All Agents</div>
    <a href="{{ route('cms.agency-agents.index') }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-user-clock me-1"></i> Agency approvals</a>
</div>

<div class="portal-card p-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th>Agent</th>
                    <th>Agency</th>
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
                    <td>{{ $agent->company ? $agent->company->displayName() : 'Independent' }}</td>
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
                <tr><td colspan="6" class="portal-empty">No agents yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $agents->links('pagination::bootstrap-5') }}</div>
@endsection
