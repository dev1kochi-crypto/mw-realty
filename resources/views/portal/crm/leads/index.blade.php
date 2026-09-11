@extends('portal.crm._layout')

@section('title', 'Leads')

@section('crm-content')
<div class="mb-4">
    <div class="portal-section-title mb-0">{{ $isAdmin ? 'All Leads' : 'My Leads' }}</div>
    <p class="text-muted mb-0" style="font-size: 0.88rem;">Every enquiry a visitor sends about {{ $isAdmin ? 'any' : 'your' }} listing lands here.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="{{ route('portal.crm.leads.index') }}" class="portal-lead-stat-link">
            <div class="portal-stat-card {{ !request('status') ? 'is-active' : '' }}">
                <div class="portal-stat-icon indigo"><i class="fas fa-layer-group"></i></div>
                <div>
                    <div class="portal-stat-value">{{ $stats['total'] }}</div>
                    <div class="portal-stat-label">All Leads</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('portal.crm.leads.index', ['status' => 'new']) }}" class="portal-lead-stat-link">
            <div class="portal-stat-card {{ request('status') === 'new' ? 'is-active' : '' }}">
                <div class="portal-stat-icon rose"><i class="fas fa-bell"></i></div>
                <div>
                    <div class="portal-stat-value">{{ $stats['new'] }}</div>
                    <div class="portal-stat-label">New</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('portal.crm.leads.index', ['status' => 'contacted']) }}" class="portal-lead-stat-link">
            <div class="portal-stat-card {{ request('status') === 'contacted' ? 'is-active' : '' }}">
                <div class="portal-stat-icon amber"><i class="fas fa-phone"></i></div>
                <div>
                    <div class="portal-stat-value">{{ $stats['contacted'] }}</div>
                    <div class="portal-stat-label">Contacted</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('portal.crm.leads.index', ['status' => 'closed']) }}" class="portal-lead-stat-link">
            <div class="portal-stat-card {{ request('status') === 'closed' ? 'is-active' : '' }}">
                <div class="portal-stat-icon teal"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="portal-stat-value">{{ $stats['closed'] }}</div>
                    <div class="portal-stat-label">Closed</div>
                </div>
            </div>
        </a>
    </div>
</div>

@if(!$isAdmin)
<form method="GET" class="portal-card p-3 mb-3 d-flex flex-wrap gap-3 align-items-end">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <div>
        <label class="form-label small mb-1 text-muted fw-semibold">Stage</label>
        <select name="stage_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All stages</option>
            @foreach($stages as $stage)
            <option value="{{ $stage->id }}" {{ (string) request('stage_id') === (string) $stage->id ? 'selected' : '' }}>{{ $stage->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label small mb-1 text-muted fw-semibold">Source</label>
        <select name="source_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All sources</option>
            @foreach($sources as $source)
            <option value="{{ $source->id }}" {{ (string) request('source_id') === (string) $source->id ? 'selected' : '' }}>{{ $source->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label small mb-1 text-muted fw-semibold">Tag</label>
        <select name="tag_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All tags</option>
            @foreach($tags as $tag)
            <option value="{{ $tag->id }}" {{ (string) request('tag_id') === (string) $tag->id ? 'selected' : '' }}>{{ $tag->name }}</option>
            @endforeach
        </select>
    </div>
    @if(request('stage_id') || request('source_id') || request('tag_id'))
    <a href="{{ route('portal.crm.leads.index', ['status' => request('status')]) }}" class="btn btn-sm btn-link text-muted">Clear filters</a>
    @endif
</form>
@endif

<div class="portal-card p-3 p-md-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th>Lead</th>
                    <th>Property</th>
                    @if($isAdmin)
                    <th>Owner</th>
                    @endif
                    <th>Stage</th>
                    <th>Tags</th>
                    <th>Received</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="portal-lead-avatar">{{ strtoupper(mb_substr($lead->name ?: '?', 0, 1)) }}</span>
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $lead->name ?: 'Unknown' }}</div>
                                <div class="text-muted text-truncate" style="font-size: 0.78rem; max-width: 180px;">{{ $lead->email ?: $lead->phone ?: '-' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $lead->property?->getTranslation('title') ?? '-' }}</td>
                    @if($isAdmin)
                    <td>
                        @if($lead->owner)
                            {{ $lead->owner->type === 'company' ? ($lead->owner->company_name ?: $lead->owner->name) : $lead->owner->name }}
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    @endif
                    <td>
                        @if($lead->stage)
                        <span class="portal-stage-pill" style="background: {{ $lead->stage->color }}22; color: {{ $lead->stage->color }};">
                            <span class="portal-color-dot" style="background: {{ $lead->stage->color }};"></span>{{ $lead->stage->name }}
                        </span>
                        @else
                        <span class="text-muted">&mdash;</span>
                        @endif
                    </td>
                    <td>
                        @forelse($lead->tags as $tag)
                        <span class="portal-tag-chip" style="background: {{ $tag->color }}22; color: {{ $tag->color }};">{{ $tag->name }}</span>
                        @empty
                        <span class="text-muted">&mdash;</span>
                        @endforelse
                    </td>
                    <td class="text-muted">{{ $lead->created_at->format('d M Y') }}</td>
                    <td><span class="portal-badge-status portal-badge-{{ $lead->status }}">{{ ucfirst($lead->status) }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('portal.crm.leads.show', $lead->id) }}" class="portal-btn-ghost btn btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 8 : 7 }}" class="portal-empty">
                        <div class="portal-empty-icon"><i class="fas fa-address-book"></i></div>
                        <div class="fw-semibold mb-1">No leads {{ request('status') || request('stage_id') || request('source_id') || request('tag_id') ? 'match these filters' : 'yet' }}</div>
                        <div style="font-size: 0.85rem;">They'll show up here the moment a visitor enquires about {{ $isAdmin ? 'a' : 'one of your' }} listing{{ $isAdmin ? '' : 's' }}.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $leads->links() }}</div>
@endsection
