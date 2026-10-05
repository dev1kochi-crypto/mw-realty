@extends('portal.crm._layout')

@section('title', 'Master - Property Options')

@php
    // Left menu groups — every key in $lists appears once; anything not grouped falls into "Other".
    $groups = [
        'Listing form dropdowns' => ['property_type', 'listing_type', 'completion_status', \App\Models\Filter::FURNISHING_KEY, \App\Models\Filter::EMIRATE_KEY, \App\Models\Filter::RENTAL_PERIOD_KEY],
        'Features (with icons)' => ['amenity', 'easy_access', 'property_attribute'],
        'Nearby places' => [\App\Models\NearbyPlace::FILTER_KEY],
    ];
    $grouped = collect($groups)->flatten()->all();
    if ($other = array_values(array_diff(array_keys($lists), $grouped))) {
        $groups['Other'] = $other;
    }
    $hints = [
        'property_type' => 'Property Type on the listing form and the website search.',
        'listing_type' => 'Offering type (Rent / Sale) on the listing form and the website search.',
        'completion_status' => 'Ready / off-plan, on the listing form and the website search.',
        \App\Models\Filter::FURNISHING_KEY => 'Furnishing type on the listing form.',
        \App\Models\Filter::EMIRATE_KEY => 'Emirate on the listing form (Core details).',
        \App\Models\Filter::RENTAL_PERIOD_KEY => 'Rental period, shown on rent listings only.',
        'amenity' => 'Amenities agents tick on a listing — shown with their icon on the property page.',
        'easy_access' => 'What is close by (metro, malls, schools …), ticked on a listing.',
        'property_attribute' => 'Other notable features of a unit (corner unit, high floor …).',
        \App\Models\NearbyPlace::FILTER_KEY => 'Types used to group nearby places (schools, hospitals …).',
    ];
    [$listLabel, $listIcon] = $lists[$key];
    $mainLang = $languages->first();
    $otherLangs = $languages->slice(1);
@endphp

