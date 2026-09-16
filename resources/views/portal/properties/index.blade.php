@extends('portal.layouts.app')

@section('title', 'Properties')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="portal-section-title mb-0">{{ $isAdmin ? 'All Properties' : 'My Properties' }}</div>
    <a href="{{ route('portal.properties.create') }}" class="btn btn-portal-primary btn-sm">
        <i class="fas fa-plus me-1"></i> Add Property
    </a>
</div>

@if($planUsage)
    @if($planUsage['remaining'] === 0)
        <div class="alert alert-danger d-flex justify-content-between align-items-center mb-3">
            <span><i class="fas fa-exclamation-triangle me-2"></i>You've used all {{ $planUsage['used'] }} of your {{ $planUsage['plan']?->getTranslation('name') ?? 'plan' }} listings. Upgrade to add more.</span>
        </div>
    @elseif($planUsage['plan'])
        <div class="alert alert-info d-flex justify-content-between align-items-center mb-3">
            <span><i class="fas fa-layer-group me-2"></i>{{ $planUsage['plan']->getTranslation('name') }} plan &mdash;
                {{ $planUsage['remaining'] === null ? 'unlimited listings' : $planUsage['used'] . ' of ' . $planUsage['plan']->property_limit . ' listings used' }}</span>
        </div>
    @endif
@endif

<div class="portal-card p-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th>Property</th>
                    @if($isAdmin)
                    <th>Owner</th>
                    @endif
                    <th>Type / Location</th>
                    <th>Listing</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($properties as $property)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            @php $thumb = $property->galleryImages()[0]['url'] ?? null; @endphp
                            <img src="{{ $thumb ?: 'https://placehold.co/60x40?text=No+Image' }}" style="width:60px;height:40px;object-fit:cover;border-radius:8px;">
                            <div class="fw-semibold">{{ $property->getTranslation('title') }}</div>
                        </div>
                    </td>
                    @if($isAdmin)
                    <td>
                        @if($property->owner)
                            {{ $property->owner->type === 'company' ? ($property->owner->company_name ?: $property->owner->name) : $property->owner->name }}
                        @else
                            <span class="text-muted">MW Realty</span>
                        @endif
                    </td>
                    @endif
                    <td>{{ $property->filterLabel('property_type') ?: '-' }} &middot; {{ $property->location ?: '-' }}</td>
                    <td>{{ $property->filterLabel('listing_type') ?: '-' }}</td>
                    <td>{{ $property->price ? number_format($property->price) . ' ' . $property->currency : '-' }}</td>
                    <td>
                        <span class="portal-badge-status {{ $property->status ? 'portal-badge-active' : 'portal-badge-inactive' }}">
                            {{ $property->status ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('portal.properties.edit', $property->id) }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-edit"></i></a>
                        <button type="button" class="portal-btn-ghost btn btn-sm delete-property" data-id="{{ $property->id }}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ $isAdmin ? 7 : 6 }}" class="portal-empty">No properties yet. <a href="{{ route('portal.properties.create') }}">Add your first listing</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $properties->links() }}</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.delete-property');
        if (!btn) return;
        if (!confirm('Delete this property?')) return;
        fetch("{{ url('portal/properties') }}/" + btn.dataset.id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        }).then(() => location.reload());
    });
</script>
@endpush
