@extends('portal.crm._layout')

@section('title', 'Property Finder review')

{{-- Super Admin: imported Property Finder listings (Portal\Crm\PropertyFinderController::review).
     Approve → the listing joins the normal permit flow; Reject → the property is removed for good. --}}
@push('styles')
<style>
    .pfr-tabs { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .pfr-thumb { width: 64px; height: 48px; border-radius: 8px; object-fit: cover; background: #eef1f8; flex-shrink: 0; }
    .pfr-thumb--empty { display: grid; place-items: center; color: #9aa3b5; }
    .pfr-title { font-weight: 600; color: var(--portal-primary-dark); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .pfr-meta { font-size: .76rem; color: var(--portal-muted); }
    .pfr-chip { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .72rem; font-weight: 600; background: #eef1f8; color: var(--portal-primary); }
    .pfr-chip--warn { background: #fff4e0; color: #9a5b00; }
    .pfr-bulk { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; padding: 10px 14px; border: 1px solid #d6e4fb; border-radius: 12px; background: #f5f9ff; }
    .pfr-bulk[hidden] { display: none; }
    .pfr-search { position: relative; }
    .pfr-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--portal-muted); font-size: .8rem; }
    .pfr-search input { padding-left: 30px; min-width: 240px; border-radius: 8px; }
</style>
@endpush

@section('crm-content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <a href="{{ route('portal.crm.integrations.property-finder.show') }}" class="portal-link-muted small"><i class="fas fa-arrow-left me-1"></i>Property Finder</a>
        <div class="portal-section-title mb-0 mt-1">Property Finder — imported listings</div>
        <p class="text-muted mb-0" style="font-size: .85rem;">Approve to let a listing go live (its permit details still apply). Reject removes it — it won't be imported again.</p>
    </div>
</div>

<div class="pfr-tabs">
    <ul class="nav portal-lang-tabs mb-0">
        @foreach(['pending' => 'Waiting for review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('portal.crm.integrations.property-finder.review', ['tab' => $key, 'q' => $search ?: null]) }}">
                {{ $label }}<span class="portal-tab-count">{{ $counts[$key] ?? 0 }}</span>
            </a>
        </li>
        @endforeach
    </ul>
    <form method="GET" class="pfr-search">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <i class="fas fa-search"></i>
        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search title, reference or account…" aria-label="Search imported listings">
    </form>
</div>

@if($tab === 'pending' && $imports->isNotEmpty())
<form method="POST" action="{{ route('portal.crm.integrations.property-finder.review.action') }}" id="pfrForm" class="pfr-bulk" hidden>
    @csrf
    <span class="small fw-semibold me-auto"><span id="pfrCount">0</span> selected</span>
    <input type="text" name="note" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Reason (sent with a rejection)" maxlength="500" aria-label="Rejection reason">
    <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger js-pfr-reject"><i class="fas fa-xmark me-1"></i>Reject</button>
    <button type="submit" name="action" value="approve" class="btn btn-sm btn-portal-primary"><i class="fas fa-check me-1"></i>Approve</button>
</form>
@endif

<div class="portal-card p-0 mb-3">
    @if($imports->isEmpty())
    <div class="text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>{{ $search !== '' ? 'No listings match “' . $search . '”.' : 'Nothing here.' }}</div>
    @else
    <div class="table-responsive">
        <table class="table portal-table align-middle mb-0">
            <thead><tr>
                @if($tab === 'pending')<th style="width: 36px;"><input type="checkbox" class="form-check-input" id="pfrAll" aria-label="Select all"></th>@endif
                <th>Listing</th><th>Account</th><th>Price</th><th>Permit</th><th>Imported</th>@if($tab !== 'pending')<th>Reviewed</th>@endif
            </tr></thead>
            <tbody>
                @foreach($imports as $import)
                @php
                    $p = $import->property;
                    $cover = $p?->galleryImages()[0]['url'] ?? null;
                    $showUrl = $p ? route($p->segment === \App\Models\Property::SEGMENT_COMMERCIAL ? 'portal.commercial.show' : 'portal.properties.show', $p->id) : null;
                @endphp
                <tr>
                    @if($tab === 'pending')<td><input type="checkbox" class="form-check-input js-pfr-check" name="ids[]" value="{{ $import->id }}" form="pfrForm" aria-label="Select listing {{ $import->id }}"></td>@endif
                    <td>
                        <div class="d-flex gap-3 align-items-center">
                            @if($cover)<img src="{{ $cover }}" alt="" class="pfr-thumb" loading="lazy">@else<span class="pfr-thumb pfr-thumb--empty"><i class="fas fa-image"></i></span>@endif
                            <div class="min-w-0">
                                @if($p)
                                <a href="{{ $showUrl }}" class="pfr-title" target="_blank" rel="noopener">{{ $p->getTranslation('title') ?: $p->reference_no }}</a>
                                <div class="pfr-meta">{{ $p->reference_no }} · {{ ucfirst((string) $p->listing_type) }}{{ $p->property_type ? ' · ' . $p->filterLabel('property_type') : '' }}{{ $p->bedrooms !== null ? ' · ' . ($p->bedrooms ? $p->bedrooms . ' bed' : 'Studio') : '' }}</div>
                                <div class="pfr-meta">{{ $p->getTranslation('address') }}</div>
                                @else
                                <span class="text-muted">Removed</span>
                                @endif
                                <div class="pfr-meta">PF {{ $import->pf_reference ?: $import->pf_listing_id }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $import->owner?->displayName() ?? '—' }}</div>
                        @if($import->owner)<div class="pfr-meta">{{ $import->owner->isAgency() ? 'Agency' : 'Agent' }}</div>@endif
                    </td>
                    <td class="text-nowrap">{{ $p?->price ? 'AED ' . number_format((float) $p->price) : '—' }}</td>
                    <td>
                        @if($p?->permit_number)<span class="pfr-chip">{{ $p->permit_number }}</span>@else<span class="pfr-chip pfr-chip--warn">No permit</span>@endif
                    </td>
                    <td class="text-nowrap small">{{ $import->created_at->format('d M Y') }}</td>
                    @if($tab !== 'pending')
                    <td class="small">{{ $import->reviewed_at?->format('d M Y') }}@if($import->review_note)<div class="pfr-meta">{{ $import->review_note }}</div>@endif</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
<div>{{ $imports->links('pagination::bootstrap-5') }}</div>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.getElementById('pfrForm');
        if (!form) return;
        var all = document.getElementById('pfrAll'), checks = Array.prototype.slice.call(document.querySelectorAll('.js-pfr-check'));
        function refresh() {
            var n = checks.filter(function (c) { return c.checked; }).length;
            form.hidden = n === 0;
            document.getElementById('pfrCount').textContent = n;
            all.checked = n > 0 && n === checks.length;
            all.indeterminate = n > 0 && n < checks.length;
        }
        all.addEventListener('change', function () { checks.forEach(function (c) { c.checked = all.checked; }); refresh(); });
        checks.forEach(function (c) { c.addEventListener('change', refresh); });

        // Rejecting deletes the properties — confirm first.
        form.querySelector('.js-pfr-reject').addEventListener('click', async function (e) {
            if (form.dataset.confirmed) return;
            e.preventDefault();
            var btn = this;
            if (await window.portalConfirm({ title: 'Reject the selected listings?', message: 'Their properties and photos are removed, and they won\'t be imported again.', confirmText: 'Reject', tone: 'danger' })) {
                form.dataset.confirmed = '1';
                form.requestSubmit(btn);
            }
        });
    })();
</script>
@endpush
