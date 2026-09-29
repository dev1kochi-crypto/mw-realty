@extends('portal.layouts.app')

@section('title', 'Sold Listings')

@section('content')
@php
    $money = fn ($value, $currency = 'AED') => $currency . ' ' . number_format((float) $value);
    $sold = $totals[\App\Models\Property::SOLD] ?? null;
    $rented = $totals[\App\Models\Property::RENTED] ?? null;
@endphp

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <div class="portal-section-title mb-0">Sold Listings</div>
        <div class="text-muted small">Listings marked sold or rented. They are off the website; revert one to put it back on the market.</div>
    </div>
    @if(Route::has('portal.crm.reports.index'))
    <a href="{{ route('portal.crm.reports.index', ['report' => 'sales']) }}" class="btn btn-sm portal-btn-ghost"><i class="fas fa-chart-line me-1"></i>Sales Report</a>
    @endif
</div>

<div class="row g-3 mb-3">
    @foreach([
        ['Sold', number_format($sold->total ?? 0), 'fa-key', 'indigo'],
        ['Sales Value', $money($sold->value ?? 0), 'fa-sack-dollar', 'teal'],
        ['Rented', number_format($rented->total ?? 0), 'fa-file-signature', 'amber'],
        ['Rental Value', $money($rented->value ?? 0), 'fa-coins', 'rose'],
    ] as [$label, $value, $icon, $tone])
    <div class="col-6 col-lg-3">
        <div class="portal-stat-card">
            <div class="portal-stat-icon {{ $tone }}"><i class="fas {{ $icon }}"></i></div>
            <div><div class="portal-stat-value">{{ $value }}</div><div class="portal-stat-label">{{ $label }}</div></div>
        </div>
    </div>
    @endforeach
</div>

<div class="portal-card p-4">
    <form method="GET" action="{{ route('portal.sold.index') }}" class="row g-2 mb-3">
        <div class="col-md-7"><input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" maxlength="100" placeholder="Search title, ref no, address, city or buyer name"></div>
        <div class="col-6 col-md-2">
            <select name="type" class="form-select form-select-sm">
                <option value="">Sold &amp; Rented</option>
                <option value="sold" @selected($type === 'sold')>Sold</option>
                <option value="rented" @selected($type === 'rented')>Rented</option>
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-portal-primary flex-grow-1" type="submit"><i class="fas fa-search me-1"></i>Filter</button>
            @if($search !== '' || $type)<a href="{{ route('portal.sold.index') }}" class="btn btn-sm portal-btn-ghost">Clear</a>@endif
        </div>
    </form>

    @if($listings->isEmpty())
    <div class="text-center portal-muted py-5">No sold or rented listings match.</div>
    @else
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="small text-uppercase portal-muted">
                    <th>Property</th><th>Outcome</th><th class="text-end">Price</th><th>Date</th><th>Buyer / Tenant</th><th>Agent</th>@if($isAdmin)<th>Account</th>@endif<th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($listings as $property)
                @php
                    $thumb = $property->galleryImages()[0]['url'] ?? null;
                    $routePrefix = $property->segment === \App\Models\Property::SEGMENT_COMMERCIAL ? 'portal.commercial' : 'portal.properties';
                    $rentedOut = $property->sold_type === \App\Models\Property::RENTED;
                @endphp
                <tr data-sold-row="{{ $property->id }}">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="{{ $thumb ?: 'https://placehold.co/64x48?text=%20' }}" alt="" style="width: 64px; height: 48px; object-fit: cover; border-radius: 8px;">
                            <div class="min-w-0">
                                <a href="{{ route($routePrefix . '.show', $property->id) }}" class="fw-semibold text-decoration-none d-block text-truncate" style="max-width: 260px;">{{ $property->getTranslation('title') ?: 'Property' }}</a>
                                <div class="small portal-muted">{{ $property->reference_no }}@if($property->filterLabel('property_type')) &middot; {{ $property->filterLabel('property_type') }}@endif</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge {{ $rentedOut ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success-emphasis' }}">{{ $rentedOut ? 'Rented' : 'Sold' }}</span>
                        @if($property->rented_until)<div class="small portal-muted">until {{ $property->rented_until->format('d M Y') }}</div>@endif
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="fw-bold">{{ $money($property->sold_price, $property->currency ?: 'AED') }}</div>
                        @if($property->price && (float) $property->price !== (float) $property->sold_price)<div class="small portal-muted">listed {{ $money($property->price, $property->currency ?: 'AED') }}</div>@endif
                        @if($property->sold_commission)<div class="small portal-muted">commission {{ $money($property->sold_commission, $property->currency ?: 'AED') }}</div>@endif
                    </td>
                    <td class="small text-nowrap">{{ $property->sold_at?->format('d M Y') }}</td>
                    <td class="small">
                        @if($property->soldLead)
                        <a href="{{ route('portal.crm.leads.show', $property->soldLead->id) }}" class="fw-semibold text-decoration-none">{{ $property->soldLead->name ?: 'Lead #' . $property->soldLead->id }}</a>
                        <div class="portal-muted">{{ $property->soldLead->email ?: $property->soldLead->formatted_phone }}</div>
                        @else
                        <span class="portal-muted">—</span>
                        @endif
                    </td>
                    <td class="small">{{ $property->soldAgent?->name ?? '—' }}</td>
                    @if($isAdmin)<td class="small">{{ $property->owner?->displayName() ?? 'MW Realty' }}</td>@endif
                    <td class="text-end text-nowrap">
                        @if($property->sold_notes)
                        <button type="button" class="btn btn-sm portal-btn-ghost" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-content="{{ $property->sold_notes }}" title="Notes"><i class="fas fa-note-sticky"></i></button>
                        @endif
                        <button type="button" class="btn btn-sm portal-btn-ghost revert-sold" data-id="{{ $property->id }}" data-type="{{ $property->sold_type }}" title="Put back on the market"><i class="fas fa-rotate-left me-1"></i>Revert</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="portal-muted small">Showing {{ $listings->firstItem() }}–{{ $listings->lastItem() }} of {{ $listings->total() }}</span>
        <div>{{ $listings->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el));

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.revert-sold');
        if (!btn) return;
        if (!confirm('Put this listing back on the market? It will show on the website again (if it was active), and the sale is removed from Sold Listings. The lead keeps its history.')) return;
        btn.disabled = true;
        fetch("{{ url('portal/properties') }}/" + btn.dataset.id + '/revert-sold', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        })
            .then(r => { if (!r.ok) throw new Error(); location.reload(); })
            .catch(() => { btn.disabled = false; alert('Could not revert. Please try again.'); });
    });
</script>
@endpush
