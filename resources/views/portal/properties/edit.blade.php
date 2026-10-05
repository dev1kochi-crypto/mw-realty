@extends('portal.layouts.app')

@section('title', 'Edit ' . $itemLabel)

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <div class="portal-section-title mb-1">Edit {{ $itemLabel }}</div>
        <p class="text-muted small mb-0">{{ $property->getTranslation('title') }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route($routePrefix . '.index') }}" class="btn portal-btn-ghost btn-sm">Cancel</a>
        <button type="submit" form="propertyForm" class="btn btn-portal-primary btn-sm px-4"><i class="fas fa-save me-1"></i> Update {{ $itemLabel }}</button>
    </div>
</div>

{{-- Why this listing isn't live (permit not verified / expired / taken down) — the permit is in Core details. --}}
@if(!$property->canGoLive() && !$property->isSold())
@php
    [$reviewTone, $reviewIcon, $reviewText] = match ($property->compliance_status) {
        \App\Models\Property::COMPLIANCE_CHANGES_REQUESTED => ['danger', 'fa-rotate-left', 'MW Realty took this listing down. Check the permit details, validate the permit again in Core details, then click Update.'],
        \App\Models\Property::COMPLIANCE_EXPIRED => ['danger', 'fa-ban', 'The permit expired, so the listing is offline. Enter the renewed permit in Core details, validate it and click Update.'],
        \App\Models\Property::COMPLIANCE_PENDING => ['info', 'fa-shield-halved', 'Not on the website yet: the permit isn\'t verified. Click Validate next to the permit number in Core details, then Update — it goes live as soon as the permit is verified.'],
        default => ['warning', 'fa-file-circle-exclamation', 'Not on the website yet: add the permit details in Core details and validate the permit, then click Update.'],
    };
@endphp
<div class="alert alert-{{ $reviewTone }} d-flex gap-3 align-items-start">
    <i class="fas {{ $reviewIcon }} mt-1"></i>
    <div class="flex-grow-1">
        <div class="fw-bold">{{ $property->complianceLabel() }}</div>
        <div class="small">{{ $reviewText }}</div>
    </div>
    <button type="button" class="btn btn-sm btn-light flex-shrink-0" data-open-tab="#tab-basic">Open permit details</button>
</div>
@endif

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route($routePrefix . '.update', $property->id) }}" method="POST" enctype="multipart/form-data" id="propertyForm">
    @csrf
    @method('PUT')
    @include('portal.properties._form')
    <div class="mt-4 pt-2">
        <button type="submit" class="btn btn-portal-primary px-4">Update {{ $itemLabel }}</button>
        <a href="{{ route($routePrefix . '.index') }}" class="portal-btn-ghost btn">Cancel</a>
    </div>
</form>
@endsection
