@extends('portal.layouts.app')

@section('title', 'Review Listing')

@include('portal.listing-approvals._styles')

@section('content')
@php
    $P = \App\Models\Property::class;
    $expired = fn ($date) => $date && $date->lt(today());
    $thumb = $property->galleryImages()[0]['url'] ?? null;
    $checks = [
        'DLD permit number' => (bool) $property->permit_number,
        'Permit valid (not expired)' => $property->permit_expires_at && !$expired($property->permit_expires_at),
        'Madmoun QR code' => (bool) $property->permit_qr,
        'Form A uploaded' => (bool) $property->authorization_document,
        'Form A valid' => (bool) $property->authorization_document && !$expired($property->authorization_expires_at),
        'Agency ORN on file' => (bool) $property->owner?->orn_number || !$property->owner || $property->owner->type !== 'company',
    ];
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
            <div class="la-section-title"><i class="fas fa-file-shield"></i>DLD Advertising Permit</div>
            <div class="row g-4 align-items-start">
                <div class="col-sm-auto text-center">
                    @if($property->permit_qr)
                    <a href="{{ media_url($property->permit_qr) }}" target="_blank" rel="noopener"><img src="{{ media_url($property->permit_qr) }}" alt="Permit QR" class="la-qr"></a>
                    <div class="la-sub mt-1">Scan to open DLD's record</div>
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
                            <span class="la-sub">Not provided — use the QR or the DLD website.</span>
                            @endif
                        </div>
                        <div class="col-12">
                            <a href="https://dubailand.gov.ae/en/eservices/validate-real-estate-licenses-and-permits/" target="_blank" rel="noopener" class="btn btn-sm portal-btn-ghost"><i class="fas fa-shield-halved me-1"></i>Check permit number on DLD</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="portal-card p-4 mb-3">
            <div class="la-section-title"><i class="fas fa-file-signature"></i>Owner Authorisation (Form A)</div>
            <div class="row g-3">
                <div class="col-sm-4">
                    <div class="la-field-label">Agreement</div>
                    <div class="la-field-value">{{ $P::AUTHORIZATION_TYPES[$property->authorization_type] ?? '—' }}</div>
                </div>
                <div class="col-sm-4">
                    <div class="la-field-label">Form A expires</div>
                    <div class="la-field-value {{ $expired($property->authorization_expires_at) ? 'text-danger' : '' }}">{{ $property->authorization_expires_at?->format('d M Y') ?? '—' }}</div>
                </div>
                <div class="col-sm-4">
                    <div class="la-field-label">Title deed / Oqood</div>
                    <div class="la-field-value">{{ $property->title_deed_no ?: '—' }}</div>
                </div>
                <div class="col-12 d-flex gap-2 flex-wrap">
                    @forelse(collect(\App\Services\ListingComplianceService::DOCUMENT_FIELDS)->filter(fn ($label, $field) => $property->{$field}) as $field => $label)
                    <a href="{{ route('portal.properties.compliance-document', [$property->id, $field]) }}" class="btn btn-sm portal-btn-ghost"><i class="fas fa-file-arrow-down me-1"></i>{{ $label }}</a>
                    @empty
                    <span class="la-sub">No documents uploaded.</span>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="portal-card p-4 mb-3">
            <div class="la-section-title"><i class="fas fa-code-compare"></i>Does the ad match the permit?</div>
            <p class="la-sub mb-3">The DLD permit is issued for these exact details. Compare them with DLD's record.</p>
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
                <div class="alert alert-warning small mt-3 mb-0"><strong>Can't approve yet:</strong> {{ implode(', ', $missing) }}.</div>
                @endif
            </div>

            @if($property->compliance_note && $property->compliance_status !== $P::COMPLIANCE_APPROVED)
            <div class="alert alert-secondary small"><div class="fw-semibold mb-1">Last note</div><div style="white-space: pre-line;">{{ $property->compliance_note }}</div></div>
            @endif

            @if($property->isSold())
            <div class="alert alert-info small">This listing is marked {{ $property->sold_type }} — nothing to review.</div>
            @else
            @if($property->compliance_status !== $P::COMPLIANCE_APPROVED)
            <form method="POST" action="{{ route('portal.listing-approvals.approve', $property->id) }}" class="portal-card p-4 mb-3" style="border-color: #0f8a4f;">
                @csrf
                <div class="fw-bold mb-1 text-success"><i class="fas fa-circle-check me-1"></i>Approve &amp; publish</div>
                <p class="small la-sub">You've confirmed the permit on DLD and its details match this ad. The listing goes live straight away, and the agency / agent gets an email.</p>
                <textarea name="note" class="form-control form-control-sm mb-2" rows="2" maxlength="2000" placeholder="Note to the agency (optional)"></textarea>
                <button type="submit" class="btn btn-success btn-sm w-100" @disabled($missing)><i class="fas fa-check me-1"></i>Approve</button>
            </form>
            @endif

            <form method="POST" action="{{ route('portal.listing-approvals.request-changes', $property->id) }}" class="portal-card p-4">
                @csrf
                <div class="fw-bold mb-1 text-danger"><i class="fas fa-rotate-left me-1"></i>{{ $property->compliance_status === $P::COMPLIANCE_APPROVED ? 'Take down & request changes' : 'Request changes' }}</div>
                <p class="small la-sub">The agency / agent gets this note by email and in the portal. The listing stays offline until they fix it and save again.</p>
                <textarea name="note" class="form-control form-control-sm mb-2 @error('note') is-invalid @enderror" rows="3" maxlength="2000" required placeholder="What needs fixing, e.g. the permit is for unit 1204 but the ad says 1402">{{ old('note') }}</textarea>
                @error('note')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-outline-danger btn-sm w-100">Send back</button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
