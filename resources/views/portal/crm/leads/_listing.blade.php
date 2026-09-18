{{-- Filters + stats cards + table + pagination — reloaded via AJAX (LeadController::index
     with an XHR header) after Create/Edit/Delete so the page never has to fully reload. --}}
@php($activeFilterCount = collect(['search', 'owner_id', 'stage_id', 'source_id', 'tag_id', 'date_from', 'date_to'])->filter(fn ($key) => filled(request($key)))->count())
<div class="portal-card portal-filter-bar mb-3">
    <div class="portal-filter-bar__header d-flex flex-wrap align-items-center gap-2">
        <span class="portal-filter-bar__icon"><i class="fas fa-sliders" aria-hidden="true"></i></span>
        <span class="fw-semibold" style="font-size: 0.88rem;">Filter Leads</span>
        @if($activeFilterCount)
        <span class="portal-filter-bar__badge">{{ $activeFilterCount }} active</span>
        @endif
    </div>
    <form method="GET" class="portal-filter-bar__form p-3 d-flex flex-nowrap gap-3 align-items-end">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="portal-filter-field portal-filter-field--search">
            <label class="form-label mb-1">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, email, phone">
            </div>
        </div>
        @if($isAdmin)
        <div class="portal-filter-field portal-filter-field--owner">
            <label class="form-label mb-1">Owner</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <select name="owner_id" class="form-select">
                    <option value="">All owners</option>
                    @foreach($owners as $owner)
                    <option value="{{ $owner->id }}" {{ (string) request('owner_id') === (string) $owner->id ? 'selected' : '' }}>{{ $owner->displayName() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @endif
        <div class="portal-filter-field portal-filter-field--stage">
            <label class="form-label mb-1">Stage</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
                <select name="stage_id" class="form-select">
                    <option value="">All stages</option>
                    @foreach($stages as $stage)
                    <option value="{{ $stage->id }}" {{ (string) request('stage_id') === (string) $stage->id ? 'selected' : '' }}>{{ $stage->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="portal-filter-field portal-filter-field--source">
            <label class="form-label mb-1">Source</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-share-nodes"></i></span>
                <select name="source_id" class="form-select">
                    <option value="">All sources</option>
                    @foreach($sources as $source)
                    <option value="{{ $source->id }}" {{ (string) request('source_id') === (string) $source->id ? 'selected' : '' }}>{{ $source->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="portal-filter-field portal-filter-field--tag">
            <label class="form-label mb-1">Tag</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-tag"></i></span>
                <select name="tag_id" class="form-select">
                    <option value="">All tags</option>
                    @foreach($tags as $tag)
                    <option value="{{ $tag->id }}" {{ (string) request('tag_id') === (string) $tag->id ? 'selected' : '' }}>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="portal-filter-field portal-filter-field--date">
            <label class="form-label mb-1">From</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-calendar-days"></i></span>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
        </div>
        <div class="portal-filter-field portal-filter-field--date">
            <label class="form-label mb-1">To</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <button type="submit" class="btn btn-portal-primary btn-sm text-nowrap"><i class="fas fa-filter me-1"></i> Filter</button>
            @if($activeFilterCount)
            <a href="{{ route('portal.crm.leads.index', ['status' => request('status')]) }}" class="btn btn-outline-secondary btn-sm text-nowrap"><i class="fas fa-rotate-left me-1"></i> Clear</a>
            @endif
        </div>
    </form>
</div>


<div id="leadBulkActionsBar" class="portal-card p-2 px-3 mb-3 d-none d-flex align-items-center justify-content-between">
    <span class="small fw-semibold"><span id="leadSelectedCount">0</span> selected</span>
    <button type="button" class="btn btn-sm btn-outline-danger" id="bulkDeleteLeadsBtn">
        <i class="fas fa-trash-can me-1"></i> Delete Selected
    </button>
</div>

@php($hasTableField = fn (string $field) => in_array($field, $leadTableColumns, true))
<div class="portal-card p-3 p-md-4">
    <div class="portal-table-toolbar">
        <span id="leadTableScrollHint" class="portal-table-scroll-hint d-none"><i class="fas fa-arrows-left-right me-1" aria-hidden="true"></i>Scroll horizontally to see all selected fields</span>
    </div>
    <div class="table-responsive">
        <table class="table portal-table mb-0" id="leadsDataTable" data-has-rows="{{ $leads->isNotEmpty() ? 'true' : 'false' }}">
            <thead>
                <tr>
                    <th style="width: 2.5rem;"><input type="checkbox" class="form-check-input" id="selectAllLeads"></th>
                    <th data-column-key="lead">Lead</th>
                    @if($hasTableField('email'))
                    <th data-column-key="email">Email</th>
                    @endif
                    @if($hasTableField('phone'))
                    <th data-column-key="phone">Phone</th>
                    @endif
                    @if($isAdmin && $hasTableField('owner'))
                    <th data-column-key="owner">Owner</th>
                    @endif
                    @if($hasTableField('stage'))
                    <th data-column-key="stage">Stage</th>
                    @endif
                    @if($hasTableField('status'))
                    <th data-column-key="status">Status</th>
                    @endif
                    @if($hasTableField('source'))
                    <th data-column-key="source">Source</th>
                    @endif
                    @if($hasTableField('tags'))
                    <th data-column-key="tags">Tags</th>
                    @endif
                    @if($hasTableField('message'))
                    <th data-column-key="message">Message</th>
                    @endif
                    @if($hasTableField('notes'))
                    <th data-column-key="notes">Notes</th>
                    @endif
                    @if($hasTableField('received'))
                    <th data-column-key="received">Received</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr class="portal-lead-row" data-lead-id="{{ $lead->id }}" tabindex="0" aria-label="View {{ $lead->name ?: 'lead' }} details">
                    <td data-lead-selection><input type="checkbox" class="form-check-input lead-select-checkbox" value="{{ $lead->id }}"></td>
                    <td data-column-key="lead">
                        <div class="d-flex align-items-center gap-2">
                            <span class="portal-lead-avatar">{{ strtoupper(mb_substr($lead->name ?: '?', 0, 1)) }}</span>
                            <div class="min-w-0">
                                <button type="button" class="portal-lead-name-button view-lead-btn" data-id="{{ $lead->id }}" aria-label="View details for {{ $lead->name ?: 'lead' }}">
                                    {{ $lead->name ?: 'Unknown' }}
                                </button>
                                <div class="text-muted text-truncate" style="font-size: 0.78rem; max-width: 180px;">{{ $lead->email ?: $lead->formatted_phone ?: '-' }}</div>
                            </div>
                        </div>
                    </td>
                    @if($hasTableField('email'))
                    <td data-column-key="email" data-search="{{ $lead->email ?? '' }}"><span class="portal-table-email">{{ $lead->email ?: '-' }}</span></td>
                    @endif
                    @if($hasTableField('phone'))
                    <td data-column-key="phone" data-search="{{ $lead->formatted_phone ?? '' }}">{{ $lead->formatted_phone ?: '-' }}</td>
                    @endif
                    @if($isAdmin && $hasTableField('owner'))
                    <td data-column-key="owner">
                        @if($lead->owner)
                            {{ $lead->owner->displayName() }}
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    @endif
                    @if($hasTableField('stage'))
                    <td data-column-key="stage" data-search="{{ $lead->stage?->name ?? '' }}" data-order="{{ $lead->stage?->name ?? '' }}">
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
                    @endif
                    @if($hasTableField('status'))
                    <td data-column-key="status" data-search="{{ $lead->status }}">
                        <span class="portal-badge-status portal-badge-{{ $lead->status }}">{{ ucfirst($lead->status) }}</span>
                    </td>
                    @endif
                    @if($hasTableField('source'))
                    <td>{{ $lead->source?->name ?? '—' }}</td>
                    @endif
                    @if($hasTableField('tags'))
                    <td data-column-key="tags" data-search="{{ $lead->tags->pluck('name')->implode(' ') }}">
                        <div class="portal-tags-cell">
                            <button type="button" class="portal-tags-picker" data-id="{{ $lead->id }}" title="Manage tags" aria-label="Manage tags for {{ $lead->name ?: 'lead' }}">
                                @forelse($lead->tags->take(1) as $tag)
                                <span class="portal-tag-chip" style="background: {{ $tag->color }}22; color: {{ $tag->color }};">{{ $tag->name }}</span>
                                @empty
                                <span class="portal-tags-empty"><i class="fas fa-plus" aria-hidden="true"></i> Add tags</span>
                                @endforelse
                                @if($lead->tags->isNotEmpty())
                                <i class="fas fa-pen portal-tags-picker-icon" aria-hidden="true"></i>
                                @endif
                            </button>
                            @if($lead->tags->count() > 1)
                            <button type="button" class="portal-tag-overflow" data-id="{{ $lead->id }}" aria-haspopup="true" aria-expanded="false" title="Show other tags">+{{ $lead->tags->count() - 1 }}</button>
                            @endif
                        </div>
                    </td>
                    @endif
                    @if($hasTableField('message'))
                    <td data-column-key="message" data-search="{{ $lead->message ?? '' }}">
                        <span class="portal-lead-message-excerpt">{{ \Illuminate\Support\Str::limit($lead->message ?: '-', 70) }}</span>
                    </td>
                    @endif
                    @if($hasTableField('notes'))
                    <td data-column-key="notes" data-order="{{ $lead->notes_history_count }}">
                        @if($lead->notes_history_count)
                        <span class="portal-lead-notes-count"><i class="fas fa-note-sticky" aria-hidden="true"></i>{{ $lead->notes_history_count }}</span>
                        @else
                        <span class="text-muted">-</span>
                        @endif
                    </td>
                    @endif
                    @if($hasTableField('received'))
                    <td data-column-key="received" class="text-muted" data-order="{{ $lead->created_at->format('Y-m-d H:i:s') }}">{{ $lead->created_at->format('d M Y') }}</td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ count($leadTableColumns) + 1 }}" class="portal-empty" data-empty-cell>
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
