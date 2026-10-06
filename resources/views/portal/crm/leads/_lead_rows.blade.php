{{-- One page of lead rows — the listing's first page (_listing) and every page / sort / Quick
     search the server-side DataTable asks for (LeadController::index with dt=1). --}}
@php($hasTableField = fn (string $field) => in_array($field, $leadTableColumns, true))
@foreach($leads as $lead)
<tr class="portal-lead-row" data-lead-id="{{ $lead->id }}" tabindex="0" aria-label="View {{ $lead->name ?: 'lead' }} details">
    <td data-lead-selection><input type="checkbox" class="form-check-input lead-select-checkbox" value="{{ $lead->id }}"></td>
    <td class="portal-lead-sn">{{ $leads->firstItem() + $loop->index }}</td>
    <td data-column-key="lead">
        <div class="d-flex align-items-center gap-2">
            <span class="portal-lead-avatar">{{ strtoupper(mb_substr($lead->name ?: '?', 0, 1)) }}</span>
            <div class="min-w-0">
                {{-- Name + repeat-enquiry count on one line, so the row keeps its height. --}}
                <div class="d-flex align-items-center gap-1 flex-nowrap" style="max-width: 200px;">
                    <a href="{{ route('portal.crm.leads.show', $lead->id) }}" class="portal-lead-name-button text-decoration-none text-truncate" style="min-width: 0;" aria-label="View details for {{ $lead->name ?: 'lead' }}" title="{{ $lead->name }}">
                        {{ $lead->name ?: 'Unknown' }}
                    </a>
                    @if($lead->enquiry_count > 1)
                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis flex-shrink-0" style="font-size: 0.66rem; padding: 0.2em 0.5em;" title="Enquired {{ $lead->enquiry_count }} times{{ $lead->last_enquired_at ? ' — latest ' . $lead->last_enquired_at->format('d M Y') : '' }}">&times;{{ $lead->enquiry_count }}</span>
                    @endif
                </div>
                <div class="text-muted text-truncate" style="font-size: 0.78rem; max-width: 180px;">{{ $lead->email ?: $lead->formatted_phone ?: '-' }}</div>
            </div>
        </div>
    </td>
    @if($hasTableField('email'))
    <td data-column-key="email"><span class="portal-table-email">{{ $lead->email ?: '-' }}</span></td>
    @endif
    @if($hasTableField('phone'))
    <td data-column-key="phone">{{ $lead->formatted_phone ?: '-' }}</td>
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
    @if($hasTableField('agent'))
    <td data-column-key="agent">
        @if($lead->agent)
            <div class="fw-semibold">{{ $lead->agent->name }}</div>
        @else
            <span class="badge bg-warning-subtle text-warning-emphasis">Unassigned</span>
        @endif
        @if($lead->assignmentLabel() && $lead->agent)
            <div class="text-muted" style="font-size: 0.72rem;">{{ $lead->assignmentLabel() }}</div>
        @endif
    </td>
    @endif
    @if($hasTableField('stage'))
    <td data-column-key="stage">
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
    <td data-column-key="status">
        <span class="portal-badge-status portal-badge-{{ $lead->status }}">{{ ucfirst($lead->status) }}</span>
    </td>
    @endif
    @if($hasTableField('source'))
    <td data-column-key="source">{{ $lead->source?->name ?? '—' }}</td>
    @endif
    @if($hasTableField('tags'))
    <td data-column-key="tags">
        <div class="portal-tags-cell">
            <button type="button" class="portal-tags-picker" data-id="{{ $lead->id }}" title="Manage tags" aria-label="Manage tags for {{ $lead->name ?: 'lead' }}">
                @forelse($lead->tags->take(1) as $tag)
                <span class="portal-tag-chip" style="background: {{ $tag->color }}22; color: {{ $tag->color }};">{{ $tag->name }}</span>
                @empty
                <span class="portal-tags-empty"><i class="fas fa-plus" aria-hidden="true"></i> Add tags</span>
                @endforelse
            </button>
            @if($lead->tags->count() > 1)
            <button type="button" class="portal-tag-overflow" data-id="{{ $lead->id }}" aria-haspopup="true" aria-expanded="false" title="Show other tags">+{{ $lead->tags->count() - 1 }}</button>
            @endif
            @if($lead->tags->isNotEmpty())
            <button type="button" class="portal-tags-picker portal-tags-add" data-id="{{ $lead->id }}" title="Add tags" aria-label="Add tags to {{ $lead->name ?: 'lead' }}"><i class="fas fa-plus" aria-hidden="true"></i></button>
            @endif
        </div>
    </td>
    @endif
    @if($hasTableField('message'))
    <td data-column-key="message">
        <span class="portal-lead-message-excerpt">{{ \Illuminate\Support\Str::limit($lead->message ?: '-', 70) }}</span>
    </td>
    @endif
    @if($hasTableField('notes'))
    <td data-column-key="notes">
        @if($lead->notes_history_count)
        <span class="portal-lead-notes-count"><i class="fas fa-note-sticky" aria-hidden="true"></i>{{ $lead->notes_history_count }}</span>
        @else
        <span class="text-muted">-</span>
        @endif
    </td>
    @endif
    @if($hasTableField('received'))
    <td data-column-key="received" class="text-muted">{{ $lead->created_at->format('d M Y') }}</td>
    @endif
</tr>
@endforeach
