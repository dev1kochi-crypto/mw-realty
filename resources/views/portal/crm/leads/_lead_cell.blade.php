{{-- One cell of a lead row — $column is a LeadTablePreferenceService key; the row renders the
     viewer's shown fields in their saved order (_lead_rows). --}}
@switch($column)
    @case('lead')
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
    @break

    @case('email')
    <td data-column-key="email"><span class="portal-table-email">{{ $lead->email ?: '-' }}</span></td>
    @break

    @case('phone')
    <td data-column-key="phone">{{ $lead->formatted_phone ?: '-' }}</td>
    @break

    @case('owner')
    <td data-column-key="owner">
        @if($lead->owner)
            {{ $lead->owner->displayName() }}
        @else
            <span class="text-muted">-</span>
        @endif
    </td>
    @break

    @case('agent')
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
    @break

    @case('stage')
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
    @break

    @case('status')
    <td data-column-key="status">
        <span class="portal-badge-status portal-badge-{{ $lead->status }}">{{ ucfirst($lead->status) }}</span>
    </td>
    @break

    @case('source')
    <td data-column-key="source">{{ $lead->source?->name ?? '—' }}</td>
    @break

    @case('tags')
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
    @break

    @case('message')
    <td data-column-key="message">
        <span class="portal-lead-message-excerpt">{{ \Illuminate\Support\Str::limit($lead->message ?: '-', 70) }}</span>
    </td>
    @break

    @case('notes')
    <td data-column-key="notes">
        @if($lead->notes_history_count)
        <span class="portal-lead-notes-count"><i class="fas fa-note-sticky" aria-hidden="true"></i>{{ $lead->notes_history_count }}</span>
        @else
        <span class="text-muted">-</span>
        @endif
    </td>
    @break

    @case('received')
    <td data-column-key="received" class="text-muted">{{ $lead->created_at->format('d M Y') }}</td>
    @break

    @case('company')
    <td data-column-key="company">{{ $lead->company ?: '-' }}</td>
    @break

    @case('country')
    <td data-column-key="country">{{ $lead->country ?: '-' }}</td>
    @break

    @case('property')
    <td data-column-key="property">
        @if($lead->property)
        <span class="d-inline-block text-truncate" style="max-width: 220px;" title="{{ $lead->property->getTranslation('title') }}">{{ $lead->property->getTranslation('title') }}</span>
        @else
        <span class="text-muted">-</span>
        @endif
    </td>
    @break

    @case('campaign')
    @php $campaign = $lead->extra_fields['facebook_campaign'] ?? null; @endphp
    <td data-column-key="campaign">
        @if($campaign)
        <span class="d-inline-block text-truncate" style="max-width: 220px;" title="{{ $campaign }}"><i class="fab fa-facebook text-primary me-1" aria-hidden="true"></i>{{ $campaign }}</span>
        @else
        <span class="text-muted">-</span>
        @endif
    </td>
    @break

    @case('enquiries')
    <td data-column-key="enquiries">{{ max(1, (int) $lead->enquiry_count) }}</td>
    @break

    @case('last_enquiry')
    <td data-column-key="last_enquiry" class="text-muted">{{ ($lead->last_enquired_at ?? $lead->created_at)->format('d M Y') }}</td>
    @break

    @case('updated')
    <td data-column-key="updated" class="text-muted">{{ $lead->updated_at?->format('d M Y') ?? '-' }}</td>
    @break

    @case('page')
    <td data-column-key="page">
        @if($lead->page_url)
        <a href="{{ $lead->page_url }}" target="_blank" rel="noopener" class="d-inline-block text-truncate text-decoration-none" style="max-width: 220px;" title="{{ $lead->page_url }}">{{ $lead->page_source ?: preg_replace('#^https?://#', '', $lead->page_url) }}</a>
        @else
        <span class="text-muted">-</span>
        @endif
    </td>
    @break

    @case('assigned')
    <td data-column-key="assigned" class="text-muted">{{ $lead->assigned_at?->format('d M Y') ?? '-' }}</td>
    @break

    @case('closed')
    <td data-column-key="closed" class="text-muted">{{ $lead->closed_at?->format('d M Y') ?? '-' }}</td>
    @break

    {{-- Website insights (Lead::withVisitorStats) — each opens the lead's Insights tab. --}}
    @case('views')
    @case('time_on_site')
    @case('searches')
    @case('chats')
    @case('last_active')
    @php
        $insight = match ($column) {
            'views' => (int) $lead->visitor_property_views,
            'time_on_site' => (int) $lead->visitor_seconds ? \App\Services\Visitors\VisitorInsights::duration((int) $lead->visitor_seconds) : 0,
            'searches' => (int) $lead->visitor_searches,
            'chats' => (int) $lead->visitor_chats,
            'last_active' => $lead->visitor_last_seen ? \Illuminate\Support\Carbon::parse($lead->visitor_last_seen)->diffForHumans() : 0,
        };
    @endphp
    <td data-column-key="{{ $column }}" class="text-nowrap">
        @if(!$lead->visitor_lead_id)
        <span class="text-muted" title="Not a website visitor">-</span>
        @elseif($insight)
        <a href="{{ route('portal.crm.leads.show', $lead->id) }}#insights" class="portal-insight-link" title="Open website insights">{{ $insight }}</a>
        @else
        <span class="text-muted">{{ $column === 'last_active' ? '-' : '0' }}</span>
        @endif
    </td>
    @break
@endswitch
