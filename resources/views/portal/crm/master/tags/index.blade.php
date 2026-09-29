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

@include('portal.crm.master._shared_note', ['what' => 'tags'])

<div class="portal-card p-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead><tr><th>Name</th><th class="text-center">Leads</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($tags as $tag)
                @php $editable = $isAdmin || !$tag->isGlobal(); @endphp
                <tr>
                    <td>
                        <span class="portal-tag-chip" style="background: {{ $tag->color }}22; color: {{ $tag->color }};">{{ $tag->name }}</span>
                        @include('portal.crm.master._global_badge', ['item' => $tag])
                    </td>
                    <td class="text-center">@include('portal.crm.master._lead_count', ['type' => 'tags', 'label' => 'tag', 'item' => $tag, 'editable' => $editable])</td>
                    <td class="text-end">
                        @if($editable)
                        <button type="button" class="btn btn-sm portal-btn-ghost edit-tag-btn"
                            data-id="{{ $tag->id }}" data-name="{{ $tag->name }}" data-color="{{ $tag->color }}">Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-tag-btn" data-id="{{ $tag->id }}" data-type="tags" data-label="tag" data-name="{{ $tag->name }}" data-count="{{ $tag->leads_count }}">Delete</button>
                        @else
                        <span class="portal-muted small">View only</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="portal-empty">No tags yet — add one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@include('portal.crm.master._linked_leads_modal')

@include('portal.crm.master._item_modal', ['kind' => 'tag', 'mode' => 'add'])
@include('portal.crm.master._item_modal', ['kind' => 'tag', 'mode' => 'edit'])

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
            btn.addEventListener('click', async function () {
                if (Number(btn.dataset.count) > 0) { window.openLinkedLeads(btn, true); return; }
                if (!(await window.portalConfirm({ title: 'Delete this tag?', message: 'It will be removed from your lead tags.', confirmText: 'Delete', tone: 'danger' }))) return;
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
