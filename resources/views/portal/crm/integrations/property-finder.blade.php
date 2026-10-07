@extends('portal.crm._layout')

@section('title', 'Property Finder')

{{-- CRM › Integrations › Property Finder (IntegrationController::propertyFinder; actions in
     Portal\Crm\PropertyFinderController). --}}
@include('portal.crm.integrations._shared_styles')

@section('crm-content')
<div class="mb-3">
    <a href="{{ route('portal.crm.integrations.index') }}" class="portal-link-muted small"><i class="fas fa-arrow-left me-1"></i>All integrations</a>
    <div class="portal-section-title mb-0 mt-1">Property Finder</div>
</div>

@include('portal.crm.integrations._property_finder')
@endsection
