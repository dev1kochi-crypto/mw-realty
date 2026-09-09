@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Agents & Companies</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Agents & Companies</h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                    <i class="fas fa-info-circle text-primary me-2"></i>
                    Agents and companies register their own account at <code>/portal/register</code>. New accounts are
                    <strong>pending</strong> until approved here — only approved accounts can log in and list properties.
                </div>
                <div class="table-responsive">
                    <table class="table premium-table mb-0 w-100">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Properties</th>
                                <th>Enquiries</th>
                                <th>Plan</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(function() {
        const table = $('.premium-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('cms.portal-accounts.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'display_name', name: 'display_name'},
                {data: 'type', name: 'type'},
                {data: 'email', name: 'email'},
                {data: 'phone', name: 'phone'},
                {data: 'properties_count', name: 'properties_count'},
                {data: 'enquiries_count', name: 'enquiries_count'},
                {data: 'plan', name: 'plan', orderable: false, searchable: false},
                {data: 'status', name: 'status', className: 'text-center'},
                {data: 'action', name: 'action', orderable: false, searchable: false}
            ],
            order: [[0, 'desc']]
        });

        const base = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/portal-accounts/";

        $(document).on('click', '.approve-item', function() {
            const id = $(this).data('id');
            $.post(base + id + '/approve', { _token: '{{ csrf_token() }}' }).done(() => table.ajax.reload(null, false));
        });

        $(document).on('click', '.reject-item', function() {
            if (!confirm('Reject this account? They will not be able to log in.')) return;
            const id = $(this).data('id');
            $.post(base + id + '/reject', { _token: '{{ csrf_token() }}' }).done(() => table.ajax.reload(null, false));
        });

        $(document).on('click', '.delete-item', function() {
            if (!confirm('Delete this account permanently?')) return;
            const id = $(this).data('id');
            $.ajax({ url: base + id, type: 'DELETE', data: { _token: '{{ csrf_token() }}' }, success: () => table.ajax.reload(null, false) });
        });

        $(document).on('change', '.plan-select', function() {
            const id = $(this).data('id');
            const planId = $(this).val();
            $.post(base + id + '/assign-plan', { _token: '{{ csrf_token() }}', plan_id: planId })
                .done(() => table.ajax.reload(null, false));
        });
    });
</script>
@endpush
