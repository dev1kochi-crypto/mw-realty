@extends('portal.layouts.app')

@section('title', $sectionTitle)

@section('content')
@php
    // Drag reorder only makes sense on the unfiltered list (a search result isn't a contiguous
    // slice of the display order); the per-card "Move to" actions work either way.
    $canDrag = $search === '' && !$filtered && $properties->count() > 1;
    $positionOffset = ($properties->currentPage() - 1) * $properties->perPage();
@endphp
@php
    $P = \App\Models\Property::class;
    // DLD permit review pills: [icon, hint, needs the account's action?]
    $reviewOrder = [
        $P::COMPLIANCE_CHANGES_REQUESTED => ['fa-rotate-left', 'Taken down by MW Realty — fix and save', true],
        $P::COMPLIANCE_EXPIRED => ['fa-ban', 'Add the renewed permit', true],
        $P::COMPLIANCE_DRAFT => ['fa-file-circle-exclamation', 'Add permit details', true],
        $P::COMPLIANCE_PENDING => ['fa-shield-halved', 'Validate the permit / waiting for MW Realty approval', true],
        $P::COMPLIANCE_APPROVED => ['fa-circle-check', 'Permit verified — can be live', false],
    ];
    $needsAction = collect($reviewOrder)->filter(fn ($meta) => $meta[2])->keys()->sum(fn ($key) => (int) ($reviewCounts[$key] ?? 0));
    $pillLabels = [$P::COMPLIANCE_PENDING => config('permits.superadmin_approval') ? 'Awaiting approval' : 'Not verified / approval'] + $P::COMPLIANCE_LABELS;
@endphp

{{-- One panel: title + search + plan chips + actions, then filters and the DLD permit pills. --}}
<div class="pl-panel mb-3">
    <div class="pl-panel__row">
        <div class="pl-panel__title">
            <div class="form-check mb-0" title="Select all on this page">
                <input class="form-check-input" type="checkbox" id="selectAllProperties" @if($properties->isEmpty()) disabled @endif>
                <label class="form-check-label small text-nowrap portal-muted" for="selectAllProperties">Select all</label>
            </div>
            <div class="portal-section-title mb-0 text-nowrap">{{ $isAdmin ? 'All ' . $sectionTitle : 'My ' . $sectionTitle }}</div>
        </div>

        <form method="GET" action="{{ route($routePrefix . '.index') }}" class="portal-list-search" role="search" id="propertyFilterForm">
            <div class="portal-list-search__field">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ $search }}" class="form-control" maxlength="100"
                       placeholder="Search by title, ref no, RERA, address, community or city{{ $isAdmin ? ', agent / agency' : '' }}" aria-label="Search {{ strtolower($sectionTitle) }}">
            </div>
            @if($filters['review'])<input type="hidden" name="review" value="{{ $filters['review'] }}">@endif
            @if($search !== '')
            <a href="{{ route($routePrefix . '.index', array_filter($filters)) }}" class="portal-list-search__clear" title="Clear search" aria-label="Clear search"><i class="fas fa-times"></i></a>
            @endif
            <button type="submit" class="btn btn-portal-primary btn-sm px-3">Search</button>
        </form>

        @if($planUsage || $featuredQuota)
        <div class="portal-list-toolbar__chips">
            @include('portal.properties._usage_chips')
        </div>
        @endif

        <div class="pl-panel__actions">
            {{-- Grid (cards) / List (table) — remembered for the session (PortalPropertyController::index). --}}
            <div class="pv-toggle" role="group" aria-label="Layout">
                <a href="{{ route($routePrefix . '.index', array_merge(request()->query(), ['view' => 'grid'])) }}" class="{{ $viewMode === 'grid' ? 'is-active' : '' }}" title="Grid view" aria-label="Grid view" @if($viewMode === 'grid') aria-current="true" @endif><i class="fas fa-grip"></i><span>Grid</span></a>
                <a href="{{ route($routePrefix . '.index', array_merge(request()->query(), ['view' => 'list'])) }}" class="{{ $viewMode === 'list' ? 'is-active' : '' }}" title="List view" aria-label="List view" @if($viewMode === 'list') aria-current="true" @endif><i class="fas fa-list"></i><span>List</span></a>
            </div>
            <div id="bulkActionsBar" class="dropdown d-none">
                <button class="btn btn-portal-danger btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Bulk Actions (<span id="bulkSelectedCount">0</span>)
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button class="dropdown-item bulk-action-btn" type="button" data-action="active"><i class="fas fa-check-circle text-success me-2"></i>Mark Active</button></li>
                    <li><button class="dropdown-item bulk-action-btn" type="button" data-action="inactive"><i class="fas fa-times-circle text-secondary me-2"></i>Mark Inactive</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item bulk-action-btn" type="button" data-action="delete"><i class="fas fa-trash text-danger me-2"></i>Delete Selected</button></li>
                </ul>
            </div>
            <a href="{{ route($routePrefix . '.create') }}" class="btn btn-portal-primary btn-sm text-nowrap">
                <i class="fas fa-plus me-1"></i> Add {{ $itemLabel }}
            </a>
        </div>
    </div>

    <div class="pl-panel__row pl-panel__row--filters">
        {{-- Filters — submit with the search box (form="propertyFilterForm"), applied on change. --}}
        <div class="pf-bar">
            <span class="pf-bar__label"><i class="fas fa-filter me-1"></i>Filter</span>
            @if($canFilterAgent)
            <div class="pf-combo">
                <input type="hidden" name="agent" form="propertyFilterForm" id="pfAgentValue" value="{{ $filters['agent'] ?? '' }}">
                <button type="button" class="pf-select pf-combo__toggle {{ $filters['agent'] ? 'is-set' : '' }}" id="pfAgentToggle" aria-haspopup="listbox" aria-expanded="false">
                    <i class="fas fa-user-tie me-1"></i>
                    <span id="pfAgentLabel">{{ $filters['agent'] === 'none' ? 'No agent (agency listings)' : ($filterAgent?->name ?? 'All agents') }}</span>
                    <i class="fas fa-chevron-down ms-1 small"></i>
                </button>
                <div class="pf-combo__menu d-none" id="pfAgentMenu">
                    <input type="search" class="form-control form-control-sm" id="pfAgentSearch" placeholder="Search agents…" autocomplete="off">
                    <div class="pf-combo__list" id="pfAgentList" data-url="{{ route('portal.properties.agent-options') }}"></div>
                </div>
            </div>
            @endif
            <select name="listing" form="propertyFilterForm" class="pf-select {{ $filters['listing'] ? 'is-set' : '' }}" aria-label="Buy or rent" data-autosubmit>
                <option value="">Buy &amp; Rent</option>
                <option value="sale" @selected($filters['listing'] === 'sale')>Buy</option>
                <option value="rent" @selected($filters['listing'] === 'rent')>Rent</option>
            </select>
            <select name="status" form="propertyFilterForm" class="pf-select {{ $filters['status'] ? 'is-set' : '' }}" aria-label="Status" data-autosubmit>
                <option value="">Active &amp; Inactive</option>
                <option value="active" @selected($filters['status'] === 'active')>Active</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
            </select>
            <label class="pf-select pf-check {{ $filters['premium'] ? 'is-set' : '' }}">
                <input type="checkbox" name="premium" value="1" form="propertyFilterForm" @checked($filters['premium']) data-autosubmit> <i class="fas fa-star text-warning"></i> Premium only
            </label>
            @if($filtered)
            <a href="{{ route($routePrefix . '.index', array_filter(['q' => $search ?: null])) }}" class="pf-clear"><i class="fas fa-times me-1"></i>Clear filters</a>
            @endif
        </div>

        {{-- DLD permit review: each pill filters the grid (click again to clear). --}}
        @if($reviewCounts->sum() > 0)
        <div class="pr-review" aria-label="DLD permit review">
            <span class="pr-review__title" title="A listing goes live once its advertising permit is verified (Validate in Core details).">
                <i class="fas fa-file-shield"></i> Permit
                @if(!$isAdmin && $needsAction > 0)<span class="pr-review__alert" title="Listings that need your action">{{ $needsAction }}</span>@endif
            </span>
            <div class="pr-review__pills">
                @foreach($reviewOrder as $key => [$icon, $hint, $actionable])
                @php $count = (int) ($reviewCounts[$key] ?? 0); $active = $filters['review'] === $key; @endphp
                <a href="{{ route($routePrefix . '.index', array_filter(array_merge($filters, ['review' => $active ? null : $key, 'q' => $search ?: null]))) }}"
                   class="pr-review__pill pr-tone-{{ $key }} {{ $active ? 'is-active' : '' }} {{ $count === 0 ? 'is-empty' : '' }} {{ $actionable && $count > 0 ? 'is-urgent' : '' }}"
                   @if($active) aria-current="true" @endif title="{{ $pillLabels[$key] }}: {{ $hint }} — {{ $active ? 'click to show all' : 'click to show only these' }}">
                    <i class="fas {{ $icon }}"></i>
                    <strong>{{ number_format($count) }}</strong>
                    <span>{{ $pillLabels[$key] }}</span>
                    @if($active)<i class="fas fa-times pr-review__x" aria-hidden="true"></i>@endif
                </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    @if($search !== '' || $filtered)
    <div class="pl-panel__foot">
        <strong>{{ $properties->total() }}</strong> result{{ $properties->total() === 1 ? '' : 's' }}@if($search !== '') for &ldquo;<strong>{{ $search }}</strong>&rdquo;@endif · drag to reorder is off while searching or filtering &mdash; use <i class="fas fa-sort"></i> <strong>Move to</strong> on a card instead.
    </div>
    @elseif($canDrag)
    <div class="pl-panel__foot"><i class="fas fa-grip-vertical me-1"></i> Drag a card by its handle to reorder this page, or use <i class="fas fa-sort"></i> <strong>Move to</strong> to send it to any position.</div>
    @endif
