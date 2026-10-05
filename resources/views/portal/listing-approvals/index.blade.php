@extends('portal.layouts.app')

@section('title', 'Listing Permits')

@include('portal.listing-approvals._styles')

@section('content')
@php
    $P = \App\Models\Property::class;
    $approvalOn = (bool) config('permits.superadmin_approval');
    $labels = [$P::COMPLIANCE_PENDING => $approvalOn ? 'Awaiting approval' : 'Not verified / approval'] + $P::COMPLIANCE_LABELS;
    $tabs = [
        $P::COMPLIANCE_APPROVED => ['fa-circle-check', 'Permit verified (or none needed) — these can be live. Take one down any time if something is wrong.'],
        $P::COMPLIANCE_PENDING => ['fa-shield-halved', $approvalOn
            ? 'Waiting for your approval — not on the website until you approve them. Open one, check the permit, then Approve & publish.'
            : 'Not on the website yet. DTCM and None (DIFC / JAFZA) listings wait for your approval; RERA / ADREC ones go live by themselves once the agency / agent validates the permit (or you can check and approve them).'],
        $P::COMPLIANCE_DRAFT => ['fa-file-circle-exclamation', 'Saved without complete permit details — not on the website until the agent adds and validates the permit.'],
        $P::COMPLIANCE_EXPIRED => ['fa-ban', 'Taken offline automatically when the permit expired. They come back once a renewed permit is validated.'],
        $P::COMPLIANCE_CHANGES_REQUESTED => ['fa-rotate-left', 'Taken down by you. They stay offline until the agent changes the permit details and validates it again.'],
    ];
    $daysLeft = fn ($date) => $date ? (int) today()->diffInDays($date, false) : null;
@endphp

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <div class="portal-section-title mb-1">Listing Permits</div>
        <div class="text-muted small" style="max-width: 720px;">
            @if($approvalOn)
            Every listing's advertising permit. Agencies and agents validate the permit with DLD / ADREC in the property form, then the listing waits here for your approval before it shows on the website.
            @else
            Every listing's advertising permit. Agencies and agents validate the permit with DLD / ADREC in the property form; a verified listing goes live by itself. DTCM and None (DIFC / JAFZA) listings can't be validated online, so they wait for your approval.
            @endif
            Expired listings never show on the website.
        </div>
    </div>
    <a href="https://dubailand.gov.ae/en/eservices/validate-real-estate-licenses-and-permits/" target="_blank" rel="noopener" class="btn btn-sm btn-portal-primary"><i class="fas fa-shield-halved me-1"></i>Verify a permit on DLD</a>
</div>

<nav class="la-status-grid mb-4" aria-label="Permit status">
    @foreach($tabs as $key => [$icon])
    @php $count = (int) ($counts[$key] ?? 0); @endphp
    <a href="{{ route('portal.listing-approvals.index', ['tab' => $key]) }}" class="la-status la-tone-{{ $key }} {{ $tab === $key ? 'is-active' : '' }}" @if($tab === $key) aria-current="page" @endif>
        <span class="la-status__icon"><i class="fas {{ $icon }}"></i></span>
        <span class="min-w-0">
            <span class="la-status__count d-block">{{ number_format($count) }}</span>
            <span class="la-status__label d-block">{{ $labels[$key] }}</span>
        </span>
    </a>
    @endforeach
</nav>

