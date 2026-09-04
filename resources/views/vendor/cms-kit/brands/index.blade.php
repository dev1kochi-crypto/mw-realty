@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Brands</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Brands</h5>
        <div class="d-flex gap-2">
            @if(auth('cms')->user()->can('brands.delete'))
            <div class="dropdown" id="bulkActions" style="display: none;">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
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
            @if(auth('cms')->user()->can('brands.create'))
            <a href="{{ route('cms.brands.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Add Brand
            </a>
            @endif
        </div>
    </div>
    <form id="bulkForm" action="{{ route('cms.brands.bulk-action') }}" method="POST">
        @csrf
        <input type="hidden" name="action" id="bulkActionInput">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table premium-table mb-0 w-100" id="brandsTable">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th style="width: 50px;">#</th>
                        <th style="width: 100px;">Logo</th>
                        <th>ALT Text</th>
                        <th style="width: 100px;">Order</th>
                        <th style="width: 100px;" class="text-center">Status</th>
                        <th style="width: 100px;" class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    </form>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(function() {
    let table = $('#brandsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('cms.brands.index') }}",
        columns: [
            {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'image', name: 'image', orderable: false, searchable: false},
            {data: 'image_alt', name: 'image_alt'},
            {data: 'order', name: 'order', orderable: false, searchable: false},
            {data: 'status', name: 'status', orderable: false, searchable: false, className: 'text-center'},
            {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end pe-4'}
        ],
        order: [[1, 'asc']],
        drawCallback: function() {
            toggleBulkButton();
        }
    });

    $('#selectAll').on('change', function() {
        $('.row-checkbox').prop('checked', this.checked);
        toggleBulkButton();
    });

    $(document).on('change', '.row-checkbox', function() {
        toggleBulkButton();
    });

    function toggleBulkButton() {
        let count = $('.row-checkbox:checked').length;
        $('#selectedCount').text(count);
        $('#bulkActions').toggle(count > 0);
        $('#selectAll').prop('checked', count > 0 && count === $('.row-checkbox').length && $('.row-checkbox').length > 0);
    }

    window.bulkAction = function(action) {
        if (action === 'delete' && !confirm('Are you sure you want to delete selected items?')) {
            return;
        }

        const ids = $('.row-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        $.post("{{ route('cms.brands.bulk-action') }}", {
            ids: ids,
            action: action,
            _token: "{{ csrf_token() }}"
        }).done(function() {
            table.ajax.reload(null, false);
            $('#selectAll').prop('checked', false);
            toggleBulkButton();
        });
    };

    // Single delete
    $(document).on('click', '.delete-item', function() {
        let id = $(this).data('id');
        if(confirm('Delete this brand?')) {
            $.ajax({
                url: "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/brands/" + id,
                type: 'DELETE',
                data: { _token: "{{ csrf_token() }}" },
                success: function() {
                    table.ajax.reload(null, false);
                }
            });
        }
    });

    // Status toggle
    $(document).on('change', '.toggle-status', function() {
        let id = $(this).data('id');
        $.post("{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/brands/" + id + "/toggle-status", {
            _token: "{{ csrf_token() }}"
        }).fail(function() {
            table.ajax.reload(null, false);
        });
    });

    // Reorder
    $(document).on('change', '.reorder-input', function() {
        let id = $(this).data('id');
        let order = $(this).val();
        $.post("{{ route('cms.brands.reorder') }}", {
            id: id,
            order_index: order,
            _token: "{{ csrf_token() }}"
        }, function() {
            table.ajax.reload(null, false);
        });
    });
});
</script>
@endpush
