@extends('portal.crm._layout')

@section('title', 'Deleted Leads')

@section('crm-content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="portal-section-title mb-0">Deleted Leads</div>
        <p class="text-muted mb-0" style="font-size: 0.85rem;">Restore a lead to bring it back, or permanently delete it.</p>
    </div>
    <a href="{{ route('portal.crm.leads.index') }}" class="portal-btn-ghost btn btn-sm">Back to Leads</a>
</div>

<div class="portal-card p-3 p-md-4">
    {{-- Bulk bar — shown while leads are ticked (or "select all" is on). --}}
    <div id="trashBulkBar" class="d-none align-items-center flex-wrap gap-2 mb-3 p-2 rounded" style="background: rgba(0,0,0,.04);">
        <span class="fw-semibold ms-1"><span id="trashBulkCount">0</span> selected</span>
        <span id="trashSelectAllHint" class="text-muted d-none" style="font-size: .85rem;">
            All {{ $leads->count() }} on this page are selected.
            <a href="#" id="trashSelectAllLink">Select all {{ $leads->total() }} deleted leads</a>
        </span>
        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-sm portal-btn-ghost" id="trashBulkClear">Clear</button>
            <button type="button" class="btn btn-sm portal-btn-ghost" data-trash-action="restore"><i class="fas fa-rotate-left me-1"></i>Restore</button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-trash-action="force"><i class="fas fa-trash me-1"></i>Delete Permanently</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th style="width: 36px;">
                        @if($leads->isNotEmpty())
                        <input type="checkbox" class="form-check-input" id="trashSelectPage" aria-label="Select all on this page">
                        @endif
                    </th>
                    <th>Lead</th>
                    <th>Property</th>
                    @if($isAdmin)
                    <th>Owner</th>
                    @endif
                    <th>Stage</th>
                    <th>Deleted</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr>
                    <td><input type="checkbox" class="form-check-input trash-row-checkbox" value="{{ $lead->id }}" aria-label="Select {{ $lead->name ?: 'lead' }}"></td>
                    <td>
                        <div class="fw-semibold">{{ $lead->name ?: 'Unknown' }}</div>
                        <div class="text-muted" style="font-size: 0.78rem;">{{ $lead->email ?: $lead->formatted_phone ?: '-' }}</div>
                    </td>
                    <td>{{ $lead->property?->getTranslation('title') ?? '-' }}</td>
                    @if($isAdmin)
                    <td>{{ $lead->owner?->displayName() ?? '-' }}</td>
                    @endif
                    <td>{{ $lead->stage?->name ?? '-' }}</td>
                    <td class="text-muted">{{ $lead->deleted_at->format('d M Y, H:i') }}</td>
                    <td class="text-end">
                        <form action="{{ route('portal.crm.leads.restore', $lead->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm portal-btn-ghost">Restore</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-outline-danger force-delete-lead-btn" data-id="{{ $lead->id }}">Delete Permanently</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 7 : 6 }}" class="portal-empty">No deleted leads.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $leads->links('pagination::bootstrap-5') }}</div>

<form id="forceDeleteLeadForm" method="POST" class="d-none">
    @csrf
    @method('DELETE')
</form>

<form id="trashBulkForm" method="POST" action="{{ route('portal.crm.leads.bulk-trashed') }}" class="d-none">
    @csrf
    <input type="hidden" name="action" id="trashBulkAction">
</form>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.force-delete-lead-btn').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                if (!(await window.portalConfirm({ title: 'Delete this lead forever?', message: 'It will be permanently removed and can\'t be restored.', confirmText: 'Delete forever', tone: 'danger' }))) return;
                const form = document.getElementById('forceDeleteLeadForm');
                form.action = "{{ url('portal/crm/leads') }}/" + btn.dataset.id + '/force';
                form.submit();
            });
        });

        /*
         * Selection: the ticked rows on this page, or "select all" = every deleted lead on every
         * page (the server resolves it from select_all=1) minus any row unticked afterwards.
         */
        const total = {{ $leads->total() }};
        const pageBoxes = Array.from(document.querySelectorAll('.trash-row-checkbox'));
        const pageToggle = document.getElementById('trashSelectPage');
        const bar = document.getElementById('trashBulkBar');
        const hint = document.getElementById('trashSelectAllHint');
        let selectAll = false;

        const ticked = () => pageBoxes.filter(b => b.checked);
        const unticked = () => pageBoxes.filter(b => !b.checked);
        const count = () => selectAll ? total - unticked().length : ticked().length;

        function refresh() {
            const n = count();
            const allOnPage = pageBoxes.length > 0 && unticked().length === 0;
            document.getElementById('trashBulkCount').textContent = n;
            bar.classList.toggle('d-none', n === 0);
            bar.classList.toggle('d-flex', n > 0);
            if (pageToggle) {
                pageToggle.checked = allOnPage;
                pageToggle.indeterminate = !allOnPage && ticked().length > 0;
            }
            hint.classList.toggle('d-none', !(allOnPage && !selectAll && total > pageBoxes.length));
        }

        function clearSelection() {
            selectAll = false;
            pageBoxes.forEach(b => { b.checked = false; });
            refresh();
        }

        pageToggle?.addEventListener('change', function () {
            if (!pageToggle.checked) return clearSelection();
            pageBoxes.forEach(b => { b.checked = true; });
            refresh();
        });
        pageBoxes.forEach(b => b.addEventListener('change', refresh));
        document.getElementById('trashBulkClear').addEventListener('click', clearSelection);
        document.getElementById('trashSelectAllLink').addEventListener('click', function (e) {
            e.preventDefault();
            selectAll = true;
            refresh();
        });

        document.querySelectorAll('[data-trash-action]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const n = count();
                if (!n) return;
                const action = btn.dataset.trashAction;
                const noun = n === 1 ? '1 lead' : n + ' leads';
                const confirmed = action === 'restore'
                    ? await window.portalConfirm({ title: 'Restore ' + noun + '?', message: 'They will go back to your leads list.', confirmText: 'Restore' })
                    : await window.portalConfirm({ title: 'Delete ' + noun + ' forever?', message: 'They will be permanently removed and can\'t be restored.', confirmText: 'Delete forever', tone: 'danger' });
                if (!confirmed) return;

                const form = document.getElementById('trashBulkForm');
                form.querySelectorAll('.trash-bulk-field').forEach(el => el.remove());
                document.getElementById('trashBulkAction').value = action;

                function add(name, value) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    input.className = 'trash-bulk-field';
                    form.appendChild(input);
                }
                if (selectAll) {
                    add('select_all', '1');
                    unticked().forEach(b => add('exclude_ids[]', b.value));
                } else {
                    ticked().forEach(b => add('ids[]', b.value));
                }
                form.submit();
            });
        });
    });
</script>
@endpush
@endsection
