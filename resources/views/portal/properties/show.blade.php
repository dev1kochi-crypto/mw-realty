@extends('portal.layouts.app')

@section('title', $property->getTranslation('title'))

@php
    $gallery = $property->galleryImages();
    $hasImages = count($gallery) > 0;
    // Same placeholder the listing cards use, so an image-less listing still gets a proper hero.
    $placeholder = 'https://placehold.co/1600x400?text=No+Image';
    $photos = $hasImages ? array_column($gallery, 'url') : [$placeholder];

    $detail = $property->details;
    $ownerLabel = $property->owner
        ? ($property->owner->type === 'company' ? $property->owner->displayName() : $property->owner->name)
        : 'MW Realty';
    $price = $property->price ? number_format($property->price) . ' ' . $property->currency : 'Price on request';
    $listingLabel = $property->filterLabel('listing_type');
    $address = collect(['address', 'community', 'city', 'country'])
        ->map(fn ($part) => $property->getTranslation($part))->filter()->implode(', ');
    $iconUrl = fn ($icon) => $icon ? (str_starts_with($icon, 'http') ? $icon : asset('storage/' . $icon)) : null;
    $chipLabel = fn ($row) => $row['label'][app()->getLocale()] ?? ($row['label']['en'] ?? reset($row['label']));

    $stats = array_filter([
        ['fa-bed', $property->bedrooms, 'Bedrooms'],
        ['fa-bath', $property->bathrooms, 'Bathrooms'],
        ['fa-ruler-combined', $property->sqft ? number_format($property->sqft) : null, 'Sq.ft'],
        ['fa-square-parking', $detail?->parking, 'Parking'],
        ['fa-warehouse', $detail?->garage, 'Garage'],
        ['fa-calendar-alt', $detail?->year_built, 'Year Built'],
    ], fn ($s) => filled($s[1]));

    $facts = array_filter([
        ['fa-tag', 'Listing Type', $listingLabel],
        ['fa-building', 'Property Type', $property->filterLabel('property_type')],
        ['fa-drafting-compass', 'Completion', $property->filterLabel('completion_status')],
        ['fa-layer-group', 'Category', $property->category ? ($property->filterLabel('category') ?: $property->category) : null],
        ['fa-map-signs', 'Location', $property->location ? ($property->filterLabel('location') ?: $property->location) : null],
        ['fa-couch', 'Furnishing', $detail ? ($detail->furnished ? 'Furnished' : 'Unfurnished') : null],
        ['fa-mountain', 'View', $detail?->view],
        ['fa-stairs', 'Floor', $detail?->floor],
        ['fa-user-check', 'Direct From Owner', $detail?->direct_from_owner],
        ['fa-shield-alt', 'Security Deposit', $detail?->security_deposit ? number_format($detail->security_deposit) . ' ' . $property->currency : null],
        ['fa-id-card', 'RERA ID', $property->rera_id],
        ['fa-hashtag', 'Reference', $property->reference_no],
        ['fa-envelope-open-text', 'Postal Code', $property->postal_code],
        ['fa-clock', 'Published', $property->published_at?->format('d M Y, h:i A')],
        ['fa-link', 'Slug', $property->slug],
        ['fa-sort-numeric-down', 'Display Order', $property->order_index],
    ], fn ($f) => filled($f[2]));

    $chipGroups = $detail ? array_filter([
        ['Amenities', 'fa-swimming-pool', $detail->amenities],
        ['Easy Access', 'fa-route', $detail->easy_access],
        ['Attributes', 'fa-list-check', $detail->property_attributes],
    ], fn ($g) => count($g[2])) : [];

    $nearbyGroups = $property->nearbyPlaces->groupBy(fn ($place) => $place->typeLabel() ?: 'Other');
    $hasMap = $property->latitude && $property->longitude;
    $seo = array_filter($property->metadata ?? [], fn ($v) => is_string($v) && trim($v) !== '');
@endphp

