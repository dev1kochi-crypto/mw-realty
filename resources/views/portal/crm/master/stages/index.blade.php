@extends('portal.crm._layout')


@section('title', 'Master - Stages')

@section('crm-content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <div class="portal-section-title mb-0">Stages</div>
        <p class="text-muted mb-0" style="font-size: 0.85rem;">Build your lead pipeline — drag to reorder, pick a color per stage, and mark which ones count as closed.</p>
    </div>
    <button type="button" id="openAddStageModal" class="btn btn-portal-primary btn-sm">
        <i class="fas fa-plus me-1" aria-hidden="true"></i> Add Stage
    </button>
</div>

@include('portal.crm.master._shared_note', ['what' => 'stages'])

<div class="portal-card p-4">
    <div class="text-muted small mb-2"><i class="fas fa-arrows-alt me-1"></i> Drag your own rows to reorder your pipeline.</div>
    <div class="table-responsive">
        <table class="table portal-table mb-0" id="stagesTable">
            <thead>
                <tr><th></th><th>Name</th><th>Closed?</th><th>Default</th><th class="text-center">Leads</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                @forelse($stages as $stage)
                @php $editable = $isAdmin || !$stage->isGlobal(); @endphp
                <tr @if($editable) draggable="true" @endif data-id="{{ $stage->id }}">
                    <td class="text-muted" @if($editable) style="cursor:grab;" @endif>@if($editable)<i class="fas fa-grip-lines"></i>@else<i class="fas fa-lock" title="Set by MW Realty"></i>@endif</td>
                    <td>
                        <span class="portal-color-dot" style="background: {{ $stage->color }};"></span>
                        <span class="fw-semibold">{{ $stage->name }}</span>
                        @include('portal.crm.master._global_badge', ['item' => $stage])
                    </td>
                    <td>{{ $stage->is_closed ? 'Yes' : 'No' }}</td>
                    <td>
                        @if($stage->is_default)
                        <span class="portal-badge-status portal-badge-active">Default</span>
                        @elseif($editable)
                        <form action="{{ route('portal.crm.master.stages.set-default', $stage->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm p-0">Set default</button>
                        </form>
                        @endif
                    </td>
                    <td class="text-center">@include('portal.crm.master._lead_count', ['type' => 'stages', 'label' => 'stage', 'item' => $stage, 'editable' => $editable])</td>
                    <td class="text-end">
                        @if($editable)
                        <button type="button" class="btn btn-sm portal-btn-ghost edit-stage-btn"
                            data-id="{{ $stage->id }}" data-name="{{ $stage->name }}" data-color="{{ $stage->color }}" data-closed="{{ $stage->is_closed ? 1 : 0 }}">
                            Edit
                        </button>
                        @if(!$stage->is_default)
                        <button type="button" class="btn btn-sm btn-outline-danger delete-stage-btn" data-id="{{ $stage->id }}" data-type="stages" data-label="stage" data-name="{{ $stage->name }}" data-count="{{ $stage->leads_count }}">Delete</button>
                        @endif
                        @else
                        <span class="portal-muted small">View only</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="portal-empty">No stages yet — add one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@include('portal.crm.master._linked_leads_modal')

<div class="modal fade" id="addStageModal" tabindex="-1" aria-labelledby="addStageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('portal.crm.master.stages.store') }}" method="POST">
                @csrf
                <input type="hidden" name="stage_form" value="create">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStageModalLabel">Add Stage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="stageName">Name</label>
                        <input type="text" name="name" id="stageName" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required maxlength="100" autofocus>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="stageColor">Color</label>
                        <input type="color" name="color" id="stageColor" class="form-control form-control-color" value="{{ old('color', '#4f46e5') }}">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_closed" value="1" class="form-check-input" id="stageIsClosed" @checked(old('is_closed'))>
                        <label class="form-check-label" for="stageIsClosed">Counts as closed</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary">Add Stage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editStageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editStageForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Stage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="editStageName" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color" id="editStageColor" class="form-control form-control-color">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_closed" value="1" class="form-check-input" id="editStageClosed">
                        <label class="form-check-label" for="editStageClosed">Counts as closed</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const addModalEl = document.getElementById('addStageModal');
        const addModal = new bootstrap.Modal(addModalEl);
        const addStageButton = document.getElementById('openAddStageModal');
        const addStageName = document.getElementById('stageName');

        addStageButton.addEventListener('click', function () {
            addModal.show();
        });

        addModalEl.addEventListener('shown.bs.modal', function () {
            addStageName.focus();
        });

        @if(old('stage_form') === 'create' && $errors->any())
        addModal.show();
        @endif

        const editModalEl = document.getElementById('editStageModal');
        const editModal = new bootstrap.Modal(editModalEl);
        const editForm = document.getElementById('editStageForm');

        document.querySelectorAll('.edit-stage-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                editForm.action = "{{ url('portal/crm/master/stages') }}/" + btn.dataset.id;
                document.getElementById('editStageName').value = btn.dataset.name;
                document.getElementById('editStageColor').value = btn.dataset.color;
                document.getElementById('editStageClosed').checked = btn.dataset.closed === '1';
                editModal.show();
            });
        });

        document.querySelectorAll('.delete-stage-btn').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                // Still used by leads → show those leads so the stage can be taken off them first.
                if (Number(btn.dataset.count) > 0) { window.openLinkedLeads(btn, true); return; }
                if (!(await window.portalConfirm({ title: 'Delete this stage?', message: 'It will be removed from your lead pipeline.', confirmText: 'Delete', tone: 'danger' }))) return;
                fetch("{{ url('portal/crm/master/stages') }}/" + btn.dataset.id, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                }).then(r => r.json()).then(function (data) {
                    if (data.success) { window.location.reload(); } else { alert(data.message || 'Could not delete this stage.'); }
                });
            });
        });

        const table = document.getElementById('stagesTable')?.querySelector('tbody');
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
                fetch("{{ route('portal.crm.master.stages.reorder') }}", {
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