</div>

@if($viewMode === 'list' && $properties->isNotEmpty())
    @include('portal.properties._list_table')
@else
@forelse($properties as $property)
    @if($loop->first)
    <div class="row g-4" id="propertyGrid">
    @endif
    @php
        $thumb = $property->galleryImages()[0]['url'] ?? null;
        $quality = app(\App\Services\ListingQualityService::class)->score($property);
        $listingLabel = $property->filterLabel('listing_type');
        $typeLabel = $property->filterLabel('property_type');
        $ownerLabel = $property->owner
            ? ($property->owner->type === 'company' ? ($property->owner->company_name ?: $property->owner->name) : $property->owner->name)
            : 'MW Realty';
        // The Location tab's required Address/Community/City fields live per-language in
        // translations; the bare `location` column is only the optional location filter slug.
        $addressLine = collect(['address', 'community', 'city'])
            ->map(fn ($part) => $property->getTranslation($part))
            ->filter()
            ->implode(', ')
            ?: ($property->location ? ($property->filterLabel('location') ?: $property->location) : null);
        $scheduled = $property->isFeatureScheduled();
        $position = $search === '' && !$filtered ? $positionOffset + $loop->iteration : null;
    @endphp
    <div class="col-sm-6 col-lg-4 col-xl-3 portal-property-col" data-id="{{ $property->id }}">
        <div class="portal-property-card" data-property-row>
            <div class="portal-property-card__media">
                <div class="form-check portal-property-card__checkbox">
                    <input class="form-check-input property-select-checkbox" type="checkbox" value="{{ $property->id }}" aria-label="Select this property">
                </div>
                @if($canDrag)
                <span class="portal-property-card__drag" title="Drag to reorder" aria-label="Drag to reorder"><i class="fas fa-grip-vertical"></i></span>
                @endif
                <img src="{{ $thumb ?: 'https://placehold.co/400x240?text=No+Image' }}" alt="">
                @if($listingLabel)
                <span class="portal-property-card__badge">{{ $listingLabel }}</span>
                @endif
                @if($property->featured)
                <span class="portal-property-card__badge portal-property-card__badge--featured"><i class="fas fa-star"></i> Premium</span>
                @elseif($scheduled)
                <span class="portal-property-card__badge portal-property-card__badge--scheduled"><i class="far fa-clock"></i> Scheduled</span>
                @endif
                @php
                    // DLD permit review (ListingComplianceService): not live until approved. Shown as a
                    // chip on the photo + a one-line bar in the Premium slot, so every card keeps its layout.
                    $notLive = !$property->canGoLive() && !$property->isSold();
                    [$reviewIcon, $reviewText, $reviewCta] = $property->awaitingImportReview() ? ['fa-user-shield', 'Imported from Property Finder — in MW Realty review', 'View']
                        : ($property->awaitingApproval() ? ['fa-user-shield', 'Waiting for MW Realty to approve it', 'View'] : match ($property->compliance_status) {
                        $P::COMPLIANCE_CHANGES_REQUESTED => ['fa-rotate-left', 'Taken down by MW Realty — update and validate the permit', 'Fix now'],
                        $P::COMPLIANCE_EXPIRED => ['fa-ban', 'Permit expired — add the renewed permit', 'Renew'],
                        $P::COMPLIANCE_PENDING => ['fa-shield-halved', 'Permit not verified — validate it to go live', 'Validate'],
                        default => ['fa-file-circle-exclamation', 'Add the permit details to go live', 'Add'],
                    });
                @endphp
                @if($notLive)
                <span class="portal-property-card__review pr-tone-{{ $property->compliance_status }}"><i class="fas {{ $reviewIcon }}"></i>{{ $property->complianceLabel() }}</span>
                @endif
                {{-- Quality score (ListingQualityService) — hover for the breakdown, click for Listing Performance. --}}
                <button type="button" class="pq-ring is-{{ $quality['tone'] }} listing-insights" data-id="{{ $property->id }}" data-quality="{{ $property->id }}"
                        style="--pq: {{ $quality['score'] }};" aria-label="Quality score {{ $quality['score'] }} of 100 — open listing performance"><span>{{ $quality['score'] }}%</span></button>
                <template id="pqBreakdown{{ $property->id }}">
                    <div class="pq-pop-head"><span>Quality score</span><span class="pq-pop-score is-{{ $quality['tone'] }}"><i class="fas fa-gauge-high me-1"></i>{{ $quality['score'] }}/100</span></div>
                    @include('portal.properties._quality_breakdown', ['quality' => $quality])
                </template>
            </div>
            <div class="portal-property-card__body">
                <h3 class="portal-property-card__title" title="{{ $property->getTranslation('title') }}">{{ $property->getTranslation('title') }}</h3>
                <p class="portal-property-card__location"><i class="fas fa-map-marker-alt"></i> <span title="{{ $addressLine }}">{{ $addressLine ?: '—' }}</span></p>

                <div class="portal-property-card__stats">
                    @if($property->bedrooms)<span><i class="fas fa-bed"></i> {{ $property->bedrooms }}</span>@endif
                    @if($property->bathrooms)<span><i class="fas fa-bath"></i> {{ $property->bathrooms }}</span>@endif
                    @if($property->sqft)<span><i class="fas fa-ruler-combined"></i> {{ number_format($property->sqft) }} sq.ft</span>@endif
                </div>

                <div class="portal-property-card__price-row">
                    <span class="portal-property-card__price">{{ $property->price ? number_format($property->price) . ' ' . $property->currency : 'Price on request' }}</span>
                    <div class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                        <input class="form-check-input property-status-toggle m-0" type="checkbox" role="switch" id="statusToggle{{ $property->id }}" data-id="{{ $property->id }}" @checked($property->status)>
                        <label class="form-check-label small fw-semibold status-toggle-label {{ $property->status ? 'text-success' : 'text-secondary' }}" for="statusToggle{{ $property->id }}">{{ $property->status ? 'Active' : 'Inactive' }}</label>
                    </div>
                </div>

                {{-- Who works this listing: the assigned agent, or the agency itself when none. --}}
                @php
                    $cardAgent = $property->agent ?? ($property->owner?->type === 'agent' ? $property->owner : null);
                    $agentInitials = $cardAgent ? collect(preg_split('/\s+/', trim($cardAgent->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') : '';
                @endphp
                <div class="pf-agent">
                    @if($cardAgent)
                        @if($cardAgent->avatar)
                        <img src="{{ media_url($cardAgent->avatar) }}" alt="" class="pf-agent__avatar">
                        @else
                        <span class="pf-agent__avatar pf-agent__avatar--initials">{{ $agentInitials }}</span>
                        @endif
                        <span class="text-truncate"><span class="portal-muted">Agent</span> <strong>{{ $cardAgent->name }}</strong></span>
                    @else
                        <span class="pf-agent__avatar pf-agent__avatar--agency"><i class="fas fa-building"></i></span>
                        <span class="text-truncate"><strong>Agency listing</strong> <span class="portal-muted">· no agent</span></span>
                    @endif
                </div>

                <div class="pl-card-perf">
                    <button type="button" class="listing-insights" data-id="{{ $property->id }}" title="Listing performance & leads"><i class="fas fa-user-group"></i>Leads <strong>{{ $property->leads_count ?: '-' }}</strong></button>
                    <span class="pl-card-perf__q is-{{ $quality['tone'] }}"><i class="fas fa-gauge-high"></i>Quality score <strong>{{ $quality['score'] }}</strong></span>
                </div>

                <div class="portal-property-card__meta">
                    <span>{{ $typeLabel ?: '—' }}</span>
                    @if($property->reference_no)<span>Ref: {{ $property->reference_no }}</span>@endif
                    @if($isAdmin)<span>{{ $ownerLabel }}</span>@endif
                </div>

                @if($notLive && !$property->featured && !$scheduled)
                <a href="{{ route($routePrefix . '.edit', $property->id) }}#tab-basic" class="portal-property-card__feature pr-card-review pr-tone-{{ $property->compliance_status }}" title="{{ $reviewText }}">
                    <span class="text-truncate"><i class="fas {{ $reviewIcon }} me-1"></i>{{ $reviewText }}</span>
                    @if($reviewCta)<span class="pr-card-review__cta">{{ $reviewCta }} <i class="fas fa-arrow-right"></i></span>@endif
                </a>
                @else
                <div class="portal-property-card__feature {{ $property->featured ? 'is-featured' : ($scheduled ? 'is-scheduled' : '') }}">
                    @if($property->featured)
                        <span><i class="fas fa-star me-1"></i>{{ $property->featured_until ? 'Premium · ends ' . $property->featured_until->format('d M Y') : 'Premium · no end date' }}</span>
                        <span class="portal-property-card__feature-actions">
                            @if(isset($featureEditable[$property->id]))<button type="button" class="btn btn-link btn-sm p-0 edit-feature-dates" data-id="{{ $property->id }}" data-title="{{ $property->getTranslation('title') }}" data-thumb="{{ $thumb }}" data-ref="{{ $property->reference_no }}" data-live="1" data-start="{{ ($property->featured_from ?? now())->format('Y-m-d') }}" data-end="{{ $property->featured_until?->format('Y-m-d') }}">Edit</button>@endif
                            <button type="button" class="btn btn-link btn-sm p-0 unfeature-property" data-id="{{ $property->id }}" data-scheduled="0">Stop</button>
                        </span>
                    @elseif($scheduled)
                        <span title="{{ $property->featured_until ? 'Ends ' . $property->featured_until->format('d M Y') : 'No end date' }}"><i class="far fa-clock me-1"></i>Starts {{ $property->featured_from->format('d M') }}{{ $property->featured_until ? ' → ' . $property->featured_until->format('d M') : '' }}</span>
                        <span class="portal-property-card__feature-actions">
                            @if(isset($featureEditable[$property->id]))<button type="button" class="btn btn-link btn-sm p-0 edit-feature-dates" data-id="{{ $property->id }}" data-title="{{ $property->getTranslation('title') }}" data-thumb="{{ $thumb }}" data-ref="{{ $property->reference_no }}" data-live="0" data-start="{{ $property->featured_from->format('Y-m-d') }}" data-end="{{ $property->featured_until?->format('Y-m-d') }}">Edit</button>@endif
                            <button type="button" class="btn btn-link btn-sm p-0 unfeature-property" data-id="{{ $property->id }}" data-scheduled="1">Cancel</button>
                        </span>
                    @elseif($isAdmin || ($featuredQuota && $featuredQuota['limit'] > 0))
                        <button type="button" class="btn btn-link btn-sm p-0 feature-property" data-id="{{ $property->id }}"
                                data-title="{{ $property->getTranslation('title') }}" data-thumb="{{ $thumb }}" data-ref="{{ $property->reference_no }}"
                                @disabled(!$isAdmin && !$property->status) title="{{ $isAdmin || $property->status ? '' : 'Activate the listing to make it premium' }}"><i class="far fa-star me-1"></i>Make premium</button>
                    @else
                        <a href="{{ route('portal.plans.index') }}" class="portal-muted text-decoration-none"><i class="fas fa-lock me-1"></i>Premium needs a paid plan</a>
                    @endif
                </div>
                @endif

                <div class="portal-property-card__actions">
                    <a href="{{ route($routePrefix . '.show', $property->id) }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-eye me-1"></i>View</a>
                    <a href="{{ route($routePrefix . '.edit', $property->id) }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
                    <button type="button" class="portal-btn-ghost btn btn-sm portal-property-card__icon-btn listing-insights" data-id="{{ $property->id }}" title="Listing performance" aria-label="Listing performance"><i class="fas fa-chart-line"></i></button>
                    <button type="button" class="portal-btn-ghost btn btn-sm portal-property-card__icon-btn mark-sold-property" data-id="{{ $property->id }}"
                            data-title="{{ $property->getTranslation('title') }}" data-ref="{{ $property->reference_no }}" data-thumb="{{ $thumb }}"
                            data-price="{{ $property->price ? (float) $property->price : '' }}" data-currency="{{ $property->currency ?: 'AED' }}"
                            data-type="{{ $property->listing_type === 'rent' ? 'rented' : 'sold' }}"
                            title="Mark as sold / rented" aria-label="Mark as sold or rented"><i class="fas fa-handshake"></i></button>
                    <button type="button" class="portal-btn-ghost btn btn-sm portal-property-card__icon-btn delete-property" data-id="{{ $property->id }}" title="Delete" aria-label="Delete"><i class="fas fa-trash"></i></button>
                    @if($totalListings > 1)
                    <div class="dropdown">
                        <button type="button" class="portal-btn-ghost btn btn-sm portal-property-card__icon-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Move to" aria-label="Move to position">
                            <i class="fas fa-sort"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Move to{{ $position ? ' · now #' . $position . ' of ' . $totalListings : '' }}</h6></li>
                            <li><button class="dropdown-item move-property" type="button" data-id="{{ $property->id }}" data-position="top" @disabled($position === 1)><i class="fas fa-angle-double-up me-2"></i>Top (position 1)</button></li>
                            <li><button class="dropdown-item move-property" type="button" data-id="{{ $property->id }}" data-position="bottom" @disabled($position === $totalListings)><i class="fas fa-angle-double-down me-2"></i>Bottom (position {{ $totalListings }})</button></li>
                            <li><button class="dropdown-item move-property-to" type="button" data-id="{{ $property->id }}" data-title="{{ $property->getTranslation('title') }}" data-current="{{ $position }}"><i class="fas fa-hashtag me-2"></i>Position&hellip;</button></li>
                        </ul>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @if($loop->last)
    </div>
    @endif
@empty
    <div class="portal-card p-5 text-center portal-empty">
        @if($search !== '' || $filtered)
            Nothing matches{{ $search !== '' ? ' “' . $search . '”' : '' }}{{ $filtered ? ' these filters' : '' }}. <a href="{{ route($routePrefix . '.index') }}">Clear search &amp; filters</a>.
        @else
            No {{ strtolower($sectionTitle) }} yet. <a href="{{ route($routePrefix . '.create') }}">Add your first listing</a>.
        @endif
    </div>
@endforelse
@endif

@if($properties->total() > 0)
<div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="portal-muted small">Showing {{ $properties->firstItem() }}–{{ $properties->lastItem() }} of {{ $properties->total() }}</span>
    <div>{{ $properties->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
</div>
@endif

@include('portal.properties._feature_modal')
@include('portal.properties._sold_modal')

{{-- Listing Performance — filled from properties.insights (PortalPropertyController::insights). --}}
<div class="offcanvas offcanvas-end lp-offcanvas" tabindex="-1" id="listingPerformance" aria-labelledby="listingPerformanceTitle">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="listingPerformanceTitle">Listing Performance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="listingPerformanceBody"></div>
</div>
<div class="pq-pop d-none" id="pqPop" role="tooltip"></div>

{{-- Move to position modal --}}
<div class="modal fade" id="moveModal" tabindex="-1" aria-labelledby="moveModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content" id="moveForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="moveModalTitle"><i class="fas fa-sort me-2"></i>Move to position</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small portal-muted mb-2 text-truncate" id="moveTitle"></p>
                <label class="form-label fw-semibold" for="movePosition">New position</label>
                <input type="number" class="form-control" id="movePosition" min="1" max="{{ $totalListings }}" required>
                <div class="form-text">1 is shown first. {{ $totalListings }} listings in total, {{ $properties->perPage() }} per page.</div>
                <div class="alert alert-danger small mt-3 mb-0 d-none" id="moveError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-portal-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-portal-primary btn-sm" id="moveSubmit">Move</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Listing Performance side panel + quality score hover breakdown.
(function () {
    const panelEl = document.getElementById('listingPerformance');
    const body = document.getElementById('listingPerformanceBody');
    const insightsUrl = @json(route('portal.properties.insights', ['id' => '__ID__']));
    let request = 0;

    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('.listing-insights');
        if (!trigger) return;
        e.preventDefault();
        const id = ++request;
        body.innerHTML = '<div class="lp-loading"><span class="spinner-border spinner-border-sm me-2"></span>Loading performance…</div>';
        bootstrap.Offcanvas.getOrCreateInstance(panelEl).show();
        fetch(insightsUrl.replace('__ID__', trigger.dataset.id), { headers: { Accept: 'text/html' } })
            .then((r) => { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then((html) => { if (id === request) body.innerHTML = html; })
            .catch(() => { if (id === request) body.innerHTML = '<div class="lp-empty"><i class="fas fa-triangle-exclamation"></i>Couldn\'t load the performance — please try again.</div>'; });
    });

    // Hover breakdown, positioned next to the ring (fixed, so card overflow never clips it).
    const pop = document.getElementById('pqPop');
    let hideTimer = null;
    document.addEventListener('mouseover', function (e) {
        const ring = e.target.closest('.pq-ring');
        if (!ring) return;
        clearTimeout(hideTimer);
        const template = document.getElementById('pqBreakdown' + ring.dataset.quality);
        if (!template) return;
        pop.innerHTML = template.innerHTML;
        pop.classList.remove('d-none');
        const r = ring.getBoundingClientRect();
        const width = pop.offsetWidth, height = pop.offsetHeight;
        const left = Math.min(window.innerWidth - width - 12, Math.max(12, r.right - width));
        const top = r.bottom + 8 + height > window.innerHeight ? r.top - height - 8 : r.bottom + 8;
        pop.style.left = left + 'px';
        pop.style.top = Math.max(12, top) + 'px';
    });
    document.addEventListener('mouseout', function (e) {
        if (e.target.closest('.pq-ring')) hideTimer = setTimeout(() => pop.classList.add('d-none'), 120);
    });
})();

    const JSON_HEADERS = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' };

    // Card (grid) or row (list) — both are [data-property-row]. A list row's open menu sits on <body>, so fall back to its row by id.
    const cardTitle = el => (el.closest('[data-property-row]') || document.querySelector(`tr[data-property-row][data-id="${el.dataset.id}"]`))?.querySelector('.portal-property-card__title, [data-property-title]')?.textContent.trim();

    // Filters: selects / checkbox apply at once; the agent picker searches the server (20 a page, more on scroll).
    (function () {
        const form = document.getElementById('propertyFilterForm');
        if (!form) return;
        document.querySelectorAll('[data-autosubmit]').forEach(el => el.addEventListener('change', () => form.requestSubmit()));

        const toggle = document.getElementById('pfAgentToggle');
        if (!toggle) return;
        const menu = document.getElementById('pfAgentMenu'), list = document.getElementById('pfAgentList');
        const search = document.getElementById('pfAgentSearch'), value = document.getElementById('pfAgentValue');
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        let page = 1, more = false, loading = false, term = '', timer = null, requestNo = 0;

        const item = (val, label) => `<button type="button" class="pf-combo__item ${String(value.value) === String(val) ? 'is-current' : ''}" data-value="${esc(val)}">${label}</button>`;
        function load(reset) {
            if (loading && !reset) return;
            if (reset) {
                page = 1;
                list.innerHTML = term ? '' : item('', 'All agents') + item('none', '<i class="fas fa-building me-1"></i>No agent (agency listings)');
            }
            loading = true;
            const mine = ++requestNo;
            fetch(`${list.dataset.url}?q=${encodeURIComponent(term)}&page=${page}`, { headers: { Accept: 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (mine !== requestNo) return;
                    data.results.forEach(a => list.insertAdjacentHTML('beforeend', item(a.id, esc(a.text))));
                    if (page === 1 && !data.results.length) list.insertAdjacentHTML('beforeend', '<div class="pf-combo__status">No agents found.</div>');
                    more = !!data.pagination?.more;
                    page++;
                })
                .catch(() => list.insertAdjacentHTML('beforeend', '<div class="pf-combo__status">Could not load agents.</div>'))
                .finally(() => { if (mine === requestNo) loading = false; });
        }
        const close = () => { menu.classList.add('d-none'); toggle.setAttribute('aria-expanded', 'false'); };
        toggle.addEventListener('click', () => {
            const opening = menu.classList.contains('d-none');
            menu.classList.toggle('d-none', !opening);
            toggle.setAttribute('aria-expanded', String(opening));
            if (opening) { term = ''; search.value = ''; load(true); search.focus(); }
        });
        search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { term = search.value.trim(); load(true); }, 250); });
        search.addEventListener('keydown', e => { if (e.key === 'Escape') close(); if (e.key === 'Enter') e.preventDefault(); });
        list.addEventListener('scroll', () => { if (more && !loading && list.scrollTop + list.clientHeight >= list.scrollHeight - 30) load(false); });
        list.addEventListener('click', e => {
            const btn = e.target.closest('.pf-combo__item');
            if (!btn) return;
            value.value = btn.dataset.value;
            form.requestSubmit();
        });
        document.addEventListener('pointerdown', e => { if (!e.target.closest('.pf-combo')) close(); });
    })();

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.delete-property');
        if (!btn) return;
        const name = cardTitle(btn);
        const ok = await window.portalConfirm({
            title: @json('Delete this ' . strtolower($itemLabel) . '?'),
            message: (name ? '“' + name + '” ' : 'This listing ') + 'and all its photos will be removed. This can\'t be undone.',
            confirmText: 'Delete',
            tone: 'danger',
        });
        if (!ok) return;
        fetch("{{ url('portal/properties') }}/" + btn.dataset.id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        }).then(() => location.reload());
    });

    // Feature (opens the shared popup) / stop or cancel a feature.
    document.addEventListener('click', async function (e) {
        const featureBtn = e.target.closest('.feature-property');
        if (featureBtn) {
            window.openFeatureModal({ id: featureBtn.dataset.id, title: featureBtn.dataset.title, thumb: featureBtn.dataset.thumb, ref: featureBtn.dataset.ref });
            return;
        }
        const editBtn = e.target.closest('.edit-feature-dates');
        if (editBtn) {
            const d = editBtn.dataset;
            window.openFeatureEditModal({ id: d.id, title: d.title, thumb: d.thumb, ref: d.ref, live: d.live === '1', start: d.start, end: d.end });
            return;
        }
        const stopBtn = e.target.closest('.unfeature-property');
        if (!stopBtn) return;
        const name = cardTitle(stopBtn);
        const ok = await window.portalConfirm(stopBtn.dataset.scheduled === '1' ? {
            title: 'Cancel scheduled premium?',
            message: (name ? '“' + name + '” won\'t be premium. ' : '') + 'The booking is removed, so it no longer counts toward your plan.',
            confirmText: 'Cancel premium',
            cancelText: 'Keep it',
            tone: 'warning',
        } : {
            title: 'Remove premium now?',
            message: (name ? '“' + name + '” ' : 'This listing ') + 'loses its Premium badge and top placement on the website right away.'
                + @json($featuredQuota && $featuredQuota['per_month'] ? ' It still counts toward this month\'s quota.' : ''),
            confirmText: 'Remove premium',
            cancelText: 'Keep premium',
            tone: 'warning',
        });
        if (!ok) return;
        stopBtn.disabled = true;
        fetch("{{ url('portal/properties') }}/" + stopBtn.dataset.id + '/unfeature', { method: 'POST', headers: JSON_HEADERS })
            .then(r => { if (!r.ok) throw new Error(); location.reload(); })
            .catch(() => { stopBtn.disabled = false; alert('Could not update premium. Please try again.'); });
    });

    // Move to top / bottom / position — renumbers the whole list server-side (so it works across
    // pages), then opens the page the listing landed on and highlights it.
    (function () {
        const moveUrl = id => @json(route($routePrefix . '.move', '__ID__')).replace('__ID__', id);
        const searching = @json($search !== '' || $filtered);
        const baseUrl = @json(route($routePrefix . '.index'));
        const modalEl = document.getElementById('moveModal');
        const modal = new bootstrap.Modal(modalEl);
        const input = document.getElementById('movePosition');
        const errorBox = document.getElementById('moveError');
        const submitBtn = document.getElementById('moveSubmit');
        let moveId = null;

        function move(id, position) {
            return fetch(moveUrl(id), { method: 'POST', headers: JSON_HEADERS, body: JSON.stringify({ position }) })
                .then(async r => {
                    const data = await r.json().catch(() => ({}));
                    if (!r.ok) throw new Error(Object.values(data.errors || {})[0]?.[0] || data.message || 'Could not move this listing.');
                    if (searching) { location.reload(); return; }
                    const url = new URL(baseUrl, location.origin);
                    if (data.page > 1) url.searchParams.set('page', data.page);
                    url.hash = 'property-' + id;
                    location.href = url.toString();
                });
        }

        document.addEventListener('click', function (e) {
            const quick = e.target.closest('.move-property');
            if (quick) {
                quick.disabled = true;
                move(quick.dataset.id, quick.dataset.position).catch(err => { quick.disabled = false; alert(err.message); });
                return;
            }
            const to = e.target.closest('.move-property-to');
            if (to) {
                moveId = to.dataset.id;
                document.getElementById('moveTitle').textContent = to.dataset.title + (to.dataset.current ? ' — now #' + to.dataset.current : '');
                input.value = to.dataset.current || '';
                errorBox.classList.add('d-none');
                submitBtn.disabled = false;
                modal.show();
            }
        });
        modalEl.addEventListener('shown.bs.modal', () => input.select());

        document.getElementById('moveForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const position = parseInt(input.value, 10);
            const max = parseInt(input.max, 10);
            if (!position || position < 1 || position > max) {
                errorBox.textContent = 'Enter a position between 1 and ' + max + '.';
                errorBox.classList.remove('d-none');
                return;
            }
            submitBtn.disabled = true;
            move(moveId, position).catch(err => { errorBox.textContent = err.message; errorBox.classList.remove('d-none'); submitBtn.disabled = false; });
        });

        const m = location.hash.match(/^#property-(\d+)$/);
        const moved = m && document.querySelector('.portal-property-col[data-id="' + m[1] + '"]');
        if (moved) {
            moved.scrollIntoView({ block: 'center' });
            moved.classList.add('is-moved');
            setTimeout(() => moved.classList.remove('is-moved'), 2400);
            history.replaceState(null, '', location.pathname + location.search);
        }
    })();

    // Per-card Active/Inactive switch.
    document.addEventListener('change', function (e) {
        const toggle = e.target.closest('.property-status-toggle');
        if (!toggle) return;
        const label = toggle.parentElement.querySelector('.status-toggle-label');
        const setLabel = on => {
            label.textContent = on ? 'Active' : 'Inactive';
            label.classList.toggle('text-success', on);
            label.classList.toggle('text-secondary', !on);
        };

        toggle.disabled = true;
        setLabel(toggle.checked);
        fetch("{{ url('portal/properties') }}/" + toggle.dataset.id + "/toggle-status", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        })
            .then(r => { if (!r.ok) return r.json().catch(() => ({})).then(body => { throw new Error(body.message || ''); }); })
            .catch(err => {
                toggle.checked = !toggle.checked;
                setLabel(toggle.checked);
                alert(err.message || 'Could not update the status. Please try again.');
            })
            .finally(() => { toggle.disabled = false; });
    });

    (function () {
        const selectAll = document.getElementById('selectAllProperties');
        const checkboxes = () => Array.from(document.querySelectorAll('.property-select-checkbox'));
        const bar = document.getElementById('bulkActionsBar');
        const countLabel = document.getElementById('bulkSelectedCount');

        function refreshBar() {
            const checked = checkboxes().filter(cb => cb.checked);
            bar.classList.toggle('d-none', checked.length === 0);
            countLabel.textContent = checked.length;
            if (selectAll) {
                const all = checkboxes();
                selectAll.checked = all.length > 0 && checked.length === all.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            }
        }

        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('property-select-checkbox')) {
                refreshBar();
            }
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes().forEach(cb => { cb.checked = selectAll.checked; });
                refreshBar();
            });
        }

        document.querySelectorAll('.bulk-action-btn').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const action = btn.dataset.action;
                const ids = checkboxes().filter(cb => cb.checked).map(cb => cb.value);
                if (ids.length === 0) return;
                if (action === 'delete' && !(await window.portalConfirm({
                    title: 'Delete ' + ids.length + ' listing' + (ids.length === 1 ? '' : 's') + '?',
                    message: 'The selected listing' + (ids.length === 1 ? '' : 's') + ' and all their photos will be removed. This can\'t be undone.',
                    confirmText: 'Delete ' + ids.length,
                    tone: 'danger',
                }))) return;

                btn.disabled = true;
                fetch("{{ route('portal.properties.bulk-action') }}", {
                    method: 'POST',
                    headers: JSON_HEADERS,
                    body: JSON.stringify({ ids: ids, action: action }),
                })
                    .then(r => { if (!r.ok) throw new Error(); location.reload(); })
                    .catch(() => { btn.disabled = false; alert('Could not apply the bulk action. Please try again.'); });
            });
        });

        refreshBar();
    })();

    // Drag-and-drop reorder of the cards on this page. Only the grip handle arms a drag, so the
    // card's buttons, links and checkbox behave normally.
    (function () {
        const grid = document.getElementById('propertyGrid');
        if (!grid || !grid.querySelector('.portal-property-card__drag')) return;
        let dragEl = null;

        grid.addEventListener('mousedown', function (e) {
            const handle = e.target.closest('.portal-property-card__drag');
            if (handle) handle.closest('.portal-property-col').setAttribute('draggable', 'true');
        });
        grid.addEventListener('dragstart', function (e) {
            const col = e.target.closest('.portal-property-col');
            if (!col || col.getAttribute('draggable') !== 'true') return;
            dragEl = col;
            e.dataTransfer.effectAllowed = 'move';
            setTimeout(() => col.classList.add('is-dragging'), 0);
        });
        grid.addEventListener('dragover', function (e) {
            if (!dragEl) return;
            e.preventDefault();
            const target = e.target.closest('.portal-property-col');
            if (!target || target === dragEl) return;
            const rect = target.getBoundingClientRect();
            (e.clientX - rect.left) > rect.width / 2 ? target.after(dragEl) : target.before(dragEl);
        });
        grid.addEventListener('drop', function (e) {
            if (dragEl) e.preventDefault();
        });
        grid.addEventListener('dragend', function () {
            if (!dragEl) return;
            dragEl.classList.remove('is-dragging');
            dragEl.removeAttribute('draggable');
            dragEl = null;

            const order = Array.from(grid.querySelectorAll('.portal-property-col')).map(col => col.dataset.id);
            fetch("{{ route($routePrefix . '.reorder') }}", {
                method: 'POST',
                headers: JSON_HEADERS,
                body: JSON.stringify({ order: order }),
            }).then(r => { if (!r.ok) throw new Error(); location.reload(); })
              .catch(() => { alert('Could not save the new order.'); location.reload(); });
        });
        document.addEventListener('mouseup', function () {
            if (!dragEl) grid.querySelectorAll('.portal-property-col[draggable]').forEach(col => col.removeAttribute('draggable'));
        });
    })();
