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
    $initialOf = fn ($name) => strtoupper(mb_substr(trim((string) $name) ?: '?', 0, 1));
    $status = $lead->status ?: 'inactive';
    $ownerName = $lead->owner?->displayName();
    $agentName = $lead->agent?->name;
@endphp

@section('crm-content')
<div class="portal-lead-page">
    {{-- Header --}}
    <div class="portal-card portal-lp-header mb-3">
        <div class="portal-lp-header-top">
            <a href="{{ route('portal.crm.leads.index') }}" class="portal-lp-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> All leads</a>
            <div class="portal-lp-nav" role="group" aria-label="Lead navigation">
                <a @if($previousLeadId) href="{{ route('portal.crm.leads.show', $previousLeadId) }}" @endif class="portal-lp-icon-btn {{ $previousLeadId ? '' : 'disabled' }}" title="Previous lead" aria-label="Previous lead"><i class="fas fa-chevron-left"></i></a>
                <a @if($nextLeadId) href="{{ route('portal.crm.leads.show', $nextLeadId) }}" @endif class="portal-lp-icon-btn {{ $nextLeadId ? '' : 'disabled' }}" title="Next lead" aria-label="Next lead"><i class="fas fa-chevron-right"></i></a>
                <a href="{{ route('portal.crm.leads.index') }}" class="portal-lp-icon-btn" title="Close — back to all leads" aria-label="Close"><i class="fas fa-xmark"></i></a>
            </div>
        </div>

        <div class="portal-lp-header-main">
            <span class="portal-lp-initials">{{ $initials }}</span>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h1 class="portal-lp-name">{{ $lead->name ?: 'Unknown' }}</h1>
                    <span data-highlight-key="status" class="portal-lp-status is-{{ $status }}">{{ ucfirst($status) }}</span>
                </div>
                <div class="portal-lp-header-meta">
                    <span><i class="fas fa-hashtag" aria-hidden="true"></i>{{ $lead->id }}</span>
                    <span><i class="far fa-calendar" aria-hidden="true"></i>Received {{ $lead->created_at->format('d M Y, H:i') }}</span>
                    <span><i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>via {{ $channel }}</span>
                </div>
                <div class="portal-lp-header-chips">
                    @if($lead->stage)
                    <span class="portal-lp-badge" style="--badge: {{ $lead->stage->color }};" title="Stage"><span class="portal-lp-badge-dot"></span>{{ $lead->stage->name }}</span>
                    @else
                    <span class="portal-lp-badge portal-lp-badge-empty" title="Stage"><span class="portal-lp-badge-dot"></span>No stage</span>
                    @endif
                    <span class="portal-lp-badge portal-lp-badge-accent" title="Source"><i class="fas fa-globe" aria-hidden="true"></i>{{ $lead->source?->name ?: $channel }}</span>
                    @if($lead->enquiry_count > 1)
                    <span class="portal-lp-badge portal-lp-badge-warning"><i class="fas fa-repeat" aria-hidden="true"></i>Enquired {{ $lead->enquiry_count }}×</span>
                    @endif
                    @foreach($lead->tags as $tag)
                    <span class="portal-lp-badge" style="--badge: {{ $tag->color }};"><i class="fas fa-tag" aria-hidden="true"></i>{{ $tag->name }}</span>
                    @endforeach
                </div>
            </div>
            <div class="portal-lp-quick">
                @if($lead->formatted_phone)
                <a href="{{ $telHref($lead->formatted_phone) }}" class="portal-lp-quick-btn" title="Call {{ $lead->formatted_phone }}"><i class="fas fa-phone"></i><span>Call</span></a>
                <a href="{{ $waHref($lead->formatted_phone) }}" target="_blank" rel="noopener" class="portal-lp-quick-btn is-whatsapp" title="WhatsApp"><i class="fab fa-whatsapp"></i><span>WhatsApp</span></a>
                @endif
                @if($lead->email)
                <a href="mailto:{{ $lead->email }}" class="portal-lp-quick-btn" title="Email {{ $lead->email }}"><i class="fas fa-envelope"></i><span>Email</span></a>
                @endif
            </div>
        </div>

        <div class="portal-lp-stats">
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon"><i class="fas fa-user-tie"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Assigned agent</div><div class="portal-lp-stat-value text-truncate" title="{{ $agentName }}">{{ $agentName ?: 'Unassigned' }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-accent"><i class="fas fa-building"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Property</div><div class="portal-lp-stat-value text-truncate" title="{{ $propertyTitle }}">{{ $propertyTitle ?: '—' }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-warning"><i class="fas fa-envelope-open-text"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Enquiries</div><div class="portal-lp-stat-value">{{ $lead->enquiry_count ?: 1 }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-success"><i class="fas fa-clock-rotate-left"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Last updated</div><div class="portal-lp-stat-value">{{ $lead->updated_at->diffForHumans() }}</div></div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav portal-lp-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#leadPageProfile" type="button" role="tab"><i class="fas fa-id-card" aria-hidden="true"></i>Profile</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#leadPageTimeline" type="button" role="tab"><i class="fas fa-stream" aria-hidden="true"></i>Timeline <span class="portal-lp-tab-count">{{ $timeline->count() }}</span></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#leadPageSummary" type="button" role="tab"><i class="fas fa-chart-pie" aria-hidden="true"></i>Summary</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="leadPageInsightsTab" data-bs-toggle="tab" data-bs-target="#leadPageInsights" type="button" role="tab"><i class="fas fa-chart-line" aria-hidden="true"></i>Insights @if($visitorInsights)<span class="portal-lp-tab-count">{{ $visitorInsights['stats']['property_views'] }}</span>@endif</button>
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
                            <h2><span class="portal-lp-card-icon"><i class="fas fa-user"></i></span>Name</h2>
                            <button type="button" class="portal-lp-card-action" data-card-edit title="Edit name"><i class="fas fa-pen"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <div class="portal-lp-field" data-card-view><i class="far fa-user" aria-hidden="true"></i>{{ $lead->name ?: '—' }}</div>
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
                            <h2><span class="portal-lp-card-icon {{ $group['type'] === 'email' ? 'is-accent' : '' }}"><i class="fas {{ $group['icon'] }}"></i></span>{{ $group['title'] }} @if(count($group['items']) > 1)<span class="portal-lp-count">{{ count($group['items']) }}</span>@endif</h2>
                            <button type="button" class="portal-lp-card-add" data-card-edit title="Add {{ $group['type'] === 'phone' ? 'phone number' : 'email' }}"><i class="fas fa-plus"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <form class="d-none portal-lp-add-form" data-card-form data-lead-form data-url="{{ $leadUrl }}/contacts" data-method="POST">
                                <input type="hidden" name="type" value="{{ $group['type'] }}">
                                @if($group['type'] === 'phone')
                                <input type="hidden" name="phone_country_code" value="+971">
                                <input type="tel" name="value" class="form-control" placeholder="50 123 4567" maxlength="20" required data-phone-input>

                                @else
                                <input type="email" name="value" class="form-control" placeholder="name@example.com" maxlength="255" required>
                                @endif
                                @include('portal.crm.leads._show_form_actions', ['saveLabel' => 'Add'])
                            </form>

                            <div class="d-grid gap-2">
                                @forelse($group['items'] as $item)
                                <div class="portal-lp-contact {{ $item['primary'] ? 'is-primary' : '' }}">
                                    <span class="portal-lp-contact-icon {{ $group['type'] === 'email' ? 'is-accent' : '' }}"><i class="fas {{ $group['icon'] }}" aria-hidden="true"></i></span>
                                    <a class="portal-lp-contact-value" href="{{ $group['type'] === 'phone' ? $telHref($item['label']) : 'mailto:' . $item['value'] }}">{{ $item['label'] }}</a>
                                    @if($item['primary'])<span class="portal-lp-primary"><i class="fas fa-star" aria-hidden="true"></i>Primary</span>@endif
                                    <div class="portal-lp-contact-actions">
                                        @if($group['type'] === 'phone')
                                        <a href="{{ $waHref($item['label']) }}" target="_blank" rel="noopener" title="WhatsApp" class="is-whatsapp"><i class="fab fa-whatsapp"></i></a>
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
                            <h2><span class="portal-lp-card-icon"><i class="fas fa-circle-info"></i></span>Lead Basic Details</h2>
                            <button type="button" class="portal-lp-card-action" data-card-edit title="Edit details"><i class="fas fa-pen"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <div data-card-view>
                                @if($propertyTitle)
                                <div class="portal-lp-property">
                                    <span class="portal-lp-property-icon"><i class="fas fa-building"></i></span>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="portal-lp-label">Interested in</div>
                                        @if($propertyUrl)<a href="{{ $propertyUrl }}" target="_blank" rel="noopener" class="portal-lp-property-title">{{ $propertyTitle }} <i class="fas fa-arrow-up-right-from-square"></i></a>@else<div class="portal-lp-property-title">{{ $propertyTitle }}</div>@endif
                                        @if($lead->property->reference_no)<div class="portal-lp-property-ref">Ref: {{ $lead->property->reference_no }}</div>@endif
                                    </div>
                                </div>
                                @endif
                                <dl class="portal-lp-grid">
                                    <div class="portal-lp-fact"><dt><i class="fas fa-briefcase" aria-hidden="true"></i>Company</dt><dd>{{ $lead->company ?: '—' }}</dd></div>
                                    <div class="portal-lp-fact"><dt><i class="fas fa-earth-asia" aria-hidden="true"></i>Country</dt><dd>{{ $lead->country ?: '—' }}</dd></div>
                                    <div class="portal-lp-fact"><dt><i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>Came in via</dt><dd>{{ $channel }}</dd></div>
                                    @unless($propertyTitle)
                                    <div class="portal-lp-fact"><dt><i class="fas fa-building" aria-hidden="true"></i>Property</dt><dd>—</dd></div>
                                    @endunless
                                    @foreach($enquiryDetails as $row)
                                    <div class="portal-lp-fact"><dt><i class="fas fa-list-check" aria-hidden="true"></i>{{ $row['label'] }}</dt><dd>{{ $row['value'] }}</dd></div>
                                    @endforeach
                                </dl>
                                <div class="portal-lp-message">
                                    <div class="portal-lp-label"><i class="fas fa-quote-left me-1" aria-hidden="true"></i>Enquiry message</div>
                                    <div class="portal-lp-message-copy">{{ $lead->message ?: 'No message was left with this enquiry.' }}</div>
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
                    @if($purchases->isNotEmpty())
                    {{-- Purchases — listings marked sold / rented to this lead --}}
                    <section class="portal-lp-card">
                        <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon is-success"><i class="fas fa-handshake"></i></span>Purchases</h2></header>
                        <div class="portal-lp-card-body">
                            @foreach($purchases as $purchase)
                            <div class="d-flex justify-content-between gap-2 py-2 @if(!$loop->last) portal-lp-divided @endif">
                                <div class="min-w-0">
                                    <a href="{{ route(($purchase->segment === \App\Models\Property::SEGMENT_COMMERCIAL ? 'portal.commercial' : 'portal.properties') . '.show', $purchase->id) }}" class="fw-semibold text-decoration-none d-block text-truncate">{{ $purchase->getTranslation('title') ?: 'Property' }}</a>
                                    <div class="small text-muted">
                                        {{ $purchase->sold_type === \App\Models\Property::RENTED ? 'Rented' : 'Bought' }} {{ $purchase->sold_at?->format('d M Y') }}
                                        @if($purchase->rented_until) &middot; until {{ $purchase->rented_until->format('d M Y') }}@endif
                                        @if($purchase->reference_no) &middot; {{ $purchase->reference_no }}@endif
                                    </div>
                                </div>
                                <span class="fw-bold text-nowrap">{{ ($purchase->currency ?: 'AED') . ' ' . number_format((float) $purchase->sold_price) }}</span>
                            </div>
                            @endforeach
                        </div>
                    </section>
                    @endif

                    {{-- Tags --}}
                    <section class="portal-lp-card" data-card id="leadTagsCard">
                        <header class="portal-lp-card-head">
                            <h2><span class="portal-lp-card-icon is-accent"><i class="fas fa-tags"></i></span>Tags</h2>
                            <button type="button" class="portal-lp-card-add" data-card-edit title="Manage tags"><i class="fas fa-plus"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <div class="d-flex flex-wrap gap-2" data-card-view>
                                @forelse($lead->tags as $tag)
                                <span class="portal-lp-tag" style="--tag: {{ $tag->color }};"><i class="fas fa-tag" aria-hidden="true"></i>{{ $tag->name }}</span>
                                @empty
                                <div class="portal-lp-empty w-100"><i class="fas fa-tags" aria-hidden="true"></i>No tags yet.</div>
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
                        <header class="portal-lp-card-head">
                            <h2><span class="portal-lp-card-icon"><i class="fas fa-layer-group"></i></span>Stage</h2>
                            <button type="button" class="portal-lp-card-action" data-card-edit title="Change stage"><i class="fas fa-pen"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <button type="button" class="portal-lp-pill-btn" data-card-view data-card-edit title="Change stage" style="--stage: {{ $lead->stage?->color ?: '#6b7094' }};">
                                <span class="portal-lp-pill-dot"></span>{{ $lead->stage?->name ?: 'No stage' }}
                                <i class="fas fa-chevron-down portal-lp-pill-caret" aria-hidden="true"></i>
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

                    {{-- Source — where the lead came from; fixed once captured, so read-only. --}}
                    <section class="portal-lp-card">
                        <header class="portal-lp-card-head">
                            <h2><span class="portal-lp-card-icon is-accent"><i class="fas fa-globe"></i></span>Source</h2>
                            <span class="portal-lp-readonly" title="The source is fixed once the lead is captured"><i class="fas fa-lock" aria-hidden="true"></i>Read-only</span>
                        </header>
                        <div class="portal-lp-card-body">
                            <div class="portal-lp-row">
                                <span class="portal-lp-row-icon is-accent"><i class="fas fa-share-nodes"></i></span>
                                <div class="min-w-0">
                                    <div class="portal-lp-row-title">{{ $lead->source?->name ?: 'No source' }}</div>
                                    <div class="portal-lp-row-sub">Came in via {{ $channel }}</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Owner (Super Admin) --}}
                    @if($isAdmin)
                    <section class="portal-lp-card" data-card>
                        <header class="portal-lp-card-head">
                            <h2><span class="portal-lp-card-icon"><i class="fas fa-building-user"></i></span>Owner</h2>
                            <button type="button" class="portal-lp-card-action" data-card-edit title="Transfer lead"><i class="fas fa-pen"></i></button>
                        </header>
                        <div class="portal-lp-card-body">
                            <div class="portal-lp-row" data-card-view>
                                <span class="portal-lp-avatar">{{ $ownerName ? $initialOf($ownerName) : 'SA' }}</span>
                                <div class="min-w-0">
                                    <div class="portal-lp-row-title text-truncate">{{ $ownerName ?: 'Unassigned (Super Admin)' }}</div>
                                    <div class="portal-lp-row-sub">{{ $lead->owner ? ($lead->owner->isAgency() ? 'Agency' : 'Agent') . ' account' : 'Not transferred yet' }}</div>
                                </div>
                            </div>
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
                            <h2><span class="portal-lp-card-icon is-success"><i class="fas fa-user-tie"></i></span>Assigned Agent</h2>
                            @if($canAssign)<button type="button" class="portal-lp-card-action" data-card-edit title="Assign agent"><i class="fas fa-pen"></i></button>@endif
                        </header>
                        <div class="portal-lp-card-body">
                            <div class="portal-lp-row" data-card-view>
                                <span class="portal-lp-avatar {{ $agentName ? 'is-success' : 'is-empty' }}">@if($agentName){{ $initialOf($agentName) }}@else<i class="fas fa-user-slash"></i>@endif</span>
                                <div class="min-w-0">
                                    <div class="portal-lp-row-title text-truncate">{{ $agentName ?: ($lead->owner?->isAgency() ? 'Unassigned (agency level)' : 'Unassigned') }}</div>
                                    @if($lead->agent && $lead->assignmentLabel())
                                    <div class="portal-lp-row-sub">{{ $lead->assignmentLabel() }}{{ $lead->assigned_at ? ' · ' . $lead->assigned_at->format('d M Y, H:i') : '' }}</div>
                                    @endif
                                </div>
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
                            <details class="portal-lp-history">
                                <summary><i class="fas fa-clock-rotate-left me-1" aria-hidden="true"></i>Assignment history ({{ $assignmentHistory->count() }})</summary>
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
                        <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon"><i class="fas fa-id-badge"></i></span>Lead</h2></header>
                        <div class="portal-lp-card-body">
                            <dl class="portal-lead-page-summary mb-0">
                                <div><dt>Status</dt><dd><span data-highlight-key="status" class="portal-lp-status is-{{ $status }}">{{ ucfirst($status) }}</span></dd></div>
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
                        <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon is-warning"><i class="fas fa-chart-line"></i></span>Activity</h2></header>
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

        {{-- ============ Insights — the website visitor's tracked activity (VisitorInsights) ============ --}}
        <div class="tab-pane fade" id="leadPageInsights" role="tabpanel">
            @if($visitorInsights)
                @include('visitor-insights._panel', $visitorInsights + ['feedUrl' => route('portal.crm.leads.insights', $lead->id)])
            @else
            <section class="portal-lp-card">
                <div class="portal-lp-card-body text-center text-muted py-5">
                    <i class="fas fa-chart-line fa-2x mb-3 d-block opacity-50" aria-hidden="true"></i>
                    <div class="fw-semibold mb-1">No website activity for this lead</div>
                    <div class="small">Insights — property views, time spent, searches, favorites, AI chats — appear for leads that came from the website
                        (AI chat, enquiry forms, customer accounts). This one came in via {{ $channel }}.</div>
                </div>
            </section>
            @endif
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
    // …/leads/{id}#insights (the leads table's Insights cell) opens straight on the Insights tab.
    if (window.location.hash === '#insights') {
        bootstrap.Tab.getOrCreateInstance(document.getElementById('leadPageInsightsTab')).show();
    }

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
