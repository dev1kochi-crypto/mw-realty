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
        <div id="bulkActionsBar" class="dropdown d-none">
            <button class="btn btn-portal-danger btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                Bulk Actions (<span id="bulkSelectedCount">0</span>)
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><button class="dropdown-item bulk-action-btn" type="button" data-action="active"><i class="fas fa-check-circle text-success me-2"></i>Mark Active</button></li>
                <li><button class="dropdown-item bulk-action-btn" type="button" data-action="inactive"><i class="fas fa-times-circle text-secondary me-2"></i>Mark Inactive</button></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item bulk-action-btn" type="button" data-action="delete"><i class="fas fa-trash text-danger me-2"></i>Delete Selected</button></li>
            </ul>
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

@if($featuredQuota)
<div class="portal-featured-quota mb-3">
    <span class="portal-featured-quota__icon"><i class="fas fa-star"></i></span>
    @if($featuredQuota['limit'] > 0)
    <div class="flex-grow-1">
        @if($featuredQuota['per_month'])
        <div class="fw-bold">Featured listings: {{ $featuredQuota['used'] }} of {{ $featuredQuota['limit'] }} used this month</div>
        @else
        <div class="fw-bold">Featured listings: {{ $featuredQuota['used'] }} of {{ $featuredQuota['limit'] }} featured now</div>
        @endif
        <div class="small portal-muted">
            {{ $featuredQuota['max_days'] ? 'Each feature runs up to ' . $featuredQuota['max_days'] . ' days.' : 'No limit on how long each feature runs.' }}
            @if($featuredQuota['per_month'])
            Quota resets on {{ now()->addMonthNoOverflow()->startOfMonth()->format('d M') }}.
            @else
            A slot frees up as soon as a feature ends or you stop it.
            @endif
        </div>
    </div>
    @else
    <div class="flex-grow-1">
        <div class="fw-bold">Get more eyes on your listings</div>
        <div class="small portal-muted">Featured listings appear first on the website. Not included in your plan.</div>
    </div>
    <a href="{{ route('portal.plans.index') }}" class="btn btn-portal-primary btn-sm">Upgrade</a>
    @endif
</div>
@endif

@forelse($properties as $property)
    @if($loop->first)
    @if($properties->count() > 1)
    <p class="portal-muted small mb-2"><i class="fas fa-grip-vertical me-1"></i> Drag a card by its handle to change the display order.</p>
    @endif
    <div class="row g-4" id="propertyGrid">
    @endif
    @php
        $thumb = $property->galleryImages()[0]['url'] ?? null;
        $listingLabel = $property->filterLabel('listing_type');
        $typeLabel = $property->filterLabel('property_type');
        $ownerLabel = $property->owner
            ? ($property->owner->type === 'company' ? ($property->owner->company_name ?: $property->owner->name) : $property->owner->name)
            : 'MW Realty';
        // The Location tab's required Address/Community/City fields live per-language in
        // translations; the bare `location` column is only the optional location filter slug.
        $addressLine = collect(['address', 'community', 'city'])
            ->map(fn ($part) => $property->getTranslation($part))
            ->filter()
            ->implode(', ')
            ?: ($property->location ? ($property->filterLabel('location') ?: $property->location) : null);
    @endphp
    <div class="col-sm-6 col-lg-4 col-xl-3 portal-property-col" data-id="{{ $property->id }}">
        <div class="portal-property-card" data-property-row>
            <div class="portal-property-card__media">
                <div class="form-check portal-property-card__checkbox">
                    <input class="form-check-input property-select-checkbox" type="checkbox" value="{{ $property->id }}" aria-label="Select this property">
                </div>
                @if($properties->count() > 1)
                <span class="portal-property-card__drag" title="Drag to reorder" aria-label="Drag to reorder"><i class="fas fa-grip-vertical"></i></span>
                @endif
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
                <p class="portal-property-card__location"><i class="fas fa-map-marker-alt"></i> <span title="{{ $addressLine }}">{{ $addressLine ?: '—' }}</span></p>

                <div class="portal-property-card__stats">
                    @if($property->bedrooms)<span><i class="fas fa-bed"></i> {{ $property->bedrooms }}</span>@endif
                    @if($property->bathrooms)<span><i class="fas fa-bath"></i> {{ $property->bathrooms }}</span>@endif
                    @if($property->sqft)<span><i class="fas fa-ruler-combined"></i> {{ number_format($property->sqft) }} sq.ft</span>@endif
                </div>

                <div class="portal-property-card__price-row">
                    <span class="portal-property-card__price">{{ $property->price ? number_format($property->price) . ' ' . $property->currency : 'Price on request' }}</span>
                    <div class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                        <input class="form-check-input property-status-toggle m-0" type="checkbox" role="switch" id="statusToggle{{ $property->id }}" data-id="{{ $property->id }}" @checked($property->status)>
                        <label class="form-check-label small fw-semibold status-toggle-label {{ $property->status ? 'text-success' : 'text-secondary' }}" for="statusToggle{{ $property->id }}">{{ $property->status ? 'Active' : 'Inactive' }}</label>
                    </div>
                </div>

                <div class="portal-property-card__meta">
                    <span>{{ $typeLabel ?: '—' }}</span>
                    @if($property->reference_no)<span>Ref: {{ $property->reference_no }}</span>@endif
                    @if($isAdmin)<span>{{ $ownerLabel }}</span>@endif
                </div>

                <div class="portal-property-card__feature {{ $property->featured ? 'is-featured' : '' }}">
                    @if($property->featured)
                        <span><i class="fas fa-star me-1"></i>{{ $property->featured_until ? 'Featured · ends ' . $property->featured_until->format('d M, h:i A') : 'Featured · no end date' }}</span>
                        <button type="button" class="btn btn-link btn-sm p-0 unfeature-property" data-id="{{ $property->id }}">Stop</button>
                    @elseif($isAdmin || ($featuredQuota && $featuredQuota['remaining'] > 0))
                        <button type="button" class="btn btn-link btn-sm p-0 feature-property" data-id="{{ $property->id }}" @disabled(!$property->status) title="{{ $property->status ? '' : 'Activate the listing to feature it' }}"><i class="far fa-star me-1"></i>Feature this listing</button>
                    @elseif($featuredQuota && $featuredQuota['limit'] > 0)
                        <span class="portal-muted"><i class="far fa-star me-1"></i>{{ $featuredQuota['per_month'] ? 'Monthly featured quota used' : 'All featured slots in use' }}</span>
                    @else
                        <a href="{{ route('portal.plans.index') }}" class="portal-muted text-decoration-none"><i class="fas fa-lock me-1"></i>Featuring needs a paid plan</a>
                    @endif
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