<div class="portal-card p-4 la-tone-{{ $tab }}">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div class="la-hint flex-grow-1"><i class="fas {{ $tabs[$tab][0] }}"></i><span>{{ $tabs[$tab][1] }}</span></div>
        <form method="GET" action="{{ route('portal.listing-approvals.index') }}" class="d-flex gap-2" style="min-width: min(100%, 420px);">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm flex-grow-1" maxlength="100" placeholder="Title, ref, permit no, agency or agent" aria-label="Search listings">            <button class="btn btn-sm btn-portal-primary px-3" type="submit"><i class="fas fa-search"></i></button>
            @if($search !== '')<a href="{{ route('portal.listing-approvals.index', ['tab' => $tab]) }}" class="btn btn-sm portal-btn-ghost" title="Clear search"><i class="fas fa-times"></i></a>@endif
        </form>
    </div>

    @if($listings->isEmpty())
    <div class="la-empty">
        <i class="fas {{ $tabs[$tab][0] }}"></i>
        {{ $search !== '' ? 'No listings match your search.' : 'Nothing in ' . strtolower($labels[$tab]) . ' right now.' }}
    </div>
    @else
    <div class="la-row la-row--head">
        <div>Property</div><div>Agency / Agent</div><div>Permit</div><div>{{ $tab === $P::COMPLIANCE_APPROVED ? 'Verified' : 'Saved' }}</div><div></div>
    </div>
    @foreach($listings as $property)
    @php
        $thumb = $property->galleryImages()[0]['url'] ?? null;
        $left = $daysLeft($property->permit_expires_at);
        $when = $tab === $P::COMPLIANCE_APPROVED ? $property->compliance_reviewed_at : $property->compliance_submitted_at;
    @endphp
    <div class="la-row">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <img src="{{ $thumb ?: 'https://placehold.co/128x96?text=%20' }}" alt="" class="la-thumb" loading="lazy">
            <div class="min-w-0">
                <a href="{{ route('portal.listing-approvals.show', $property->id) }}" class="la-title" title="{{ $property->getTranslation('title') }}">{{ $property->getTranslation('title') ?: 'Property' }}</a>
                <div class="la-sub">{{ $property->reference_no }} &middot; {{ $property->currency ?: 'AED' }} {{ number_format((float) $property->price) }} &middot; {{ $property->listing_type === 'rent' ? 'Rent' : 'Sale' }}</div>
            </div>
        </div>
        <div class="min-w-0 small">
            <div class="fw-semibold text-truncate">{{ $property->owner?->displayName() ?? 'MW Realty' }}</div>
            <div class="la-sub text-truncate">{{ $property->agent && $property->agent_id !== $property->portal_user_id ? 'Agent: ' . $property->agent->name : 'No agent assigned' }}</div>
        </div>
        <div class="small">
            @if($property->permit_number)
            <div class="la-permit">{{ $property->permit_number }}</div>
            @if($left === null)
            <span class="la-chip">No expiry date</span>
            @elseif($left < 0)
            <span class="la-chip la-chip--bad"><i class="fas fa-ban"></i>Expired {{ $property->permit_expires_at->format('d M Y') }}</span>
            @elseif($left <= 30)
            <span class="la-chip la-chip--warn"><i class="fas fa-hourglass-half"></i>{{ $left }} day{{ $left === 1 ? '' : 's' }} left</span>
            @else
            <span class="la-chip la-chip--ok"><i class="far fa-calendar"></i>Until {{ $property->permit_expires_at->format('d M Y') }}</span>
            @endif
            @if(\App\Support\PermitRules::validates($property->permit_type))
                @if($property->permit_verified_at && in_array($property->permit_verified_via, ['dld', 'adrec'], true))
                <span class="la-chip la-chip--ok mt-1"><i class="fas fa-circle-check"></i>Verified with {{ \App\Support\PermitRules::issuer($property->permit_type) }}</span>
                @elseif($property->permit_verified_at)
                <span class="la-chip la-chip--ok mt-1" title="Approved before online validation"><i class="fas fa-circle-check"></i>Verified</span>
                @else
                <span class="la-chip la-chip--warn mt-1" title="Waiting for the agency / agent to validate it"><i class="fas fa-shield-halved"></i>Not verified</span>
                @endif
            @endif
            @elseif(!\App\Support\PermitRules::requiresPermit($property->permit_type) && $property->permit_type)
            <span class="la-chip"><i class="fas fa-circle-info"></i>No permit needed</span>
            @else
            <span class="la-chip la-chip--warn"><i class="fas fa-triangle-exclamation"></i>No permit</span>
            @endif
            @if($property->awaitingApproval())
            <span class="la-chip la-chip--warn mt-1"><i class="fas fa-user-shield"></i>Awaiting approval</span>
            @endif

        </div>
        <div class="small">
            @if($when)
            <div class="fw-semibold">{{ $when->format('d M Y') }}</div>
            <div class="la-sub">{{ $when->diffForHumans() }}</div>
            @else
            <span class="la-sub">—</span>
            @endif
        </div>
        <div class="la-row__action text-end">
            <a href="{{ route('portal.listing-approvals.show', $property->id) }}" class="btn btn-sm {{ $tab === $P::COMPLIANCE_PENDING ? 'btn-portal-primary' : 'portal-btn-ghost' }}">
                Open <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
    @endforeach

    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="portal-muted small">Showing {{ $listings->firstItem() }}–{{ $listings->lastItem() }} of {{ number_format($listings->total()) }}</span>
        <div>{{ $listings->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
    </div>
    @endif
</div>
@endsection