@push('styles')
<style>
    .po-layout { display: grid; grid-template-columns: 250px minmax(0, 1fr); gap: 1.25rem; align-items: start; }
    .po-nav { position: sticky; top: calc(var(--portal-topbar-h, 80px) + 1rem); padding: 0.75rem; }
    .po-nav-group + .po-nav-group { margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--portal-border); }
    .po-nav-label { font-size: 0.68rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: var(--portal-muted); padding: 0 0.6rem 0.4rem; }
    .po-nav-link { display: flex; align-items: center; gap: 0.6rem; padding: 0.55rem 0.6rem; border-radius: 10px; color: var(--portal-text); font-weight: 600; font-size: 0.87rem; text-decoration: none; transition: background 0.15s ease, color 0.15s ease; }
    .po-nav-link i { width: 18px; text-align: center; color: var(--portal-muted); font-size: 0.85rem; }
    .po-nav-link:hover { background: var(--portal-bg); color: var(--portal-primary); }
    .po-nav-link.is-active { background: var(--portal-primary); color: #fff; }
    .po-nav-link.is-active i { color: #fff; }
    .po-nav-count { margin-left: auto; min-width: 26px; padding: 0.1rem 0.45rem; border-radius: 50px; background: var(--portal-bg); color: var(--portal-muted); font-size: 0.72rem; font-weight: 700; text-align: center; }
    .po-nav-link.is-active .po-nav-count { background: rgba(255, 255, 255, 0.2); color: #fff; }

    .po-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem 1rem; padding-bottom: 1rem; margin-bottom: 0.5rem; border-bottom: 1px solid var(--portal-border); }
    .po-head-icon { flex: 0 0 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(79, 70, 229, 0.1); color: var(--portal-primary); }
    .po-head-title { font-weight: 800; font-size: 1.05rem; color: var(--portal-text); }
    .po-head-hint { font-size: 0.82rem; color: var(--portal-muted); }
    .po-head-actions { margin-left: auto; display: flex; align-items: center; gap: 0.5rem; }
    .po-search { position: relative; }
    .po-search i { position: absolute; left: 0.7rem; top: 50%; transform: translateY(-50%); color: var(--portal-muted); font-size: 0.8rem; }
    .po-search input { padding-left: 2rem; width: 200px; border-radius: 10px; }

    .po-table th { white-space: nowrap; }
    .po-table td { vertical-align: middle; }
    .po-grip { width: 28px; color: var(--portal-muted); }
    .po-row[draggable="true"] .po-grip { cursor: grab; }
    .po-row.is-dragging { opacity: 0.4; }
    .po-row.is-off .po-fade { opacity: 0.45; }
    .po-icon-cell { width: 52px; }
    .po-icon-box { width: 36px; height: 36px; border-radius: 10px; background: var(--portal-bg); display: flex; align-items: center; justify-content: center; }
    .po-icon-box img { width: 22px; height: 22px; object-fit: contain; }
    .po-name { font-weight: 700; color: var(--portal-text); }
    .po-name-alt { display: block; font-size: 0.82rem; color: var(--portal-muted); margin-top: 0.1rem; }
    .po-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.76rem; color: var(--portal-muted); background: var(--portal-bg); padding: 0.15rem 0.45rem; border-radius: 6px; }
    .po-used { display: inline-block; min-width: 34px; padding: 0.15rem 0.55rem; border-radius: 50px; font-size: 0.78rem; font-weight: 700; background: var(--portal-bg); color: var(--portal-muted); }
    .po-used.is-used { background: rgba(79, 70, 229, 0.1); color: var(--portal-primary); }
    .po-actions { width: 1%; white-space: nowrap; }
    .po-actions .btn { width: 34px; height: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }

    .po-icon-preview { flex: 0 0 44px; height: 44px; border-radius: 10px; border: 1px solid var(--portal-border); display: flex; align-items: center; justify-content: center; background: var(--portal-bg); }
    .po-icon-preview img { width: 26px; height: 26px; object-fit: contain; }

    @media (max-width: 991.98px) {
        .po-layout { grid-template-columns: minmax(0, 1fr); }
        .po-nav { position: static; display: flex; gap: 0.35rem; overflow-x: auto; padding: 0.5rem; }
        .po-nav-group, .po-nav-group + .po-nav-group { display: contents; }
        .po-nav-label { display: none; }
        .po-nav-link { flex: 0 0 auto; white-space: nowrap; }
        .po-head-actions { margin-left: 0; width: 100%; }
        .po-search, .po-search input { flex: 1; width: 100%; }
    }
</style>
@endpush

@section('crm-content')
<div class="mb-3">
    <div class="portal-section-title mb-0">Property Options</div>
    <p class="text-muted mb-0" style="font-size: 0.85rem;">The choices on the property form, for every agent and agency. Only Super Admin can change them — agents and agencies ask for new ones with a ticket.</p>
</div>

@if($errors->any())
<div class="alert alert-danger py-2">{{ $errors->first() }}</div>
@endif

<div class="po-layout">
    <nav class="portal-card po-nav" aria-label="Option lists">
        @foreach($groups as $groupLabel => $groupKeys)
        <div class="po-nav-group">
            <div class="po-nav-label">{{ $groupLabel }}</div>
            @foreach($groupKeys as $listKey)
            @continue(!isset($lists[$listKey]))
            <a href="{{ route('portal.crm.master.property-options.index', ['list' => $listKey]) }}" class="po-nav-link {{ $listKey === $key ? 'is-active' : '' }}" @if($listKey === $key) aria-current="page" @endif>
                <i class="fas {{ $lists[$listKey][1] }}"></i>{{ $lists[$listKey][0] }}<span class="po-nav-count">{{ $counts[$listKey] ?? 0 }}</span>
            </a>
            @endforeach
        </div>
        @endforeach
    </nav>

    <div class="portal-card p-4">
        <div class="po-head">
            <span class="po-head-icon"><i class="fas {{ $listIcon }}"></i></span>
            <div>
                <div class="po-head-title">{{ $listLabel }} <span class="text-muted fw-semibold" style="font-size: 0.85rem;">· {{ $values->count() }}</span></div>
                <div class="po-head-hint">{{ $hints[$key] ?? '' }}</div>
            </div>
            <div class="po-head-actions">
                @if($values->count() > 8)
                <div class="po-search">
                    <i class="fas fa-search"></i>
                    <input type="search" class="form-control form-control-sm" id="poSearch" placeholder="Search {{ strtolower($listLabel) }}" aria-label="Search options">
                </div>
                @endif
                <button type="button" class="btn btn-portal-primary btn-sm text-nowrap" id="poAdd"><i class="fas fa-plus me-1"></i>Add option</button>
            </div>
        </div>

        <div class="text-muted small mb-2"><i class="fas fa-arrows-alt me-1"></i> Drag rows to set the order shown on the form. Switching an option off hides it from new listings; listings that already use it keep it.</div>
        <div class="table-responsive">
            <table class="table portal-table po-table mb-0">
                <thead>
                    <tr>
                        <th class="po-grip"></th>
                        @if($hasIcons)<th class="po-icon-cell">Icon</th>@endif
                        <th>Name</th>
                        <th>Code</th>
                        <th class="text-center">{{ ucfirst($usageLabel) }}</th>
                        <th class="text-center">Shown</th>
                        <th class="text-end po-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="poRows">
                    @forelse($values as $option)
                    @php $used = $usage[$option->value] ?? 0; @endphp
                    <tr class="po-row {{ $option->status ? '' : 'is-off' }}" draggable="true" data-id="{{ $option->id }}"
                        data-search="{{ mb_strtolower(collect($option->translations)->pluck('label')->push($option->value)->implode(' ')) }}">
                        <td class="po-grip"><i class="fas fa-grip-lines"></i></td>
                        @if($hasIcons)
                        <td class="po-icon-cell po-fade">
                            <span class="po-icon-box">@if($option->icon)<img src="{{ media_url($option->icon) }}" alt="">@else<i class="fas fa-image text-muted"></i>@endif</span>
                        </td>
                        @endif
                        <td class="po-fade">
                            <span class="po-name">{{ $option->translations[$mainLang->code]['label'] ?? $option->value }}</span>
                            @foreach($otherLangs as $lang)
                            <span class="po-name-alt" @if($lang->code === 'ar') dir="rtl" style="text-align: left;" @endif>{{ $option->translations[$lang->code]['label'] ?? '—' }}</span>
                            @endforeach
                        </td>
                        <td class="po-fade"><span class="po-code">{{ $option->value }}</span></td>
                        <td class="text-center po-fade"><span class="po-used {{ $used ? 'is-used' : '' }}">{{ number_format($used) }}</span></td>
                        <td class="text-center">
                            <div class="form-check form-switch d-inline-block mb-0">
                                <input class="form-check-input po-toggle" type="checkbox" role="switch" data-id="{{ $option->id }}" @checked($option->status) aria-label="Shown on the form">
                            </div>
                        </td>
                        <td class="text-end po-actions">
                            <button type="button" class="btn btn-sm portal-btn-ghost po-edit" title="Edit" aria-label="Edit" data-id="{{ $option->id }}" data-value="{{ $option->value }}"
                                    data-used="{{ $used }}" data-icon="{{ media_url($option->icon) }}" data-labels='@json(collect($option->translations)->map(fn ($t) => $t['label'] ?? ''))'><i class="fas fa-pen"></i></button>
                            <span class="d-inline-block" title="{{ $used ? 'Used by ' . $used . ' ' . $usageLabel . ' — switch it off instead' : 'Delete' }}">
                                <button type="button" class="btn btn-sm portal-btn-ghost text-danger po-delete" aria-label="Delete" data-id="{{ $option->id }}" @disabled($used > 0) @if($used) style="pointer-events: none;" @endif><i class="fas fa-trash"></i></button>
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ 6 + ($hasIcons ? 1 : 0) }}" class="portal-empty">No options yet — add the first one.</td></tr>
                    @endforelse
                    <tr id="poNoMatch" class="d-none"><td colspan="{{ 6 + ($hasIcons ? 1 : 0) }}" class="portal-empty">No option matches your search.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="poModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="poForm" method="POST" enctype="multipart/form-data" action="{{ route('portal.crm.master.property-options.store', $key) }}">
            @csrf
            <input type="hidden" name="_method" id="poMethod" value="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="poTitle">Add option · {{ $lists[$key][0] }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @foreach($languages as $lang)
                <div class="mb-3">
                    <label class="form-label" for="poLabel{{ $lang->code }}">Name — {{ $lang->name ?? strtoupper($lang->code) }} @if($loop->first)<span class="text-danger">*</span>@endif</label>
                    <input type="text" class="form-control po-label" id="poLabel{{ $lang->code }}" name="translations[{{ $lang->code }}][label]" data-lang="{{ $lang->code }}" maxlength="255"
                           @if($lang->code === 'ar') dir="rtl" @endif @if($loop->first) required @endif>
                </div>
                @endforeach
                <div class="mb-1">
                    <label class="form-label" for="poValue">Code <span class="text-muted small">(saved on listings — made from the name if empty)</span></label>
                    <input type="text" class="form-control" id="poValue" name="value" maxlength="100" placeholder="e.g. duplex">
                    <div class="form-text d-none" id="poValueLocked"><i class="fas fa-lock me-1"></i>Used by listings, so the code can't change. You can still rename it.</div>
                </div>
                @if($hasIcons)
                <div class="mt-3">
                    <label class="form-label" for="poIcon">Icon <span class="text-muted small">(SVG or PNG, square, max 1 MB — shown on the listing page)</span></label>
                    <div class="d-flex align-items-center gap-3">
                        <span class="po-icon-preview" id="poIconPreview"><i class="fas fa-image text-muted"></i></span>
                        <input type="file" class="form-control" id="poIcon" name="icon" accept=".svg,image/png,image/jpeg,image/webp">
                    </div>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-portal-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-portal-primary btn-sm" id="poSave">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const base = @json(rtrim(route('portal.crm.master.property-options.index'), '/') . '/' . $key);
    const headers = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' };
    const modal = new bootstrap.Modal(document.getElementById('poModal'));
    const form = document.getElementById('poForm'), method = document.getElementById('poMethod');
    const title = document.getElementById('poTitle'), value = document.getElementById('poValue'), locked = document.getElementById('poValueLocked');
    const listName = @json($lists[$key][0]);
    // Icon lists only: preview of the current / newly chosen icon.
    const iconInput = document.getElementById('poIcon'), iconPreview = document.getElementById('poIconPreview');
    const showIcon = src => { if (iconPreview) iconPreview.innerHTML = src ? `<img src="${src}" alt="">` : '<i class="fas fa-image text-muted"></i>'; };
    iconInput?.addEventListener('change', () => showIcon(iconInput.files[0] ? URL.createObjectURL(iconInput.files[0]) : null));

    // Search (lists with more than 8 options).
    const search = document.getElementById('poSearch'), noMatch = document.getElementById('poNoMatch');
    search?.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        document.querySelectorAll('#poRows tr[data-id]').forEach(tr => {
            const hit = !q || tr.dataset.search.includes(q);
            tr.classList.toggle('d-none', !hit);
            shown += hit ? 1 : 0;
        });
        noMatch.classList.toggle('d-none', shown > 0);
    });

    document.getElementById('poAdd').addEventListener('click', () => {
        form.reset();
        form.action = @json(route('portal.crm.master.property-options.store', $key));
        method.value = 'POST';
        title.textContent = 'Add option · ' + listName;
        value.readOnly = false; locked.classList.add('d-none');
        showIcon(null);
        modal.show();
    });

    document.addEventListener('click', e => {
        const edit = e.target.closest('.po-edit');
        if (edit) {
            form.reset();
            form.action = `${base}/${edit.dataset.id}`;
            method.value = 'PUT';
            title.textContent = 'Edit option · ' + listName;
            const labels = JSON.parse(edit.dataset.labels || '{}');
            form.querySelectorAll('.po-label').forEach(i => { i.value = labels[i.dataset.lang] || ''; });
            value.value = edit.dataset.value;
            const inUse = Number(edit.dataset.used) > 0;
            value.readOnly = inUse; locked.classList.toggle('d-none', !inUse);
            showIcon(edit.dataset.icon || null);
            modal.show();
            return;
        }
        const del = e.target.closest('.po-delete');
        if (del && confirm('Delete this option?')) {
            fetch(`${base}/${del.dataset.id}`, { method: 'DELETE', headers })
                .then(async r => { const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.message || 'Could not delete.'); location.reload(); })
                .catch(err => alert(err.message));
        }
    });

    document.addEventListener('change', e => {
        const toggle = e.target.closest('.po-toggle');
        if (!toggle) return;
        toggle.disabled = true;
        fetch(`${base}/${toggle.dataset.id}/toggle`, { method: 'POST', headers })
            .then(r => { if (!r.ok) throw new Error(); toggle.closest('tr').classList.toggle('is-off', !toggle.checked); })
            .catch(() => { toggle.checked = !toggle.checked; alert('Could not update. Please try again.'); })
            .finally(() => { toggle.disabled = false; });
    });

    // Drag to reorder.
    const rows = document.getElementById('poRows');
    let dragged = null;
    rows.addEventListener('dragstart', e => { dragged = e.target.closest('tr'); dragged?.classList.add('is-dragging'); });
    rows.addEventListener('dragover', e => {
        e.preventDefault();
        const over = e.target.closest('tr');
        if (!dragged || !over || over === dragged) return;
        const after = e.clientY > over.getBoundingClientRect().top + over.offsetHeight / 2;
        over.parentNode.insertBefore(dragged, after ? over.nextSibling : over);
    });
    rows.addEventListener('dragend', () => {
        if (!dragged) return;
        dragged.classList.remove('is-dragging');
        dragged = null;
        const order = [...rows.querySelectorAll('tr[data-id]')].map(tr => Number(tr.dataset.id));
        fetch(`${base}/reorder`, { method: 'POST', headers, body: JSON.stringify({ order }) }).catch(() => alert('Could not save the order.'));
    });

    @if($errors->any()) modal.show(); @endif
})();
</script>
@endpush
