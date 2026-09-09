@extends('portal.layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="portal-section-title">Welcome back, {{ $owner->name ?? ($cmsActor->name ?? 'Super Admin') }}</div>
@if($isAdmin)
<p class="text-muted mb-3" style="margin-top:-0.5rem;">Showing data across every agent and company.</p>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="portal-stat-card">
            <div class="portal-stat-icon indigo"><i class="fas fa-building"></i></div>
            <div>
                <div class="portal-stat-value">{{ $stats['total_properties'] }}</div>
                <div class="portal-stat-label">Total Properties</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="portal-stat-card">
            <div class="portal-stat-icon teal"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="portal-stat-value">{{ $stats['active_properties'] }}</div>
                <div class="portal-stat-label">Active Listings</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="portal-stat-card">
            <div class="portal-stat-icon amber"><i class="fas fa-address-book"></i></div>
            <div>
                <div class="portal-stat-value">{{ $stats['total_enquiries'] }}</div>
                <div class="portal-stat-label">Total Enquiries</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="portal-stat-card">
            <div class="portal-stat-icon rose"><i class="fas fa-bell"></i></div>
            <div>
                <div class="portal-stat-value">{{ $stats['new_enquiries'] }}</div>
                <div class="portal-stat-label">New Leads</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="portal-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="portal-section-title mb-0">Recent Properties</div>
                <a href="{{ route('portal.properties.index') }}" class="portal-btn-ghost btn btn-sm">View All</a>
            </div>
            @forelse($recentProperties as $property)
                <div class="d-flex justify-content-between align-items-center py-2 @if(!$loop->last) border-bottom @endif">
                    <div>
                        <div class="fw-semibold">{{ $property->getTranslation('title') }}</div>
                        <div class="text-muted" style="font-size:0.8rem;">{{ $property->filterLabel('property_type') ?: '-' }} &middot; {{ $property->location ?: '-' }}</div>
                    </div>
                    <span class="portal-badge-status {{ $property->status ? 'portal-badge-active' : 'portal-badge-inactive' }}">
                        {{ $property->status ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            @empty
                <div class="portal-empty">No properties yet. <a href="{{ route('portal.properties.create') }}">Add your first listing</a>.</div>
            @endforelse
        </div>
    </div>

    <div class="col-lg-6">
        <div class="portal-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="portal-section-title mb-0">Recent Enquiries</div>
                <a href="{{ route('portal.crm.index') }}" class="portal-btn-ghost btn btn-sm">View All</a>
            </div>
            @forelse($recentEnquiries as $enquiry)
                <div class="d-flex justify-content-between align-items-center py-2 @if(!$loop->last) border-bottom @endif">
                    <div>
                        <div class="fw-semibold">{{ $enquiry->name ?: 'Unknown' }}</div>
                        <div class="text-muted" style="font-size:0.8rem;">{{ $enquiry->email }}</div>
                    </div>
                    <span class="portal-badge-status portal-badge-{{ $enquiry->status }}">{{ ucfirst($enquiry->status) }}</span>
                </div>
            @empty
                <div class="portal-empty">No enquiries yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
