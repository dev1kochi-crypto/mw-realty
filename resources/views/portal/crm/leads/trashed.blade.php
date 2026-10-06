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
    <div id="trashBulkBar" data-total="{{ $leads->total() }}" class="d-none align-items-center flex-wrap gap-2 mb-3 p-2 rounded" style="background: rgba(0,0,0,.04);">
        <span class="fw-semibold ms-1"><span id="trashBulkCount">0</span> selected</span>
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
                    <th style="width: 2.5rem;">
                        @if($leads->isNotEmpty())
                        <input type="checkbox" class="form-check-input" id="trashSelectAll" aria-label="Select all {{ $leads->total() }} deleted leads">
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
         * Selection, as on the Leads page: the header checkbox selects every deleted lead on every
         * page (the server resolves it from select_all=1), minus any row unticked afterwards;
         * otherwise just the ticked rows.
         */
        const headerBox = document.getElementById('trashSelectAll');
        const total = Number(document.getElementById('trashBulkBar').dataset.total || 0);
        const pageBoxes = Array.from(document.querySelectorAll('.trash-row-checkbox'));
        const bar = document.getElementById('trashBulkBar');
        let selectAll = false;

        const ticked = () => pageBoxes.filter(b => b.checked);
        const unticked = () => pageBoxes.filter(b => !b.checked);
        const count = () => selectAll ? total - unticked().length : ticked().length;

        function refresh() {
            const n = count();
            document.getElementById('trashBulkCount').textContent = n;
            bar.classList.toggle('d-none', n === 0);
            bar.classList.toggle('d-flex', n > 0);
            if (headerBox) {
                headerBox.checked = total > 0 && n === total;
                headerBox.indeterminate = n > 0 && n < total;
            }
        }

        function clearSelection() {
            selectAll = false;
            pageBoxes.forEach(b => { b.checked = false; });
            refresh();
        }

        headerBox?.addEventListener('change', function () {
            if (!headerBox.checked) return clearSelection();
            selectAll = true;
            pageBoxes.forEach(b => { b.checked = true; });
            refresh();
        });
        pageBoxes.forEach(b => b.addEventListener('change', refresh));
        document.getElementById('trashBulkClear').addEventListener('click', clearSelection);

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
