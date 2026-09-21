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

<div class="portal-card p-4">
    <div class="text-muted small mb-2"><i class="fas fa-arrows-alt me-1"></i> Drag rows to reorder.</div>
    <div class="table-responsive">
        <table class="table portal-table mb-0" id="sourcesTable">
            <thead><tr><th></th><th>Name</th>@if($isAdmin)<th>Owner</th>@endif<th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($sources as $source)
                <tr draggable="true" data-id="{{ $source->id }}">
                    <td class="text-muted" style="cursor:grab;"><i class="fas fa-grip-lines"></i></td>
                    <td class="fw-semibold">{{ $source->name }}</td>
                    @if($isAdmin)
                    <td>{{ $source->owner?->displayName() ?? '-' }}</td>
                    @endif
                    <td class="text-end">
                        <button type="button" class="btn btn-sm portal-btn-ghost edit-source-btn"
                            data-id="{{ $source->id }}" data-name="{{ $source->name }}">Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-source-btn" data-id="{{ $source->id }}">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ $isAdmin ? 4 : 3 }}" class="portal-empty">No sources yet — add one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addSourceModal" tabindex="-1" aria-labelledby="addSourceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('portal.crm.master.sources.store') }}" method="POST">
                @csrf
                <input type="hidden" name="source_form" value="create">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSourceModalLabel">Add Source</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="sourceName">Name</label>
                        <input type="text" name="name" id="sourceName" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required maxlength="100" autofocus>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary">Add Source</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editSourceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editSourceForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Source</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="editSourceName" class="form-control" required maxlength="100">
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
            btn.addEventListener('click', function () {
                if (!confirm('Delete this source?')) return;
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
