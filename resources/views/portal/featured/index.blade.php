@extends('portal.layouts.app')

@section('title', 'Premium')

@section('content')
@php
    $canAdd = $isAdmin || ($featuredQuota && $featuredQuota['limit'] > 0);
@endphp
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <div class="portal-section-title mb-1">Premium Listings</div>
        <p class="portal-muted small mb-0">Live and scheduled premium listings across Properties and Commercial. Premium listings get a badge and appear in the Premium section on the website.</p>
    </div>
    @if($canAdd)
    <button type="button" class="btn btn-portal-primary btn-sm" id="addFeaturedBtn" @disabled(!$isAdmin && ($featuredQuota['remaining'] ?? 0) === 0 && !$featuredQuota['per_month'])>
        <i class="fas fa-plus me-1"></i> Add Premium
    </button>
    @endif
</div>

{{-- Tabs + quota chip on one line (details in the chip's tooltip). --}}
<div class="portal-list-toolbar mb-3">
    <div class="portal-featured-tabs" role="tablist">
        @foreach(['all' => ['All', $counts['live'] + $counts['scheduled']], 'live' => ['Live', $counts['live']], 'scheduled' => ['Scheduled', $counts['scheduled']]] as $key => [$label, $count])
        <a href="{{ route('portal.featured.index', $key === 'all' ? [] : ['status' => $key]) }}" class="portal-featured-tabs__tab {{ $filter === $key ? 'is-active' : '' }}" role="tab" aria-selected="{{ $filter === $key ? 'true' : 'false' }}">
            {{ $label }} <span>{{ $count }}</span>
        </a>
        @endforeach
    </div>
    @if($featuredQuota)
    <div class="portal-list-toolbar__chips">
        @include('portal.properties._usage_chips', ['planUsage' => null, 'showManageFeatured' => false])
    </div>
    @endif
</div>

<div class="portal-card p-0 overflow-hidden">
    @if($listings->isEmpty())
        <div class="p-5 text-center portal-empty">
            <i class="far fa-star fa-2x mb-2 d-block" style="color:#f59e0b"></i>
            @if($filter === 'scheduled')
                Nothing scheduled.
            @elseif($filter === 'live')
                No listings are premium right now.
            @else
                No premium listings yet.
            @endif
            @if($canAdd)<a href="#" class="js-add-featured">Make a listing premium</a>.@endif
        </div>
    @else
    <div class="table-responsive">
        <table class="table portal-featured-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Listing</th>
                    <th>Menu</th>
                    @if($isAdmin)<th>Owner</th>@endif
                    <th>Status</th>
                    <th>Period</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($listings as $listing)
                @php
                    $thumb = $listing->galleryImages()[0]['url'] ?? null;
                    $commercial = $listing->segment === \App\Models\Property::SEGMENT_COMMERCIAL;
                    $prefix = $commercial ? 'portal.commercial' : 'portal.properties';
                    $live = $listing->featured;
                    $ownerLabel = $listing->owner ? ($listing->owner->type === 'company' ? ($listing->owner->company_name ?: $listing->owner->name) : $listing->owner->name) : 'MW Realty';
                    $daysLeft = $live && $listing->featured_until ? max(0, (int) ceil(now()->floatDiffInDays($listing->featured_until))) : null;
                @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <img src="{{ $thumb ?: 'https://placehold.co/96x72?text=No+Image' }}" alt="" class="portal-featured-table__thumb">
                            <div class="min-w-0">
                                <a href="{{ route($prefix . '.show', $listing->id) }}" class="portal-featured-table__title">{{ $listing->getTranslation('title') }}</a>
                                <div class="small portal-muted">Ref: {{ $listing->reference_no }}@if(!$listing->status) &middot; <span class="text-danger">Inactive</span>@endif</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="fm__tag {{ $commercial ? 'fm__tag--commercial' : '' }}">{{ $commercial ? 'Commercial' : 'Property' }}</span></td>
                    @if($isAdmin)<td class="small">{{ $ownerLabel }}</td>@endif
                    <td>
                        @if($live)
                            <span class="portal-featured-status is-live"><i class="fas fa-circle"></i> Live</span>
                        @else
                            <span class="portal-featured-status is-scheduled"><i class="far fa-clock"></i> Scheduled</span>
                        @endif
                    </td>
                    <td class="small">
                        <div class="fw-semibold">
                            @if($listing->featured_from)
                                {{ $listing->featured_from->format('d M Y') }} &rarr;
                            @endif
                            {{ $listing->featured_until ? $listing->featured_until->format('d M Y') : 'No end date' }}
                        </div>
                        <div class="portal-muted">
                            @if($live && $daysLeft !== null)
                                {{ $daysLeft <= 1 ? 'Ends within a day' : $daysLeft . ' days left' }}
                            @elseif(!$live)
                                Goes live {{ $listing->featured_from->diffForHumans() }}
                            @else
                                Until stopped
                            @endif
                        </div>
                    </td>
                    <td class="text-end text-nowrap">
                        {{-- Edit = change this feature's dates (the listing itself opens from its title). --}}
                        @if(isset($editable[$listing->id]) && $canAdd)
                        <button type="button" class="portal-btn-ghost btn btn-sm edit-feature-dates" title="Edit premium dates" aria-label="Edit premium dates"
                                data-id="{{ $listing->id }}" data-title="{{ $listing->getTranslation('title') }}" data-thumb="{{ $thumb }}" data-ref="{{ $listing->reference_no }}"
                                data-live="{{ $live ? 1 : 0 }}" data-start="{{ ($listing->featured_from ?? now())->format('Y-m-d') }}" data-end="{{ $listing->featured_until?->format('Y-m-d') }}">
                            <i class="fas fa-edit"></i>
                        </button>
                        @else
                        <span class="d-inline-block" tabindex="0" title="Set by MW Realty — contact us to change these dates">
                            <button type="button" class="portal-btn-ghost btn btn-sm" disabled aria-label="Premium dates set by MW Realty"><i class="fas fa-edit"></i></button>
                        </span>
                        @endif
                        <button type="button" class="btn btn-sm portal-featured-stop unfeature-property" data-id="{{ $listing->id }}" data-scheduled="{{ $live ? 0 : 1 }}">
                            {{ $live ? 'Stop' : 'Cancel' }}
                        </button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div class="mt-3">{{ $listings->links('pagination::bootstrap-5') }}</div>

@if($canAdd)
    @include('portal.properties._feature_modal', ['pickerUrl' => route('portal.featured.eligible')])
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('click', function (e) {
        if (e.target.closest('#addFeaturedBtn, .js-add-featured')) {
            e.preventDefault();
            window.openFeatureModal && window.openFeatureModal();
            return;
        }
        const editBtn = e.target.closest('.edit-feature-dates');
        if (editBtn) {
            const d = editBtn.dataset;
            window.openFeatureEditModal({ id: d.id, title: d.title, thumb: d.thumb, ref: d.ref, live: d.live === '1', start: d.start, end: d.end });
            return;
        }
        const stopBtn = e.target.closest('.unfeature-property');
        if (!stopBtn) return;
        confirmStop(stopBtn);
    });

    async function confirmStop(stopBtn) {
        const scheduled = stopBtn.dataset.scheduled === '1';
        const name = stopBtn.closest('tr')?.querySelector('.portal-featured-table__title')?.textContent.trim();
        const ok = await window.portalConfirm(scheduled ? {
            title: 'Cancel scheduled premium?',
            message: (name ? '“' + name + '” won\'t be premium. ' : '') + 'The booking is removed, so it no longer counts toward your plan.',
            confirmText: 'Cancel premium',
            cancelText: 'Keep it',
            tone: 'warning',
        } : {
            title: 'Remove premium now?',
            message: (name ? '“' + name + '” ' : 'This listing ') + 'loses its Premium badge and top placement on the website right away.'
                + @json($featuredQuota && $featuredQuota['per_month'] ? ' It still counts toward this month\'s quota.' : ''),
            confirmText: 'Remove premium',
            cancelText: 'Keep premium',
            tone: 'warning',
        });
        if (!ok) return;
        stopBtn.disabled = true;
        fetch("{{ url('portal/properties') }}/" + stopBtn.dataset.id + '/unfeature', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        })
            .then(r => { if (!r.ok) throw new Error(); location.reload(); })
            .catch(() => { stopBtn.disabled = false; alert('Could not update premium. Please try again.'); });
    }
</script>
@endpush
