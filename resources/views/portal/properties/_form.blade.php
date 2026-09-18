@php
    $isEdit = isset($property);
    $details = $isEdit ? $property->details : null;
    $val = fn ($field, $default = null) => old($field, $isEdit ? ($property->{$field} ?? $default) : $default);
    $detailVal = fn ($field, $default = null) => old($field, $isEdit && $details ? ($details->{$field} ?? $default) : $default);
    $meta = $isEdit ? ($property->metadata ?? []) : [];
    $metaVal = fn ($field) => old("metadata.{$field}", $meta[$field] ?? '');

    $repeaterSections = [
        'amenities' => ['title' => 'Amenities', 'hint' => 'Pool, Gym, Parking, etc.', 'icon' => 'fa-swimming-pool'],
        'easy_access' => ['title' => 'Easy Access', 'hint' => 'Metro, highway access, etc.', 'icon' => 'fa-route'],
        'property_attributes' => ['title' => 'Attributes', 'hint' => 'Any other notable attribute.', 'icon' => 'fa-star'],
    ];

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
@endphp

<div class="property-tabs-shell">
    <div class="nav property-tabs-sidebar" role="tablist" aria-orientation="vertical">
        <div class="property-tabs-group-label">Listing Info</div>
        <button type="button" role="tab" class="property-tab-link active" data-bs-toggle="pill" data-bs-target="#tab-basic">
            <span class="property-tab-icon"><i class="fas fa-file-lines"></i></span> <span>Basic</span>
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
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-nearby">
            <span class="property-tab-icon"><i class="fas fa-location-dot"></i></span> <span>Nearby Places</span>
        </button>
        <button type="button" role="tab" class="property-tab-link" data-bs-toggle="pill" data-bs-target="#tab-publishing">
            <span class="property-tab-icon"><i class="fas fa-bullhorn"></i></span> <span>Publishing</span>
        </button>
    </div>

    <div class="property-tabs-content tab-content">

        {{-- Basic (Reference & Classification, Title/Description per language, Pricing & Status, Map) --}}
        <div class="tab-pane fade show active" id="tab-basic" role="tabpanel">
            <div class="property-tab-pane-head">
                <div class="property-tab-pane-title">Basic Information</div>
                <div class="property-tab-pane-hint">Reference, classification, title and description, per language</div>
            </div>
            <ul class="nav property-lang-tabs mb-4" id="langTabs" role="tablist" data-langs="{{ $languages->pluck('code')->implode(',') }}">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="{{ $lang->code }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $lang->code }}-content" type="button" role="tab" data-lang="{{ $lang->code }}">
                        {{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>

            <div class="tab-content mb-4">
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

            <hr class="my-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Reference ID</label>
                    <input type="text" class="form-control" value="{{ $val('reference_no') }}" placeholder="Auto-generated on save" readonly disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">RERA ID</label>
                    <input type="text" name="rera_id" class="form-control" value="{{ $val('rera_id') }}" placeholder="e.g. RERA70613">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                    <input type="text" name="slug" id="slugInput" class="form-control" value="{{ $val('slug') }}" placeholder="Auto-generated from title">
                </div>
                @php
                    $selectFields = [
                        'property_type' => 'Property Type',
                        'listing_type' => 'Listing Type (Buy / Rent)',
                        'category' => 'Category',
                        'completion_status' => 'Completion Status',
                    ];
                    $requiredSelectFields = ['property_type', 'listing_type'];
                    $searchPlaceholders = [
                        'property_type' => 'Search property type',
                        'listing_type' => 'Search listing type',
                        'category' => 'Search category',
                        'completion_status' => 'Search completion status',
                    ];
                @endphp
                @foreach($selectFields as $field => $label)
                <div class="col-md-4">
                    <label class="form-label fw-semibold">{{ $label }} @if(in_array($field, $requiredSelectFields, true))<span class="text-danger">*</span>@endif</label>
                    @if(isset($filterOptions[$field]) && $filterOptions[$field]->activeValues->count())
                        {{-- Option text is swapped live via JS to match whichever language tab above is
                        currently active — the selected value itself isn't per-language. For the fixed,
                        developer-defined value sets (Property Type/Listing Type/Completion Status) the
                        text comes from the static-texts JSON files (CMS > Languages > Static Texts);
                        everything else (Category, Location — fully admin-configurable) uses the
                        FilterValue's own per-language label. --}}
                        <select name="{{ $field }}" class="form-select lang-aware-select" data-placeholder="{{ $searchPlaceholders[$field] ?? 'Search' }}">
                            <option value=""></option>
                            @foreach($filterOptions[$field]->activeValues as $option)
                            @php
                                $labelFor = function (string $langCode) use ($field, $option, $staticLabels, $staticSelectFields, $fallbackLang) {
                                    if (in_array($field, $staticSelectFields, true)) {
                                        return $staticLabels[$langCode]["{$field}.{$option->value}"]
                                            ?? $staticLabels[$fallbackLang]["{$field}.{$option->value}"]
                                            ?? $option->getTranslation('label', $langCode);
                                    }
                                    return $option->getTranslation('label', $langCode) ?: $option->getTranslation('label', $fallbackLang);
                                };
                            @endphp
                            <option value="{{ $option->value }}"
                                {{ $val($field) == $option->value ? 'selected' : '' }}
                                @foreach($languages as $lang)
                                data-label-{{ $lang->code }}="{{ $labelFor($lang->code) }}"
                                @endforeach
                            >{{ $labelFor($languages->first()->code ?? $fallbackLang) }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" name="{{ $field }}" class="form-control" value="{{ $val($field) }}" placeholder="Free text (no options configured yet)">
                    @endif
                </div>
                @endforeach

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Furnishing</label>
                    <select name="furnished" class="form-select lang-aware-select" data-placeholder="Search furnishing">
                        <option value=""></option>
                        <option value="0"
                            {{ !$detailVal('furnished') ? 'selected' : '' }}
                            @foreach($languages as $lang)
                            data-label-{{ $lang->code }}="{{ $staticLabels[$lang->code]['furnished.no'] ?? $staticLabels[$fallbackLang]['furnished.no'] ?? 'Unfurnished' }}"
                            @endforeach
                        >{{ $staticLabels[$languages->first()->code ?? 'en']['furnished.no'] ?? 'Unfurnished' }}</option>
                        <option value="1"
                            {{ $detailVal('furnished') ? 'selected' : '' }}
                            @foreach($languages as $lang)
                            data-label-{{ $lang->code }}="{{ $staticLabels[$lang->code]['furnished.yes'] ?? $staticLabels[$fallbackLang]['furnished.yes'] ?? 'Furnished' }}"
                            @endforeach
                        >{{ $staticLabels[$languages->first()->code ?? 'en']['furnished.yes'] ?? 'Furnished' }}</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">
            <div class="property-tab-pane-title mb-3" style="font-size: 0.95rem;">Pricing &amp; Status</div>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Price <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="price" class="form-control" value="{{ $val('price') }}" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                    <input type="text" name="currency" class="form-control" value="{{ $val('currency', 'AED') }}">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Direct From Owner</label>
                    <input type="text" name="direct_from_owner" class="form-control" value="{{ $detailVal('direct_from_owner') }}" placeholder="Example: Yes, Owner Listed">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Security Deposit</label>
                    <input type="number" step="0.01" name="security_deposit" class="form-control" value="{{ $detailVal('security_deposit') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Virtual Tour URL</label>
                    <input type="url" name="virtual_tour_url" class="form-control" value="{{ $detailVal('virtual_tour_url') }}" placeholder="https://example.com/tour">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Bedrooms</label>
                    <input type="number" name="bedrooms" class="form-control" value="{{ $val('bedrooms') }}" min="0">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Bathrooms</label>
                    <input type="number" name="bathrooms" class="form-control" value="{{ $val('bathrooms') }}" min="0">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Sqft</label>
                    <input type="number" name="sqft" class="form-control" value="{{ $val('sqft') }}" min="0">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Garage</label>
                    <input type="number" name="garage" class="form-control" value="{{ $detailVal('garage') }}" min="0">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Parking Spaces</label>
                    <input type="number" name="parking" class="form-control" value="{{ $detailVal('parking') }}">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Year Built</label>
                    <input type="number" name="year_built" class="form-control" value="{{ $detailVal('year_built') }}">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Floor</label>
                    <input type="text" name="floor" class="form-control" value="{{ $detailVal('floor') }}">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">View</label>
                    <input type="text" name="view" class="form-control" value="{{ $detailVal('view') }}" placeholder="e.g. Sea View">
                </div>
                <div class="col-12"><hr class="my-1"></div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="status" id="propertyStatus" {{ $val('status', true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="propertyStatus">Active (visible on site)</label>
                    </div>
                </div>
                {{-- Featured — deferred for later, per request; re-enable when ready.
                @if($isAdmin ?? false)
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="featured" id="propertyFeatured" {{ $val('featured') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="propertyFeatured">Featured</label>
                    </div>
                </div>
                @endif
                --}}
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
                        <label class="form-label fw-bold">Assigned Agent</label>
                        <select name="agent_id" class="form-select">
                            <option value="">— No agent assigned —</option>
                            @foreach($agentOptions as $agent)
                            <option value="{{ $agent->id }}" {{ (string) $val('agent_id') === (string) $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                            @endforeach
                        </select>
                        @if($agentOptions->isEmpty())
                        <div class="form-text">You haven't added any agents yet. <a href="{{ route('portal.agents.create') }}" target="_blank">Add one</a>.</div>
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

        {{-- Icon-repeater sections: Amenities / Easy Access / Attributes --}}
        @php $repeaterTabIds = ['amenities' => 'tab-amenities', 'easy_access' => 'tab-easy-access', 'property_attributes' => 'tab-attributes']; @endphp
        @foreach($repeaterSections as $field => $section)
        <div class="tab-pane fade" id="{{ $repeaterTabIds[$field] }}" role="tabpanel">
            <div class="property-tab-pane-head">
                <div class="property-tab-pane-title">{{ $section['title'] }}</div>
                <div class="property-tab-pane-hint">{{ $section['hint'] }}</div>
            </div>
            <div class="d-flex justify-content-end mb-3">
                <button type="button" class="btn btn-sm portal-btn-ghost repeater-add-btn" data-target="{{ $field }}"><i class="fas fa-plus me-1"></i>Add Row</button>
            </div>
            <div id="repeater-{{ $field }}" data-field="{{ $field }}">
                @foreach($detailVal($field, []) as $i => $row)
                <div class="d-flex align-items-center gap-2 mb-2 repeater-row">
                    <div class="repeater-icon-preview">
                        @if(!empty($row['icon']))
                            <img src="{{ str_starts_with($row['icon'], 'http') ? $row['icon'] : asset('storage/' . $row['icon']) }}">
                        @else
                            <i class="fas fa-image text-muted"></i>
                        @endif
                    </div>
                    <input type="hidden" name="{{ $field }}[{{ $i }}][existing_icon]" value="{{ $row['icon'] ?? '' }}">
                    <input type="file" name="{{ $field }}[{{ $i }}][icon]" class="form-control form-control-sm repeater-icon-input" accept="image/*" style="max-width:160px;">
                    @foreach($languages as $lang)
                    <input type="text" name="{{ $field }}[{{ $i }}][label][{{ $lang->code }}]" class="form-control form-control-sm" value="{{ $row['label'][$lang->code] ?? '' }}" placeholder="Label ({{ strtoupper($lang->code) }})">
                    @endforeach
                    <button type="button" class="btn btn-sm btn-outline-danger repeater-remove-btn text-nowrap"><i class="fas fa-times"></i></button>
                </div>
                @endforeach
            </div>
            <template id="repeater-template-{{ $field }}">
                <div class="d-flex align-items-center gap-2 mb-2 repeater-row">
                    <div class="repeater-icon-preview"><i class="fas fa-image text-muted"></i></div>
                    <input type="file" name="{{ $field }}[__INDEX__][icon]" class="form-control form-control-sm repeater-icon-input" accept="image/*" style="max-width:160px;">
                    @foreach($languages as $lang)
                    <input type="text" name="{{ $field }}[__INDEX__][label][{{ $lang->code }}]" class="form-control form-control-sm" placeholder="Label ({{ strtoupper($lang->code) }})">
                    @endforeach
                    <button type="button" class="btn btn-sm btn-outline-danger repeater-remove-btn text-nowrap"><i class="fas fa-times"></i></button>
                </div>
            </template>
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
                @foreach($existingFloorPlans as $i => $fp)
                <div class="row g-2 mb-2 align-items-end floor-plan-row">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Title</label>
                        <input type="text" name="floor_plans[{{ $i }}][label]" class="form-control form-control-sm" value="{{ $fp['label'] ?? '' }}" placeholder="1 Bedroom Apartments">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Sqft From</label>
                        <input type="number" name="floor_plans[{{ $i }}][size_from]" class="form-control form-control-sm" value="{{ $fp['size_from'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Sqft To</label>
                        <input type="number" name="floor_plans[{{ $i }}][size_to]" class="form-control form-control-sm" value="{{ $fp['size_to'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Image</label>
                        <input type="hidden" name="floor_plans[{{ $i }}][existing_image]" value="{{ $fp['image'] ?? '' }}">
                        <input type="file" name="floor_plans[{{ $i }}][image]" class="form-control form-control-sm" accept="image/*">
                        @if(!empty($fp['image']))
                        <img src="{{ asset('storage/' . $fp['image']) }}" class="mt-1 rounded" style="height:40px;">
                        @endif
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100 floor-plan-remove-btn"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                @endforeach
            </div>
            <hr class="my-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Downloadable Floor Plan File</label>
                    <input type="file" name="floor_plan_file" class="form-control">
                    @if($isEdit && $details?->floor_plan_file)
                    <div class="mt-2"><a href="{{ asset('storage/' . $details->floor_plan_file) }}" target="_blank" class="small">Current file</a></div>
                    @endif
                </div>
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

        {{-- Nearby Places --}}
        <div class="tab-pane fade" id="tab-nearby" role="tabpanel">
            <div class="property-tab-pane-head d-flex justify-content-between align-items-start">
                <div>
                    <div class="property-tab-pane-title">Nearby Places</div>
                    <div class="property-tab-pane-hint">Pick a type, then a specific place, and add it.</div>
                </div>
                @if($isAdmin ?? false)
                <a href="{{ route('portal.nearby-places.index') }}" target="_blank" class="btn btn-sm portal-btn-ghost">Manage</a>
                @endif
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
                    <img src="{{ asset('storage/' . $meta['og_image']) }}" class="mt-2 rounded" style="height:50px;">
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
        position: sticky;
        top: 1.25rem;
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

    @media (max-width: 991.98px) {
        .property-tabs-shell { flex-direction: column; }
        .property-tabs-sidebar { flex-direction: row; flex-wrap: nowrap; overflow-x: auto; width: 100%; padding: 0.75rem; position: sticky; top: 0; z-index: 2; }
        .property-tabs-group-label { display: none; }
        .property-tab-link { flex: 0 0 auto; }
        .property-tabs-content { padding: 1.5rem; }
    }

    .property-lang-tabs { gap: 0.5rem; background: var(--portal-bg); padding: 0.35rem; border-radius: 50px; display: inline-flex; }
    .property-lang-tabs .nav-link { border-radius: 50px; padding: 0.4rem 1.1rem; font-weight: 700; font-size: 0.85rem; color: var(--portal-muted); border: none; }
    .property-lang-tabs .nav-link.active { background: var(--portal-primary); color: #fff; }

    .repeater-icon-preview { width: 44px; height: 44px; flex-shrink: 0; border-radius: 10px; border: 1px solid var(--portal-border); background: var(--portal-bg); display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .repeater-icon-preview img { width: 100%; height: 100%; object-fit: cover; }

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
        initLangAwareSelects();
    }
    initLangAwareSelects();
    document.querySelectorAll('#langTabs [data-lang]').forEach(function (tabBtn) {
        tabBtn.addEventListener('shown.bs.tab', function () { applyLangAwareSelectOptions(tabBtn.dataset.lang); });
    });
    const initialLangTab = document.querySelector('#langTabs [data-lang].active');
    if (initialLangTab) applyLangAwareSelectOptions(initialLangTab.dataset.lang);

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

    // --- Icon repeaters (Amenities / Easy Access / Property Attributes) ---
    function wireRepeaterRemove(row) {
        row.querySelector('.repeater-remove-btn')?.addEventListener('click', function () {
            row.remove();
        });
    }
    document.querySelectorAll('.repeater-row').forEach(wireRepeaterRemove);

    document.querySelectorAll('.repeater-add-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const field = btn.dataset.target;
            const container = document.getElementById('repeater-' + field);
            const index = container.querySelectorAll('.repeater-row').length;
            const template = document.getElementById('repeater-template-' + field);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.trim().replaceAll('__INDEX__', index);
            const row = wrapper.firstElementChild;
            container.appendChild(row);
            wireRepeaterRemove(row);
        });
    });

    // --- Floor plan rows ---
    document.querySelectorAll('.floor-plan-remove-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { btn.closest('.floor-plan-row').remove(); });
    });
    document.getElementById('addFloorPlanBtn')?.addEventListener('click', function () {
        const container = document.getElementById('floorPlanRows');
        const index = container.querySelectorAll('.floor-plan-row').length;
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-end floor-plan-row';
        row.innerHTML = `
            <div class="col-md-4"><label class="form-label small mb-1">Title</label><input type="text" name="floor_plans[${index}][label]" class="form-control form-control-sm" placeholder="1 Bedroom Apartments"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Sqft From</label><input type="number" name="floor_plans[${index}][size_from]" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Sqft To</label><input type="number" name="floor_plans[${index}][size_to]" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label small mb-1">Image</label><input type="file" name="floor_plans[${index}][image]" class="form-control form-control-sm" accept="image/*"></div>
            <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100 floor-plan-remove-btn"><i class="fas fa-times"></i></button></div>
        `;
        container.appendChild(row);
        row.querySelector('.floor-plan-remove-btn').addEventListener('click', function () { row.remove(); });
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
                    ? '<option value="">Select place</option>' + places.map(p => `<option value="${p.id}" data-name="${p.name.replace(/"/g, '&quot;')}">${p.name}</option>`).join('')
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
        { name: 'slug', tab: 'tab-basic', label: 'Slug' },
        { name: 'property_type', tab: 'tab-basic', label: 'Property Type', select2: true },
        { name: 'listing_type', tab: 'tab-basic', label: 'Listing Type', select2: true },
        { name: 'price', tab: 'tab-basic', label: 'Price' },
        { name: 'currency', tab: 'tab-basic', label: 'Currency' },
        { name: 'latitude', tab: 'tab-location', label: 'Latitude' },
        { name: 'longitude', tab: 'tab-location', label: 'Longitude' },
    ];
    const perLangRequiredFields = [
        { field: 'title', tab: 'tab-basic', innerPrefix: '', label: 'Title' },
        { field: 'address', tab: 'tab-location', innerPrefix: 'loc-', label: 'Address' },
        { field: 'city', tab: 'tab-location', innerPrefix: 'loc-', label: 'City' },
        { field: 'country', tab: 'tab-location', innerPrefix: 'loc-', label: 'Country' },
    ];

    function findMissingRequiredFields() {
        const missing = [];
        globalRequiredFields.forEach(function (f) {
            const el = form.querySelector(`[name="${f.name}"]`);
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
