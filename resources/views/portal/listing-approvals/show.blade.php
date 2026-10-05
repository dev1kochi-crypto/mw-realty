@extends('portal.layouts.app')

@section('title', 'Listing Permit')

@include('portal.listing-approvals._styles')

@section('content')
@php
    $P = \App\Models\Property::class;
    $expired = fn ($date) => $date && $date->lt(today());
    $thumb = $property->galleryImages()[0]['url'] ?? null;
    $R = \App\Support\PermitRules::class;
    $permitType = $property->permit_type;
    $needsPermit = $R::requiresPermit($permitType);
    $issuer = $R::issuer($permitType) ?? 'Permit';
    $emirateLabel = \App\Models\FilterValue::whereHas('filter', fn ($q) => $q->where('key', \App\Models\Filter::EMIRATE_KEY))
        ->where('value', $property->emirate)->first()?->getTranslation('label') ?? ($property->emirate ? \Illuminate\Support\Str::headline($property->emirate) : '—');
    $verifiedOnline = $property->permit_verified_at && in_array($property->permit_verified_via, ['dld', 'adrec'], true);
    $checks = $needsPermit ? array_filter([
        "{$issuer} permit number" => (bool) $property->permit_number,
        'Permit valid (not expired)' => $property->permit_expires_at && !$expired($property->permit_expires_at),
        'Permit QR code' => $R::requiresQr($permitType) ? (bool) $property->permit_qr : null,
        'Permit verified' => $R::validates($permitType) ? (bool) $property->permit_verified_at : null,
    ], fn ($v) => $v !== null) : ['No permit needed (' . ($R::TYPES[$permitType]['label'] ?? 'not set') . ')' => (bool) $permitType];
    // What the authority's record says vs what the ad says (only when the permit was verified online).
    $permitData = $property->permit_data ?? [];
    $optionLabel = fn (string $key, ?string $value) => $value === null ? null
        : (\App\Models\FilterValue::whereHas('filter', fn ($q) => $q->where('key', $key))->where('value', $value)->first()?->getTranslation('label') ?? $value);
@endphp

<a href="{{ route('portal.listing-approvals.index', ['tab' => $property->compliance_status]) }}" class="small text-decoration-none d-inline-block mb-2"><i class="fas fa-arrow-left me-1"></i>Back to {{ strtolower($property->complianceLabel()) }}</a>

