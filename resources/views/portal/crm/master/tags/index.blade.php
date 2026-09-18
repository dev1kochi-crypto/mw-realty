@extends('portal.crm._layout')


@section('title', 'Master - Tags')

@section('crm-content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <div class="portal-section-title mb-0">Tags</div>
        <p class="text-muted mb-0" style="font-size: 0.85rem;">Label leads with your own tags to spot patterns at a glance — a lead can carry more than one.</p>
    </div>
    <button type="button" id="openAddTagModal" class="btn btn-portal-primary btn-sm">
        <i class="fas fa-plus me-1" aria-hidden="true"></i> Add Tag
    </button>
</div>

<div class="portal-card p-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead><tr><th>Name</th>@if($isAdmin)<th>Owner</th>@endif<th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($tags as $tag)
                <tr>
                    <td>
                        <span class="portal-tag-chip" style="background: {{ $tag->color }}22; color: {{ $tag->color }};">{{ $tag->name }}</span>
                    </td>
                    @if($isAdmin)
                    <td>{{ $tag->owner?->displayName() ?? '-' }}</td>
                    @endif
                    <td class="text-end">
                        <button type="button" class="btn btn-sm portal-btn-ghost edit-tag-btn"
                            data-id="{{ $tag->id }}" data-name="{{ $tag->name }}" data-color="{{ $tag->color }}">Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-tag-btn" data-id="{{ $tag->id }}">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ $isAdmin ? 3 : 2 }}" class="portal-empty">No tags yet — add one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addTagModal" tabindex="-1" aria-labelledby="addTagModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('portal.crm.master.tags.store') }}" method="POST">
                @csrf
                <input type="hidden" name="tag_form" value="create">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTagModalLabel">Add Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="tagName">Name</label>
                        <input type="text" name="name" id="tagName" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required maxlength="100" autofocus>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="tagColor">Color</label>
                        <input type="color" name="color" id="tagColor" class="form-control form-control-color" value="{{ old('color', '#14b8a6') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary">Add Tag</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editTagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editTagForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="editTagName" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color" id="editTagColor" class="form-control form-control-color">
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
        const addModalEl = document.getElementById('addTagModal');
        const addModal = new bootstrap.Modal(addModalEl);
        const addTagButton = document.getElementById('openAddTagModal');
        const addTagName = document.getElementById('tagName');

        addTagButton.addEventListener('click', function () {
            addModal.show();
        });

        addModalEl.addEventListener('shown.bs.modal', function () {
            addTagName.focus();
        });

        @if(old('tag_form') === 'create' && $errors->any())
        addModal.show();
        @endif

        const editModal = new bootstrap.Modal(document.getElementById('editTagModal'));
        const editForm = document.getElementById('editTagForm');

        document.querySelectorAll('.edit-tag-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                editForm.action = "{{ url('portal/crm/master/tags') }}/" + btn.dataset.id;
                document.getElementById('editTagName').value = btn.dataset.name;
                document.getElementById('editTagColor').value = btn.dataset.color;
                editModal.show();
            });
        });

        document.querySelectorAll('.delete-tag-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this tag?')) return;
                fetch("{{ url('portal/crm/master/tags') }}/" + btn.dataset.id, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                }).then(r => r.json()).then(function (data) {
                    if (data.success) { window.location.reload(); } else { alert(data.message || 'Could not delete this tag.'); }
                });
            });
        });
    });
</script>
@endpush
@endsection
