@extends('portal.crm._layout')

@section('title', ($lead->name ?: 'Lead') . ' — Lead')

{{--
    Lead detail page. Profile tab = one card per piece of information, each edited in place;
    Timeline tab = activity / notes / source history (_show_timeline). Every card's form is
    driven by the same small script at the bottom: a form with data-lead-form posts to its
    data-url and reloads the page, so the timeline always reflects the change.
--}}
@php
    $channel = \App\Services\Crm\LeadDetailService::sourceLabel($lead->page_source);
    $propertyTitle = $lead->property?->getTranslation('title');
    $propertyUrl = $lead->property?->slug ? url('/property-details/' . $lead->property->slug) : null;
    $nameParts = preg_split('/\s+/', trim($lead->name ?: '?'));
    $initials = strtoupper(mb_substr($nameParts[0], 0, 1) . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : ''));
    $leadUrl = route('portal.crm.leads.show', $lead->id);
    $telHref = fn ($label) => 'tel:' . preg_replace('/[^\d+]/', '', $label);
    $waHref = fn ($label) => 'https://wa.me/' . preg_replace('/\D+/', '', $label);
    $phoneCodes = ['+971' => 'UAE', '+91' => 'India', '+966' => 'Saudi Arabia', '+974' => 'Qatar', '+965' => 'Kuwait', '+968' => 'Oman', '+973' => 'Bahrain', '+1' => 'US/Canada', '+44' => 'UK', '+61' => 'Australia', '+65' => 'Singapore'];
@endphp

