@php
    $isEdit = isset($property);
    $details = $isEdit ? $property->details : null;
    $val = fn ($field, $default = null) => old($field, $isEdit ? ($property->{$field} ?? $default) : $default);
    $detailVal = fn ($field, $default = null) => old($field, $isEdit && $details ? ($details->{$field} ?? $default) : $default);
    $meta = $isEdit ? ($property->metadata ?? []) : [];
    $metaVal = fn ($field) => old("metadata.{$field}", $meta[$field] ?? '');

    // Property Type / Listing Type / Completion Status option text comes from the static-texts
    // JSON files (lang/cms-static/{code}.json, editable under CMS > Languages > Static Texts) —
    // these are fixed, developer-defined value sets, unlike Category/Location which are fully
    // admin-configurable Filter values and keep using the FilterValue's own per-language label.
    $staticTranslations = app(\CMS\SiteManager\Services\StaticTranslationService::class);
    $staticLabels = [];
    foreach ($languages as $lang) {
        $staticLabels[$lang->code] = $staticTranslations->flatten($staticTranslations->read($lang->code));
    }
    $staticSelectFields = ['property_type', 'listing_type', 'completion_status'];
    $fallbackLang = config('app.fallback_locale', 'en');
    $sourceLang = $languages->first();
@endphp

@if($languages->count() > 1)
{{-- Content language switcher (drives the Description + Location language tabs) and auto-fill:
     text typed in the first language is translated into the others — see PropertyTranslateController. --}}
<div class="property-lang-bar" id="propertyLangBar" data-source="{{ $sourceLang->code }}" data-source-name="{{ $sourceLang->name }}"
     data-translate-url="{{ route('portal.properties.translate') }}" data-configured="{{ \App\Services\AutoTranslator::configured() ? 1 : 0 }}">
    <div class="property-lang-bar__label"><i class="fas fa-language"></i> Content language</div>
    <div class="property-lang-bar__switch" role="tablist" aria-label="Content language">
        @foreach($languages as $lang)
        <button type="button" role="tab" class="property-lang-pill {{ $loop->first ? 'active' : '' }}" data-lang="{{ $lang->code }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
            <span class="property-lang-pill__dot" data-state-for="{{ $lang->code }}"></span>{{ $lang->name }}
        </button>
        @endforeach
    </div>
    <div class="property-lang-bar__tools">
        <div class="form-check form-switch m-0" title="Translate {{ $sourceLang->name }} text into the other languages as you type">
            <input class="form-check-input" type="checkbox" id="autoTranslateToggle" checked>
            <label class="form-check-label small fw-semibold" for="autoTranslateToggle">Auto-fill from {{ $sourceLang->name }}</label>
        </div>
        <button type="button" class="btn btn-sm btn-portal-light" id="translateNowBtn"><i class="fas fa-wand-magic-sparkles me-1"></i>Translate now</button>
    </div>
    <div class="property-lang-bar__status" id="translateStatus" aria-live="polite"></div>
</div>
@endif