<div class="portal-card p-3 p-md-4 mb-3 la-tone-{{ $property->compliance_status }}">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <img src="{{ $thumb ?: 'https://placehold.co/192x144?text=%20' }}" alt="" class="la-thumb" style="width: 96px; height: 72px;">
        <div class="flex-grow-1 min-w-0">
            <div class="portal-section-title mb-1 text-truncate">{{ $property->getTranslation('title') ?: 'Property' }}</div>
            <div class="la-sub">{{ $property->reference_no }} &middot; {{ $property->owner?->displayName() ?? 'MW Realty' }}@if($property->agent && $property->agent_id !== $property->portal_user_id) &middot; Agent: {{ $property->agent->name }}@endif</div>
        </div>
        <span class="la-chip" style="background: var(--tone-soft); color: var(--tone); font-size: .85rem; padding: 6px 14px;"><i class="fas fa-file-shield"></i>{{ $property->complianceLabel() }}</span>
        <a href="{{ route($routePrefix . '.show', $property->id) }}" class="btn btn-sm portal-btn-ghost" target="_blank"><i class="fas fa-eye me-1"></i>Full listing</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="portal-card p-4 mb-3">
            <div class="la-section-title"><i class="fas fa-file-shield"></i>{{ $needsPermit ? "{$issuer} Advertising Permit" : 'Advertising Permit' }}</div>
            <div class="row g-3 mb-3">
                <div class="col-sm-4"><div class="la-field-label">Emirate</div><div class="la-field-value">{{ $emirateLabel }}</div></div>
                <div class="col-sm-4"><div class="la-field-label">Permit type</div><div class="la-field-value">{{ $R::TYPES[$permitType]['label'] ?? '—' }}@if($property->permit_city) · {{ $R::NORTHERN_CITIES[$property->permit_city] ?? $property->permit_city }}@endif</div></div>
                @if($needsPermit && ($R::TYPES[$permitType]['license'] ?? null))
                <div class="col-sm-4"><div class="la-field-label">{{ $R::TYPES[$permitType]['license_label'] }}</div><div class="la-field-value">{{ $property->permit_license_no ?: ($R::license($permitType, $property->owner)['number'] ?? '—') }}</div></div>
                @endif
            </div>
            @if(!$needsPermit)
            <div class="alert alert-light border small mb-0"><i class="fas fa-circle-info me-1"></i>
                {{ $permitType === 'none' ? 'DIFC / JAFZA free-zone listing — no advertising permit is issued.' : 'No advertising permit is needed for this location.' }}
                The listing can be live without a permit.</div>
            @else
            @if($R::validates($permitType))
            <div class="alert {{ $property->permit_verified_at ? 'alert-success' : 'alert-warning' }} small d-flex gap-2 align-items-start">
                <i class="fas {{ $property->permit_verified_at ? 'fa-circle-check' : 'fa-triangle-exclamation' }} mt-1"></i>
                <div>
                    @if($verifiedOnline)
                    <strong>Verified with {{ $issuer }}</strong> on {{ $property->permit_verified_at->format('d M Y, H:i') }} — the details below come from {{ $issuer }}'s record.
                    @elseif($property->permit_verified_at)
                    <strong>Verified</strong> on {{ $property->permit_verified_at->format('d M Y') }} (approved before online validation).
                    @else
                    <strong>Not verified.</strong> The agency / agent hasn't validated this permit with {{ $issuer }} yet, so the listing is not on the website. It goes live by itself once they click Validate in the property form.
                    @endif
                </div>
            </div>
            @endif
            <div class="row g-4 align-items-start">
                <div class="col-sm-auto text-center">
                    @if($property->permit_qr)
                    <a href="{{ media_url($property->permit_qr) }}" target="_blank" rel="noopener"><img src="{{ media_url($property->permit_qr) }}" alt="Permit QR" class="la-qr"></a>
                    <div class="la-sub mt-1">Scan to open {{ $issuer }}'s record</div>
                    @else
                    <div class="la-qr d-grid place-items-center text-center la-sub" style="place-items: center;">No QR uploaded</div>
                    @endif
                </div>
                <div class="col">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="la-field-label">Permit number</div>
                            <div class="la-field-value fs-5">{{ $property->permit_number ?: '—' }}</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="la-field-label">Expires</div>
                            <div class="la-field-value fs-5 {{ $expired($property->permit_expires_at) ? 'text-danger' : '' }}">{{ $property->permit_expires_at?->format('d M Y') ?? '—' }}</div>
                            @if($property->permit_expires_at && !$expired($property->permit_expires_at))<div class="la-sub">{{ $property->permit_expires_at->diffForHumans() }}</div>@endif
                        </div>
                        <div class="col-12">
                            <div class="la-field-label">Verification link</div>
                            @if($property->permit_verification_url)
                            <a href="{{ $property->permit_verification_url }}" target="_blank" rel="noopener noreferrer" class="text-break small">{{ $property->permit_verification_url }}</a>
                            @else
                            <span class="la-sub">Not provided — use the QR or the {{ $issuer }} website.</span>
                            @endif
                        </div>
                        @if($permitType === 'rera')
                        <div class="col-12">
                            <a href="https://dubailand.gov.ae/en/eservices/validate-real-estate-licenses-and-permits/" target="_blank" rel="noopener" class="btn btn-sm portal-btn-ghost"><i class="fas fa-shield-halved me-1"></i>Check permit number on DLD</a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>


        <div class="portal-card p-4 mb-3">
            <div class="la-section-title"><i class="fas fa-code-compare"></i>Does the ad match the permit?</div>
            @if($permitData)
            {{-- Verified online: the authority's record next to the ad, mismatches highlighted. --}}
            <p class="la-sub mb-3">{{ $issuer }}'s record for this permit, next to what the ad says.</p>
            <div class="table-responsive">
                <table class="table table-sm small align-middle mb-0">
                    <thead><tr><th></th><th>{{ $issuer }} permit</th><th>This ad</th></tr></thead>
                    <tbody>
                        @foreach([
                            'Category' => [$optionLabel('category', $permitData['category'] ?? null), $optionLabel('category', $property->category)],
                            'Purpose' => [$optionLabel('listing_type', $permitData['listing_type'] ?? null), $optionLabel('listing_type', $property->listing_type)],
                            'Type' => [$optionLabel('property_type', $permitData['property_type'] ?? null), $optionLabel('property_type', $property->property_type)],
                            'Location' => [$permitData['zone_name'] ?? $optionLabel('location', $permitData['location'] ?? null), $optionLabel('location', $property->location) ?? $property->getTranslation('community')],
                            'Bedrooms' => [$permitData['bedrooms'] ?? null, $property->bedrooms],
                            'Size (sq.ft)' => [isset($permitData['sqft']) ? number_format($permitData['sqft']) : null, $property->sqft ? number_format($property->sqft) : null],
                            'Price' => [isset($permitData['price']) ? number_format((float) $permitData['price']) : null, number_format((float) $property->price)],
                        ] as $label => [$onPermit, $onAd])
                        @php $mismatch = $onPermit !== null && (string) $onPermit !== (string) $onAd; @endphp
                        <tr class="{{ $mismatch ? 'table-warning' : '' }}">
                            <th class="fw-semibold">{{ $label }}</th>
                            <td>{{ $onPermit ?? '—' }}</td>
                            <td>{{ $onAd ?? '—' }} @if($mismatch)<i class="fas fa-triangle-exclamation text-warning ms-1" title="Differs from the permit"></i>@endif</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="la-sub mb-3">The permit is issued for these exact details. Compare them with {{ $needsPermit ? $issuer . "'s record" : 'the listing documents' }}.</p>
            <div class="la-compare small">
                @foreach([
                    'Purpose' => $property->listing_type === 'rent' ? 'For Rent' : 'For Sale',
                    'Type' => $property->filterLabel('property_type') ?: '—',
                    'Price' => ($property->currency ?: 'AED') . ' ' . number_format((float) $property->price),
                    'Bedrooms' => $property->bedrooms ?? '—',
                    'Size' => $property->sqft ? number_format($property->sqft) . ' sq.ft' : '—',
                    'Location' => $property->getTranslation('community') ?: $property->getTranslation('city') ?: '—',
                    'Agency ORN' => $property->owner?->orn_number ?: '—',
                    'Agent BRN' => $property->agent?->brn_number ?: '—',
                ] as $label => $value)
                <div><div class="la-field-label">{{ $label }}</div><div class="la-field-value text-truncate" title="{{ $value }}">{{ $value }}</div></div>
                @endforeach
            </div>
            @endif
        </div>

        <div class="portal-card p-4">
            <div class="la-section-title"><i class="fas fa-clock-rotate-left"></i>Review history</div>
            <div class="la-timeline">
                @forelse($property->complianceLogs as $log)
                <div class="la-timeline__item la-tone-{{ $log->to_status }}">
                    <div><span class="fw-semibold">{{ $P::COMPLIANCE_LABELS[$log->to_status] ?? $log->to_status }}</span> <span class="la-sub">· {{ $log->actorName() }} · {{ $log->created_at?->format('d M Y, H:i') }}</span></div>
                    @if($log->note)<div class="mt-1" style="white-space: pre-line;">{{ $log->note }}</div>@endif
                </div>
                @empty
                <div class="la-sub">No history yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="la-sticky">
            <div class="portal-card p-4 mb-3">
                <div class="la-section-title"><i class="fas fa-list-check"></i>Checklist</div>
                <ul class="la-checklist">
                    @foreach($checks as $label => $ok)
                    <li><i class="fas {{ $ok ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i><span>{{ $label }}</span></li>
                    @endforeach
                </ul>
                @if($missing)
                <div class="alert alert-warning small mt-3 mb-0"><strong>Missing:</strong> {{ implode(', ', $missing) }}.</div>
                @endif
            </div>

            @if($property->isSold())
            <div class="alert alert-info small">This listing is marked {{ $property->sold_type }} — nothing to check.</div>
            @else
            {{-- No approval step: a verified permit makes the listing live by itself. --}}
            @if($property->compliance_status === $P::COMPLIANCE_APPROVED)
            <div class="portal-card p-4 mb-3" style="border-color: #0f8a4f;">
                <div class="fw-bold mb-1 text-success"><i class="fas fa-circle-check me-1"></i>{{ $needsPermit ? 'Permit verified' : 'No permit needed' }}</div>
                <p class="small la-sub mb-0">
                    @if(!$needsPermit) This listing doesn't need an advertising permit, so it can be live.
                    @elseif($verifiedOnline) {{ $issuer }} verified the permit on {{ $property->permit_verified_at->format('d M Y') }} — the listing can be live.
                    @else Verified on {{ $property->permit_verified_at?->format('d M Y') }} (approved before online validation) — the listing can be live.
                    @endif
                </p>
            </div>
            @else
            {{-- Nothing for Super Admin to do: the agency / agent validates the permit in the property form. --}}
            <div class="portal-card p-4 mb-3">
                <div class="fw-bold mb-1"><i class="fas fa-eye-slash me-1"></i>Not on the website</div>
                <p class="small la-sub mb-0">
                    @if($property->compliance_status === $P::COMPLIANCE_EXPIRED) The permit expired. It comes back once the agency / agent enters and validates the renewed permit.
                    @elseif($property->compliance_status === $P::COMPLIANCE_CHANGES_REQUESTED) You took this listing down. It stays offline until the agency / agent changes the permit details.
                    @elseif($missing) Permit details are incomplete — waiting for the agency / agent to add and validate them.
                    @else The permit isn't verified yet. It goes live by itself once the agency / agent clicks Validate in the property form.
                    @endif
                </p>
            </div>
            @endif

            @if($property->compliance_status !== $P::COMPLIANCE_CHANGES_REQUESTED)
            <form method="POST" action="{{ route('portal.listing-approvals.take-down', $property->id) }}" class="portal-card p-4" id="takeDownForm">
                @csrf
                <div class="fw-bold mb-1 text-danger"><i class="fas fa-ban me-1"></i>Take down</div>
                <p class="small la-sub">Takes the listing off the website (e.g. wrong details or a DLD complaint). The agency / agent is told by email and in the portal; it stays offline until they change the permit details and validate it again.</p>
                <button type="submit" class="btn btn-outline-danger btn-sm w-100">Take down</button>
            </form>
            @endif
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Confirm before taking a listing off the website.
    document.getElementById('takeDownForm')?.addEventListener('submit', async function (e) {
        if (this.dataset.confirmed) return;
        e.preventDefault();
        if (await window.portalConfirm({ title: 'Take this listing down?', message: 'It goes off the website now and stays offline until the agency / agent changes the permit details and validates it again.', confirmText: 'Take down', tone: 'danger' })) {
            this.dataset.confirmed = '1';
            this.requestSubmit();
        }
    });
</script>
@endpush