@section('crm-content')
<div class="portal-lead-page">
    {{-- Header --}}
    <div class="portal-card portal-lp-header mb-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="portal-lp-initials">{{ $initials }}</span>
            <div class="flex-grow-1 min-w-0">
                <h1 class="portal-lp-name">{{ $lead->name ?: 'Unknown' }}</h1>
                <div class="portal-lp-header-meta">
                    <span>Stage:
                        @if($lead->stage)
                        <span class="portal-lp-badge" style="--badge: {{ $lead->stage->color }};"><i class="fas fa-layer-group" aria-hidden="true"></i>{{ $lead->stage->name }}</span>
                        @else
                        <span class="portal-lp-badge portal-lp-badge-empty">No stage</span>
                        @endif
                    </span>
                    <span>Source:
                        <span class="portal-lp-badge portal-lp-badge-accent"><i class="fas fa-globe" aria-hidden="true"></i>{{ $lead->source?->name ?: $channel }}</span>
                    </span>
                    <span data-highlight-key="status" class="portal-badge-status portal-badge-{{ $lead->status ?: 'inactive' }}">{{ ucfirst($lead->status ?: 'inactive') }}</span>
                    @if($lead->enquiry_count > 1)
                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">Enquired {{ $lead->enquiry_count }}×</span>
                    @endif
                </div>
                <div class="text-muted small mt-1">#{{ $lead->id }} · Received {{ $lead->created_at->format('d M Y, H:i') }} via {{ $channel }}</div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a @if($previousLeadId) href="{{ route('portal.crm.leads.show', $previousLeadId) }}" @endif class="portal-lp-icon-btn {{ $previousLeadId ? '' : 'disabled' }}" title="Previous lead" aria-label="Previous lead"><i class="fas fa-backward-step"></i></a>
                <a @if($nextLeadId) href="{{ route('portal.crm.leads.show', $nextLeadId) }}" @endif class="portal-lp-icon-btn {{ $nextLeadId ? '' : 'disabled' }}" title="Next lead" aria-label="Next lead"><i class="fas fa-forward-step"></i></a>
                <a href="{{ route('portal.crm.leads.index') }}" class="portal-lp-icon-btn" title="Close — back to all leads" aria-label="Close"><i class="fas fa-xmark"></i></a>
            </div>
        </div>
    </div>


    {{-- Tabs --}}
    <ul class="nav portal-lp-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#leadPageProfile" type="button" role="tab">Profile</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#leadPageTimeline" type="button" role="tab">Timeline <span class="portal-lp-tab-count">{{ $timeline->count() }}</span></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#leadPageSummary" type="button" role="tab">Summary</button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- ============ Profile ============ --}}
        <div class="tab-pane fade show active" id="leadPageProfile" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-7">
                    {{-- Name --}}
                    <section class="portal-lp-card" data-card>
                        <header class="portal-lp-card-head">
                            <h2>Name</h2>
                            <button type="button" class="portal-lp-card-action" data-card-edit title="Edit name"><i class="fas fa-pen"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <div class="portal-lp-field" data-card-view>{{ $lead->name ?: '—' }}</div>
                            <form class="d-none" data-card-form data-lead-form data-url="{{ $leadUrl }}/fields" data-method="PATCH">
                                <input type="text" name="name" class="form-control" value="{{ $lead->name }}" required maxlength="255" aria-label="Name">
                                @include('portal.crm.leads._show_form_actions')
                            </form>
                        </div>
                    </section>

                    {{-- Phone numbers / Email addresses (a lead can have several of each) --}}
                    @foreach([
                        ['type' => 'phone', 'title' => 'Phone Numbers', 'items' => $contacts['phones'], 'icon' => 'fa-phone', 'empty' => 'No phone numbers yet.'],
                        ['type' => 'email', 'title' => 'Email Addresses', 'items' => $contacts['emails'], 'icon' => 'fa-envelope', 'empty' => 'No email addresses yet.'],
                    ] as $group)
                    <section class="portal-lp-card" data-card>
                        <header class="portal-lp-card-head">
                            <h2>{{ $group['title'] }} @if(count($group['items']) > 1)<span class="portal-lp-count">{{ count($group['items']) }}</span>@endif</h2>
                            <button type="button" class="portal-lp-card-add" data-card-edit title="Add {{ $group['type'] === 'phone' ? 'phone number' : 'email' }}"><i class="fas fa-plus"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <form class="d-none portal-lp-add-form" data-card-form data-lead-form data-url="{{ $leadUrl }}/contacts" data-method="POST">
                                <input type="hidden" name="type" value="{{ $group['type'] }}">
                                @if($group['type'] === 'phone')
                                <div class="input-group">
                                    <select name="phone_country_code" class="form-select flex-grow-0" style="max-width: 140px;" aria-label="Country code">
                                        @foreach($phoneCodes as $code => $country)
                                        <option value="{{ $code }}">{{ $code }} ({{ $country }})</option>
                                        @endforeach
                                    </select>
                                    <input type="tel" name="value" class="form-control" placeholder="Phone number" maxlength="50" required>
                                </div>
                                @else
                                <input type="email" name="value" class="form-control" placeholder="name@example.com" maxlength="255" required>
                                @endif
                                @include('portal.crm.leads._show_form_actions', ['saveLabel' => 'Add'])
                            </form>

                            <div class="d-grid gap-2">
                                @forelse($group['items'] as $item)
                                <div class="portal-lp-contact {{ $item['primary'] ? 'is-primary' : '' }}">
                                    <i class="fas {{ $group['icon'] }} portal-lp-contact-icon" aria-hidden="true"></i>
                                    <a class="portal-lp-contact-value" href="{{ $group['type'] === 'phone' ? $telHref($item['label']) : 'mailto:' . $item['value'] }}">{{ $item['label'] }}</a>
                                    @if($item['primary'])<span class="portal-lp-primary">Primary</span>@endif
                                    <div class="portal-lp-contact-actions">
                                        @if($group['type'] === 'phone')
                                        <a href="{{ $waHref($item['label']) }}" target="_blank" rel="noopener" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                        @endif
                                        @unless($item['primary'])
                                        <button type="button" title="Make primary" data-lead-action data-url="{{ $leadUrl }}/contacts/{{ $item['id'] }}/primary" data-method="PATCH"><i class="far fa-star"></i></button>
                                        @endunless
                                        <button type="button" title="Remove" class="text-danger" data-lead-action data-url="{{ $leadUrl }}/contacts/{{ $item['id'] }}" data-method="DELETE" data-confirm="Remove {{ $item['label'] }} from this lead?"><i class="fas fa-trash-can"></i></button>
                                    </div>
                                </div>
                                @empty
                                <div class="portal-lp-empty">{{ $group['empty'] }}</div>
                                @endforelse
                            </div>
                        </div>
                    </section>
                    @endforeach

                    {{-- Lead basic details --}}
                    <section class="portal-lp-card" data-card>
                        <header class="portal-lp-card-head">
                            <h2>Lead Basic Details</h2>
                            <button type="button" class="portal-lp-card-action" data-card-edit title="Edit details"><i class="fas fa-pen"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <div data-card-view>
                                <dl class="portal-lp-grid">
                                    <div><dt>Company</dt><dd>{{ $lead->company ?: '—' }}</dd></div>
                                    <div><dt>Country</dt><dd>{{ $lead->country ?: '—' }}</dd></div>
                                    <div><dt>Came in via</dt><dd>{{ $channel }}</dd></div>
                                    <div><dt>Property</dt><dd>
                                        @if($propertyTitle)
                                            @if($propertyUrl)<a href="{{ $propertyUrl }}" target="_blank" rel="noopener">{{ $propertyTitle }} <i class="fas fa-arrow-up-right-from-square small"></i></a>@else{{ $propertyTitle }}@endif
                                            @if($lead->property->reference_no)<span class="d-block text-muted small">Ref: {{ $lead->property->reference_no }}</span>@endif
                                        @else — @endif
                                    </dd></div>
                                    @foreach($enquiryDetails as $row)
                                    <div><dt>{{ $row['label'] }}</dt><dd>{{ $row['value'] }}</dd></div>
                                    @endforeach
                                </dl>
                                <div class="portal-lead-view-message mt-3">
                                    <div class="portal-lead-view-label mb-1">Enquiry message</div>
                                    <div class="portal-lead-view-message-copy">{{ $lead->message ?: '—' }}</div>
                                </div>
                            </div>
                            <form class="d-none" data-card-form data-lead-form data-url="{{ $leadUrl }}/fields" data-method="PATCH">
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-semibold">Company</label>
                                        <input type="text" name="company" class="form-control" value="{{ $lead->company }}" maxlength="255">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-semibold">Country</label>
                                        <input type="text" name="country" class="form-control" value="{{ $lead->country }}" maxlength="100">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Enquiry message</label>
                                        <textarea name="message" class="form-control" rows="4" maxlength="5000">{{ $lead->message }}</textarea>
                                    </div>
                                </div>
                                @include('portal.crm.leads._show_form_actions')
                            </form>
                        </div>
                    </section>
                </div>

                <div class="col-lg-5">
                    {{-- Tags --}}
                    <section class="portal-lp-card" data-card id="leadTagsCard">
                        <header class="portal-lp-card-head">
                            <h2>Tags</h2>
                            <button type="button" class="portal-lp-card-add" data-card-edit title="Manage tags"><i class="fas fa-plus"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <div class="d-flex flex-wrap gap-1" data-card-view>
                                @forelse($lead->tags as $tag)
                                <span class="portal-tag-chip" style="background: {{ $tag->color }}22; color: {{ $tag->color }};">{{ $tag->name }}</span>
                                @empty
                                <div class="portal-lp-empty w-100">No tags yet.</div>
                                @endforelse
                            </div>
                            <form class="d-none" data-card-form data-lead-form data-url="{{ $leadUrl }}/tags" data-method="PATCH" data-empty-param="tags">
                                <div class="d-flex flex-wrap gap-2">
                                    @forelse($tags as $tag)
                                    <label class="portal-lead-page-tag-choice">
                                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="form-check-input me-1" @checked($lead->tags->contains('id', $tag->id))>
                                        <span style="color: {{ $tag->color }};">{{ $tag->name }}</span>
                                    </label>
                                    @empty
                                    <span class="text-muted small">No tags to choose from — add them under Master &gt; Tag.</span>
                                    @endforelse
                                </div>
                                @include('portal.crm.leads._show_form_actions')
                            </form>
                        </div>
                    </section>

                    {{-- Stage --}}
                    <section class="portal-lp-card" data-card>
                        <header class="portal-lp-card-head"><h2>Stage</h2></header>
                        <div class="portal-lp-card-body">
                            <button type="button" class="portal-lp-pill-btn" data-card-view data-card-edit title="Change stage">
                                @if($lead->stage)<span class="portal-color-dot" style="background: {{ $lead->stage->color }};"></span>{{ $lead->stage->name }}@else No stage @endif
                                <i class="fas fa-pen-to-square ms-1"></i>
                            </button>
                            <form class="d-none" data-card-form data-lead-form data-url="{{ $leadUrl }}/stage" data-method="PATCH">
                                <select name="stage_id" class="form-select" aria-label="Stage">
                                    <option value="">No stage</option>
                                    @foreach($stages as $stage)
                                    <option value="{{ $stage->id }}" @selected($lead->stage_id === $stage->id)>{{ $stage->name }}</option>
                                    @endforeach
                                </select>
                                @include('portal.crm.leads._show_form_actions')
                            </form>
                        </div>
                    </section>

                    {{-- Source --}}
                    <section class="portal-lp-card" data-card>
                        <header class="portal-lp-card-head"><h2>Source</h2></header>
                        <div class="portal-lp-card-body">
                            <button type="button" class="portal-lp-pill-btn" data-card-view data-card-edit title="Change source">
                                {{ $lead->source?->name ?: 'No source' }} <i class="fas fa-pen-to-square ms-1"></i>
                            </button>
                            <form class="d-none" data-card-form data-lead-form data-url="{{ $leadUrl }}/fields" data-method="PATCH">
                                <select name="source_id" class="form-select" aria-label="Source">
                                    <option value="">No source</option>
                                    @foreach($sources as $source)
                                    <option value="{{ $source->id }}" @selected($lead->source_id === $source->id)>{{ $source->name }}</option>
                                    @endforeach
                                </select>
                                @include('portal.crm.leads._show_form_actions')
                            </form>
                            <div class="text-muted small mt-2">Came in via {{ $channel }}</div>
                        </div>
                    </section>

                    {{-- Owner (Super Admin) --}}
                    @if($isAdmin)
                    <section class="portal-lp-card" data-card>
                        <header class="portal-lp-card-head">
                            <h2>Owner</h2>
                            <button type="button" class="portal-lp-card-action" data-card-edit title="Transfer lead"><i class="fas fa-pen"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <div class="portal-lp-field" data-card-view>{{ $lead->owner?->displayName() ?: 'Unassigned (Super Admin)' }}</div>
                            <form class="d-none" data-card-form data-lead-form data-url="{{ $leadUrl }}/fields" data-method="PATCH">
                                <select name="owner_id" class="form-select" aria-label="Owner" required>
                                    @if(!$lead->portal_user_id)<option value="" selected disabled>Choose an agency / agent</option>@endif
                                    @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}" @selected($lead->portal_user_id === $owner->id)>{{ $owner->displayName() }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Transfers the lead to that account (its agent assignment is reset).</div>
                                @include('portal.crm.leads._show_form_actions')
                            </form>
                        </div>
                    </section>
                    @endif

                    {{-- Assigned agent --}}
                    <section class="portal-lp-card" data-card>
                        <header class="portal-lp-card-head">
                            <h2>Assigned Agent</h2>
                            @if($canAssign)<button type="button" class="portal-lp-card-action" data-card-edit title="Assign agent"><i class="fas fa-pen"></i></button>@endif
                        </header>
                        <div class="portal-lp-card-body">
                            <div data-card-view>
                                <div class="portal-lp-field">{{ $lead->agent?->name ?: ($lead->owner?->isAgency() ? 'Unassigned (agency level)' : '—') }}</div>
                                @if($lead->agent && $lead->assignmentLabel())
                                <div class="text-muted small mt-1">{{ $lead->assignmentLabel() }}{{ $lead->assigned_at ? ' · ' . $lead->assigned_at->format('d M Y, H:i') : '' }}</div>
                                @endif
                            </div>
                            @if($canAssign)
                            <form class="d-none" data-card-form data-lead-form data-url="{{ $leadUrl }}/assign" data-method="POST">
                                <select name="agent_id" class="form-select" aria-label="Agent">
                                    <option value="">— Unassigned (agency level) —</option>
                                    @foreach($agentOptions as $agent)
                                    <option value="{{ $agent->id }}" @selected($lead->agent_id === $agent->id)>{{ $agent->name }}</option>
                                    @endforeach
                                </select>
                                @include('portal.crm.leads._show_form_actions', ['saveLabel' => 'Assign'])
                            </form>
                            @endif
                            @if($assignmentHistory->isNotEmpty())
                            <details class="mt-2">
                                <summary class="small text-muted">Assignment history ({{ $assignmentHistory->count() }})</summary>
                                <ul class="list-unstyled small mt-2 mb-0">
                                    @foreach($assignmentHistory as $row)
                                    <li class="mb-1">{{ $row->assigned_at->format('d M Y, H:i') }} — {{ $row->agent?->name ?: 'Unassigned' }} · {{ (new \App\Models\Lead(['assignment_type' => $row->assignment_type]))->assignmentLabel() ?? $row->assignment_type }}{{ $row->note ? ' — ' . $row->note : '' }}</li>
                                    @endforeach
                                </ul>
                            </details>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
        </div>

        {{-- ============ Timeline ============ --}}
        <div class="tab-pane fade" id="leadPageTimeline" role="tabpanel">
            @include('portal.crm.leads._show_timeline')
        </div>

        {{-- ============ Summary ============ --}}
        <div class="tab-pane fade" id="leadPageSummary" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-6">
                    <section class="portal-lp-card">
                        <header class="portal-lp-card-head"><h2>Lead</h2></header>
                        <div class="portal-lp-card-body">
                            <dl class="portal-lead-page-summary mb-0">
                                <div><dt>Status</dt><dd><span data-highlight-key="status" class="portal-badge-status portal-badge-{{ $lead->status ?: 'inactive' }}">{{ ucfirst($lead->status ?: 'inactive') }}</span></dd></div>
                                <div><dt>Stage</dt><dd>{{ $lead->stage?->name ?: 'No stage' }}</dd></div>
                                <div><dt>Source</dt><dd>{{ $lead->source?->name ?: 'No source' }}</dd></div>
                                <div><dt>Came in via</dt><dd>{{ $channel }}</dd></div>
                                @if($isAdmin)<div><dt>Owner</dt><dd>{{ $lead->owner?->displayName() ?: 'Unassigned (Super Admin)' }}</dd></div>@endif
                                <div><dt>Assigned agent</dt><dd>{{ $lead->agent?->name ?: '—' }}</dd></div>
                                <div><dt>Property</dt><dd>{{ $propertyTitle ?: '—' }}</dd></div>
                                <div><dt>Tags</dt><dd>{{ $lead->tags->pluck('name')->implode(', ') ?: '—' }}</dd></div>
                            </dl>
                        </div>
                    </section>
                </div>
                <div class="col-lg-6">
                    <section class="portal-lp-card">
                        <header class="portal-lp-card-head"><h2>Activity</h2></header>
                        <div class="portal-lp-card-body">
                            <dl class="portal-lead-page-summary mb-0">
                                <div><dt>Received</dt><dd>{{ $lead->created_at->format('d M Y, H:i') }}</dd></div>
                                <div><dt>Enquiries</dt><dd>{{ $lead->enquiry_count }}</dd></div>
                                @if($lead->last_enquired_at)<div><dt>Last enquiry</dt><dd>{{ $lead->last_enquired_at->format('d M Y, H:i') }}</dd></div>@endif
                                <div><dt>Emails / phones</dt><dd>{{ count($contacts['emails']) }} / {{ count($contacts['phones']) }}</dd></div>
                                <div><dt>Notes</dt><dd>{{ $notes->count() }}</dd></div>
                                <div><dt>Timeline entries</dt><dd>{{ $timeline->count() }}</dd></div>
                                @if($lead->closed_at)<div><dt>Closed</dt><dd>{{ $lead->closed_at->format('d M Y, H:i') }}</dd></div>@endif
                                <div><dt>Last updated</dt><dd>{{ $lead->updated_at->diffForHumans() }}</dd></div>
                            </dl>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick actions (floating "+" bottom-right) — contact, note, tag, status, delete. --}}
    <div class="portal-lp-fab" id="leadFab">
        <div class="portal-lp-fab-backdrop" data-fab-close></div>
        <ul class="portal-lp-fab-menu" id="leadFabMenu" aria-label="Lead actions">
            @if($lead->email)
            <li><span>Send Email</span><a href="mailto:{{ $lead->email }}" class="portal-lp-fab-item" aria-label="Send email"><i class="fas fa-envelope"></i></a></li>
            @endif
            @if($lead->formatted_phone)
            <li><span>Call</span><a href="{{ $telHref($lead->formatted_phone) }}" class="portal-lp-fab-item" aria-label="Call"><i class="fas fa-phone"></i></a></li>
            <li><span>WhatsApp</span><a href="{{ $waHref($lead->formatted_phone) }}" target="_blank" rel="noopener" class="portal-lp-fab-item" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a></li>
            @endif
            <li><span>Add Note</span><button type="button" class="portal-lp-fab-item" data-fab-note aria-label="Add note"><i class="fas fa-clipboard"></i></button></li>
            <li><span>Add Tag</span><button type="button" class="portal-lp-fab-item" data-fab-tag aria-label="Add tag"><i class="fas fa-tags"></i></button></li>
            @php($nextStatus = $lead->status === 'active' ? 'inactive' : 'active')
            <li><span>Mark as {{ ucfirst($nextStatus) }}</span><button type="button" class="portal-lp-fab-item" aria-label="Mark as {{ $nextStatus }}"
                data-highlight="status" data-pending="Marking lead as {{ $nextStatus }}…" data-lead-action data-url="{{ $leadUrl }}/fields" data-method="PATCH" data-body='@json(['status' => $nextStatus])'><i class="fas {{ $nextStatus === 'active' ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i></button></li>
            @if($canDelete)
            <li><span>Delete Lead</span>
                <form method="POST" action="{{ route('portal.crm.leads.destroy', $lead->id) }}" class="m-0" onsubmit="return confirm('Delete this lead? You can restore it later from Deleted Leads.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="portal-lp-fab-item portal-lp-fab-item-danger" aria-label="Delete lead"><i class="fas fa-trash-can"></i></button>
                </form>
            </li>
            @endif
        </ul>
        <button type="button" class="portal-lp-fab-toggle" id="leadFabToggle" aria-expanded="false" aria-controls="leadFabMenu" aria-label="Lead actions"><i class="fas fa-plus"></i></button>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const leadUrl = @json($leadUrl);
    const csrf = @json(csrf_token());

    // Top-right toast (portal layout).
    function flash(type, message) {
        window.portalToast(type, message);
    }

    // What to highlight after the reload: an explicit data-highlight key (e.g. "status" → the
    // header status badge), else the card the change was made in.
    function highlightKeyFor(el) {
        if (el.dataset.highlight) return el.dataset.highlight;
        const card = el.closest('[data-card], .portal-lp-card');
        const cards = Array.from(document.querySelectorAll('.portal-lp-card'));
        return card ? 'card:' + cards.indexOf(card) : '';
    }

    // Every change reloads the page so all cards and the timeline stay in step; the message,
    // the open tab and the highlight survive the reload.
    function reloadWith(message, tab, highlight) {
        try {
            sessionStorage.setItem('leadPageFlash', message);
            sessionStorage.setItem('leadPageTab', tab || document.querySelector('.portal-lp-tabs .nav-link.active')?.dataset.bsTarget || '');
            sessionStorage.setItem('leadPageHighlight', highlight || '');
        } catch (e) {}
        window.location.reload();
    }
    try {
        const pending = sessionStorage.getItem('leadPageFlash');
        const tab = sessionStorage.getItem('leadPageTab');
        const highlight = sessionStorage.getItem('leadPageHighlight');
        ['leadPageFlash', 'leadPageTab', 'leadPageHighlight'].forEach(function (key) { sessionStorage.removeItem(key); });
        if (pending) flash('success', pending);
        (tab || '').split(',').filter(Boolean).forEach(function (target) {
            const trigger = document.querySelector('[data-bs-target="' + target + '"]');
            if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
        });
        if (highlight) {
            const targets = highlight.startsWith('card:')
                ? [document.querySelectorAll('.portal-lp-card')[Number(highlight.slice(5))]]
                : Array.from(document.querySelectorAll('[data-highlight-key="' + highlight + '"]'));
            targets.filter(Boolean).forEach(function (el) {
                el.classList.add('portal-lp-just-changed');
                el.addEventListener('animationend', function () { el.classList.remove('portal-lp-just-changed'); }, { once: true });
            });
        }
    } catch (e) {}

    function setBusy(el, busy) {
        el.classList.toggle('is-busy', busy);
        el.disabled = busy;
        el.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function send(url, method, body) {
        return fetch(url, {
            method: method,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok) {
                    const first = Object.values(data.errors || {}).flat()[0];
                    throw new Error(first || data.message || 'Something went wrong. Please try again.');
                }
                return data;
            });
        });
    }

    // Card edit toggles: [data-card-edit] swaps the card's view for its form.
    document.querySelectorAll('[data-card]').forEach(function (card) {
        const form = card.querySelector('[data-card-form]');
        const view = card.querySelector('[data-card-view]');
        if (!form) return;
        function setEditing(on) {
            form.classList.toggle('d-none', !on);
            if (view && !view.matches('.portal-lp-empty')) view.classList.toggle('d-none', on);
            card.classList.toggle('is-editing', on);
            if (on) (form.querySelector('input:not([type=hidden]):not([type=checkbox]), select, textarea') || form).focus();
        }
        card.querySelectorAll('[data-card-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () { setEditing(form.classList.contains('d-none')); });
        });
        form.querySelectorAll('[data-card-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () { form.reset(); form.querySelector('[data-form-error]')?.classList.add('d-none'); setEditing(false); });
        });
    });

    // Card forms.
    document.querySelectorAll('[data-lead-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const error = form.querySelector('[data-form-error]');
            const save = form.querySelector('[type=submit]');
            error?.classList.add('d-none');
            setBusy(save, true);
            const body = new URLSearchParams(new FormData(form));
            if (form.dataset.emptyParam && !body.has(form.dataset.emptyParam + '[]')) body.append(form.dataset.emptyParam, '');
            send(form.dataset.url, form.dataset.method, body)
                .then(function (data) { reloadWith(data.message || 'Saved.', null, highlightKeyFor(form)); })
                .catch(function (err) {
                    if (error) { error.textContent = err.message; error.classList.remove('d-none'); }
                    flash('danger', err.message);
                    setBusy(save, false);
                });
        });
    });

    // One-click actions (make primary, remove contact, status). The button spins while it saves;
    // an optional data-pending message shows as a toast straight away.
    document.querySelectorAll('[data-lead-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.confirm && !confirm(btn.dataset.confirm)) return;
            setBusy(btn, true);
            if (btn.dataset.pending) flash('info', btn.dataset.pending);
            const body = new URLSearchParams(btn.dataset.body ? JSON.parse(btn.dataset.body) : {});
            send(btn.dataset.url, btn.dataset.method, body)
                .then(function (data) { reloadWith(data.message || 'Saved.', null, highlightKeyFor(btn)); })
                .catch(function (err) { flash('danger', err.message); setBusy(btn, false); });
        });
    });

    // Floating quick-actions menu.
    const fab = document.getElementById('leadFab');
    const fabToggle = document.getElementById('leadFabToggle');
    function setFab(open) {
        fab.classList.toggle('is-open', open);
        fabToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        fabToggle.querySelector('i').className = open ? 'fas fa-xmark' : 'fas fa-plus';
    }
    fabToggle.addEventListener('click', function () { setFab(!fab.classList.contains('is-open')); });
    fab.querySelector('[data-fab-close]').addEventListener('click', function () { setFab(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setFab(false); });
    fab.querySelectorAll('a.portal-lp-fab-item').forEach(function (link) {
        link.addEventListener('click', function () { setFab(false); });
    });

    function showTab(target) {
        const trigger = document.querySelector('[data-bs-target="' + target + '"]');
        if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
    }
    fab.querySelector('[data-fab-note]').addEventListener('click', function () {
        setFab(false);
        showTab('#leadPageTimeline');
        showTab('#leadTabNotes');
        const body = document.getElementById('leadNoteBody');
        body.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { body.focus(); }, 250);
    });
    fab.querySelector('[data-fab-tag]').addEventListener('click', function () {
        setFab(false);
        showTab('#leadPageProfile');
        const card = document.getElementById('leadTagsCard');
        if (!card.classList.contains('is-editing')) card.querySelector('[data-card-edit]').click();
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // Timeline → Notes tab.
    const noteForm = document.getElementById('leadNoteForm');
    const noteBody = document.getElementById('leadNoteBody');
    const noteError = document.getElementById('leadNoteError');
    noteForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const save = document.getElementById('leadNoteSave');
        noteBody.classList.remove('is-invalid');
        if (!noteBody.value.trim()) {
            noteError.textContent = 'Please enter a note.';
            noteBody.classList.add('is-invalid');
            return;
        }
        save.disabled = true;
        save.querySelector('.spinner-border').classList.remove('d-none');
        send(leadUrl + '/notes', 'POST', new URLSearchParams({ body: noteBody.value }))
            .then(function (data) { reloadWith(data.message || 'Note added.', '#leadPageTimeline,#leadTabNotes'); })
            .catch(function (err) {
                noteError.textContent = err.message;
                noteBody.classList.add('is-invalid');
                save.disabled = false;
                save.querySelector('.spinner-border').classList.add('d-none');
            });
    });
});
</script>
@endpush
