{{--
    List view of the listings (index.blade.php, Grid / List toggle) — one compact row per listing
    with the same actions as the cards: select, status switch, quality score, Listing Performance,
    edit, sold, delete. Rows reuse the cards' JS hooks ([data-property-row], .property-select-checkbox,
    .property-status-toggle, .listing-insights, .delete-property, .mark-sold-property). The actions
    column stays pinned to the right edge when the table scrolls sideways.
--}}
<div class="portal-card p-0 pl-list">
    <div class="table-responsive">
        <table class="table mb-0 pl-list__table">
            <thead>
                <tr>
                    <th class="pl-list__check"></th>
                    <th>Listing</th>
                    <th>Details</th>
                    <th class="text-center">Quality</th>
                    <th>Agent</th>
                    <th class="text-center">Leads</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th class="pl-list__actions-col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($properties as $property)
                @php
                    $thumb = $property->galleryImages()[0]['url'] ?? null;
                    $quality = app(\App\Services\ListingQualityService::class)->score($property);
                    $addressLine = collect(['community', 'city'])->map(fn ($part) => $property->getTranslation($part))->filter()->implode(', ');
                    $cardAgent = $property->agent ?? ($property->owner?->type === 'agent' ? $property->owner : null);
                    $live = $property->canGoLive() || $property->isSold();
                    $offering = $property->filterLabel('listing_type') ?: ucfirst((string) $property->listing_type);
                    $type = $property->filterLabel('property_type');
                @endphp
                <tr class="portal-property-col" data-id="{{ $property->id }}" data-property-row>
                    <td class="pl-list__check"><input class="form-check-input property-select-checkbox" type="checkbox" value="{{ $property->id }}" aria-label="Select this property"></td>
                    <td>
                        <div class="pl-list__listing">
                            <img src="{{ $thumb ?: 'https://placehold.co/96x72?text=%20' }}" alt="" class="pl-list__thumb">
                            <div class="min-w-0">
                                <a href="{{ route($routePrefix . '.show', $property->id) }}" class="pl-list__title" data-property-title title="{{ $property->getTranslation('title') }}">{{ $property->getTranslation('title') }}</a>
                                <div class="pl-list__sub">
                                    <span>{{ $property->reference_no ?: '#' . $property->id }}</span>
                                    @if($property->featured)<span class="pl-list__tag is-premium"><i class="fas fa-star"></i> Premium</span>@endif
                                    @if(!$live)<span class="pl-list__tag pr-tone-{{ $property->compliance_status }}">{{ $property->complianceLabel() }}</span>@endif
                                </div>
                                @if($addressLine)<div class="pl-list__loc" title="{{ $addressLine }}"><i class="fas fa-location-dot"></i>{{ $addressLine }}</div>@endif
                            </div>
                        </div>
                    </td>
                    <td class="pl-list__details">
                        <div>{{ collect([$offering, $type])->filter()->implode(' · ') ?: '—' }}</div>
                        <div class="pl-list__muted">{{ collect([$property->bedrooms ? $property->bedrooms . ' bd' : null, $property->sqft ? number_format($property->sqft) . ' sqft' : null])->filter()->implode(' · ') ?: '—' }}</div>
                    </td>
                    <td class="text-center">
                        <button type="button" class="pq-ring pq-ring--inline is-{{ $quality['tone'] }} listing-insights" data-id="{{ $property->id }}" data-quality="{{ $property->id }}"
                                style="--pq: {{ $quality['score'] }};" aria-label="Quality score {{ $quality['score'] }} of 100"><span>{{ $quality['score'] }}%</span></button>
                        <template id="pqBreakdown{{ $property->id }}">
                            <div class="pq-pop-head"><span>Quality score</span><span class="pq-pop-score is-{{ $quality['tone'] }}"><i class="fas fa-gauge-high me-1"></i>{{ $quality['score'] }}/100</span></div>
                            @include('portal.properties._quality_breakdown', ['quality' => $quality])
                        </template>
                    </td>
                    <td><span class="pl-list__agent" title="{{ $cardAgent?->name }}">{{ $cardAgent?->name ?? 'Agency listing' }}</span></td>
                    <td class="text-center">
                        <button type="button" class="pl-list__leads listing-insights" data-id="{{ $property->id }}" title="Listing performance & leads">{{ $property->leads_count ?: '-' }}</button>
                    </td>
                    <td class="pl-list__price">{{ $property->price ? number_format($property->price) . ' ' . $property->currency : 'On request' }}</td>
                    <td>
                        <div class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                            <input class="form-check-input property-status-toggle m-0" type="checkbox" role="switch" id="statusToggleList{{ $property->id }}" data-id="{{ $property->id }}" @checked($property->status)>
                            <label class="form-check-label small fw-semibold status-toggle-label {{ $property->status ? 'text-success' : 'text-secondary' }}" for="statusToggleList{{ $property->id }}">{{ $property->status ? 'Active' : 'Inactive' }}</label>
                        </div>
                    </td>
                    <td class="pl-list__actions-col">
                        <div class="pl-list__actions">
                            <button type="button" class="pl-list__icon listing-insights" data-id="{{ $property->id }}" title="Listing performance" aria-label="Listing performance"><i class="fas fa-chart-line"></i></button>
                            <div class="dropdown">
                                <button type="button" class="pl-list__icon" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="More actions" aria-label="More actions"><i class="fas fa-ellipsis"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route($routePrefix . '.edit', $property->id) }}"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                    <li><a class="dropdown-item" href="{{ route($routePrefix . '.show', $property->id) }}"><i class="fas fa-eye me-2"></i>View</a></li>
                                    <li><button type="button" class="dropdown-item mark-sold-property" data-id="{{ $property->id }}"
                                            data-title="{{ $property->getTranslation('title') }}" data-ref="{{ $property->reference_no }}" data-thumb="{{ $thumb }}"
                                            data-price="{{ $property->price ? (float) $property->price : '' }}" data-currency="{{ $property->currency ?: 'AED' }}"
                                            data-type="{{ $property->listing_type === 'rent' ? 'rented' : 'sold' }}"><i class="fas fa-handshake me-2"></i>Mark as {{ $property->listing_type === 'rent' ? 'rented' : 'sold' }}</button></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><button type="button" class="dropdown-item text-danger delete-property" data-id="{{ $property->id }}"><i class="fas fa-trash me-2"></i>Delete</button></li>
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
// Row "More actions" menus: the table scrolls sideways (clips overflow) and every row's pinned actions
// cell stacks over the rows above it, so an open menu would be cut off and covered by the next rows.
// While open, the menu lives on <body>; it goes back to its row when closed.
(function () {
    document.querySelectorAll('.pl-list [data-bs-toggle="dropdown"]').forEach(toggle => {
        const home = toggle.parentElement;
        toggle.addEventListener('show.bs.dropdown', () => {
            const menu = home.querySelector('.dropdown-menu');
            if (menu) document.body.appendChild(menu);
            toggle._plMenu = menu;
        });
        toggle.addEventListener('hidden.bs.dropdown', () => {
            if (toggle._plMenu) home.appendChild(toggle._plMenu);
        });
    });
})();
</script>
@endpush