@push('styles')
<style>
    /* Fixed overall height so the hero can never grow to the photo's natural size. */
    .pd-hero { position: relative; display: grid; grid-template-columns: 2fr 1fr; grid-template-rows: repeat(2, minmax(0, 1fr)); gap: 0.6rem; height: 400px; border-radius: var(--portal-radius); overflow: hidden; }
    .pd-hero--single { grid-template-columns: 1fr; grid-template-rows: minmax(0, 1fr); height: 320px; }
    .pd-hero__item { position: relative; overflow: hidden; cursor: zoom-in; background: var(--portal-bg); border: 0; padding: 0; min-height: 0; }
    .pd-hero:not(.pd-hero--single) .pd-hero__item:first-child { grid-row: 1 / span 2; }
    .pd-hero__item img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
    .pd-hero__item:hover img { transform: scale(1.05); }
    .pd-hero__more { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(26, 51, 87, 0.55); color: #fff; font-weight: 700; font-size: 1.1rem; }
    .pd-hero__badges { position: absolute; top: 1rem; left: 1rem; display: flex; gap: 0.5rem; z-index: 2; pointer-events: none; }
    .pd-pill { padding: 0.35rem 0.85rem; border-radius: 50px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; background: rgba(255, 255, 255, 0.94); color: var(--portal-primary-dark); box-shadow: 0 4px 12px rgba(31, 35, 64, 0.15); }
    .pd-pill--featured { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #fff; }
    .pd-pill--active { background: #16a34a; color: #fff; }
    .pd-pill--inactive { background: #6b7280; color: #fff; }
    .pd-hero__count { position: absolute; right: 1rem; bottom: 1rem; z-index: 2; pointer-events: none; }

    .pd-head { background: var(--portal-surface); border-radius: var(--portal-radius); box-shadow: var(--portal-shadow); border: 1px solid var(--portal-border); padding: 1.5rem; margin-top: -3rem; position: relative; z-index: 3; margin-inline: 1.25rem; }
    .pd-head__title { font-size: 1.6rem; font-weight: 800; color: var(--portal-text); margin: 0; }
    .pd-head__address { color: var(--portal-muted); margin: 0.35rem 0 0; }
    .pd-head__address i { color: var(--portal-accent); }
    .pd-head__price-label { font-size: 0.75rem; color: var(--portal-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
    .pd-head__price { font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg, var(--portal-primary), var(--portal-accent)); -webkit-background-clip: text; background-clip: text; color: transparent; line-height: 1.1; }

    .pd-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.75rem; margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px dashed var(--portal-border); }
    .pd-stat { display: flex; align-items: center; gap: 0.75rem; }
    .pd-stat__icon { width: 2.6rem; height: 2.6rem; flex-shrink: 0; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(36, 67, 115, 0.08); color: var(--portal-primary); font-size: 1.05rem; }
    .pd-stat__value { font-weight: 800; color: var(--portal-text); line-height: 1.1; }
    .pd-stat__label { font-size: 0.75rem; color: var(--portal-muted); }

    .pd-card { background: var(--portal-surface); border-radius: var(--portal-radius); box-shadow: var(--portal-shadow); border: 1px solid var(--portal-border); padding: 1.5rem; margin-bottom: 1.5rem; }
    .pd-card__title { display: flex; align-items: center; gap: 0.6rem; font-weight: 800; font-size: 1.05rem; color: var(--portal-text); margin-bottom: 1.1rem; }
    .pd-card__title::before { content: ''; width: 4px; height: 1.1rem; border-radius: 4px; background: linear-gradient(180deg, var(--portal-primary), var(--portal-accent)); }

    .pd-highlights { list-style: none; padding: 0; margin: 0 0 1.25rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.5rem; }
    .pd-highlights li { display: flex; gap: 0.5rem; align-items: flex-start; background: var(--portal-bg); border-radius: 10px; padding: 0.55rem 0.8rem; font-weight: 600; font-size: 0.9rem; }
    .pd-highlights i { color: #16a34a; margin-top: 0.2rem; }

    .pd-rich { color: #3b3f5c; line-height: 1.75; overflow-wrap: anywhere; }
    .pd-rich h2, .pd-rich h3, .pd-rich h4 { font-size: 1.1rem; font-weight: 700; color: var(--portal-text); margin: 1.25rem 0 0.5rem; }
    .pd-rich p { margin-bottom: 0.9rem; }
    .pd-rich ul, .pd-rich ol { padding-left: 1.2rem; }
    .pd-rich table { width: 100%; margin-bottom: 1rem; }
    .pd-rich td, .pd-rich th { border: 1px solid var(--portal-border); padding: 0.4rem 0.6rem; }
    .pd-rich.is-collapsed { max-height: 320px; overflow: hidden; -webkit-mask-image: linear-gradient(#000 70%, transparent); mask-image: linear-gradient(#000 70%, transparent); }

    .pd-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.75rem; }
    .pd-fact { display: flex; gap: 0.75rem; align-items: center; padding: 0.75rem; border: 1px solid var(--portal-border); border-radius: 12px; }
    .pd-fact i { width: 2.2rem; height: 2.2rem; flex-shrink: 0; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: rgba(202, 40, 68, 0.08); color: var(--portal-accent); }
    .pd-fact__label { font-size: 0.72rem; color: var(--portal-muted); text-transform: uppercase; letter-spacing: 0.04em; }
    .pd-fact__value { font-weight: 700; color: var(--portal-text); word-break: break-word; }

    .pd-chips { display: flex; flex-wrap: wrap; gap: 0.6rem; }
    .pd-chip { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.9rem; border-radius: 50px; background: var(--portal-bg); border: 1px solid var(--portal-border); font-weight: 600; font-size: 0.88rem; color: var(--portal-text); }
    .pd-chip img { width: 20px; height: 20px; object-fit: contain; }
    .pd-chip i { color: var(--portal-primary); }

    .pd-plan { border: 1px solid var(--portal-border); border-radius: 14px; overflow: hidden; height: 100%; }
    .pd-plan img { width: 100%; height: 180px; object-fit: contain; background: var(--portal-bg); }
    .pd-plan__body { padding: 0.9rem 1rem; }

    .pd-map { width: 100%; height: 260px; border: 0; border-radius: 12px; }

    .pd-agent { display: flex; gap: 0.9rem; align-items: center; }
    .pd-agent__avatar { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; flex-shrink: 0; background: linear-gradient(135deg, var(--portal-primary), var(--portal-accent)); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.3rem; }
    .pd-contact { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem; }
    .pd-contact a { display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.8rem; border-radius: 10px; background: var(--portal-bg); color: var(--portal-text); text-decoration: none; font-weight: 600; font-size: 0.88rem; word-break: break-all; }
    .pd-contact a:hover { background: rgba(36, 67, 115, 0.08); }
    .pd-contact i { color: var(--portal-primary); width: 1rem; text-align: center; }

    .pd-sticky { position: sticky; top: 1.5rem; }
    .pd-seo dt { font-size: 0.72rem; color: var(--portal-muted); text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; }
    .pd-seo dd { margin-bottom: 0.75rem; word-break: break-word; }

    @media (max-width: 767.98px) {
        .pd-hero, .pd-hero--single { grid-template-columns: 1fr; grid-template-rows: minmax(0, 1fr); height: 240px; }
        .pd-hero__item:not(:first-child) { display: none; }
        .pd-hero:not(.pd-hero--single) .pd-hero__item:first-child { grid-row: auto; }
        .pd-head { margin-inline: 0; margin-top: 1rem; }
        .pd-head__title { font-size: 1.3rem; }
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <nav class="portal-muted small">
        <a href="{{ route('portal.properties.index') }}" class="text-decoration-none">Properties</a>
        <i class="fas fa-chevron-right mx-1" style="font-size: 0.65rem;"></i>
        <span>{{ $property->reference_no }}</span>
    </nav>
    <div class="d-flex gap-2">
        <a href="{{ route('portal.properties.index') }}" class="btn btn-portal-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
        <a href="{{ route('portal.properties.edit', $property->id) }}" class="btn btn-portal-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
    </div>
</div>

{{-- Hero gallery --}}
<div class="pd-hero {{ count($photos) < 3 ? 'pd-hero--single' : '' }}">
    @foreach(array_slice($photos, 0, count($photos) < 3 ? 1 : 3) as $i => $url)
    <button type="button" class="pd-hero__item" data-bs-toggle="modal" data-bs-target="#pdGalleryModal" data-slide="{{ $i }}">
        <img src="{{ $url }}" alt="{{ $property->getTranslation('title') }}">
        @if($i === 2 && count($photos) > 3)
        <span class="pd-hero__more">+{{ count($photos) - 3 }} photos</span>
        @endif
    </button>
    @endforeach
    <div class="pd-hero__badges">
        @if($listingLabel)<span class="pd-pill">{{ $listingLabel }}</span>@endif
        <span class="pd-pill {{ $property->status ? 'pd-pill--active' : 'pd-pill--inactive' }}">{{ $property->status ? 'Active' : 'Inactive' }}</span>
        @if($property->featured)<span class="pd-pill pd-pill--featured"><i class="fas fa-star me-1"></i>Featured</span>@endif
    </div>
    @if($hasImages)
    <span class="pd-pill pd-hero__count"><i class="fas fa-images me-1"></i>{{ count($photos) }}</span>
    @endif
</div>

{{-- Title / price / stats --}}
<div class="pd-head mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div class="flex-grow-1" style="min-width: 0;">
            <h1 class="pd-head__title">{{ $property->getTranslation('title') ?: 'Untitled property' }}</h1>
            <p class="pd-head__address"><i class="fas fa-map-marker-alt me-1"></i>{{ $address ?: '—' }}</p>
        </div>
        <div class="text-md-end">
            <div class="pd-head__price-label">{{ $listingLabel ? $listingLabel . ' price' : 'Price' }}</div>
            <div class="pd-head__price">{{ $price }}</div>
        </div>
    </div>
    @if(count($stats))
    <div class="pd-stats">
        @foreach($stats as [$icon, $value, $label])
        <div class="pd-stat">
            <span class="pd-stat__icon"><i class="fas {{ $icon }}"></i></span>
            <div><div class="pd-stat__value">{{ $value }}</div><div class="pd-stat__label">{{ $label }}</div></div>
        </div>
        @endforeach
    </div>
    @endif
</div>

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Overview (per language) --}}
        <div class="pd-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div class="pd-card__title mb-0">Overview</div>
                @if($languages->count() > 1)
                <ul class="nav portal-lang-tabs" role="tablist">
                    @foreach($languages as $lang)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#show-lang-{{ $lang->code }}" type="button" role="tab">{{ $lang->name }}</button>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
            <div class="tab-content">
                @foreach($languages as $lang)
                @php
                    $t = $property->translations[$lang->code] ?? [];
                    $highlights = collect(preg_split('/\r\n|\r|\n/', (string) ($t['key_features'] ?? '')))->map(fn ($l) => trim($l, " \t-•*"))->filter();
                    $description = \App\Support\SafeHtml::clean($t['description'] ?? null);
                @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="show-lang-{{ $lang->code }}" role="tabpanel" @if($lang->code === 'ar') dir="rtl" @endif>
                    @if($languages->count() > 1 && !empty($t['title']))
                    <h5 class="fw-bold mb-3">{{ $t['title'] }}</h5>
                    @endif

                    @if($highlights->isNotEmpty())
                    <ul class="pd-highlights">
                        @foreach($highlights as $line)
                        <li><i class="fas fa-check-circle"></i><span>{{ $line }}</span></li>
                        @endforeach
                    </ul>
                    @endif

                    @if($description !== '')
                    <div class="pd-rich is-collapsed" data-rich>{!! $description !!}</div>
                    <button type="button" class="btn btn-link p-0 fw-bold text-decoration-none d-none" data-rich-toggle>Read more <i class="fas fa-chevron-down ms-1"></i></button>
                    @else
                    <p class="portal-muted mb-0">No description added for this language.</p>
                    @endif

                    <div class="row g-3 mt-3 pt-3 border-top">
                        @foreach(['address' => 'Address', 'community' => 'Community', 'city' => 'City', 'country' => 'Country'] as $key => $label)
                        <div class="col-6 col-md-3"><div class="pd-fact__label">{{ $label }}</div><div class="pd-fact__value">{{ $t[$key] ?? '—' }}</div></div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Property details --}}
        @if(count($facts))
        <div class="pd-card">
            <div class="pd-card__title">Property Details</div>
            <div class="pd-facts">
                @foreach($facts as [$icon, $label, $value])
                <div class="pd-fact">
                    <i class="fas {{ $icon }}"></i>
                    <div style="min-width: 0;"><div class="pd-fact__label">{{ $label }}</div><div class="pd-fact__value">{{ $value }}</div></div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Amenities / Easy Access / Attributes --}}
        @foreach($chipGroups as [$label, $fallbackIcon, $rows])
        <div class="pd-card">
            <div class="pd-card__title">{{ $label }}</div>
            <div class="pd-chips">
                @foreach($rows as $row)
                <span class="pd-chip">
                    @if($iconUrl($row['icon']))<img src="{{ $iconUrl($row['icon']) }}" alt="">@else<i class="fas {{ $fallbackIcon }}"></i>@endif
                    {{ $chipLabel($row) }}
                </span>
                @endforeach
            </div>
        </div>
        @endforeach

        {{-- Floor Plans --}}
        @if($property->floorPlans->count() || $detail?->floor_plan_file)
        <div class="pd-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="pd-card__title mb-0">Floor Plans</div>
                @if($detail?->floor_plan_file)
                <a href="{{ asset('storage/' . $detail->floor_plan_file) }}" target="_blank" class="btn btn-portal-light btn-sm"><i class="fas fa-file-download me-1"></i>Download</a>
                @endif
            </div>
            <div class="row g-3">
                @foreach($property->floorPlans as $plan)
                <div class="col-md-6">
                    <div class="pd-plan">
                        @if($plan->image)
                        <a href="{{ asset('storage/' . $plan->image) }}" target="_blank"><img src="{{ asset('storage/' . $plan->image) }}" alt="{{ $plan->label }}"></a>
                        @endif
                        <div class="pd-plan__body">
                            <div class="fw-bold">{{ $plan->label }}</div>
                            <div class="portal-muted small">
                                @if($plan->size_from || $plan->size_to)<i class="fas fa-ruler-combined me-1"></i>{{ $plan->size_from }}@if($plan->size_to) &ndash; {{ $plan->size_to }}@endif sq.ft @endif
                                @if($plan->price_from || $plan->price_to)<span class="ms-2"><i class="fas fa-tag me-1"></i>{{ number_format($plan->price_from) }}@if($plan->price_to) &ndash; {{ number_format($plan->price_to) }}@endif {{ $property->currency }}</span>@endif
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Location & Nearby --}}
        @if($hasMap || $nearbyGroups->isNotEmpty())
        <div class="pd-card">
            <div class="pd-card__title">Location &amp; Nearby</div>
            @if($hasMap)
            <iframe class="pd-map mb-3" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                src="https://maps.google.com/maps?q={{ $property->latitude }},{{ $property->longitude }}&z=15&output=embed"></iframe>
            <div class="portal-muted small mb-3"><i class="fas fa-crosshairs me-1"></i>{{ $property->latitude }}, {{ $property->longitude }}</div>
            @endif
            @foreach($nearbyGroups as $type => $places)
            <div class="mb-3">
                <div class="pd-fact__label mb-2">{{ $type }}</div>
                <div class="pd-chips">
                    @foreach($places as $place)
                    <span class="pd-chip"><i class="fas fa-map-pin"></i>{{ $place->getTranslation('name') ?? '—' }}</span>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="pd-sticky">
            {{-- Agent --}}
            <div class="pd-card">
                <div class="pd-card__title">Listing Agent</div>
                @if($agent = $property->agent)
                    <div class="pd-agent">
                        @if($agent->avatar)
                        <img src="{{ asset('storage/' . $agent->avatar) }}" class="pd-agent__avatar" alt="">
                        @else
                        <span class="pd-agent__avatar">{{ strtoupper(mb_substr($agent->name, 0, 1)) }}</span>
                        @endif
                        <div style="min-width: 0;">
                            <div class="fw-bold">{{ $agent->name }}</div>
                            @if($agent->company)<div class="portal-muted small"><i class="fas fa-building me-1"></i>{{ $agent->company->displayName() }}</div>@endif
                            @if($agent->brn_number)<div class="portal-muted small">BRN: {{ $agent->brn_number }}</div>@endif
                        </div>
                    </div>
                    <div class="pd-contact">
                        @if($agent->phone)<a href="tel:{{ $agent->phone }}"><i class="fas fa-phone"></i>{{ $agent->phone }}</a>@endif
                        @if($agent->whatsapp_number)<a href="https://wa.me/{{ preg_replace('/\D/', '', $agent->whatsapp_number) }}" target="_blank"><i class="fab fa-whatsapp"></i>{{ $agent->whatsapp_number }}</a>@endif
                        @if($agent->email)<a href="mailto:{{ $agent->email }}"><i class="fas fa-envelope"></i>{{ $agent->email }}</a>@endif
                    </div>
                @else
                    <div class="portal-muted"><i class="fas fa-user-tie me-2"></i>No agent assigned</div>
                @endif
                @if($isAdmin)
                <div class="portal-muted small mt-3 pt-3 border-top"><i class="fas fa-user-shield me-1"></i>Listed by: <span class="fw-semibold">{{ $ownerLabel }}</span></div>
                @endif
            </div>

            @if($detail?->virtual_tour_url)
            <a href="{{ $detail->virtual_tour_url }}" target="_blank" rel="noopener" class="btn btn-portal-primary w-100 mb-4"><i class="fas fa-vr-cardboard me-2"></i>Take the Virtual Tour</a>
            @endif

            {{-- Listing info --}}
            <div class="pd-card">
                <div class="pd-card__title">Listing Info</div>
                <dl class="pd-seo mb-0">
                    <dt>Created</dt><dd>{{ $property->created_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                    <dt>Last Updated</dt><dd>{{ $property->updated_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                    <dt>Photos</dt><dd>{{ $hasImages ? count($gallery) : 'None uploaded' }}</dd>
                </dl>
            </div>

            {{-- SEO --}}
            <div class="pd-card">
                <div class="pd-card__title">SEO</div>
                @if(count($seo))
                <dl class="pd-seo mb-0">
                    @foreach(['meta_title' => 'Meta Title', 'meta_description' => 'Meta Description', 'meta_keywords' => 'Keywords', 'og_title' => 'OG Title', 'og_description' => 'OG Description', 'canonical_url' => 'Canonical URL', 'robots' => 'Robots'] as $key => $label)
                        @if(!empty($seo[$key]))<dt>{{ $label }}</dt><dd>{{ $seo[$key] }}</dd>@endif
                    @endforeach
                </dl>
                @else
                <div class="portal-muted small">No custom SEO set — defaults are generated from the listing.</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Lightbox --}}
<div class="modal fade" id="pdGalleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 py-2">
                <span class="text-white small" id="pdGalleryCounter"></span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="pdGalleryCarousel" class="carousel slide">
                    <div class="carousel-inner">
                        @foreach($photos as $i => $url)
                        <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                            <img src="{{ $url }}" class="d-block w-100" style="max-height: 75vh; object-fit: contain;" alt="">
                        </div>
                        @endforeach
                    </div>
                    @if(count($photos) > 1)
                    <button class="carousel-control-prev" type="button" data-bs-target="#pdGalleryCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                    <button class="carousel-control-next" type="button" data-bs-target="#pdGalleryCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        // Lightbox: open on the clicked photo and keep the "3 / 12" counter in sync.
        const modal = document.getElementById('pdGalleryModal');
        const carouselEl = document.getElementById('pdGalleryCarousel');
        const counter = document.getElementById('pdGalleryCounter');
        const total = carouselEl.querySelectorAll('.carousel-item').length;
        const carousel = bootstrap.Carousel.getOrCreateInstance(carouselEl, { interval: false });
        const setCounter = i => { counter.textContent = (i + 1) + ' / ' + total; };

        modal.addEventListener('show.bs.modal', function (e) {
            const index = parseInt(e.relatedTarget?.dataset.slide || '0', 10);
            carousel.to(index);
            setCounter(index);
        });
        carouselEl.addEventListener('slid.bs.carousel', e => setCounter(e.to));

        // "Read more" only for descriptions long enough to be clipped.
        document.querySelectorAll('[data-rich]').forEach(function (el) {
            const btn = el.nextElementSibling;
            const check = () => {
                if (!el.offsetParent || !el.classList.contains('is-collapsed')) return; // hidden tab: measure once shown
                if (el.scrollHeight <= el.clientHeight + 10) { el.classList.remove('is-collapsed'); btn.classList.add('d-none'); }
                else btn.classList.remove('d-none');
            };
            check();
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(t => t.addEventListener('shown.bs.tab', check));
            btn.addEventListener('click', function () {
                const collapsed = el.classList.toggle('is-collapsed');
                btn.innerHTML = collapsed ? 'Read more <i class="fas fa-chevron-down ms-1"></i>' : 'Show less <i class="fas fa-chevron-up ms-1"></i>';
            });
        });
    })();
</script>
@endpush