{{-- Feature listing modal --}}
<div class="modal fade" id="featureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="featureForm">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-star text-warning me-2"></i>Feature this listing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="portal-muted small">Featured listings are highlighted and shown first on the website.</p>
                <label class="form-label fw-semibold" for="featureDays">How many days?</label>
                @if($isAdmin)
                <input type="number" class="form-control" id="featureDays" min="1" max="365" placeholder="Leave blank for no end date">
                @else
                @php $maxDays = $featuredQuota['max_days'] ?? 30; @endphp
                <select class="form-select" id="featureDays">
                    @foreach(array_unique(array_filter([1, 3, 5, 7, 10, 15, 20, 30, $maxDays], fn ($d) => $d <= $maxDays)) as $d)
                    <option value="{{ $d }}" @selected($d === $maxDays)>{{ $d }} day{{ $d === 1 ? '' : 's' }}</option>
                    @endforeach
                </select>
                <div class="form-text">
                    Your plan allows up to {{ $maxDays }} days per featured listing.
                    @if($featuredQuota['per_month'] ?? false)
                    This uses 1 of your {{ $featuredQuota['remaining'] }} remaining feature(s) this month, even if you stop it early.
                    @else
                    This uses 1 of your {{ $featuredQuota['remaining'] ?? 0 }} free featured slot(s) until it ends.
                    @endif
                </div>
                @endif
                <div class="alert alert-danger small mt-3 mb-0 d-none" id="featureError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-portal-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-portal-primary btn-sm" id="featureSubmit"><i class="fas fa-star me-1"></i>Feature</button>
            </div>
        </form>
    </div>
