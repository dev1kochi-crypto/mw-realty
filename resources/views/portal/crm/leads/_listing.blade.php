{{-- Filters + stats cards + table + pagination — reloaded via AJAX (LeadController::index
     with an XHR header) after Create/Edit/Delete so the page never has to fully reload. --}}
<form method="GET" class="portal-card p-3 mb-3 d-flex flex-wrap gap-3 align-items-end">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <div>
        <label class="form-label small mb-1 text-muted fw-semibold">Search</label>
        <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name, email, phone" style="min-width: 180px;">
    </div>
    @if($isAdmin)
    <div>
        <label class="form-label small mb-1 text-muted fw-semibold">Owner</label>
        <select name="owner_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All owners</option>
            @foreach($owners as $owner)
            <option value="{{ $owner->id }}" {{ (string) request('owner_id') === (string) $owner->id ? 'selected' : '' }}>{{ $owner->displayName() }}</option>
            @endforeach
        </select>
    </div>
    @endif
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
    <div>
        <label class="form-label small mb-1 text-muted fw-semibold">From</label>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
    </div>
    <div>
        <label class="form-label small mb-1 text-muted fw-semibold">To</label>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
    </div>
    <button type="submit" class="btn btn-portal-primary btn-sm">Filter</button>
    @if(request('search') || request('owner_id') || request('stage_id') || request('source_id') || request('tag_id') || request('date_from') || request('date_to'))
    <a href="{{ route('portal.crm.leads.index', ['status' => request('status')]) }}" class="btn btn-sm btn-link text-muted">Clear filters</a>
    @endif
</form>


<div id="leadBulkActionsBar" class="portal-card p-2 px-3 mb-3 d-none d-flex align-items-center justify-content-between">
    <span class="small fw-semibold"><span id="leadSelectedCount">0</span> selected</span>
    <button type="button" class="btn btn-sm btn-outline-danger" id="bulkDeleteLeadsBtn">
        <i class="fas fa-trash-can me-1"></i> Delete Selected
    </button>
</div>

<div class="portal-card p-3 p-md-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th style="width: 2.5rem;"><input type="checkbox" class="form-check-input" id="selectAllLeads"></th>
                    <th>Lead</th>
                    <th>Property</th>
                    @if($isAdmin)
                    <th>Owner</th>
                    @endif
                    <th>Stage</th>
                    <th>Source</th>
                    <th>Tags</th>
                    <th>Received</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr>
                    <td><input type="checkbox" class="form-check-input lead-select-checkbox" value="{{ $lead->id }}"></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="portal-lead-avatar">{{ strtoupper(mb_substr($lead->name ?: '?', 0, 1)) }}</span>
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $lead->name ?: 'Unknown' }}</div>
                                <div class="text-muted text-truncate" style="font-size: 0.78rem; max-width: 180px;">{{ $lead->email ?: $lead->formatted_phone ?: '-' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $lead->property?->getTranslation('title') ?? '-' }}</td>
                    @if($isAdmin)
                    <td>
                        @if($lead->owner)
                            {{ $lead->owner->displayName() }}
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    @endif
                    <td>
                        <div class="portal-inline-stage">
                            <button type="button" class="portal-stage-picker" title="Change stage" aria-label="Change stage for {{ $lead->name ?: 'lead' }}">
                                @if($lead->stage)
                                <span class="portal-stage-pill" style="background: {{ $lead->stage->color }}22; color: {{ $lead->stage->color }};">
                                    <span class="portal-color-dot" style="background: {{ $lead->stage->color }};"></span>{{ $lead->stage->name }}
                                </span>
                                @else
                                <span class="portal-stage-picker-empty"><i class="fas fa-plus" aria-hidden="true"></i> Set stage</span>
                                @endif
                                <i class="fas fa-chevron-down portal-stage-picker-chevron" aria-hidden="true"></i>
                            </button>
                            <select class="form-select form-select-sm portal-stage-select d-none" data-lead-id="{{ $lead->id }}" aria-label="Stage for {{ $lead->name ?: 'lead' }}">
                                <option value="">No stage</option>
                                @foreach($stages as $stage)
                                <option value="{{ $stage->id }}" {{ (int) $lead->stage_id === $stage->id ? 'selected' : '' }}>{{ $stage->name }}</option>
                                @endforeach
                                @if($lead->stage && !$stages->contains('id', $lead->stage_id))
                                <option value="{{ $lead->stage->id }}" selected>{{ $lead->stage->name }} (current)</option>
                                @endif
                            </select>
                            <span class="spinner-border spinner-border-sm text-primary d-none portal-stage-spinner" role="status" aria-hidden="true"></span>
                        </div>
                    </td>
                    <td>{{ $lead->source?->name ?? '—' }}</td>
                    <td>
                        @forelse($lead->tags as $tag)
                        <span class="portal-tag-chip" style="background: {{ $tag->color }}22; color: {{ $tag->color }};">{{ $tag->name }}</span>
                        @empty
                        <span class="text-muted">&mdash;</span>
                        @endforelse
                    </td>
                    <td class="text-muted">{{ $lead->created_at->format('d M Y') }}</td>
                    <td class="text-end text-nowrap">
                        <div class="portal-lead-actions" role="group" aria-label="Actions for {{ $lead->name ?: 'lead' }}">
                            <button type="button" class="portal-lead-action is-view view-lead-btn" data-id="{{ $lead->id }}" title="View lead details" aria-label="View {{ $lead->name ?: 'lead' }} details">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="portal-lead-action is-edit edit-lead-btn" data-id="{{ $lead->id }}" title="Edit lead" aria-label="Edit {{ $lead->name ?: 'lead' }}">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 9 : 8 }}" class="portal-empty">
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
