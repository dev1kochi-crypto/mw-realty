@extends('portal.crm._layout')


@section('title', 'Master - Sources')

@section('crm-content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <div class="portal-section-title mb-0">Sources</div>
        <p class="text-muted mb-0" style="font-size: 0.85rem;">Track which channel each lead came from — website, referral, social, and so on.</p>
    </div>
    <button type="button" id="openAddSourceModal" class="btn btn-portal-primary btn-sm">
        <i class="fas fa-plus me-1" aria-hidden="true"></i> Add Source
    </button>
</div>

@include('portal.crm.master._shared_note', ['what' => 'sources'])

<div class="portal-card p-4">
    <div class="text-muted small mb-2"><i class="fas fa-arrows-alt me-1"></i> Drag your own rows to reorder.</div>
    <div class="table-responsive">
        <table class="table portal-table mb-0" id="sourcesTable">
            <thead><tr><th></th><th>Name</th><th class="text-center">Leads</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($sources as $source)
                @php $editable = $isAdmin || !$source->isGlobal(); @endphp
                <tr @if($editable) draggable="true" @endif data-id="{{ $source->id }}">
                    <td class="text-muted" @if($editable) style="cursor:grab;" @endif>@if($editable)<i class="fas fa-grip-lines"></i>@else<i class="fas fa-lock" title="Set by MW Realty"></i>@endif</td>
                    <td class="fw-semibold">{{ $source->name }} @include('portal.crm.master._global_badge', ['item' => $source])</td>
                    <td class="text-center">@include('portal.crm.master._lead_count', ['type' => 'sources', 'label' => 'source', 'item' => $source, 'editable' => $editable])</td>
                    <td class="text-end">
                        @if($editable)
                        <button type="button" class="btn btn-sm portal-btn-ghost edit-source-btn"
                            data-id="{{ $source->id }}" data-name="{{ $source->name }}">Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-source-btn" data-id="{{ $source->id }}" data-type="sources" data-label="source" data-name="{{ $source->name }}" data-count="{{ $source->leads_count }}">Delete</button>
                        @else
                        <span class="portal-muted small">View only</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="portal-empty">No sources yet — add one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@include('portal.crm.master._linked_leads_modal')

@include('portal.crm.master._item_modal', ['kind' => 'source', 'mode' => 'add'])
@include('portal.crm.master._item_modal', ['kind' => 'source', 'mode' => 'edit'])

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const addModalEl = document.getElementById('addSourceModal');
        const addModal = new bootstrap.Modal(addModalEl);
        const addSourceButton = document.getElementById('openAddSourceModal');
        const addSourceName = document.getElementById('sourceName');

        addSourceButton.addEventListener('click', function () {
            addModal.show();
        });

        addModalEl.addEventListener('shown.bs.modal', function () {
            addSourceName.focus();
        });

        @if(old('source_form') === 'create' && $errors->any())
        addModal.show();
        @endif

        const editModal = new bootstrap.Modal(document.getElementById('editSourceModal'));
        const editForm = document.getElementById('editSourceForm');

        document.querySelectorAll('.edit-source-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                editForm.action = "{{ url('portal/crm/master/sources') }}/" + btn.dataset.id;
                document.getElementById('editSourceName').value = btn.dataset.name;
                editModal.show();
            });
        });

        document.querySelectorAll('.delete-source-btn').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                if (Number(btn.dataset.count) > 0) { window.openLinkedLeads(btn, true); return; }
                if (!(await window.portalConfirm({ title: 'Delete this source?', message: 'It will be removed from your lead sources.', confirmText: 'Delete', tone: 'danger' }))) return;
                fetch("{{ url('portal/crm/master/sources') }}/" + btn.dataset.id, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                }).then(r => r.json()).then(function (data) {
                    if (data.success) { window.location.reload(); } else { alert(data.message || 'Could not delete this source.'); }
                });
            });
        });

        const table = document.getElementById('sourcesTable')?.querySelector('tbody');
        let dragEl = null;
        if (table) {
            table.addEventListener('dragstart', function (e) {
                const row = e.target.closest('tr');
                if (!row) return;
                dragEl = row;
                setTimeout(() => row.classList.add('is-dragging'), 0);
            });
            table.addEventListener('dragend', function () {
                if (dragEl) dragEl.classList.remove('is-dragging');
                dragEl = null;
            });
            table.addEventListener('dragover', function (e) {
                e.preventDefault();
                const target = e.target.closest('tr');
                if (!target || target === dragEl || !dragEl) return;
                const rect = target.getBoundingClientRect();
                (e.clientY - rect.top) > rect.height / 2 ? target.after(dragEl) : target.before(dragEl);
            });
            table.addEventListener('drop', function (e) {
                e.preventDefault();
                const order = Array.from(table.querySelectorAll('tr')).map(row => row.dataset.id);
                fetch("{{ route('portal.crm.master.sources.reorder') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ order: order }),
                });
            });
        }
    });
</script>
@endpush
@endsection