</div>
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

    // Feature / stop featuring a listing (plan quota enforced server-side).
    (function () {
        const modalEl = document.getElementById('featureModal');
        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById('featureForm');
        const errorBox = document.getElementById('featureError');
        const submitBtn = document.getElementById('featureSubmit');
        let propertyId = null;
        const headers = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' };

        document.addEventListener('click', function (e) {
            const featureBtn = e.target.closest('.feature-property');
            if (featureBtn) {
                propertyId = featureBtn.dataset.id;
                errorBox.classList.add('d-none');
                modal.show();
                return;
            }
            const stopBtn = e.target.closest('.unfeature-property');
            if (stopBtn) {
                if (!confirm(@json($featuredQuota && $featuredQuota['per_month'] ? 'Stop featuring this listing? The feature still counts toward this month.' : 'Stop featuring this listing?'))) return;
                stopBtn.disabled = true;
                fetch("{{ url('portal/properties') }}/" + stopBtn.dataset.id + '/unfeature', { method: 'POST', headers })
                    .then(r => { if (!r.ok) throw new Error(); location.reload(); })
                    .catch(() => { stopBtn.disabled = false; alert('Could not stop featuring. Please try again.'); });
            }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const days = document.getElementById('featureDays').value;
            submitBtn.disabled = true;
            errorBox.classList.add('d-none');
            fetch("{{ url('portal/properties') }}/" + propertyId + '/feature', {
                method: 'POST', headers, body: JSON.stringify({ days: days || null }),
            })
                .then(async r => {
                    const data = await r.json().catch(() => ({}));
                    if (!r.ok) throw new Error(Object.values(data.errors || {})[0]?.[0] || data.message || 'Could not feature this listing.');
                    location.reload();
                })
                .catch(err => { errorBox.textContent = err.message; errorBox.classList.remove('d-none'); submitBtn.disabled = false; });
        });
    })();

    // Per-card Active/Inactive switch.
    document.addEventListener('change', function (e) {
        const toggle = e.target.closest('.property-status-toggle');
        if (!toggle) return;
        const label = toggle.parentElement.querySelector('.status-toggle-label');
        const setLabel = on => {
            label.textContent = on ? 'Active' : 'Inactive';
            label.classList.toggle('text-success', on);
            label.classList.toggle('text-secondary', !on);
        };

        toggle.disabled = true;
        setLabel(toggle.checked);
        fetch("{{ url('portal/properties') }}/" + toggle.dataset.id + "/toggle-status", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        })
            .then(r => { if (!r.ok) throw new Error(); })
            .catch(() => {
                toggle.checked = !toggle.checked;
                setLabel(toggle.checked);
                alert('Could not update the status. Please try again.');
            })
            .finally(() => { toggle.disabled = false; });
    });

    (function () {
        const selectAll = document.getElementById('selectAllProperties');
        const checkboxes = () => Array.from(document.querySelectorAll('.property-select-checkbox'));
        const bar = document.getElementById('bulkActionsBar');
        const countLabel = document.getElementById('bulkSelectedCount');

        function refreshBar() {
            const checked = checkboxes().filter(cb => cb.checked);
            bar.classList.toggle('d-none', checked.length === 0);
            countLabel.textContent = checked.length;
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

        document.querySelectorAll('.bulk-action-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const action = btn.dataset.action;
                const ids = checkboxes().filter(cb => cb.checked).map(cb => cb.value);
                if (ids.length === 0) return;
                if (action === 'delete' && !confirm('Delete ' + ids.length + ' selected propert' + (ids.length === 1 ? 'y' : 'ies') + '? This cannot be undone.')) return;

                btn.disabled = true;
                fetch("{{ route('portal.properties.bulk-action') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ids: ids, action: action }),
                })
                    .then(r => { if (!r.ok) throw new Error(); location.reload(); })
                    .catch(() => { btn.disabled = false; alert('Could not apply the bulk action. Please try again.'); });
            });
        });

        refreshBar();
    })();

    // Drag-and-drop reorder of the cards. Only the grip handle arms a drag, so the card's
    // buttons, links and checkbox behave normally.
    (function () {
        const grid = document.getElementById('propertyGrid');
        if (!grid) return;
        let dragEl = null;

        grid.addEventListener('mousedown', function (e) {
            const handle = e.target.closest('.portal-property-card__drag');
            if (handle) handle.closest('.portal-property-col').setAttribute('draggable', 'true');
        });
        grid.addEventListener('dragstart', function (e) {
            const col = e.target.closest('.portal-property-col');
            if (!col || col.getAttribute('draggable') !== 'true') return;
            dragEl = col;
            e.dataTransfer.effectAllowed = 'move';
            setTimeout(() => col.classList.add('is-dragging'), 0);
        });
        grid.addEventListener('dragover', function (e) {
            if (!dragEl) return;
            e.preventDefault();
            const target = e.target.closest('.portal-property-col');
            if (!target || target === dragEl) return;
            const rect = target.getBoundingClientRect();
            (e.clientX - rect.left) > rect.width / 2 ? target.after(dragEl) : target.before(dragEl);
        });
        grid.addEventListener('drop', function (e) {
            if (dragEl) e.preventDefault();
        });
        grid.addEventListener('dragend', function () {
            if (!dragEl) return;
            dragEl.classList.remove('is-dragging');
            dragEl.removeAttribute('draggable');
            dragEl = null;

            const order = Array.from(grid.querySelectorAll('.portal-property-col')).map(col => col.dataset.id);
            fetch("{{ route('portal.properties.reorder') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ order: order }),
            }).then(r => { if (!r.ok) throw new Error(); })
              .catch(() => { alert('Could not save the new order.'); location.reload(); });
        });
        document.addEventListener('mouseup', function () {
            if (!dragEl) grid.querySelectorAll('.portal-property-col[draggable]').forEach(col => col.removeAttribute('draggable'));
        });
    })();
</script>
@endpush
