@extends('portal.layouts.app')

@section('title', 'Properties')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="selectAllProperties" @if($properties->isEmpty()) disabled @endif>
            <label class="form-check-label" for="selectAllProperties">Select All</label>
        </div>
        <div class="portal-section-title mb-0">{{ $isAdmin ? 'All Properties' : 'My Properties' }}</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div id="bulkActionsBar" class="d-none align-items-center gap-2">
            <span id="bulkSelectedCount" class="portal-muted small fw-semibold"></span>
            <button type="button" id="bulkDeleteBtn" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Delete Selected</button>
        </div>
        <a href="{{ route('portal.properties.create') }}" class="btn btn-portal-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Add Property
        </a>
    </div>
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

@forelse($properties as $property)
    @if($loop->first)
    <div class="row g-4">
    @endif
    @php
        $thumb = $property->galleryImages()[0]['url'] ?? null;
        $listingLabel = $property->filterLabel('listing_type');
        $typeLabel = $property->filterLabel('property_type');
        $ownerLabel = $property->owner
            ? ($property->owner->type === 'company' ? ($property->owner->company_name ?: $property->owner->name) : $property->owner->name)
            : 'MW Realty';
    @endphp
    <div class="col-md-6 col-xl-4">
        <div class="portal-property-card" data-property-row>
            <div class="portal-property-card__media">
                <div class="form-check portal-property-card__checkbox">
                    <input class="form-check-input property-select-checkbox" type="checkbox" value="{{ $property->id }}" aria-label="Select this property">
                </div>
                <img src="{{ $thumb ?: 'https://placehold.co/400x240?text=No+Image' }}" alt="">
                @if($listingLabel)
                <span class="portal-property-card__badge">{{ $listingLabel }}</span>
                @endif
                @if($property->featured)
                <span class="portal-property-card__badge portal-property-card__badge--featured"><i class="fas fa-star"></i> Featured</span>
                @endif
            </div>
            <div class="portal-property-card__body">
                <h3 class="portal-property-card__title" title="{{ $property->getTranslation('title') }}">{{ $property->getTranslation('title') }}</h3>
                <p class="portal-property-card__location"><i class="fas fa-map-marker-alt"></i> {{ $property->location ?: '—' }}</p>

                <div class="portal-property-card__stats">
                    @if($property->bedrooms)<span><i class="fas fa-bed"></i> {{ $property->bedrooms }}</span>@endif
                    @if($property->bathrooms)<span><i class="fas fa-bath"></i> {{ $property->bathrooms }}</span>@endif
                    @if($property->sqft)<span><i class="fas fa-ruler-combined"></i> {{ number_format($property->sqft) }} sq.ft</span>@endif
                </div>

                <div class="portal-property-card__price-row">
                    <span class="portal-property-card__price">{{ $property->price ? number_format($property->price) . ' ' . $property->currency : 'Price on request' }}</span>
                    <span class="portal-badge-status {{ $property->status ? 'portal-badge-active' : 'portal-badge-inactive' }}">
                        {{ $property->status ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <div class="portal-property-card__meta">
                    <span>{{ $typeLabel ?: '—' }}</span>
                    @if($property->reference_no)<span>Ref: {{ $property->reference_no }}</span>@endif
                    @if($isAdmin)<span>{{ $ownerLabel }}</span>@endif
                </div>

                <div class="portal-property-card__actions">
                    <a href="{{ route('portal.properties.show', $property->id) }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-eye me-1"></i>View</a>
                    <a href="{{ route('portal.properties.edit', $property->id) }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
                    <button type="button" class="portal-btn-ghost btn btn-sm delete-property" data-id="{{ $property->id }}"><i class="fas fa-trash me-1"></i>Delete</button>
                </div>
            </div>
        </div>
    </div>
    @if($loop->last)
    </div>
    @endif
@empty
    <div class="portal-card p-5 text-center portal-empty">
        No properties yet. <a href="{{ route('portal.properties.create') }}">Add your first listing</a>.
    </div>
@endforelse

<div class="mt-4">{{ $properties->links('pagination::bootstrap-5') }}</div>
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

    (function () {
        const selectAll = document.getElementById('selectAllProperties');
        const checkboxes = () => Array.from(document.querySelectorAll('.property-select-checkbox'));
        const bar = document.getElementById('bulkActionsBar');
        const countLabel = document.getElementById('bulkSelectedCount');
        const deleteBtn = document.getElementById('bulkDeleteBtn');

        function refreshBar() {
            const checked = checkboxes().filter(cb => cb.checked);
            if (checked.length > 0) {
                bar.classList.remove('d-none');
                bar.classList.add('d-flex');
                countLabel.textContent = checked.length + ' selected';
            } else {
                bar.classList.add('d-none');
                bar.classList.remove('d-flex');
            }
            if (selectAll) {
                const all = checkboxes();
                selectAll.checked = all.length > 0 && checked.length === all.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            }
        }

        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('property-select-checkbox')) {
                refreshBar();
            }
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes().forEach(cb => { cb.checked = selectAll.checked; });
                refreshBar();
            });
        }

        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                const ids = checkboxes().filter(cb => cb.checked).map(cb => cb.value);
                if (ids.length === 0) return;
                if (!confirm('Delete ' + ids.length + ' selected propert' + (ids.length === 1 ? 'y' : 'ies') + '? This cannot be undone.')) return;

                deleteBtn.disabled = true;
                fetch("{{ route('portal.properties.bulk-destroy') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ids: ids }),
                })
                    .then(() => location.reload())
                    .catch(() => { deleteBtn.disabled = false; });
            });
        }

        refreshBar();
    })();
</script>
@endpush
