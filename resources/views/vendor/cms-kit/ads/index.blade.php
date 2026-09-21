@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Ad Management</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="mb-0">Ads</h5>
                    <small class="text-muted">Image/GIF banners shown at a named placement on the site.</small>
                </div>
                <div class="d-flex gap-2">
                    @if($cmsUser->can('ads.delete'))
                    <div class="dropdown" id="bulkActions" style="display: none;">
                        <button class="btn btn-outline-danger btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Bulk Actions (<span id="selectedCount">0</span>)
                        </button>
                        <ul class="dropdown-menu">
                            <li><button class="dropdown-item" type="button" onclick="bulkAction('active')"><i class="fas fa-check-circle text-success me-2"></i> Mark Active</button></li>
                            <li><button class="dropdown-item" type="button" onclick="bulkAction('inactive')"><i class="fas fa-times-circle text-secondary me-2"></i> Mark Inactive</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item" type="button" onclick="bulkAction('delete')"><i class="fas fa-trash text-danger me-2"></i> Delete Selected</button></li>
                        </ul>
                    </div>
                    @endif
                    @if($cmsUser->can('ads.create'))
                    <a href="{{ route('cms.ads.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Ad
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table premium-table mb-0 w-100">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                                <th style="width: 40px;">#</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Placement</th>
                                <th>Schedule</th>
                                <th style="width: 100px;">Order</th>
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
            ajax: "{{ route('cms.ads.index') }}",
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'image', name: 'image', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'placement', name: 'placement'},
                {data: 'schedule', name: 'schedule', orderable: false, searchable: false},
                {data: 'order', name: 'order', className: 'text-center'},
                {data: 'status', name: 'status', className: 'text-center'},
                {data: 'action', name: 'action', orderable: false, searchable: false}
            ],
            order: [[6, 'asc']],
            drawCallback: function() { updateBulkVisibility(); }
        });

        $(document).on('change', '.toggle-status', function() {
            const id = $(this).data('id');
            const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/ads/" + id + "/toggle-status";
            $.post(url, { _token: '{{ csrf_token() }}' }).done(() => table.ajax.reload(null, false));
        });

        $(document).on('change', '.reorder-input', function() {
            const id = $(this).data('id');
            const order = $(this).val();
            $.post("{{ route('cms.ads.reorder') }}", { _token: '{{ csrf_token() }}', id: id, order_index: order })
                .done(() => table.ajax.reload(null, false));
        });

        $(document).on('click', '.delete-item', function() {
            if (confirm('Are you sure you want to delete this ad?')) {
                const id = $(this).data('id');
                const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/ads/" + id;
                $.ajax({ url: url, type: 'DELETE', data: { _token: '{{ csrf_token() }}' }, success: () => table.ajax.reload(null, false) });
            }
        });

        $('#selectAll').on('change', function() {
            $('.row-checkbox').prop('checked', this.checked);
            updateBulkVisibility();
        });
        $(document).on('change', '.row-checkbox', function() { updateBulkVisibility(); });

        function updateBulkVisibility() {
            const checkedCount = $('.row-checkbox:checked').length;
            $('#selectedCount').text(checkedCount);
            $('#bulkActions').toggle(checkedCount > 0);
            $('#selectAll').prop('checked', checkedCount > 0 && checkedCount === $('.row-checkbox').length && $('.row-checkbox').length > 0);
        }

        window.bulkAction = function(action) {
            if (action === 'delete' && !confirm('Are you sure you want to delete selected ads?')) return;
            const ids = $('.row-checkbox:checked').map(function() { return $(this).val(); }).get();
            $.post("{{ route('cms.ads.bulk-action') }}", { _token: '{{ csrf_token() }}', action: action, ids: ids })
                .done(function() {
                    table.ajax.reload(null, false);
                    $('#selectAll').prop('checked', false);
                    updateBulkVisibility();
                });
        };
    });
</script>
@endpush