<div class="property-tabs-shell">
    <div class="nav property-tabs-sidebar" role="tablist" aria-orientation="vertical">
        <div class="property-tabs-group-label">Listing Info</div>
        <button type="button" role="tab" class="property-tab-link active" data-bs-toggle="pill" data-bs-target="#tab-basic">
            <span class="property-tab-icon"><i class="fas fa-file-lines"></i></span> <span>Core details</span>
        </button>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-specifications">
            <span class="property-tab-icon"><i class="fas fa-list-check"></i></span> <span>Property Details</span>
        </button>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-agent">
            <span class="property-tab-icon"><i class="fas fa-user-tie"></i></span> <span>Agent &amp; Agency</span>
        </button>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-location">
            <span class="property-tab-icon"><i class="fas fa-map-pin"></i></span> <span>Location</span>
        </button>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-amenities">
            <span class="property-tab-icon"><i class="fas fa-swimming-pool"></i></span> <span>Amenities</span>
        </button>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-easy-access">
            <span class="property-tab-icon"><i class="fas fa-route"></i></span> <span>Easy Access</span>
        </button>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-attributes">
            <span class="property-tab-icon"><i class="fas fa-star"></i></span> <span>Attributes</span>
        </button>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-floorplans">
            <span class="property-tab-icon"><i class="fas fa-border-all"></i></span> <span>Floor Plans</span>
        </button>

        <div class="property-tabs-group-label">Media &amp; Publishing</div>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-images">
            <span class="property-tab-icon"><i class="fas fa-images"></i></span> <span>Images</span>
        </button>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-brochure">
            <span class="property-tab-icon"><i class="fas fa-file-pdf"></i></span> <span>Brochure</span>
        </button>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-nearby">
            <span class="property-tab-icon"><i class="fas fa-location-dot"></i></span> <span>Nearby Places</span>
        </button>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-publishing">
            <span class="property-tab-icon"><i class="fas fa-bullhorn"></i></span> <span>Publishing</span>
        </button>
    </div>

    <div class="property-tabs-content tab-content">

        {{-- Core details / Specifications / Price / Description — one tab each (Core details keeps the "tab-basic" id) --}}
        @php
            // Params for _option-select, all passed every time (an @include also sees this view's variables).
            $opt = fn (string $field, string $label, array $extra = []) => array_merge(
                ['field' => $field, 'label' => $label, 'name' => null, 'required' => false, 'current' => null, 'placeholder' => null], $extra);
            $isLocked = fn (string $field) => in_array($field, $lockedFields ?? [], true);
            $lockIcon = '<i class="fas fa-lock text-muted ms-1 small" title="Matches the approved DLD permit"></i>';
            $availableDates = collect(old('available_dates', $isEdit ? ($property->available_dates ?? []) : []))->filter()->sort()->values();
            $availability = old('availability', $availableDates->isNotEmpty() ? 'from_date' : 'immediately');
        @endphp
        <div class="tab-pane fade show active" id="tab-basic" role="tabpanel">
            <div class="property-tab-pane-head">
                <div class="property-tab-pane-title">Core details</div>
                <div class="property-tab-pane-hint">Emirate, offering and property type, location, reference and availability</div>
            </div>

            @if(!empty($lockedFields))
            <div class="alert alert-info d-flex gap-2 align-items-start py-2 small">
                <i class="fas fa-lock mt-1"></i>
                <div>This listing's permit is {{ $isEdit && $property->compliance_status === \App\Models\Property::COMPLIANCE_APPROVED ? 'approved' : 'verified' }}, so the fields marked <i class="fas fa-lock"></i> must stay as they are on the permit.
                    Need one changed? <a href="{{ route('portal.contact.index') }}" target="_blank">Raise a ticket</a>.</div>
            </div>
            @endif

            @include('portal.properties._permit')

                {{-- One field per row, in the order the listing portals use. --}}
                <div class="row g-3 core-details-grid">
                    <div class="col-12">@include('portal.properties._option-buttons', ['field' => 'category', 'label' => 'Category', 'required' => true, 'icons' => ['residential' => 'fa-house', 'commercial' => 'fa-building']])</div>
                    <div class="col-12">@include('portal.properties._option-buttons', ['field' => 'listing_type', 'label' => 'Offering type', 'required' => true, 'icons' => ['rent' => 'fa-key', 'sale' => 'fa-tag']])</div>
                    <div class="col-12 rent-only">@include('portal.properties._option-select', $opt(\App\Models\Filter::RENTAL_PERIOD_KEY, 'Rental period', ['required' => true, 'current' => $val('rental_period', 'yearly')]))</div>
                    <div class="col-12">@include('portal.properties._option-select', $opt('property_type', 'Property type', ['required' => true]))</div>
                    <div class="col-12">@include('portal.properties._option-select', $opt('location', 'Property location', ['required' => true, 'placeholder' => 'Search community / area']))</div>
                    <div class="col-12">@include('portal.properties._option-select', $opt('completion_status', 'Completion status'))</div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Reference</label>
                        <input type="text" name="reference_no" class="form-control" value="{{ $val('reference_no', $referenceNo ?? '') }}" placeholder="Auto-generated" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">RERA ID</label>
                        <input type="text" name="rera_id" class="form-control" value="{{ $val('rera_id') }}" placeholder="e.g. RERA70613">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" id="slugInput" class="form-control" value="{{ $val('slug') }}" placeholder="Auto-generated from title">
                    </div>

                    {{-- Available: immediately, or on one or more dates (open house / viewing days). --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold d-block">Available</label>
                        <div class="property-segmented" role="radiogroup" aria-label="Available">
                            <input type="radio" class="btn-check" name="availability" id="availImmediately" value="immediately" @checked($availability !== 'from_date')>
                            <label for="availImmediately">Immediately</label>
                            <input type="radio" class="btn-check" name="availability" id="availFromDate" value="from_date" @checked($availability === 'from_date')>
                            <label for="availFromDate">From date</label>
                        </div>
                        <div id="availableDatesBox" class="property-dates-box {{ $availability === 'from_date' ? '' : 'd-none' }}">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <input type="date" id="availableDateInput" class="form-control" style="max-width: 220px;" min="{{ now()->toDateString() }}" aria-label="Add an available date">
                                <span class="form-text m-0">Pick a date to add it — add as many open house / viewing days as you need.</span>
                            </div>
                            <div id="availableDatesList" class="d-flex flex-wrap gap-2 mt-2">
                                @foreach($availableDates as $date)
                                <span class="nearby-chip" data-date="{{ $date }}">
                                    {{ \Illuminate\Support\Carbon::parse($date)->format('D, d M Y') }}
                                    <input type="hidden" name="available_dates[]" value="{{ $date }}">
                                    <button type="button" class="nearby-chip-remove" aria-label="Remove date"><i class="fas fa-times"></i></button>
                                </span>
                                @endforeach
                            </div>
                            @error('available_dates')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

            <hr class="my-4">
            <div class="small text-muted d-flex align-items-center gap-2 mb-3">
                <i class="fas fa-circle-info"></i>
                @if($isAdmin ?? false)
                    Emirate, offering type, rental period, property type, completion status, furnishing, amenities, easy access and attributes are managed in
                    <a href="{{ route('portal.crm.master.property-options.index') }}">Master › Property Options</a>.
                @else
                    Need an option that isn't listed?
                    <a href="{{ route('portal.contact.index') }}" target="_blank">Raise a ticket</a> and our team will add it.
                @endif
            </div>

            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="status" id="propertyStatus" {{ $val('status', true) ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="propertyStatus">Active (visible on site)</label>
            </div>
            @if(!$isEdit || !$property->canGoLive())
            <div class="form-text">Goes live only after MW Realty approves the listing's permit.</div>
            @endif
        </div>

        {{-- Property Details: Description, Specifications and Price in one tab --}}
        <div class="tab-pane fade" id="tab-specifications" role="tabpanel">
            <div class="property-tab-pane-head">
                <div class="property-tab-pane-title">Property Details</div>
                <div class="property-tab-pane-hint">Description, specifications and price</div>
            </div>
            {{-- Description (title, key features, description — per language) --}}
            <div class="property-subsection-title" id="section-description"><i class="fas fa-align-left"></i> Description <span>Title, key features and description, per language</span></div>
                <ul class="nav property-lang-tabs mb-4" id="langTabs" role="tablist" data-langs="{{ $languages->pluck('code')->implode(',') }}">
                    @foreach($languages as $lang)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="{{ $lang->code }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $lang->code }}-content" type="button" role="tab" data-lang="{{ $lang->code }}">
                            {{ $lang->name }}
                        </button>
                    </li>
                    @endforeach
                </ul>

                <div class="tab-content">
                    @foreach($languages as $lang)
                    @php $t = $isEdit ? ($property->translations[$lang->code] ?? []) : []; @endphp
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $lang->code }}-content" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $t['title'] ?? '') }}">
                                @error("translations.{$lang->code}.title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Key Features</label>
                                <textarea name="translations[{{ $lang->code }}][key_features]" class="form-control" rows="2" placeholder="Short highlights, one per line">{{ old("translations.{$lang->code}.key_features", $t['key_features'] ?? '') }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="translations[{{ $lang->code }}][description]" class="form-control tinymce-editor" rows="6">{{ old("translations.{$lang->code}.description", $t['description'] ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="form-text mt-3">The language picked here also sets the language of the dropdown options in the other tabs.</div>

            <div class="property-subsection-title" id="section-specifications"><i class="fas fa-ruler-combined"></i> Specifications <span>Rooms, size, furnishing, building and media links</span></div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Rooms (bedrooms) {!! $isLocked('bedrooms') ? $lockIcon : '' !!}</label>
                        <input type="number" name="bedrooms" class="form-control" value="{{ $val('bedrooms') }}" min="0" @readonly($isLocked('bedrooms'))>
                        <div class="form-text">0 = Studio</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Bathrooms</label>
                        <input type="number" name="bathrooms" class="form-control" value="{{ $val('bathrooms') }}" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Property size (sqft) {!! $isLocked('sqft') ? $lockIcon : '' !!}</label>
                        <input type="number" name="sqft" class="form-control" value="{{ $val('sqft') }}" min="0" @readonly($isLocked('sqft'))>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Developer</label>
                        <input type="text" name="developer" class="form-control" value="{{ $detailVal('developer') }}" maxlength="255" placeholder="e.g. Emaar">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Unit number</label>
                        <input type="text" name="unit_number" class="form-control" value="{{ $detailVal('unit_number') }}" maxlength="100">
                        <div class="form-text">For internal use only — never shown on the website.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">No. of parking spaces</label>
                        <input type="number" name="parking" class="form-control" value="{{ $detailVal('parking') }}" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Garage</label>
                        <input type="number" name="garage" class="form-control" value="{{ $detailVal('garage') }}" min="0">
                    </div>
                    <div class="col-md-4">@include('portal.properties._option-select', $opt(\App\Models\Filter::FURNISHING_KEY, 'Furnishing type', ['name' => 'furnished', 'current' => (string) $detailVal('furnished')]))</div>
                    <div class="col-12">
                        <label class="form-label fw-semibold mb-1">Property enhancements</label>
                        <div class="form-text mt-0 mb-2">Select the key improvements to highlight.</div>
                        <label class="property-check-card">
                            <input type="hidden" name="upgraded" value="0">
                            <input type="checkbox" class="form-check-input" name="upgraded" value="1" @checked(old('upgraded', $detailVal('upgraded')))>
                            <span><strong>Upgraded</strong><small>Renovated finishes, fixtures or appliances</small></span>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Year built</label>
                        <input type="number" name="year_built" class="form-control" value="{{ $detailVal('year_built') }}" min="1800" max="2200" placeholder="e.g. 2019">
                        <div class="form-text">The property's age is worked out from this.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Floor number</label>
                        <input type="text" name="floor" class="form-control" value="{{ $detailVal('floor') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">View</label>
                        <input type="text" name="view" class="form-control" value="{{ $detailVal('view') }}" placeholder="e.g. Sea View">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Owner name</label>
                        <input type="text" name="owner_name" class="form-control" value="{{ $detailVal('owner_name') }}" maxlength="255" placeholder="Enter owner name">
                        <div class="form-text">For internal use only — never shown on the website.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Direct from owner</label>
                        <input type="text" name="direct_from_owner" class="form-control" value="{{ $detailVal('direct_from_owner') }}" placeholder="Example: Yes, Owner Listed">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">360 URL link</label>
                        <input type="url" name="virtual_tour_url" class="form-control" value="{{ $detailVal('virtual_tour_url') }}" placeholder="https://example.com/tour">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Video tour URL</label>
                        <input type="url" name="video_tour_url" class="form-control @error('video_tour_url') is-invalid @enderror" value="{{ $detailVal('video_tour_url') }}" placeholder="https://youtube.com/...">
                        @error('video_tour_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

            <div class="property-subsection-title" id="section-price"><i class="fas fa-tag"></i> Price <span>Price, currency, cheques and deposit</span></div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Property price <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="price" class="form-control" value="{{ $val('price') }}" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                        <input type="text" name="currency" class="form-control" value="{{ $val('currency', 'AED') }}">
                    </div>
                    <div class="col-md-6 rent-only">
                        <label class="form-label fw-semibold">Number of cheques</label>
                        @php $cheques = (string) $detailVal('cheques'); @endphp
                        <select name="cheques" class="form-select">
                            <option value="">Select</option>
                            @foreach([1, 2, 3, 4, 6, 12] as $n)
                            <option value="{{ $n }}" @selected($cheques === (string) $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Security deposit</label>
                        <input type="number" step="0.01" name="security_deposit" class="form-control" value="{{ $detailVal('security_deposit') }}">
                    </div>
                </div>
        </div>

        {{-- Agent & Agency --}}
        <div class="tab-pane fade" id="tab-agent" role="tabpanel">
            <div class="property-tab-pane-head">
                <div class="property-tab-pane-title">Agent &amp; Agency</div>
                <div class="property-tab-pane-hint">Who this listing is published under</div>
            </div>

            <div class="row g-3">
                @if($lockedAgent)
                    {{-- Logged in as the agent themself — both sides are fixed, nothing to pick. --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Agent</label>
                        <input type="text" class="form-control" value="{{ $lockedAgent->name }}" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Agency</label>
                        <input type="text" class="form-control" value="{{ $lockedAgency?->displayName() ?? '—' }}" disabled>
                    </div>
                @elseif($lockedAgency)
                    {{-- Logged in as the agency — agency is fixed, pick one of our own agents. --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Agency</label>
                        <input type="text" class="form-control" value="{{ $lockedAgency->displayName() }}" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Assigned Agent <span class="text-muted fw-normal">(optional)</span></label>
                        <select name="agent_id" class="form-select @error('agent_id') is-invalid @enderror">
                            <option value="">— No agent (enquiries round-robin across your agents) —</option>
                            @foreach($agentOptions as $agent)
                            <option value="{{ $agent->id }}" {{ (string) $val('agent_id') === (string) $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                            @endforeach
                        </select>
                        @error('agent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if($agentOptions->isEmpty())
                        <div class="form-text">No active agents yet — that's fine, the listing publishes normally and enquiries come to your agency. <a href="{{ route('portal.agents.index') }}" target="_blank">Manage agents</a>.</div>
                        @endif
                    </div>
                @else
                    {{-- Super Admin — separate Agency and Agent pickers; choosing an agency narrows the agent list. --}}
                    @php $currentAgentCompanyId = $isEdit ? $property->agent?->company_id : null; @endphp
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Agency</label>
                        <select id="propertyAgencySelect" class="form-select">
                            <option value="">— All agencies —</option>
                            @foreach($agencyOptions as $agency)
                            <option value="{{ $agency->id }}" {{ (string) $currentAgentCompanyId === (string) $agency->id ? 'selected' : '' }}>{{ $agency->displayName() }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Filters the agent list below — a listing is still saved against the agent, not the agency.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Agent</label>
                        <select name="agent_id" id="propertyAgentSelect" class="form-select">
                            <option value="">— No agent assigned —</option>
                            @foreach($agentOptions as $agent)
                            <option value="{{ $agent->id }}" data-agency="{{ $agent->company_id }}" {{ (string) $val('agent_id') === (string) $agent->id ? 'selected' : '' }}>
                                {{ $agent->name }} @if($agent->company)&mdash; {{ $agent->company->displayName() }}@endif
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <script>
                        (function () {
                            var agencySelect = document.getElementById('propertyAgencySelect');
                            var agentSelect = document.getElementById('propertyAgentSelect');
                            if (!agencySelect || !agentSelect) return;

                            function applyFilter() {
                                var agencyId = agencySelect.value;
                                Array.prototype.forEach.call(agentSelect.options, function (option) {
                                    if (!option.value) return;
                                    var matches = !agencyId || option.dataset.agency === agencyId;
                                    option.hidden = !matches;
                                    if (!matches && option.selected) {
                                        agentSelect.value = '';
                                    }
                                });
                            }

                            agencySelect.addEventListener('change', applyFilter);
                            applyFilter();
                        })();
                    </script>
                @endif
            </div>
        </div>

        {{-- Location --}}
        <div class="tab-pane fade" id="tab-location" role="tabpanel">
            <div class="property-tab-pane-head">
                <div class="property-tab-pane-title">Location</div>
                <div class="property-tab-pane-hint">Area, address and map details, per language</div>
            </div>

            <ul class="nav property-lang-tabs mb-4" id="locationLangTabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="loc-{{ $lang->code }}-tab" data-bs-toggle="tab" data-bs-target="#loc-{{ $lang->code }}-content" type="button" role="tab">
                        {{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>

            <div class="tab-content">
                @foreach($languages as $lang)
                @php $t = $isEdit ? ($property->translations[$lang->code] ?? []) : []; @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="loc-{{ $lang->code }}-content" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address <span class="text-danger">*</span></label>
                            <input type="text" name="translations[{{ $lang->code }}][address]" class="form-control address-preview-field" data-lang="{{ $lang->code }}" data-part="address" value="{{ old("translations.{$lang->code}.address", $t['address'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Community</label>
                            <input type="text" name="translations[{{ $lang->code }}][community]" class="form-control address-preview-field" data-lang="{{ $lang->code }}" data-part="community" value="{{ old("translations.{$lang->code}.community", $t['community'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                            <input type="text" name="translations[{{ $lang->code }}][city]" class="form-control address-preview-field" data-lang="{{ $lang->code }}" data-part="city" value="{{ old("translations.{$lang->code}.city", $t['city'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Country <span class="text-danger">*</span></label>
                            <input type="text" name="translations[{{ $lang->code }}][country]" class="form-control address-preview-field" data-lang="{{ $lang->code }}" data-part="country" value="{{ old("translations.{$lang->code}.country", $t['country'] ?? '') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Full Address Preview</label>
                            <input type="text" class="form-control address-preview-output" data-lang="{{ $lang->code }}" readonly>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <hr class="my-4">
            <div class="property-tab-pane-title mb-3" style="font-size: 0.95rem;">Map</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Postal Code</label>
                    <input type="text" name="postal_code" class="form-control" value="{{ $val('postal_code') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Latitude <span class="text-danger">*</span></label>
                    <input type="text" name="latitude" class="form-control" value="{{ $val('latitude') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Longitude <span class="text-danger">*</span></label>
                    <input type="text" name="longitude" class="form-control" value="{{ $val('longitude') }}">
                </div>
            </div>
        </div>

        {{-- Amenities / Easy Access / Attributes: tick options from Master › Property Options --}}
        @foreach(['amenity' => ['tab-amenities', 'Amenities', 'Tick everything this property offers.'], 'easy_access' => ['tab-easy-access', 'Easy Access', 'What is close by and easy to reach.'], 'property_attribute' => ['tab-attributes', 'Attributes', 'Other notable features of this unit.']] as $listKey => [$tabId, $tabTitle, $tabHint])
        @php
            $column = \App\Models\Filter::ICON_LISTS[$listKey];
            $picked = collect(old($column, collect($detailVal($column, []))->pluck('key')->filter()->all()))->map(fn ($v) => (string) $v)->all();
            // Active options, plus any switched-off one this listing already has (so saving doesn't drop it).
            $choices = ($optionLists[$listKey]->values ?? collect())->filter(fn ($o) => $o->status || in_array($o->value, $picked, true));
        @endphp
        <div class="tab-pane fade" id="{{ $tabId }}" role="tabpanel">
            <div class="property-tab-pane-head d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div class="property-tab-pane-title">{{ $tabTitle }}</div>
                    <div class="property-tab-pane-hint">{{ $tabHint }} <span class="option-picked-count" data-list="{{ $column }}">{{ count($picked) }}</span> selected</div>
                </div>
                @if($choices->count() > 8)
                <input type="search" class="form-control form-control-sm option-search" data-list="{{ $column }}" placeholder="Search {{ strtolower($tabTitle) }}" style="max-width: 220px;">
                @endif
            </div>
            <div class="option-check-grid" data-list="{{ $column }}">
                @forelse($choices as $option)
                <label class="option-check {{ $option->status ? '' : 'is-off' }}" data-name="{{ mb_strtolower(collect($option->translations)->pluck('label')->implode(' ')) }}">
                    <input type="checkbox" name="{{ $column }}[]" value="{{ $option->value }}" @checked(in_array($option->value, $picked, true))>
                    <span class="option-check-icon">
                        @if($option->icon)<img src="{{ media_url($option->icon) }}" alt="" width="22" height="22" loading="lazy">@else<i class="fas fa-check"></i>@endif
                    </span>
                    <span class="option-check-label">
                        {{ $option->getTranslation('label') }}
                        @unless($option->status)<small class="d-block text-muted">No longer offered for new listings</small>@endunless
                    </span>
                    <i class="fas fa-circle-check option-check-tick"></i>
                </label>
                @empty
                <div class="portal-empty">
                    No {{ strtolower($tabTitle) }} options yet.
                    @if($isAdmin ?? false)<a href="{{ route('portal.crm.master.property-options.index', ['list' => $listKey]) }}" target="_blank">Add them in Master › Property Options</a>.@endif
                </div>
                @endforelse
            </div>
            @if($isAdmin ?? false)
            <div class="small text-muted mt-3"><i class="fas fa-circle-info me-1"></i>Manage this list (names and icons) in
                <a href="{{ route('portal.crm.master.property-options.index', ['list' => $listKey]) }}" target="_blank">Master › Property Options</a>.</div>
            @endif
        </div>
        @endforeach

        {{-- Floor Plans --}}
        <div class="tab-pane fade" id="tab-floorplans" role="tabpanel">
            <div class="property-tab-pane-head d-flex justify-content-between align-items-start">
                <div>
                    <div class="property-tab-pane-title">Floor Plans</div>
                    <div class="property-tab-pane-hint">Unit-type breakdown — title, size and an image per row.</div>
                </div>
                <button type="button" class="btn btn-sm portal-btn-ghost" id="addFloorPlanBtn"><i class="fas fa-plus me-1"></i>Add Row</button>
            </div>
            <div id="floorPlanRows">
                @php
                    // A brand-new property starts with a few blank rows pre-rendered so the user
                    // doesn't have to click "Add Row" just to get going — fully blank rows (no title)
                    // are silently skipped on save, so this is safe even if left untouched.
                    $existingFloorPlans = $isEdit
                        ? old('floor_plans', $property->floorPlans->toArray())
                        : old('floor_plans', array_fill(0, 3, []));
                @endphp
                @foreach(array_values($existingFloorPlans) as $i => $fp)
                    @include('portal.properties._floor_plan_row', ['i' => $i, 'fp' => $fp])
                @endforeach
            </div>
            <div class="floor-plan-empty text-muted small" id="floorPlanEmpty" @if(count($existingFloorPlans)) hidden @endif>
                <i class="fas fa-border-all me-1"></i>No floor plans yet — use <strong>Add Row</strong> to add a unit type.
            </div>
            <template id="floorPlanRowTemplate">
                @include('portal.properties._floor_plan_row', ['i' => '__INDEX__', 'fp' => []])
            </template>

            {{-- Given to website visitors only after they fill the lead form (property page › Download Floor Plan). --}}
            @php $floorPlanFile = $isEdit ? $details?->floor_plan_file : null; @endphp
            <div class="floor-plan-file mt-4">
                <div class="floor-plan-file-icon"><i class="fas fa-file-arrow-down"></i></div>
                <div class="floor-plan-file-body">
                    <div class="fw-semibold">Downloadable Floor Plan File</div>
                    <div class="small text-muted">PDF or image. Visitors download it from the property page after sharing their contact details.</div>
                    <div class="floor-plan-file-meta">
                        @if($floorPlanFile)
                        <a href="{{ media_url($floorPlanFile) }}" target="_blank" rel="noopener" class="floor-plan-file-chip" id="floorPlanFileCurrent">
                            <i class="fas fa-paperclip"></i>Current file ({{ strtoupper(pathinfo(parse_url($floorPlanFile, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'file') }})
                        </a>
                        @endif
                        <span class="floor-plan-file-chip is-new" id="floorPlanFileChosen" hidden><i class="fas fa-circle-check"></i><span></span></span>
                    </div>
                </div>
                <label class="btn btn-sm portal-btn-ghost mb-0 text-nowrap">
                    <i class="fas fa-upload me-1"></i>{{ $floorPlanFile ? 'Replace' : 'Upload' }}
                    <input type="file" name="floor_plan_file" id="floorPlanFileInput" hidden>
                </label>
            </div>
        </div>

        {{-- Images --}}
        @php $galleryImages = $isEdit ? $property->galleryImages() : []; @endphp
        <div class="tab-pane fade" id="tab-images" role="tabpanel">
            <div class="property-tab-pane-head d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div class="property-tab-pane-title">Images</div>
                    <div class="property-tab-pane-hint">Recommended: 1200x900px. Max size: 4096 KB. The first photo is used as the featured image.</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm portal-btn-ghost" id="galleryReorderBtn"><i class="fas fa-arrows-alt me-1"></i>Reorder</button>
                    <button type="button" class="btn btn-sm portal-btn-ghost" id="gallerySelectBtn"><i class="fas fa-folder-open me-1"></i>Select Images</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="galleryRemoveAllBtn" data-property="{{ $property->id ?? '' }}"><i class="fas fa-trash me-1"></i>Remove All</button>
                </div>
            </div>

            <div class="property-dropzone" id="galleryDropzone">
                <div id="galleryThumbGrid" class="gallery-thumb-grid {{ count($galleryImages) ? '' : 'd-none' }}">
                    @foreach($galleryImages as $i => $img)
                    <div class="gallery-thumb" data-number="{{ $img['number'] }}">
                        <button type="button" class="gallery-thumb-remove gallery-image-delete" data-property="{{ $property->id ?? '' }}" data-number="{{ $img['number'] }}"><i class="fas fa-times"></i></button>
                        <span class="gallery-thumb-index">{{ $i + 1 }}</span>
                        <img src="{{ $img['url'] }}" draggable="false">
                        @if($i === 0)
                        <span class="gallery-thumb-featured">FEATURED</span>
                        @endif
                    </div>
                    @endforeach
                </div>
                <div class="dz-message"><i class="fas fa-cloud-upload-alt me-1"></i> Drop gallery photos here, or click to browse</div>
            </div>
            <input type="file" name="images[]" id="galleryInput" class="d-none" multiple accept="image/*">
        </div>

        {{-- Brochure --}}
        <div class="tab-pane fade" id="tab-brochure" role="tabpanel">
            <div class="property-tab-pane-head">
                <div class="property-tab-pane-title">Property Brochure</div>
                <div class="property-tab-pane-hint">Upload a brochure (PDF, image, Word, Excel or PowerPoint) for visitors to download after submitting their contact details.</div>
            </div>
            <div class="p-3 border rounded-3">
                <label class="form-label fw-semibold" for="brochureUpload">Brochure File</label>
                <input type="file" name="brochure" id="brochureUpload" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx">
                <div class="form-text">PDF, JPG, PNG, WEBP, DOC, DOCX, XLS, XLSX, CSV, PPT or PPTX. Maximum file size: 20 MB.</div>
                @if($isEdit && $property->brochure_path)
                    <a class="small d-inline-block mt-2" href="{{ media_url($property->brochure_path) }}" target="_blank" rel="noopener">View current brochure</a>
                @endif
                @error('brochure')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Nearby Places --}}
        <div class="tab-pane fade" id="tab-nearby" role="tabpanel">
            <div class="property-tab-pane-head d-flex justify-content-between align-items-start">
                <div>
                    <div class="property-tab-pane-title">Nearby Places</div>
                    <div class="property-tab-pane-hint">Pick a type, then a specific place, and add it. Can't find it? Add your own place (opens in a new tab), then re-pick the type.</div>
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    <a href="{{ route('portal.nearby-places.create') }}" target="_blank" class="btn btn-sm portal-btn-ghost"><i class="fas fa-plus me-1"></i>New Place</a>
                    <a href="{{ route('portal.nearby-places.index') }}" target="_blank" class="btn btn-sm portal-btn-ghost">Manage</a>
                </div>
            </div>

            <div class="row g-2 align-items-end mb-3">
                <div class="col-5">
                    <label class="form-label small mb-1">Type</label>
                    <select id="nearbyTypeSelect" class="form-select form-select-sm">
                        <option value="">Select type</option>
                        @foreach($nearbyPlaceTypes?->activeValues ?? [] as $option)
                        <option value="{{ $option->value }}">{{ $option->getTranslation('label') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-5">
                    <label class="form-label small mb-1">Place</label>
                    <select id="nearbyPlaceSelect" class="form-select form-select-sm" disabled>
                        <option value="">Select type first</option>
                    </select>
                </div>
                <div class="col-2">
                    <button type="button" id="nearbyAddBtn" class="btn btn-sm btn-portal-primary w-100" disabled>Add</button>
                </div>
            </div>

            <div id="nearbyAssignedList" class="d-flex flex-wrap gap-2">
                @if($isEdit)
                @foreach($property->nearbyPlaces as $place)
                <span class="nearby-chip" data-id="{{ $place->id }}">
                    {{ $place->getTranslation('name') }}
                    <input type="hidden" name="nearby_places[]" value="{{ $place->id }}">
                    <button type="button" class="nearby-chip-remove"><i class="fas fa-times"></i></button>
                </span>
                @endforeach
                @endif
            </div>
        </div>

        {{-- Publishing --}}
        <div class="tab-pane fade" id="tab-publishing" role="tabpanel">
            <div class="property-tab-pane-head">
                <div class="property-tab-pane-title">Publishing</div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Published At</label>
                    <input type="datetime-local" name="published_at" class="form-control" value="{{ $val('published_at') ? \Illuminate\Support\Carbon::parse($val('published_at'))->format('Y-m-d\TH:i') : '' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Display Order</label>
                    <input type="number" name="order_index" class="form-control" value="{{ $val('order_index') }}" min="0">
                </div>
            </div>
            <hr class="my-4">
            <div class="property-tab-pane-title mb-1" style="font-size: 0.95rem;">SEO Metadata</div>
            <p class="text-muted small mb-3">Used on the property detail page. If empty, the page falls back to the property title and current URL.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Meta Title</label>
                    <input type="text" name="metadata[meta_title]" class="form-control" value="{{ $metaVal('meta_title') }}" placeholder="Page title for search engines">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Canonical URL</label>
                    <input type="url" name="metadata[canonical_url]" class="form-control" value="{{ $metaVal('canonical_url') }}" placeholder="https://example.com/property-details/slug">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Meta Description</label>
                    <textarea name="metadata[meta_description]" class="form-control" rows="2" placeholder="Page description">{{ $metaVal('meta_description') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Meta Keywords</label>
                    <input type="text" name="metadata[meta_keywords]" class="form-control" value="{{ $metaVal('meta_keywords') }}" placeholder="keyword1, keyword2">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">OG Title</label>
                    <input type="text" name="metadata[og_title]" class="form-control" value="{{ $metaVal('og_title') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">OG Description</label>
                    <textarea name="metadata[og_description]" class="form-control" rows="2">{{ $metaVal('og_description') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">OG Image</label>
                    <input type="file" name="metadata_og_image" class="form-control" accept="image/*">
                    @if(!empty($meta['og_image']))
                    <img src="{{ media_url($meta['og_image']) }}" class="mt-2 rounded" style="height:50px;">
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Other Meta Tags</label>
                    <textarea name="metadata[other_meta_tags]" class="form-control" rows="2" placeholder='&lt;meta name="robots" content="index,follow"&gt;'>{{ $metaVal('other_meta_tags') }}</textarea>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade portal-confirm-modal" id="galleryRemoveAllModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="btn-close portal-confirm-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body">
                <div class="portal-confirm-icon portal-confirm-icon-danger"><i class="fas fa-trash-alt"></i></div>
                <h5 class="portal-confirm-title">Remove All Gallery Photos?</h5>
                <p class="portal-confirm-text">This deletes every photo in this gallery immediately — it can't be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn portal-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn portal-confirm-btn-danger" id="galleryRemoveAllConfirmBtn">
                    <i class="fas fa-trash-alt me-1"></i>Remove All
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade portal-confirm-modal" id="galleryDeleteImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="btn-close portal-confirm-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body">
                <div class="portal-confirm-icon portal-confirm-icon-danger"><i class="fas fa-trash-alt"></i></div>
                <h5 class="portal-confirm-title">Delete This Image?</h5>
                <p class="portal-confirm-text">This photo will be removed immediately — it can't be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn portal-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn portal-confirm-btn-danger" id="galleryDeleteImageConfirmBtn">
                    <i class="fas fa-trash-alt me-1"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Sidebar and content are two independent cards (not one split box) — that's what lets the
       sidebar be only as tall as its own nav list, which is what position:sticky needs to have any
       room to move within the taller content column beside it. (A stretched-height sidebar has
       nowhere to "stick" to — its box is already exactly as tall as the scroll range allows.) */
    .property-tabs-shell { display: flex; align-items: flex-start; gap: 1.25rem; }

    .property-tabs-sidebar {
        flex: 0 0 250px;
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        padding: 1.25rem 1rem;
        background: var(--portal-bg);
        border: 1px solid var(--portal-border);
        border-radius: var(--portal-radius);
        box-shadow: var(--portal-shadow);
        /* Sticks under the portal top bar + the content-language bar (its height is published as
           --property-lang-bar-h by the script below), and scrolls itself when taller than that. */
        position: sticky;
        top: calc(var(--portal-topbar-h, 80px) + var(--property-lang-bar-h, 0px) + 1.25rem);
        max-height: calc(100vh - var(--portal-topbar-h, 80px) - var(--property-lang-bar-h, 0px) - 2.5rem);
        overflow-y: auto;
        scrollbar-width: thin;
    }
    .property-tabs-group-label { text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.08em; color: var(--portal-muted); font-weight: 800; margin: 1.1rem 0.75rem 0.4rem; }
    .property-tabs-group-label:first-child { margin-top: 0.1rem; }
    .property-tab-link {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        text-align: left;
        padding: 0.55rem 0.7rem;
        border: none;
        border-radius: 10px;
        background: transparent;
        font-weight: 700;
        font-size: 0.85rem;
        color: var(--portal-muted);
        white-space: nowrap;
        transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    }
    .property-tab-link .property-tab-icon {
        width: 26px;
        height: 26px;
        flex-shrink: 0;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.78rem;
        color: var(--portal-muted);
        background: transparent;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .property-tab-link:hover { background: var(--portal-surface); color: var(--portal-text); }
    .property-tab-link.active { background: linear-gradient(135deg, rgba(79,70,229,0.1), rgba(79,70,229,0.04)); color: var(--portal-primary); box-shadow: 0 4px 12px rgba(20, 20, 43, 0.08); }
    .property-tab-link.active .property-tab-icon { background: linear-gradient(135deg, var(--portal-primary), var(--portal-primary-dark)); color: #fff; }

    .property-tabs-content {
        flex: 1 1 auto;
        min-width: 0;
        background: var(--portal-surface);
        border: 1px solid var(--portal-border);
        border-radius: var(--portal-radius);
        box-shadow: var(--portal-shadow);
        padding: 1.75rem;
    }
    .property-tab-pane-head { margin-bottom: 1.25rem; }
    .property-tab-pane-title { font-weight: 800; font-size: 1.05rem; color: var(--portal-text); }
    .property-tab-pane-hint { font-size: 0.8rem; color: var(--portal-muted); margin-top: 0.15rem; }
    /* Sub-sections inside one tab (Property Details: Specifications / Price / Description). */
    .property-subsection-title { display: flex; align-items: baseline; flex-wrap: wrap; gap: 0.35rem 0.5rem; font-weight: 800; font-size: 0.95rem; color: var(--portal-primary); padding-bottom: 0.6rem; margin-bottom: 1rem; border-bottom: 1px solid var(--portal-border); }
    .property-subsection-title i { font-size: 0.85rem; }
    .property-subsection-title span { font-size: 0.78rem; font-weight: 500; color: var(--portal-muted); }
    #section-specifications, #section-price { margin-top: 2rem; }

    /* Content language switcher + auto-fill bar */
    .property-lang-bar { position: sticky; top: calc(var(--portal-topbar-h, 80px) + 8px); z-index: 6; display: flex; flex-wrap: wrap; align-items: center; gap: .6rem 1rem; padding: .7rem 1rem; margin-bottom: 1rem; background: var(--portal-surface); border: 1px solid var(--portal-border); border-radius: 14px; box-shadow: var(--portal-shadow); }
    .property-lang-bar__label { font-weight: 800; font-size: .85rem; color: var(--portal-text); }
    .property-lang-bar__label i { color: var(--portal-primary); margin-right: .25rem; }
    .property-lang-bar__switch { display: inline-flex; gap: .25rem; padding: .2rem; background: #f1f3f9; border-radius: 999px; }
    .property-lang-pill { display: inline-flex; align-items: center; gap: .4rem; border: 0; background: transparent; padding: .35rem .9rem; border-radius: 999px; font-weight: 700; font-size: .82rem; color: var(--portal-muted); transition: background .15s, color .15s; }
    .property-lang-pill:hover { color: var(--portal-primary); }
    .property-lang-pill.active { background: var(--portal-primary); color: #fff; box-shadow: 0 4px 10px rgba(36, 67, 115, .25); }
    .property-lang-pill__dot { width: 8px; height: 8px; border-radius: 50%; background: #c9cedb; }
    .property-lang-pill__dot.is-partial { background: #f5a623; }
    .property-lang-pill__dot.is-done { background: #2fbf71; }
    .property-lang-bar__tools { display: flex; align-items: center; gap: .75rem; margin-left: auto; }
    .property-lang-bar__tools .form-check-input:checked { background-color: var(--portal-primary); border-color: var(--portal-primary); }
    .property-lang-bar__status { flex-basis: 100%; font-size: .78rem; color: var(--portal-muted); min-height: 0; }
    .property-lang-bar__status:empty { display: none; }
    .property-lang-bar__status.is-error { color: #b02a37; }
    .property-lang-bar__status .fa-spinner { color: var(--portal-primary); }
    .is-auto-translated { background-image: linear-gradient(90deg, rgba(36, 67, 115, .05), transparent); }
    @media (max-width: 767.98px) { .property-lang-bar { position: static; } .property-lang-bar__tools { margin-left: 0; } }

    @media (max-width: 991.98px) {
        .property-tabs-shell { flex-direction: column; }
        .property-tabs-sidebar { flex-direction: row; flex-wrap: nowrap; overflow-x: auto; width: 100%; padding: 0.75rem; position: sticky; top: 0; z-index: 2; max-height: none; overflow-y: hidden; }
        .property-tabs-group-label { display: none; }
        .property-tab-link { flex: 0 0 auto; }
        .property-tabs-content { padding: 1.5rem; }
    }

    .property-lang-tabs { gap: 0.5rem; background: var(--portal-bg); padding: 0.35rem; border-radius: 50px; display: inline-flex; }
    .property-lang-tabs .nav-link { border-radius: 50px; padding: 0.4rem 1.1rem; font-weight: 700; font-size: 0.85rem; color: var(--portal-muted); border: none; }
    .property-lang-tabs .nav-link.active { background: var(--portal-primary); color: #fff; }

    /* Basic tab sections (Core details / Specifications / Price / Description) */
    .property-form-section { border: 1px solid var(--portal-border); border-radius: 14px; padding: 1.25rem 1.25rem 1.4rem; margin-bottom: 1.25rem; background: var(--portal-surface); }
    .property-form-section-title { display: flex; align-items: center; gap: 0.55rem; font-weight: 700; font-size: 0.98rem; color: var(--portal-text); margin-bottom: 1rem; }
    .property-form-section-title i { color: var(--portal-primary); font-size: 0.9rem; }
    .property-segmented { display: inline-flex; width: 100%; max-width: 520px; border: 1.5px solid var(--portal-border); border-radius: 10px; padding: 3px; background: var(--portal-bg); }
    .property-segmented label { flex: 1; text-align: center; padding: 0.5rem 0.75rem; border-radius: 8px; font-weight: 600; font-size: 0.88rem; color: var(--portal-muted); cursor: pointer; transition: background 0.15s ease, color 0.15s ease; }
    .property-segmented .btn-check:checked + label { background: var(--portal-surface); color: var(--portal-primary); box-shadow: 0 0 0 1.5px var(--portal-primary); }
    .property-segmented .btn-check:focus-visible + label { outline: 2px solid var(--portal-primary); outline-offset: 2px; }
    .property-segmented--wide { max-width: none; }

    /* Core details: big icon buttons for Category / Offering type */
    .option-buttons { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; }
    .option-button { display: flex; align-items: center; justify-content: center; gap: 0.55rem; padding: 0.85rem 1rem; border: 1.5px solid var(--portal-border); border-radius: 12px; background: var(--portal-bg); color: var(--portal-text); font-weight: 600; font-size: 0.9rem; cursor: pointer; margin: 0; transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease; }
    .option-button i { color: var(--portal-primary); font-size: 1rem; }
    .option-button:hover { border-color: rgba(79, 70, 229, 0.45); }
    .btn-check:checked + .option-button { border-color: var(--portal-primary); background: var(--portal-surface); box-shadow: 0 0 0 1px var(--portal-primary); color: var(--portal-primary); }
    .btn-check:focus-visible + .option-button { outline: 2px solid var(--portal-primary); outline-offset: 2px; }
    .btn-check:disabled + .option-button { opacity: 0.55; cursor: not-allowed; }
    .btn-check:disabled:checked + .option-button { opacity: 0.85; background: var(--portal-bg); }

    /* Permit (Core details) */
    .permit-validate-btn { min-width: 110px; height: calc(2.6rem + 3px); font-weight: 700; color: var(--portal-primary); border: 1.5px solid var(--portal-primary) !important; border-radius: 10px; }
    .permit-validate-btn:disabled { color: var(--portal-muted); border-color: var(--portal-border) !important; }
    .permit-status { display: flex; align-items: center; gap: 0.8rem; margin-top: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; background: var(--portal-bg); font-size: 0.85rem; }
    .permit-status strong { display: block; color: var(--portal-text); }
    .permit-status span:not(.permit-status-icon) { color: var(--portal-muted); }
    .permit-status-icon { flex: 0 0 32px; height: 32px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; color: var(--portal-muted); }
    .permit-status.is-idle .permit-status-icon i::before { content: "\f062"; }
    .permit-status.is-checking .permit-status-icon i::before { content: "\f110"; }
    .permit-status.is-checking .permit-status-icon i { animation: fa-spin 1s linear infinite; }
    .permit-status.is-verified { background: #dcfce7; }
    .permit-status.is-verified .permit-status-icon { color: #15803d; }
    .permit-status.is-verified .permit-status-icon i::before { content: "\f00c"; }
    .permit-status.is-verified strong { color: #166534; }
    .permit-status.is-pending { background: #fef9c3; }
    .permit-status.is-pending .permit-status-icon { color: #a16207; }
    .permit-status.is-pending .permit-status-icon i::before { content: "\f017"; }
    .permit-status.is-error { background: #fee2e2; }
    .permit-status.is-error .permit-status-icon { color: #b91c1c; }
    .permit-status.is-error .permit-status-icon i::before { content: "\f00d"; }
    .property-dates-box { margin-top: 0.75rem; padding: 0.85rem; border: 1px dashed var(--portal-border); border-radius: 12px; }
    .property-check-card { display: inline-flex; align-items: flex-start; gap: 0.7rem; padding: 0.8rem 1rem; border: 1.5px solid var(--portal-border); border-radius: 12px; cursor: pointer; min-width: 280px; }
    .property-check-card:has(input:checked) { border-color: var(--portal-primary); background: rgba(79, 70, 229, 0.04); }
    .property-check-card .form-check-input { margin-top: 0.2rem; }
    .property-check-card strong { display: block; font-size: 0.9rem; }
    .property-check-card small { color: var(--portal-muted); }

    /* Amenities / Easy Access / Attributes checkbox grid */
    .option-check-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.6rem; }
    .option-check { position: relative; display: flex; align-items: center; gap: 0.7rem; padding: 0.7rem 2.2rem 0.7rem 0.75rem; border: 1.5px solid var(--portal-border); border-radius: 12px; cursor: pointer; background: var(--portal-surface); transition: border-color 0.15s ease, background 0.15s ease; }
    .option-check:hover { border-color: rgba(79, 70, 229, 0.45); }
    .option-check input { position: absolute; opacity: 0; pointer-events: none; }
    .option-check:has(input:checked) { border-color: var(--portal-primary); background: rgba(79, 70, 229, 0.05); }
    .option-check:has(input:focus-visible) { outline: 2px solid var(--portal-primary); outline-offset: 2px; }
    .option-check.is-off { opacity: 0.7; }
    .option-check-icon { flex: 0 0 36px; height: 36px; border-radius: 10px; background: var(--portal-bg); display: flex; align-items: center; justify-content: center; color: var(--portal-muted); }
    .option-check-icon img { width: 22px; height: 22px; object-fit: contain; }
    .option-check-label { font-weight: 600; font-size: 0.86rem; color: var(--portal-text); line-height: 1.25; }
    .option-check-tick { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--portal-border); font-size: 1.05rem; transition: color 0.15s ease; }
    .option-check:has(input:checked) .option-check-tick { color: var(--portal-primary); }

    /* Floor plan rows: image tile · Title / Sqft From / Sqft To · remove, all on one aligned line. */
    .floor-plan-card { display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1rem; margin-bottom: 0.75rem; background: var(--portal-surface); border: 1px solid var(--portal-border); border-radius: 14px; transition: border-color 0.15s ease, box-shadow 0.15s ease; }
    .floor-plan-card:hover, .floor-plan-card:focus-within { border-color: #c9d3e6; box-shadow: var(--portal-shadow); }
    .floor-plan-thumb { position: relative; flex: 0 0 76px; width: 76px; height: 76px; margin: 0; border: 2px dashed var(--portal-border); border-radius: 12px; background: var(--portal-bg); overflow: hidden; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: border-color 0.15s ease; }
    .floor-plan-thumb:hover { border-color: var(--portal-primary); }
    .floor-plan-thumb.has-image { border-style: solid; background: #fff; }
    .floor-plan-thumb input[type="file"] { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
    .floor-plan-thumb img { width: 100%; height: 100%; object-fit: contain; padding: 4px; }
    .floor-plan-thumb-empty { display: flex; flex-direction: column; align-items: center; gap: 0.2rem; color: var(--portal-muted); font-size: 0.68rem; font-weight: 600; }
    .floor-plan-thumb-empty i { font-size: 1.1rem; }
    .floor-plan-thumb.has-image .floor-plan-thumb-empty { display: none; }
    .floor-plan-thumb-edit { position: absolute; inset: auto 4px 4px auto; width: 22px; height: 22px; border-radius: 50%; background: var(--portal-primary); color: #fff; font-size: 0.62rem; display: none; align-items: center; justify-content: center; }
    .floor-plan-thumb.has-image .floor-plan-thumb-edit { display: flex; }
    .floor-plan-fields { flex: 1 1 auto; min-width: 0; display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr); gap: 0.75rem; }
    .floor-plan-fields .form-label { color: var(--portal-muted); font-weight: 600; }
    .floor-plan-remove { flex: 0 0 38px; width: 38px; height: 38px; align-self: center; border: 1px solid #f3c9d1; border-radius: 10px; background: #fff5f7; color: var(--portal-accent); display: inline-flex; align-items: center; justify-content: center; transition: background 0.15s ease, color 0.15s ease; }
    .floor-plan-remove:hover { background: var(--portal-accent); border-color: var(--portal-accent); color: #fff; }
    .floor-plan-empty { padding: 1.25rem; border: 2px dashed var(--portal-border); border-radius: 14px; text-align: center; }
    .floor-plan-file { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.1rem; border: 1px solid var(--portal-border); border-radius: 14px; background: var(--portal-bg); }
    .floor-plan-file-icon { flex: 0 0 46px; width: 46px; height: 46px; border-radius: 12px; background: #fff; color: var(--portal-primary); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; box-shadow: 0 2px 8px rgba(36, 67, 115, 0.08); }
    .floor-plan-file-body { flex: 1 1 auto; min-width: 0; }
    .floor-plan-file-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.45rem; }
    .floor-plan-file-meta:empty { display: none; }
    .floor-plan-file-chip { display: inline-flex; align-items: center; gap: 0.4rem; max-width: 100%; padding: 0.25rem 0.65rem; border-radius: 999px; background: #fff; border: 1px solid var(--portal-border); color: var(--portal-primary); font-size: 0.78rem; font-weight: 600; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .floor-plan-file-chip.is-new { color: #18794e; border-color: #bfe5cf; background: #effaf3; }
    @media (max-width: 767.98px) {
        .floor-plan-card { flex-wrap: wrap; }
        .floor-plan-fields { order: 3; flex-basis: 100%; grid-template-columns: 1fr 1fr; }
        .floor-plan-field--title { grid-column: 1 / -1; }
        .floor-plan-remove { margin-left: auto; }
        .floor-plan-file { flex-wrap: wrap; }
    }

    .property-dropzone { border: 2px dashed var(--portal-border); border-radius: 12px; background: var(--portal-bg); min-height: 120px; cursor: pointer; }
    .property-dropzone .dz-message { font-size: 0.85rem; color: var(--portal-muted); font-weight: 600; padding: 1.5rem; text-align: center; }
    .property-dropzone .dz-preview .dz-image { border-radius: 10px; }
    .property-existing-thumb { display: flex; align-items: center; gap: 0.6rem; }
    .property-existing-thumb img { height: 56px; border-radius: 8px; }

    .gallery-thumb-grid { display: flex; flex-wrap: wrap; gap: 0.75rem; padding: 1rem 1rem 0; border-radius: 12px; border: 2px dashed transparent; cursor: default; transition: border-color 0.15s ease, background 0.15s ease; }
    .gallery-thumb-grid.gallery-reorder-active { border-color: var(--portal-primary); background: rgba(79, 70, 229, 0.04); }
    .gallery-thumb-grid:not(.d-none) + .dz-message { padding-top: 0.75rem; }
    .gallery-thumb { position: relative; width: 96px; height: 96px; border-radius: 10px; overflow: hidden; border: 1px solid var(--portal-border); flex-shrink: 0; }
    .gallery-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; -webkit-user-drag: none; user-select: none; }
    .gallery-thumb-remove { position: absolute; top: 4px; left: 4px; width: 20px; height: 20px; border-radius: 50%; border: none; background: #e11d48; color: #fff; font-size: 0.6rem; display: flex; align-items: center; justify-content: center; line-height: 1; z-index: 2; }
    .gallery-thumb-remove:hover { background: #be123c; }
    .gallery-thumb-index { position: absolute; top: 4px; right: 4px; min-width: 20px; height: 20px; padding: 0 0.3rem; border-radius: 50%; background: rgba(20, 20, 43, 0.65); color: #fff; font-size: 0.68rem; font-weight: 700; display: flex; align-items: center; justify-content: center; z-index: 2; }
    .gallery-thumb-featured { position: absolute; left: 0; right: 0; bottom: 0; background: var(--portal-primary); color: #fff; font-size: 0.6rem; font-weight: 800; letter-spacing: 0.04em; text-align: center; padding: 0.15rem 0; }
    #galleryReorderBtn.active { background: var(--portal-primary); color: #fff; border-color: var(--portal-primary); }
    .gallery-reorder-active .gallery-thumb { cursor: grab; }
    .gallery-reorder-active .gallery-thumb img { outline: 2px dashed var(--portal-primary); outline-offset: -2px; }
    .gallery-thumb.is-dragging { opacity: 0.4; }

    /* Attractive portal-branded confirm popup — replaces both the plain Bootstrap default look and
       the native browser confirm() for destructive gallery actions (Remove All / Delete Image). */
    .portal-confirm-modal .modal-content { border: none; border-radius: 20px; box-shadow: 0 24px 60px rgba(31, 35, 64, 0.22); overflow: hidden; }
    .portal-confirm-modal .modal-body { text-align: center; padding: 2.25rem 2rem 0.5rem; }
    .portal-confirm-close { position: absolute; top: 1rem; right: 1rem; z-index: 2; opacity: 0.5; }
    .portal-confirm-close:hover { opacity: 0.9; }
    .portal-confirm-icon { width: 64px; height: 64px; margin: 0 auto 1.1rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .portal-confirm-icon-danger { background: linear-gradient(135deg, #fde2e2, #fbc7c9); color: #e11d48; box-shadow: 0 8px 20px rgba(225, 29, 72, 0.2); }
    .portal-confirm-title { font-weight: 800; font-size: 1.15rem; color: var(--portal-text); margin-bottom: 0.5rem; }
    .portal-confirm-text { color: var(--portal-muted); font-size: 0.9rem; line-height: 1.5; max-width: 340px; margin: 0 auto; }
    .portal-confirm-modal .modal-footer { border-top: none; justify-content: center; gap: 0.6rem; padding: 1.5rem 2rem 2rem; }
    .portal-confirm-modal .modal-footer .btn { flex: 1; max-width: 160px; border-radius: 12px; font-weight: 700; padding: 0.65rem 1rem; }
    .portal-confirm-btn-danger { background: linear-gradient(135deg, #e11d48, #be123c); color: #fff; border: none; box-shadow: 0 10px 20px rgba(225, 29, 72, 0.28); transition: transform 0.15s ease, box-shadow 0.15s ease; }
    .portal-confirm-btn-danger:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 14px 24px rgba(225, 29, 72, 0.34); }

    .nearby-chip { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--portal-bg); border: 1px solid var(--portal-border); border-radius: 50px; padding: 0.35rem 0.5rem 0.35rem 0.9rem; font-size: 0.82rem; font-weight: 600; }
    .nearby-chip-remove { border: none; background: none; color: var(--portal-muted); width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; }
    .nearby-chip-remove:hover { background: #fde2e2; color: #dc3545; }

    .property-validation-banner { margin-bottom: 1.25rem; font-weight: 600; border-radius: 10px; }
    .property-tabs-content .is-invalid { border-color: #dc3545 !important; box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.12) !important; }

    /* --- Attractive input styling (applies within this form only) --- */
    .property-tabs-content .form-label { font-size: 0.85rem; color: var(--portal-text); margin-bottom: 0.4rem; }
    .property-tabs-content .form-control,
    .property-tabs-content .form-select,
    .property-tabs-content textarea.form-control {
        border: 1.5px solid var(--portal-border);
        border-radius: 10px;
        padding: 0.6rem 0.85rem;
        font-size: 0.9rem;
        background: var(--portal-surface);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .property-tabs-content .form-control:focus,
    .property-tabs-content .form-select:focus,
    .property-tabs-content textarea.form-control:focus {
        border-color: var(--portal-primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
    }
    .property-tabs-content .form-control::placeholder { color: #a8abc4; }
    .property-tabs-content .form-control[type="file"] { padding: 0.45rem 0.7rem; }
    .property-tabs-content .form-check-input { width: 2.4em; height: 1.3em; cursor: pointer; }
    .property-tabs-content .form-check-input:checked { background-color: var(--portal-primary); border-color: var(--portal-primary); }

    /* Select2 themed to match the inputs above, with a bilingual-friendly (native script + latin)
       row height and a search box that feels like part of the same design system. */
    .property-tabs-content .select2-container--default .select2-selection--single {
        height: auto;
        border: 1.5px solid var(--portal-border);
        border-radius: 10px;
        padding: 0.6rem 0.85rem;
        background: var(--portal-surface);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .property-tabs-content .select2-container--default.select2-container--open .select2-selection--single,
    .property-tabs-content .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: var(--portal-primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
    }
    .property-tabs-content .select2-selection__rendered { line-height: 1.3 !important; font-size: 0.9rem; color: var(--portal-text) !important; padding: 0 !important; }
    .property-tabs-content .select2-selection__placeholder { color: #a8abc4 !important; }
    .property-tabs-content .select2-selection__arrow { height: 100% !important; top: 0 !important; right: 10px !important; }
    .property-tabs-content .select2-selection__clear { margin-right: 6px; }
    .select2-dropdown { border-radius: 12px !important; border: 1px solid var(--portal-border) !important; box-shadow: 0 12px 28px rgba(20, 20, 43, 0.12); overflow: hidden; }
    .select2-search--dropdown { padding: 0.6rem !important; }
    .select2-search--dropdown .select2-search__field { border-radius: 8px !important; border: 1.5px solid var(--portal-border) !important; padding: 0.45rem 0.65rem !important; outline: none; }
    .select2-results__option { padding: 0.55rem 0.9rem !important; font-size: 0.88rem; }
    .select2-container--default .select2-results__option--highlighted[aria-selected] { background: var(--portal-primary) !important; color: #fff !important; }
    .select2-container--default .select2-results__option--highlighted[aria-selected] .select2-bilingual-secondary { color: rgba(255,255,255,0.75) !important; }
    .select2-container--default .select2-results__option[aria-selected="true"] { background: rgba(79, 70, 229, 0.08); }
    .select2-bilingual-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
    .select2-bilingual-primary { font-weight: 600; }
    .select2-bilingual-secondary { color: var(--portal-muted); font-size: 0.82rem; direction: ltr; }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
<script>
Dropzone.autoDiscover = false;

document.addEventListener('DOMContentLoaded', function () {
    // Description (per language) — TinyMCE needs a moment after the language tab actually
    // becomes visible to size itself correctly, so it's initialized once up front here rather
    // than lazily per tab; hidden tab-panes still render it fine on modern TinyMCE versions.
    if (window.tinymce) {
        tinymce.init({
            selector: '.tinymce-editor',
            height: 260,
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
            toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link | removeformat | code | help',
            // Lets the language auto-fill below react to Description edits.
            setup: function (editor) {
                editor.on('input change undo redo', function () {
                    document.dispatchEvent(new CustomEvent('property-editor-change', { detail: editor }));
                });
            },
        });
    }

    const form = document.getElementById('propertyForm');
    if (!form) return;

    // --- Slug: auto-fills live from the fallback-language Title as you type, mirroring the
    // controller's own server-side fallback — stops once the user edits Slug by hand. ---
    (function () {
        const slugInput = document.getElementById('slugInput');
        const fallbackLangCode = (document.getElementById('langTabs')?.dataset.langs || 'en').split(',')[0];
        const titleInput = document.querySelector(`input[name="translations[${fallbackLangCode}][title]"]`);
        if (!slugInput || !titleInput) return;

        let slugTouched = !!slugInput.value.trim();
        slugInput.addEventListener('input', function () { slugTouched = true; });

        function slugify(text) {
            return text.toString().trim().toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
        titleInput.addEventListener('input', function () {
            if (slugTouched) return;
            slugInput.value = slugify(titleInput.value);
        });
    })();

    // --- Property Type / Listing Type / Category / Completion Status: searchable Select2 pickers
    // whose option text follows the active Basic-tab language (English/Arabic), using each
    // option's per-language label (static-texts JSON for the fixed value sets, FilterValue's own
    // translation for admin-configurable ones — see the labelFor() closure in the Blade above).
    // Dropdown rows show every configured language at once (active language first, the rest muted
    // on the right) so the other language is always visible while picking, not just the active one. ---
    function capitalizeLang(lang) { return lang.charAt(0).toUpperCase() + lang.slice(1); }
    const allLangCodes = (document.getElementById('langTabs')?.dataset.langs || 'en').split(',');
    let currentFormLang = document.querySelector('#langTabs [data-lang].active')?.dataset.lang || allLangCodes[0];

    function formatBilingualOption(state) {
        if (!state.id || !state.element) return state.text;
        const el = state.element;
        const primary = el.dataset['label' + capitalizeLang(currentFormLang)] || state.text;
        const secondary = allLangCodes
            .filter(function (code) { return code !== currentFormLang; })
            .map(function (code) { return el.dataset['label' + capitalizeLang(code)]; })
            .filter(Boolean)
            .join(' / ');
        const $row = $('<div class="select2-bilingual-row"></div>');
        $row.append($('<span class="select2-bilingual-primary"></span>').text(primary));
        if (secondary) $row.append($('<span class="select2-bilingual-secondary"></span>').text(secondary));
        return $row;
    }

    function initLangAwareSelects() {
        $('.lang-aware-select').each(function () {
            const $el = $(this);
            if ($el.data('select2')) $el.select2('destroy');
            $el.select2({
                width: '100%',
                placeholder: $el.data('placeholder') || 'Search',
                allowClear: true,
                templateResult: formatBilingualOption,
                escapeMarkup: function (m) { return m; },
            });
        });
    }
    function applyLangAwareSelectOptions(lang) {
        currentFormLang = lang;
        document.querySelectorAll('.lang-aware-select').forEach(function (select) {
            Array.from(select.options).forEach(function (opt) {
                const label = opt.dataset['label' + capitalizeLang(lang)];
                if (label) opt.textContent = label;
            });
        });
        document.querySelectorAll('.lang-aware-label').forEach(function (el) {
            const label = el.dataset['label' + capitalizeLang(lang)];
            if (label) el.querySelector('span').textContent = label;
        });
        initLangAwareSelects();
    }
    initLangAwareSelects();
    document.querySelectorAll('#langTabs [data-lang]').forEach(function (tabBtn) {
        tabBtn.addEventListener('shown.bs.tab', function () { applyLangAwareSelectOptions(tabBtn.dataset.lang); });
    });
    const initialLangTab = document.querySelector('#langTabs [data-lang].active');
    if (initialLangTab) applyLangAwareSelectOptions(initialLangTab.dataset.lang);

    // --- Content language switcher + auto-fill. The bar's pills switch the Description and
    // Location language tabs together. Text typed in the source language (first one) is translated
    // into the others (PropertyTranslateController → Google Translate). A target field is only
    // filled while it's empty or still holds the last auto-filled text — once someone edits it
    // by hand it's theirs and is never overwritten. ---
    (function () {
        const bar = document.getElementById('propertyLangBar');
        if (!bar) return;

        // Publish the bar's height (+ its gap) so the sticky section menu sits below it, not under it.
        const publishBarHeight = function () {
            const sticky = getComputedStyle(bar).position === 'sticky';
            document.documentElement.style.setProperty('--property-lang-bar-h', sticky ? (bar.offsetHeight + 8) + 'px' : '0px');
        };
        publishBarHeight();
        if (window.ResizeObserver) new ResizeObserver(publishBarHeight).observe(bar);
        window.addEventListener('resize', publishBarHeight);

        const source = bar.dataset.source;
        const targets = allLangCodes.filter(function (c) { return c !== source; });
        const FIELDS = ['title', 'key_features', 'description', 'address', 'community', 'city', 'country'];
        const REQUIRED = ['title', 'address', 'city', 'country'];
        const pills = Array.from(bar.querySelectorAll('.property-lang-pill'));
        const statusEl = document.getElementById('translateStatus');
        const toggle = document.getElementById('autoTranslateToggle');
        const configured = bar.dataset.configured === '1';
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        const fieldEl = function (lang, f) { return form.querySelector('[name="translations[' + lang + '][' + f + ']"]'); };
        const editorOf = function (el) { return el && window.tinymce && el.id ? tinymce.get(el.id) : null; };
        const getVal = function (el) { const ed = editorOf(el); return (ed ? ed.getContent() : el.value).trim(); };
        function setVal(el, value) {
            const ed = editorOf(el);
            if (ed) { ed.setContent(value); ed.save(); el.dataset.autoValue = ed.getContent().trim(); }
            else { el.value = value; el.dataset.autoValue = value.trim(); el.dispatchEvent(new Event('input', { bubbles: true })); }
            (ed ? ed.getContainer() : el).classList.add('is-auto-translated');
        }
        // Still "linked" to the source: empty, or unchanged since we last filled it.
        const isLinked = function (el) { if (el.readOnly || el.disabled) return false; const v = getVal(el); return v === '' || (el.dataset.autoValue !== undefined && v === el.dataset.autoValue); };

        function setStatus(html, isError) {
            statusEl.innerHTML = html || '';
            statusEl.classList.toggle('is-error', !!isError);
        }

        // --- switching ---
        function switchLang(code) {
            pills.forEach(function (p) { const on = p.dataset.lang === code; p.classList.toggle('active', on); p.setAttribute('aria-selected', on); });
            [document.querySelector('#langTabs [data-lang="' + code + '"]'), document.getElementById('loc-' + code + '-tab')].forEach(function (btn) {
                if (btn && !btn.classList.contains('active')) bootstrap.Tab.getOrCreateInstance(btn).show();
            });
        }
        pills.forEach(function (p) { p.addEventListener('click', function () { switchLang(p.dataset.lang); }); });
        document.querySelectorAll('#langTabs [data-lang]').forEach(function (btn) {
            btn.addEventListener('shown.bs.tab', function () { switchLang(btn.dataset.lang); });
        });
        allLangCodes.forEach(function (code) {
            document.getElementById('loc-' + code + '-tab')?.addEventListener('shown.bs.tab', function () { switchLang(code); });
        });

        // --- per-language completeness dots ---
        function refreshDots() {
            allLangCodes.forEach(function (code) {
                const dot = bar.querySelector('[data-state-for="' + code + '"]');
                if (!dot) return;
                const filled = REQUIRED.filter(function (f) { const el = fieldEl(code, f); return el && getVal(el) !== ''; }).length;
                dot.classList.toggle('is-done', filled === REQUIRED.length);
                dot.classList.toggle('is-partial', filled > 0 && filled < REQUIRED.length);
                dot.title = filled === REQUIRED.length ? 'Complete' : filled ? 'Partly filled' : 'Empty';
            });
        }

        // --- translating ---
        let timer = null;
        let inFlight = false;
        let queued = false;

        async function translate(force) {
            if (!configured) { setStatus('<i class="fas fa-circle-info me-1"></i>Auto-translate isn\'t set up yet — type each language by hand (or ask the site admin to add a Google Translate key).', true); return; }
            if (inFlight) { queued = true; return; }

            // Per target: the source fields whose target is still linked (or everything, when forced).
            const sourceValues = {};
            FIELDS.forEach(function (f) { const el = fieldEl(source, f); if (el && getVal(el) !== '') sourceValues[f] = getVal(el); });
            const wanted = {};
            targets.forEach(function (t) {
                Object.keys(sourceValues).forEach(function (f) {
                    const el = fieldEl(t, f);
                    if (el && (force || isLinked(el))) (wanted[f] = wanted[f] || []).push(t);
                });
            });
            const fields = {};
            Object.keys(wanted).forEach(function (f) { fields[f] = sourceValues[f]; });
            if (!Object.keys(fields).length) { if (force) setStatus('<i class="fas fa-circle-check text-success me-1"></i>Nothing to translate yet — fill in the ' + bar.dataset.sourceName + ' fields first.'); return; }

            inFlight = true;
            setStatus('<i class="fas fa-spinner fa-spin me-1"></i>Translating from ' + bar.dataset.sourceName + '…');
            try {
                const res = await fetch(bar.dataset.translateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ source: source, targets: targets, fields: fields }),
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok) throw new Error(data.message || 'Translation failed — please try again.');

                let count = 0;
                Object.keys(wanted).forEach(function (f) {
                    wanted[f].forEach(function (t) {
                        const el = fieldEl(t, f);
                        const value = data.translations?.[t]?.[f];
                        // Skip if the user typed into it while the request was running.
                        if (el && value && (force || isLinked(el))) { setVal(el, value); count++; }
                    });
                });
                refreshDots();
                const names = targets.map(function (t) { return pills.find(function (p) { return p.dataset.lang === t; })?.textContent.trim(); }).join(', ');
                setStatus(count ? '<i class="fas fa-circle-check text-success me-1"></i>Auto-filled ' + names + ' from ' + bar.dataset.sourceName + '. You can edit any field by hand — edited fields won\'t be overwritten.' : '');
            } catch (e) {
                setStatus('<i class="fas fa-triangle-exclamation me-1"></i>' + e.message, true);
            } finally {
                inFlight = false;
                if (queued) { queued = false; schedule(); }
            }
        }

        function schedule() {
            refreshDots();
            if (!toggle.checked) return;
            clearTimeout(timer);
            timer = setTimeout(function () { translate(false); }, 1200);
        }

        FIELDS.forEach(function (f) {
            const el = fieldEl(source, f);
            if (el && !el.classList.contains('tinymce-editor')) el.addEventListener('input', schedule);
        });
        document.addEventListener('property-editor-change', function (e) {
            const el = e.detail.getElement();
            if (el.name === 'translations[' + source + '][description]') schedule();
            else refreshDots();
        });
        targets.forEach(function (t) {
            FIELDS.forEach(function (f) {
                const el = fieldEl(t, f);
                if (el) el.addEventListener('input', function (ev) {
                    if (ev.isTrusted) el.classList.remove('is-auto-translated');
                    refreshDots();
                });
            });
        });

        document.getElementById('translateNowBtn').addEventListener('click', function () {
            const anyManual = targets.some(function (t) {
                return FIELDS.some(function (f) { const el = fieldEl(t, f), src = fieldEl(source, f); return el && src && getVal(src) !== '' && !isLinked(el); });
            });
            const force = anyManual && confirm('Some translated fields were edited by hand. Overwrite them with a fresh translation?\n\nOK = overwrite everything · Cancel = only fill the untouched fields');
            translate(force);
        });
        toggle.addEventListener('change', function () { if (toggle.checked) translate(false); });
        if (!configured) {
            toggle.checked = false;
            toggle.disabled = true;
            setStatus('<i class="fas fa-circle-info me-1"></i>Auto-translate isn\'t set up yet — type each language by hand (or ask the site admin to add a Google Translate key).');
        }
        refreshDots();
    })();

    // --- Dropzone-powered pickers feeding the real hidden <input type="file"> the form already
    // submits normally with — no separate upload endpoint needed, this is purely a nicer picker UI.
    function syncHiddenInput(inputEl, files) {
        const dt = new DataTransfer();
        files.forEach(function (f) { if (f instanceof File) dt.items.add(f); });
        inputEl.files = dt.files;
    }

    function makePickerDropzone(elId, inputId, options) {
        const el = document.getElementById(elId);
        const input = document.getElementById(inputId);
        if (!el || !input) return null;

        const dz = new Dropzone('#' + elId, Object.assign({
            url: '#',
            autoProcessQueue: false,
            addRemoveLinks: true,
            dictRemoveFile: 'Remove',
            acceptedFiles: 'image/*',
        }, options));

        dz.on('addedfile', function () { syncHiddenInput(input, dz.files); });
        dz.on('removedfile', function () { syncHiddenInput(input, dz.files); });

        return dz;
    }

    // Message markup lives in the Blade template itself (a .dz-message element inside the
    // target div) rather than dictDefaultMessage — Dropzone's own auto-generated message
    // didn't render reliably here, but providing it ourselves is the documented fallback.
    // previewsContainer: false suppresses Dropzone's own (plain filename/size list) preview UI
    // entirely — freshly dropped photos get the same numbered-thumbnail treatment as already-saved
    // ones below instead, via the addedfile/removedfile handlers right after this.
    // clickable is scoped to just the "Drop gallery photos here" message (not the whole box) since
    // the thumbnail grid now lives inside this same dashed box — otherwise clicking a thumbnail's
    // remove button would also bubble up and re-open the file picker.
    const galleryDz = makePickerDropzone('galleryDropzone', 'galleryInput', {
        maxFiles: 30,
        previewsContainer: false,
        clickable: '#galleryDropzone .dz-message',
    });

    // "Select Images" just opens the same picker Dropzone already wires up on click.
    document.getElementById('gallerySelectBtn')?.addEventListener('click', function () {
        document.querySelector('#galleryDropzone .dz-message')?.click();
    });

    // Newly staged (not-yet-uploaded) photos get rendered into the same #galleryThumbGrid, with the
    // same numbered-badge look as already-saved ones — just marked data-pending so their × button
    // pulls them out of the Dropzone queue instead of hitting the delete API.
    (function () {
        const galleryThumbGridEl = document.getElementById('galleryThumbGrid');
        if (!galleryThumbGridEl || !galleryDz) return;

        galleryDz.on('addedfile', function (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const thumb = document.createElement('div');
                thumb.className = 'gallery-thumb';
                thumb.dataset.pending = 'true';
                thumb.innerHTML = `
                    <button type="button" class="gallery-thumb-remove gallery-pending-remove"><i class="fas fa-times"></i></button>
                    <span class="gallery-thumb-index"></span>
                    <img src="${e.target.result}" draggable="false">
                `;
                thumb._dzFile = file;
                file._thumbEl = thumb;
                galleryThumbGridEl.appendChild(thumb);
                galleryThumbGridEl.classList.remove('d-none');
                renumberGalleryThumbs();
            };
            reader.readAsDataURL(file);
        });

        galleryDz.on('removedfile', function (file) {
            file._thumbEl?.remove();
            renumberGalleryThumbs();
            if (!galleryThumbGridEl.querySelector('.gallery-thumb')) galleryThumbGridEl.classList.add('d-none');
        });

        galleryThumbGridEl.addEventListener('click', function (e) {
            const btn = e.target.closest('.gallery-pending-remove');
            if (!btn) return;
            const file = btn.closest('.gallery-thumb')?._dzFile;
            if (file) galleryDz.removeFile(file);
        });
    })();

    // "Remove All": clears anything staged-but-not-yet-uploaded, and for an existing property also
    // deletes every already-saved gallery image via a single request instead of one-by-one. Works on
    // the create page too, where there's no property yet and only staged files need clearing.
    // Confirmation is a styled modal (matching the rest of the portal) instead of the native browser
    // confirm() popup.
    const galleryRemoveAllModalEl = document.getElementById('galleryRemoveAllModal');
    const galleryRemoveAllModal = galleryRemoveAllModalEl ? new bootstrap.Modal(galleryRemoveAllModalEl) : null;
    document.getElementById('galleryRemoveAllBtn')?.addEventListener('click', function () {
        galleryRemoveAllModal?.show();
    });
    document.getElementById('galleryRemoveAllConfirmBtn')?.addEventListener('click', function () {
        galleryRemoveAllModal?.hide();
        const propertyId = document.getElementById('galleryRemoveAllBtn')?.dataset.property;
        galleryDz?.removeAllFiles(true);
        const finish = function () {
            const grid = document.getElementById('galleryThumbGrid');
            grid.innerHTML = '';
            grid.classList.add('d-none');
        };
        if (propertyId) {
            fetch("{{ url('portal/properties') }}/" + propertyId + "/images", {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            }).then(finish);
        } else {
            finish();
        }
    });

    // "Reorder": drag-and-drop the existing (already-saved) thumbnails, persisted immediately —
    // newly staged-but-unsaved files from Dropzone aren't part of this since they have no order yet.
    const reorderBtn = document.getElementById('galleryReorderBtn');
    const thumbGrid = document.getElementById('galleryThumbGrid');
    reorderBtn?.addEventListener('click', function () {
        const isOn = thumbGrid.classList.toggle('gallery-reorder-active');
        thumbGrid.querySelectorAll('.gallery-thumb').forEach(el => el.setAttribute('draggable', isOn));
        reorderBtn.classList.toggle('active', isOn);
        reorderBtn.innerHTML = isOn ? '<i class="fas fa-check me-1"></i>Done' : '<i class="fas fa-arrows-alt me-1"></i>Reorder';
    });

    // Every drag/dragover/drop listener here also stopPropagation()s — the thumb grid sits inside
    // Dropzone's own target element, and a saved thumb's <img src="..."> pointing at a real file URL
    // gets treated by the browser as a native "file" drag; without stopping it from bubbling up to
    // #galleryDropzone, Dropzone's own drop handler sees that synthesized file and re-adds the same
    // image as a brand new pending upload — duplicating it.
    let dragEl = null;
    thumbGrid?.addEventListener('dragstart', function (e) {
        const thumb = e.target.closest('.gallery-thumb');
        if (!thumb) return;
        e.stopPropagation();
        dragEl = thumb;
        setTimeout(() => thumb.classList.add('is-dragging'), 0);
    });
    thumbGrid?.addEventListener('dragend', function (e) {
        e.stopPropagation();
        dragEl?.classList.remove('is-dragging');
        dragEl = null;
    });
    thumbGrid?.addEventListener('dragenter', function (e) {
        if (dragEl) e.stopPropagation();
    });
    thumbGrid?.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (!dragEl) return;
        e.stopPropagation();
        const target = e.target.closest('.gallery-thumb');
        if (!target || target === dragEl) return;
        const rect = target.getBoundingClientRect();
        (e.clientX - rect.left) > rect.width / 2 ? target.after(dragEl) : target.before(dragEl);
    });
    // Filenames never change on reorder — only the displayed index badges and which thumb (always
    // whichever is first) carries the "FEATURED" tag need to follow the new order, live.
    function renumberGalleryThumbs() {
        thumbGrid.querySelectorAll('.gallery-thumb').forEach(function (thumb, i) {
            const badge = thumb.querySelector('.gallery-thumb-index');
            if (badge) badge.textContent = i + 1;
            let featured = thumb.querySelector('.gallery-thumb-featured');
            if (i === 0 && !featured) {
                featured = document.createElement('span');
                featured.className = 'gallery-thumb-featured';
                featured.textContent = 'FEATURED';
                thumb.appendChild(featured);
            } else if (i !== 0 && featured) {
                featured.remove();
            }
        });
    }

    // On drop, saved (already-uploaded) thumbs persist their new order to the server immediately;
    // staged (not-yet-uploaded) thumbs instead reorder Dropzone's own file queue + the hidden input,
    // so the new order is simply whatever gets uploaded when the form is actually submitted.
    thumbGrid?.addEventListener('drop', function (e) {
        if (!dragEl) return; // not our own reorder drag — let it bubble to Dropzone as a real file drop
        e.preventDefault();
        e.stopPropagation();
        renumberGalleryThumbs();

        const pendingFiles = Array.from(thumbGrid.querySelectorAll('.gallery-thumb[data-pending="true"]'))
            .map(el => el._dzFile)
            .filter(Boolean);
        if (galleryDz && pendingFiles.length) {
            galleryDz.files = pendingFiles;
            syncHiddenInput(document.getElementById('galleryInput'), pendingFiles);
        }

        const propertyId = document.getElementById('galleryRemoveAllBtn')?.dataset.property
            || thumbGrid.querySelector('.gallery-image-delete')?.dataset.property;
        const savedOrder = Array.from(thumbGrid.querySelectorAll('.gallery-thumb:not([data-pending])')).map(el => el.dataset.number);
        if (propertyId && savedOrder.length) {
            fetch("{{ url('portal/properties') }}/" + propertyId + "/images/reorder", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ order: savedOrder }),
            });
        }
    });
    // --- Amenities / Easy Access / Attributes checkbox grids: live "N selected" + search ---
    document.querySelectorAll('.option-check-grid').forEach(function (grid) {
        const counter = document.querySelector(`.option-picked-count[data-list="${grid.dataset.list}"]`);
        grid.addEventListener('change', function () {
            if (counter) counter.textContent = grid.querySelectorAll('input:checked').length;
        });
    });
    document.querySelectorAll('.option-search').forEach(function (input) {
        input.addEventListener('input', function () {
            const q = input.value.trim().toLowerCase();
            document.querySelectorAll(`.option-check-grid[data-list="${input.dataset.list}"] .option-check`).forEach(function (item) {
                item.hidden = q !== '' && !item.dataset.name.includes(q);
            });
        });
    });

    // --- Available: Immediately, or one or more dates (each date becomes a removable chip) ---
    const datesBox = document.getElementById('availableDatesBox');
    const datesList = document.getElementById('availableDatesList');
    const dateInput = document.getElementById('availableDateInput');
    function wireDateChip(chip) {
        chip.querySelector('.nearby-chip-remove')?.addEventListener('click', function () { chip.remove(); });
    }
    datesList?.querySelectorAll('.nearby-chip').forEach(wireDateChip);
    document.querySelectorAll('input[name="availability"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            datesBox.classList.toggle('d-none', radio.value !== 'from_date' || !radio.checked);
            if (radio.value === 'from_date' && radio.checked && !datesList.children.length) dateInput.focus();
        });
    });
    dateInput?.addEventListener('change', function () {
        const value = dateInput.value;
        if (!value || datesList.querySelector(`[data-date="${value}"]`)) { dateInput.value = ''; return; }
        const label = new Date(value + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
        const chip = document.createElement('span');
        chip.className = 'nearby-chip';
        chip.dataset.date = value;
        chip.innerHTML = `${label} <input type="hidden" name="available_dates[]" value="${value}"> <button type="button" class="nearby-chip-remove" aria-label="Remove date"><i class="fas fa-times"></i></button>`;
        // Keep the chips in date order.
        const after = [...datesList.children].find(function (c) { return c.dataset.date > value; });
        datesList.insertBefore(chip, after || null);
        wireDateChip(chip);
        dateInput.value = '';
    });

    // --- Rental period / number of cheques only apply to a rent listing ---
    // Offering type is a radio group (or a hidden input once the permit locks it).
    const listingTypeValue = () => (form.querySelector('[name="listing_type"]:checked') || form.querySelector('input[type="hidden"][name="listing_type"]') || {}).value || '';
    function toggleRentOnly() {
        const isRent = listingTypeValue() === 'rent';
        document.querySelectorAll('.rent-only').forEach(function (el) { el.classList.toggle('d-none', !isRent); });
    }
    form.querySelectorAll('[data-option-buttons="listing_type"] input').forEach(function (radio) { radio.addEventListener('change', toggleRentOnly); });
    toggleRentOnly();

    // --- Permit (Core details): which permit the emirate needs, and Validate (see _permit) ---
    (function () {
        const block = document.getElementById('permitBlock');
        if (!block) return;
        const types = JSON.parse(block.dataset.types || '{}');
        const licenses = JSON.parse(block.dataset.licenses || '{}');
        const numberInput = document.getElementById('permitNumber');
        const validateBtn = document.getElementById('permitValidate');
        const statusBox = document.getElementById('permitStatus');
        const tokenInput = document.getElementById('permitToken');
        const licenseSelect = document.getElementById('permitLicense');
        let verified = block.dataset.verified === '1';

        const fieldValue = name => (form.querySelector(`[name="${name}"]:not([type="radio"])`) || form.querySelector(`[name="${name}"]:checked`) || {}).value || '';
        const emirate = () => fieldValue('emirate');
        const permitType = () => {
            const city = fieldValue('permit_city');
            switch (emirate()) {
                case 'dubai': return fieldValue('permit_type') || 'rera';
                case 'abu_dhabi': return 'adrec';
                case 'northern_emirates': return city === 'al_ain' ? 'adrec' : (city === 'other' ? 'not_required' : null);
                default: return null;
            }
        };

        function setStatus(state, title, text) {
            statusBox.className = 'permit-status is-' + state;
            statusBox.querySelector('strong').textContent = title;
            statusBox.querySelector('span:not(.permit-status-icon)').textContent = text;
        }

        // Rent only (DTCM holiday homes): Sale can't be picked.
        function applyRentOnly(rentOnly) {
            const sale = document.getElementById('opt-listing_type-sale');
            const rent = document.getElementById('opt-listing_type-rent');
            if (!sale || !sale.name) return; // locked by the permit
            sale.disabled = rentOnly;
            if (rentOnly && sale.checked && rent) { rent.checked = true; toggleRentOnly(); }
        }

        function render() {
            const type = permitType();
            const info = types[type] || {};
            const show = {
                dubai: emirate() === 'dubai',
                northern: emirate() === 'northern_emirates',
                licensed: type === 'rera' || type === 'adrec',
                numbered: ['rera', 'dtcm', 'adrec'].includes(type),
                qr: type === 'rera' || type === 'adrec',
                validates: !!info.validates,
                'no-permit': type === 'none' || type === 'not_required',
            };
            block.querySelectorAll('[data-permit-show]').forEach(el => el.classList.toggle('d-none', !show[el.dataset.permitShow]));
            if (info.number_label) block.querySelector('[data-permit-number-label]').textContent = info.number_label;

            if (show.licensed) {
                const license = licenses[type];
                block.querySelector('[data-permit-license-label]').textContent = info.license_label || 'License';
                licenseSelect.innerHTML = license
                    ? `<option>${license.name} — ${type === 'adrec' ? 'Brokerage Registration Number' : 'ORN'}: ${license.number}</option>`
                    : '<option>No license on file</option>';
                block.querySelector('[data-permit-license-help]').textContent = type === 'adrec'
                    ? 'ADREC checks the broker license together with the permit number.'
                    : 'DLD checks the permit against this RERA office registration number (ORN).';
                const missing = block.querySelector('[data-permit-license-missing]');
                missing.classList.toggle('d-none', !!license);
                // Who has to add the license, and where (PortalPropertyController::permitLicenses).
                const help = (licenses.missing || {})[type] || {};
                missing.querySelector('[data-permit-license-missing-text]').textContent = help.text || '';
                const link = missing.querySelector('[data-permit-license-missing-link]');
                link.classList.toggle('d-none', !help.url);
                if (help.url) { link.href = help.url; link.textContent = help.link; }
                validateBtn.disabled = !license;
            }
            applyRentOnly(type === 'dtcm');
        }

        // Anything that changes which permit is checked throws away an earlier, unsaved result.
        function resetVerification() {
            if (!tokenInput.value) return;
            tokenInput.value = '';
            setStatus('idle', 'Ready to validate', 'Enter the permit number above.');
            validateBtn.textContent = 'Validate';
        }

        // Fill a listing field from the permit and lock it (posted through a hidden input).
        function lockField(name, value) {
            const el = form.querySelector(`[name="${name}"]`);
            if (!el || value === undefined || value === null || value === '') return;
            if (el.type === 'radio') {
                const radios = [...form.querySelectorAll(`input[type="radio"][name="${name}"]`)];
                const pick = radios.find(r => r.value === String(value));
                if (!pick) return; // the permit's value isn't an option here
                pick.checked = true;
                radios.forEach(r => { r.disabled = true; r.removeAttribute('name'); });
                const hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = name; hidden.value = String(value); hidden.dataset.permitLock = '1';
                pick.closest('[data-option-buttons]').after(hidden);
                pick.closest('[class*="col-"]')?.querySelector('.form-label')?.insertAdjacentHTML('beforeend', ' <i class="fas fa-lock text-muted ms-1 small" title="Filled in from the verified permit"></i>');
                return;
            }
            if (el.tagName === 'SELECT') {
                if (![...el.options].some(o => o.value === String(value))) return; // the permit's value isn't an option here
                $(el).val(String(value)).trigger('change');
                el.disabled = true;
                el.removeAttribute('name');
                const hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = name; hidden.value = String(value); hidden.dataset.permitLock = '1';
                el.after(hidden);
            } else {
                el.value = value;
                el.readOnly = true;
            }
            el.closest('[class*="col-"]')?.querySelector('.form-label')?.insertAdjacentHTML('beforeend', ' <i class="fas fa-lock text-muted ms-1 small" title="Filled in from the verified permit"></i>');
        }

        validateBtn?.addEventListener('click', async function () {
            const number = numberInput.value.trim();
            if (!number) { numberInput.classList.add('is-invalid'); numberInput.focus(); return; }
            validateBtn.disabled = true;
            setStatus('checking', 'Checking…', 'Asking ' + (permitType() === 'adrec' ? 'ADREC' : 'DLD') + ' about this permit.');
            try {
                const res = await fetch(block.dataset.validateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        emirate: emirate(), permit_type: fieldValue('permit_type'), permit_city: fieldValue('permit_city'),
                        permit_number: number, property_id: block.dataset.propertyId || null,
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.status === 422) {
                    setStatus('error', 'Check the permit number', Object.values(data.errors || {})[0]?.[0] || data.message || 'This permit number is not valid.');
                } else if (!res.ok) {
                    setStatus('error', 'Could not validate', 'Something went wrong. Please try again.');
                } else if (data.status === 'verified') {
                    tokenInput.value = data.token || '';
                    verified = true;
                    setStatus('verified', 'Verification successful', data.message || 'Permit verified successfully.');
                    const f = data.fields || {};
                    ['category', 'listing_type', 'property_type', 'location'].forEach(name => lockField(name, f[name]));
                    ['bedrooms', 'sqft'].forEach(name => lockField(name, f[name]));
                    if (f.expires_at) document.getElementById('permitExpires').value = f.expires_at;
                    numberInput.readOnly = true;
                    validateBtn.textContent = 'Refresh';
                    toggleRentOnly();
                } else if (data.status === 'invalid') {
                    setStatus('error', 'Verification failed', data.message);
                } else {
                    setStatus('pending', 'Not verified online', data.message);
                }
            } catch (e) {
                setStatus('error', 'Could not validate', 'Check your connection and try again.');
            } finally {
                validateBtn.disabled = false;
            }
        });

        numberInput?.addEventListener('input', () => { numberInput.classList.remove('is-invalid'); resetVerification(); });
        block.querySelectorAll('[name="permit_type"], #permitCity').forEach(el => el.addEventListener('change', () => { resetVerification(); render(); }));
        const emirateSelect = document.getElementById('field-emirate');
        if (emirateSelect) $(emirateSelect).on('change', () => { resetVerification(); render(); });
        render();
    })();

    // --- Floor plan rows ---
    const floorPlanRows = document.getElementById('floorPlanRows');
    const floorPlanEmpty = document.getElementById('floorPlanEmpty');
    // Next free index — never reuse one after a removal, or two rows would share floor_plans[n].
    let floorPlanIndex = floorPlanRows ? floorPlanRows.querySelectorAll('.floor-plan-row').length : 0;
    function wireFloorPlanRow(row) {
        row.querySelector('.floor-plan-remove-btn').addEventListener('click', function () {
            row.remove();
            if (floorPlanEmpty) floorPlanEmpty.hidden = floorPlanRows.querySelector('.floor-plan-row') !== null;
        });
        // Show the picked image in the tile straight away.
        row.querySelector('.floor-plan-image-input').addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;
            const img = row.querySelector('.floor-plan-thumb img');
            img.src = URL.createObjectURL(file);
            img.hidden = false;
            row.querySelector('.floor-plan-thumb').classList.add('has-image');
        });
    }
    floorPlanRows?.querySelectorAll('.floor-plan-row').forEach(wireFloorPlanRow);
    document.getElementById('addFloorPlanBtn')?.addEventListener('click', function () {
        const html = document.getElementById('floorPlanRowTemplate').innerHTML.trim().replaceAll('__INDEX__', floorPlanIndex++);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const row = wrapper.firstElementChild;
        floorPlanRows.appendChild(row);
        wireFloorPlanRow(row);
        if (floorPlanEmpty) floorPlanEmpty.hidden = true;
        row.querySelector('input[type="text"]').focus();
    });
    document.getElementById('floorPlanFileInput')?.addEventListener('change', function () {
        const chosen = document.getElementById('floorPlanFileChosen');
        const file = this.files && this.files[0];
        chosen.hidden = !file;
        chosen.querySelector('span').textContent = file ? file.name : '';
    });

    // --- Nearby Places: Type -> Place cascading picker ---
    const typeSelect = document.getElementById('nearbyTypeSelect');
    const placeSelect = document.getElementById('nearbyPlaceSelect');
    const addBtn = document.getElementById('nearbyAddBtn');
    const assignedList = document.getElementById('nearbyAssignedList');

    function wireChipRemove(chip) {
        chip.querySelector('.nearby-chip-remove')?.addEventListener('click', function () { chip.remove(); });
    }
    assignedList?.querySelectorAll('.nearby-chip').forEach(wireChipRemove);

    typeSelect?.addEventListener('change', function () {
        placeSelect.innerHTML = '<option value="">Loading...</option>';
        placeSelect.disabled = true;
        addBtn.disabled = true;
        if (!this.value) {
            placeSelect.innerHTML = '<option value="">Select type first</option>';
            return;
        }
        fetch("{{ route('portal.properties.nearby-places-by-type') }}?type=" + encodeURIComponent(this.value))
            .then(res => res.json())
            .then(data => {
                const places = data.places || [];
                placeSelect.innerHTML = places.length
                    ? '<option value="">Select place</option>' + places.map(p => `<option value="${p.id}" data-name="${p.name.replace(/"/g, '&quot;')}">${p.name}${p.own ? ' (my place)' : ''}</option>`).join('')
                    : '<option value="">No places of this type yet</option>';
                placeSelect.disabled = places.length === 0;
            });
    });

    placeSelect?.addEventListener('change', function () {
        addBtn.disabled = !this.value;
    });

    addBtn?.addEventListener('click', function () {
        const opt = placeSelect.options[placeSelect.selectedIndex];
        if (!opt || !opt.value) return;
        if (assignedList.querySelector(`.nearby-chip[data-id="${opt.value}"]`)) return; // already added

        const chip = document.createElement('span');
        chip.className = 'nearby-chip';
        chip.dataset.id = opt.value;
        chip.innerHTML = `${opt.dataset.name} <input type="hidden" name="nearby_places[]" value="${opt.value}"> <button type="button" class="nearby-chip-remove"><i class="fas fa-times"></i></button>`;
        assignedList.appendChild(chip);
        wireChipRemove(chip);

        placeSelect.value = '';
        addBtn.disabled = true;
    });

    // --- Location: live "Full Address Preview" per language, built from Address/Community/City/
    // Country (per-language) plus the global Postal Code field. ---
    const postalCodeInput = document.querySelector('input[name="postal_code"]');
    function updateAddressPreview(lang) {
        const parts = ['address', 'community', 'city'].map(function (part) {
            return document.querySelector(`.address-preview-field[data-lang="${lang}"][data-part="${part}"]`)?.value.trim();
        }).filter(Boolean);
        const country = document.querySelector(`.address-preview-field[data-lang="${lang}"][data-part="country"]`)?.value.trim();
        if (postalCodeInput?.value.trim()) parts.push(postalCodeInput.value.trim());
        if (country) parts.push(country);
        const output = document.querySelector(`.address-preview-output[data-lang="${lang}"]`);
        if (output) output.value = parts.join(', ');
    }
    document.querySelectorAll('.address-preview-field').forEach(function (input) {
        input.addEventListener('input', function () { updateAddressPreview(input.dataset.lang); });
    });
    postalCodeInput?.addEventListener('input', function () {
        document.querySelectorAll('.address-preview-output').forEach(function (output) { updateAddressPreview(output.dataset.lang); });
    });
    document.querySelectorAll('.address-preview-output').forEach(function (output) { updateAddressPreview(output.dataset.lang); });

    // --- Required-field guided validation. Native HTML5 `required` doesn't work here: a required
    // field inside a non-active Bootstrap tab-pane is `display:none`, and Chrome silently blocks
    // the whole submit (no visible error) because it can't focus a hidden field to show its native
    // validation bubble — this fully custom check replaces that, and jumps straight to whichever
    // tab (and language sub-tab, for per-language fields) holds the first missing value. ---
    const globalRequiredFields = [
        { name: 'emirate', tab: 'tab-basic', label: 'Emirate', select2: true },
        { name: 'slug', tab: 'tab-basic', label: 'Slug' },
        { name: 'property_type', tab: 'tab-basic', label: 'Property Type', select2: true },
        { name: 'category', tab: 'tab-basic', label: 'Category', radio: true },
        { name: 'listing_type', tab: 'tab-basic', label: 'Offering type', radio: true },
        { name: 'location', tab: 'tab-basic', label: 'Property location', select2: true },
        { name: 'price', tab: 'tab-specifications', label: 'Price' },
        { name: 'currency', tab: 'tab-specifications', label: 'Currency' },
        { name: 'latitude', tab: 'tab-location', label: 'Latitude' },
        { name: 'longitude', tab: 'tab-location', label: 'Longitude' },
    ];
    const perLangRequiredFields = [
        { field: 'title', tab: 'tab-specifications', innerPrefix: '', label: 'Title' },
        { field: 'address', tab: 'tab-location', innerPrefix: 'loc-', label: 'Address' },
        { field: 'city', tab: 'tab-location', innerPrefix: 'loc-', label: 'City' },
        { field: 'country', tab: 'tab-location', innerPrefix: 'loc-', label: 'Country' },
    ];

    function findMissingRequiredFields() {
        const missing = [];
        globalRequiredFields.forEach(function (f) {
            const el = form.querySelector(`[name="${f.name}"]`);
            // Button groups (Category, Offering type): one of them must be picked.
            if (el && el.type === 'radio') {
                if (!form.querySelector(`[name="${f.name}"]:checked`)) missing.push(Object.assign({ el: el.closest('[data-option-buttons]'), innerTabId: null }, f));
                return;
            }
            if (el && !el.value.trim()) missing.push(Object.assign({ el: el, innerTabId: null }, f));
        });
        allLangCodes.forEach(function (code) {
            perLangRequiredFields.forEach(function (f) {
                const el = form.querySelector(`[name="translations[${code}][${f.field}]"]`);
                if (el && !el.value.trim()) {
                    missing.push({
                        el: el,
                        tab: f.tab,
                        label: `${f.label} (${code.toUpperCase()})`,
                        innerTabId: f.innerPrefix + code + '-tab',
                    });
                }
            });
        });
        return missing;
    }

    let validationBanner = null;
    function showValidationBanner(message) {
        if (!validationBanner) {
            validationBanner = document.createElement('div');
            validationBanner.className = 'alert alert-danger property-validation-banner';
            form.prepend(validationBanner);
        }
        validationBanner.textContent = message;
    }
    function clearValidationBanner() {
        validationBanner?.remove();
        validationBanner = null;
    }

    document.querySelectorAll('.form-control, .lang-aware-select').forEach(function (el) {
        el.addEventListener('input', function () { el.classList.remove('is-invalid'); });
        el.addEventListener('change', function () { el.classList.remove('is-invalid'); });
    });

    function focusMissingField(target) {
        const outerBtn = document.querySelector(`.property-tab-link[data-bs-target="#${target.tab}"]`);
        if (outerBtn && window.bootstrap) bootstrap.Tab.getOrCreateInstance(outerBtn).show();

        setTimeout(function () {
            if (target.innerTabId) {
                const innerBtn = document.getElementById(target.innerTabId);
                if (innerBtn && window.bootstrap) bootstrap.Tab.getOrCreateInstance(innerBtn).show();
            }
            setTimeout(function () {
                target.el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (target.select2) {
                    $(target.el).select2('open');
                } else {
                    target.el.classList.add('is-invalid');
                    target.el.focus();
                }
            }, 150);
        }, outerBtn ? 150 : 0);
    }

    form.addEventListener('submit', function (e) {
        const missing = findMissingRequiredFields();
        if (missing.length === 0) {
            clearValidationBanner();
            return;
        }
        e.preventDefault();
        // The portal layout's global double-submit guard (capture-phase, runs before this handler)
        // already disabled the Save buttons and swapped in a spinner for every submit attempt —
        // undo that here since this attempt isn't actually going through.
        window.restoreSubmitButtons?.(form);
        const first = missing[0];
        showValidationBanner(
            missing.length > 1
                ? `Please fill in "${first.label}" — ${missing.length} required fields are still empty. Save will work once they're all filled.`
                : `Please fill in "${first.label}" — it's required before this can be saved.`
        );
        focusMissingField(first);
    });
});

document.addEventListener('DOMContentLoaded', function () {
    // Runs in its own DOMContentLoaded listener (not inline at parse time) since the Bootstrap JS
    // bundle that provides `bootstrap.Modal` loads later in the page — instantiating it too early
    // throws "bootstrap is not defined" and silently breaks this whole click handler.
    const modalEl = document.getElementById('galleryDeleteImageModal');
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    const confirmBtn = document.getElementById('galleryDeleteImageConfirmBtn');
    let pendingBtn = null;

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.gallery-image-delete');
        if (!btn) return;
        pendingBtn = btn;
        modal?.show();
    });

    confirmBtn?.addEventListener('click', function () {
        if (!pendingBtn) return;
        modal?.hide();
        fetch("{{ url('portal/properties') }}/" + pendingBtn.dataset.property + "/images/" + pendingBtn.dataset.number, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        }).then(() => location.reload());
    });
});
</script>

@push('scripts')
<script>
    // Open a form tab from the URL (#tab-basic, e.g. the "Fix now" link on a listing card) or a [data-open-tab] button.
    (function () {
        const open = target => {
            if (target === '#tab-compliance') target = '#tab-basic'; // older links (emails) — the permit now lives in Core details
            const link = document.querySelector('.property-tab-link[data-bs-target="' + target + '"]');
            if (!link || !window.bootstrap) return;
            bootstrap.Tab.getOrCreateInstance(link).show();
            link.scrollIntoView({ behavior: 'smooth', block: 'center' });
        };
        document.addEventListener('click', e => {
            const btn = e.target.closest('[data-open-tab]');
            if (btn) open(btn.dataset.openTab);
        });
        if (/^#tab-[\w-]+$/.test(location.hash)) {
            window.addEventListener('load', () => open(location.hash));
        }
        @if($errors->any())
        // After a failed save, show the tab holding the first field with an error.
        window.addEventListener('load', () => {
            const pane = document.querySelector('.property-tabs-content > .tab-pane .is-invalid, .property-tabs-content > .tab-pane .invalid-feedback.d-block, .property-tabs-content > .tab-pane .text-danger.small')
                ?.closest('.property-tabs-content > .tab-pane');
            if (pane) open('#' + pane.id);
        });
        @endif
    })();
</script>
@endpush
