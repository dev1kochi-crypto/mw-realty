@extends('portal.layouts.app')

@section('title', $property->getTranslation('title'))

@php
    $gallery = $property->galleryImages();
    $detail = $property->details;
    $ownerLabel = $property->owner
        ? ($property->owner->type === 'company' ? $property->owner->displayName() : $property->owner->name)
        : 'MW Realty';
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <div class="portal-section-title mb-1">{{ $property->getTranslation('title') }}</div>
        <div class="portal-muted small">
            Ref: {{ $property->reference_no }}
            <span class="portal-badge-status {{ $property->status ? 'portal-badge-active' : 'portal-badge-inactive' }} ms-2">{{ $property->status ? 'Active' : 'Inactive' }}</span>
            @if($property->featured)<span class="badge bg-warning text-dark ms-1"><i class="fas fa-star"></i> Featured</span>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('portal.properties.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
        <a href="{{ route('portal.properties.edit', $property->id) }}" class="btn btn-portal-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Gallery --}}
        <div class="portal-card p-3 mb-4">
            @if(count($gallery))
                <div class="row g-2">
                    <div class="col-8">
                        <img src="{{ $gallery[0]['url'] }}" class="w-100 h-100 rounded" style="object-fit: cover; max-height: 420px;" alt="">
                    </div>
                    <div class="col-4">
                        <div class="row g-2">
                            @foreach(array_slice($gallery, 1, 4) as $img)
                            <div class="col-12">
                                <img src="{{ $img['url'] }}" class="w-100 rounded" style="object-fit: cover; height: 98px;" alt="">
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center text-muted py-5">No images uploaded for this listing yet.</div>
            @endif
        </div>

        {{-- Per-language content --}}
        <div class="portal-card p-4 mb-4">
            <ul class="nav portal-lang-tabs mb-4" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#show-lang-{{ $lang->code }}" type="button" role="tab">{{ $lang->name }}</button>
                </li>
                @endforeach
            </ul>
            <div class="tab-content">
                @foreach($languages as $lang)
                @php $t = $property->translations[$lang->code] ?? []; @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="show-lang-{{ $lang->code }}" role="tabpanel">
                    <h4>{{ $t['title'] ?? '—' }}</h4>
                    <p class="portal-muted mb-3"><i class="fas fa-map-marker-alt me-1"></i>{{ $t['address'] ?? '—' }}</p>
                    @if(!empty($t['key_features']))
                    <p class="fw-semibold mb-2">{{ $t['key_features'] }}</p>
                    @endif
                    @if(!empty($t['description']))
                    <p style="white-space: pre-line;">{{ $t['description'] }}</p>
                    @endif
                    <div class="row g-3 mt-2">
                        <div class="col-md-4"><span class="portal-muted small d-block">Community</span>{{ $t['community'] ?? '—' }}</div>
                        <div class="col-md-4"><span class="portal-muted small d-block">City</span>{{ $t['city'] ?? '—' }}</div>
                        <div class="col-md-4"><span class="portal-muted small d-block">Country</span>{{ $t['country'] ?? '—' }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Amenities / Easy Access / Attributes --}}
        @if($detail && (count($detail->amenities) || count($detail->easy_access) || count($detail->property_attributes)))
        <div class="portal-card p-4 mb-4">
            @foreach([['Amenities', $detail->amenities], ['Easy Access', $detail->easy_access], ['Attributes', $detail->property_attributes]] as [$label, $rows])
                @if(count($rows))
                <div class="mb-3">
                    <div class="fw-bold mb-2">{{ $label }}</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($rows as $row)
                        <span class="badge bg-light text-dark border">
                            @if($row['icon'])<i class="{{ $row['icon'] }} me-1"></i>@endif
                            {{ $row['label'][app()->getLocale()] ?? ($row['label']['en'] ?? reset($row['label'])) }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach
        </div>
        @endif

        {{-- Floor Plans --}}
        @if($property->floorPlans->count())
        <div class="portal-card p-4 mb-4">
            <div class="fw-bold mb-3">Floor Plans</div>
            <div class="row g-3">
                @foreach($property->floorPlans as $plan)
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        @if($plan->image)<img src="{{ asset('storage/' . $plan->image) }}" class="w-100 rounded mb-2" style="max-height: 160px; object-fit: cover;" alt="">@endif
                        <div class="fw-semibold">{{ $plan->label }}</div>
                        <div class="portal-muted small">
                            @if($plan->size_from || $plan->size_to){{ $plan->size_from }}&ndash;{{ $plan->size_to }} sqft @endif
                            @if($plan->price_from || $plan->price_to)&middot; {{ number_format($plan->price_from) }}&ndash;{{ number_format($plan->price_to) }} {{ $property->currency }}@endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Nearby Places --}}
        @if($property->nearbyPlaces->count())
        <div class="portal-card p-4">
            <div class="fw-bold mb-3">Nearby Places</div>
            <div class="d-flex flex-wrap gap-2">
                @foreach($property->nearbyPlaces as $place)
                <span class="badge bg-light text-dark border"><i class="fas fa-map-pin me-1"></i>{{ $place->getTranslation('name') ?? $place->name ?? '—' }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        {{-- Key Facts --}}
        <div class="portal-card p-4 mb-4">
            <div class="fw-bold mb-3">Key Facts</div>
            <div class="portal-property-card__price-row mb-3">
                <span class="portal-property-card__price fs-5">{{ $property->price ? number_format($property->price) . ' ' . $property->currency : 'Price on request' }}</span>
            </div>
            <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                <li><i class="fas fa-tag me-2 text-muted"></i>{{ $property->filterLabel('listing_type') ?: '—' }}</li>
                <li><i class="fas fa-building me-2 text-muted"></i>{{ $property->filterLabel('property_type') ?: '—' }}</li>
                <li><i class="fas fa-drafting-compass me-2 text-muted"></i>{{ $property->filterLabel('completion_status') ?: '—' }}</li>
                @if($property->category)<li><i class="fas fa-layer-group me-2 text-muted"></i>{{ $property->filterLabel('category') ?: $property->category }}</li>@endif
                <li><i class="fas fa-map-marker-alt me-2 text-muted"></i>{{ $property->filterLabel('location') ?: ($property->location ?: '—') }}</li>
                @if($property->bedrooms)<li><i class="fas fa-bed me-2 text-muted"></i>{{ $property->bedrooms }} Bedrooms</li>@endif
                @if($property->bathrooms)<li><i class="fas fa-bath me-2 text-muted"></i>{{ $property->bathrooms }} Bathrooms</li>@endif
                @if($property->sqft)<li><i class="fas fa-ruler-combined me-2 text-muted"></i>{{ number_format($property->sqft) }} sq.ft</li>@endif
                @if($detail?->year_built)<li><i class="fas fa-calendar me-2 text-muted"></i>Built {{ $detail->year_built }}</li>@endif
                @if($detail?->floor)<li><i class="fas fa-building me-2 text-muted"></i>Floor {{ $detail->floor }}</li>@endif
                @if($detail?->parking)<li><i class="fas fa-square-parking me-2 text-muted"></i>{{ $detail->parking }} Parking</li>@endif
                @if($detail)<li><i class="fas fa-couch me-2 text-muted"></i>{{ $detail->furnished ? 'Furnished' : 'Unfurnished' }}</li>@endif
                @if($property->rera_id)<li><i class="fas fa-id-card me-2 text-muted"></i>RERA: {{ $property->rera_id }}</li>@endif
            </ul>
        </div>

        {{-- Agent & Agency --}}
        <div class="portal-card p-4 mb-4">
            <div class="fw-bold mb-3">Agent &amp; Agency</div>
            @if($property->agent)
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fas fa-user-tie text-muted"></i>
                    <div>
                        <div class="fw-semibold">{{ $property->agent->name }}</div>
                        @if($property->agent->company)<div class="portal-muted small">{{ $property->agent->company->displayName() }}</div>@endif
                    </div>
                </div>
            @else
                <div class="portal-muted small mb-2"><i class="fas fa-user-tie me-2"></i>No agent assigned</div>
            @endif
            @if($isAdmin)
            <div class="portal-muted small mt-2 pt-2 border-top">Listed by: {{ $ownerLabel }}</div>
            @endif
        </div>

        @if($detail?->virtual_tour_url)
        <div class="portal-card p-4">
            <a href="{{ $detail->virtual_tour_url }}" target="_blank" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-vr-cardboard me-1"></i>View Virtual Tour</a>
        </div>
        @endif
    </div>
</div>
@endsection
