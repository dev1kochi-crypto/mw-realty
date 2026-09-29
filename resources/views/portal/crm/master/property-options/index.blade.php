@extends('portal.crm._layout')

@section('title', 'Master - Property Options')

@push('styles')
<style>
    .po-tabs { display: flex; gap: 0.35rem; flex-wrap: wrap; padding: 0.3rem; border-radius: 14px; background: var(--portal-surface); border: 1px solid var(--portal-border); width: fit-content; max-width: 100%; }
    .po-tab { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.5rem 1rem; border-radius: 10px; font-weight: 700; font-size: 0.86rem; color: var(--portal-muted); text-decoration: none; }
    .po-tab:hover { color: var(--portal-primary); }
    .po-tab.is-active { background: var(--portal-primary); color: #fff; }
    .po-tab .badge { background: rgba(0,0,0,0.08); color: inherit; font-weight: 700; }
    .po-tab.is-active .badge { background: rgba(255,255,255,0.22); }
    .po-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.78rem; color: var(--portal-muted); }
    .po-row.is-off td:not(:last-child) { opacity: 0.5; }
    .po-row[draggable="true"] .po-grip { cursor: grab; }
    .po-row.is-dragging { opacity: 0.4; }
</style>
@endpush

@section('crm-content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <div class="portal-section-title mb-0">Property Options</div>
        <p class="text-muted mb-0" style="font-size: 0.85rem;">The choices in the property form's dropdowns, for every agent and agency. Only Super Admin can change them — agents and agencies ask for new ones with a ticket.</p>
    </div>
    <button type="button" class="btn btn-portal-primary btn-sm" id="poAdd"><i class="fas fa-plus me-1"></i>Add {{ $lists[$key][0] }}</button>
</div>

<nav class="po-tabs mb-3">
    @foreach($lists as $listKey => [$label, $icon])
    <a href="{{ route('portal.crm.master.property-options.index', ['list' => $listKey]) }}" class="po-tab {{ $listKey === $key ? 'is-active' : '' }}">
        <i class="fas {{ $icon }}"></i>{{ $label }} <span class="badge rounded-pill">{{ $counts[$listKey] ?? 0 }}</span>
    </a>
    @endforeach
</nav>

@if($errors->any())
<div class="alert alert-danger py-2">{{ $errors->first() }}</div>
@endif

<div class="portal-card p-4">
    <div class="text-muted small mb-2"><i class="fas fa-arrows-alt me-1"></i> Drag rows to set the order shown in the form. Switching an option off hides it from new listings; listings that already use it keep it.</div>
    <div class="table-responsive">
        <table class="table portal-table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width: 32px;"></th>
                    @foreach($languages as $lang)<th>{{ $lang->name ?? strtoupper($lang->code) }}</th>@endforeach
                    <th>Code</th>
                    <th class="text-center">Listings</th>
                    <th class="text-center">Shown</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="poRows">
                @forelse($values as $option)
                @php $used = $usage[$option->value] ?? 0; @endphp
                <tr class="po-row {{ $option->status ? '' : 'is-off' }}" draggable="true" data-id="{{ $option->id }}">
                    <td class="text-muted po-grip"><i class="fas fa-grip-lines"></i></td>
                    @foreach($languages as $lang)
                    <td class="{{ $loop->first ? 'fw-semibold' : '' }}" @if($lang->code === 'ar') dir="rtl" @endif>{{ $option->translations[$lang->code]['label'] ?? '—' }}</td>
                    @endforeach
                    <td><span class="po-code">{{ $option->value }}</span></td>
                    <td class="text-center">{{ number_format($used) }}</td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block mb-0">
                            <input class="form-check-input po-toggle" type="checkbox" role="switch" data-id="{{ $option->id }}" @checked($option->status) aria-label="Shown in the form">
                        </div>
                    </td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm portal-btn-ghost po-edit" data-id="{{ $option->id }}" data-value="{{ $option->value }}"
                                data-used="{{ $used }}" data-labels='@json(collect($option->translations)->map(fn ($t) => $t['label'] ?? ''))'>Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger po-delete" data-id="{{ $option->id }}" @disabled($used > 0)
                                title="{{ $used ? 'Used by listings — switch it off instead' : 'Delete' }}">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ 5 + $languages->count() }}" class="portal-empty">No options yet — add the first one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="poModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="poForm" method="POST" action="{{ route('portal.crm.master.property-options.store', $key) }}">
            @csrf
            <input type="hidden" name="_method" id="poMethod" value="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="poTitle">Add {{ $lists[$key][0] }}</h5>
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

    document.getElementById('poAdd').addEventListener('click', () => {
        form.reset();
        form.action = @json(route('portal.crm.master.property-options.store', $key));
        method.value = 'POST';
        title.textContent = 'Add ' + listName;
        value.readOnly = false; locked.classList.add('d-none');
        modal.show();
    });

    document.addEventListener('click', e => {
        const edit = e.target.closest('.po-edit');
        if (edit) {
            form.reset();
            form.action = `${base}/${edit.dataset.id}`;
            method.value = 'PUT';
            title.textContent = 'Edit ' + listName;
            const labels = JSON.parse(edit.dataset.labels || '{}');
            form.querySelectorAll('.po-label').forEach(i => { i.value = labels[i.dataset.lang] || ''; });
            value.value = edit.dataset.value;
            const inUse = Number(edit.dataset.used) > 0;
            value.readOnly = inUse; locked.classList.toggle('d-none', !inUse);
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