</script>
@endpush

@push('styles')
<style>
    /* DLD permit review strip + the admin's note on a sent-back card. */
    .min-w-0 { min-width: 0; }

    /* Listing header panel: title · search · plan chips · actions, then filters + DLD permit pills. */
    .pl-panel { padding: 12px 14px; border-radius: var(--portal-radius); border: 1px solid var(--portal-border); background: var(--portal-surface); box-shadow: var(--portal-shadow); }
    .pl-panel__row { display: flex; align-items: center; gap: 10px 12px; flex-wrap: wrap; }
    .pl-panel__row--filters { justify-content: space-between; margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--portal-border); }
    .pl-panel__title { display: flex; align-items: center; gap: 12px; }
    .pl-panel .portal-list-search { flex: 1 1 300px; min-width: 0; padding: 3px 3px 3px 2px; background: var(--portal-bg); border-color: transparent; box-shadow: none; }
    .pl-panel .portal-list-search__field .form-control { padding-top: 5px; padding-bottom: 5px; }
    .pl-panel .portal-list-toolbar__chips { margin-left: 0; }
    .pl-panel__actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
    .pl-panel__foot { margin-top: 10px; padding-top: 8px; border-top: 1px dashed var(--portal-border); font-size: .78rem; color: var(--portal-muted); }
    .pl-panel .pf-select { height: 34px; }
    @media (max-width: 767.98px) {
        .pl-panel__actions { margin-left: 0; }
        .pl-panel .portal-list-search { flex-basis: 100%; order: 3; flex-wrap: nowrap; }
        .pl-panel .portal-list-search__field { flex-basis: auto; }
    }

    /* DLD permit pills (click to filter, click again to clear) — sit at the end of the filter row. */
    .pr-review { display: flex; align-items: center; gap: 8px 10px; min-width: 0; }
    .pr-review__title { display: inline-flex; align-items: center; gap: 6px; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: var(--portal-muted); white-space: nowrap; cursor: help; }
    .pr-review__title .fa-file-shield { color: var(--portal-primary); font-size: .9rem; }
    .pr-review__alert { display: inline-grid; place-items: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: var(--portal-accent); color: #fff; font-size: .7rem; letter-spacing: 0; }
    .pr-review__pills { display: flex; flex-wrap: wrap; gap: 6px; }

    .pr-tone-changes_requested { --tone: #c2410c; --tone-soft: #fdeee4; }
    .pr-tone-expired { --tone: #be123c; --tone-soft: #fde8ed; }
    .pr-tone-draft { --tone: #b7791f; --tone-soft: #fdf3e1; }
    .pr-tone-pending { --tone: #244373; --tone-soft: #e9eef6; }
    .pr-tone-approved { --tone: #0f8a4f; --tone-soft: #e5f5ec; }

    .pr-review__pill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 999px; border: 1px solid transparent; background: var(--tone-soft); color: var(--tone); font-size: .8rem; font-weight: 600; line-height: 1.2; text-decoration: none; white-space: nowrap; transition: transform .15s ease, box-shadow .15s ease; }
    .pr-review__pill:hover { color: var(--tone); transform: translateY(-1px); box-shadow: 0 4px 10px rgba(31, 35, 64, .08); }
    .pr-review__pill strong { font-weight: 800; }
    .pr-review__pill span { color: var(--portal-text); font-weight: 600; }
    .pr-review__pill.is-urgent { border-color: var(--tone); }
    .pr-review__pill.is-empty:not(.is-active) { background: #f3f4f8; color: #a3a8c3; }
    .pr-review__pill.is-empty:not(.is-active) span { color: #8a8fab; }
    .pr-review__pill.is-active { background: var(--tone); border-color: var(--tone); color: #fff; box-shadow: 0 4px 12px color-mix(in srgb, var(--tone) 35%, transparent); }
    .pr-review__pill.is-active span { color: #fff; }
    .pr-review__x { font-size: .7rem; opacity: .85; }
    @media (max-width: 767.98px) {
        .pr-review__pills { flex-wrap: nowrap; overflow-x: auto; width: 100%; padding-bottom: 2px; scrollbar-width: none; }
        .pr-review__pills::-webkit-scrollbar { display: none; }
    }
    /* Card: permit status chip on the photo (bottom centre, between the checkbox and drag handle). */
    .portal-property-card__review { position: absolute; left: 50%; bottom: .75rem; transform: translateX(-50%); z-index: 2; display: inline-flex; align-items: center; gap: 5px; max-width: calc(100% - 7rem); padding: .3rem .7rem; border-radius: 50px; background: var(--tone); color: #fff; font-size: .7rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; box-shadow: 0 4px 12px rgba(0, 0, 0, .22); }
    /* Card: the one-line permit bar that takes the Premium row's place while the listing isn't live. */
    .pr-card-review { background: var(--tone-soft); color: var(--tone); text-decoration: none; min-width: 0; }
    .pr-card-review:hover { color: var(--tone); filter: brightness(.97); }
    .pr-card-review > .text-truncate { min-width: 0; color: var(--portal-text); font-weight: 600; }
    .pr-card-review > .text-truncate i { color: var(--tone); }
    .pr-card-review__cta { flex-shrink: 0; font-weight: 700; white-space: nowrap; }
    .text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
@endpush
