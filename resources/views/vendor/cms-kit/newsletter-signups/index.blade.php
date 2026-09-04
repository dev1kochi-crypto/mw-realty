@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Newsletter Signups</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0">Newsletter Signups</h5>
        @if(auth('cms')->user()->can('newsletter.delete'))
        <div class="dropdown" id="bulkActions" style="display: none;">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                Bulk Actions (<span id="selectedCount">0</span>)
            </button>
            <ul class="dropdown-menu">
                <li><button class="dropdown-item" type="button" onclick="bulkAction('delete')"><i class="fas fa-trash text-danger me-2"></i> Delete Selected</button></li>
            </ul>
        </div>
        @endif
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table premium-table mb-0 w-100" id="newsletterTable">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th style="width: 50px;">#</th>
                        <th>Email</th>
                        <th style="width: 200px;">Subscribed At</th>
                        <th class="text-end pe-4" style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
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
    let table = $('#newsletterTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('cms.newsletter-signups.index') }}",
        columns: [
            {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'email', name: 'email'},
            {data: 'created_at', name: 'created_at'},
            {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end pe-4'}
        ],
        order: [[3, 'desc']],
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
        if (action === 'delete' && !confirm('Are you sure you want to delete selected newsletter signups?')) {
            return;
        }

        let ids = $('.row-checkbox:checked').map(function() { return $(this).val(); }).get();
        $.post("{{ route('cms.newsletter-signups.bulk-action') }}", {
            ids: ids,
            action: action,
            _token: "{{ csrf_token() }}"
        }, function() {
            table.ajax.reload(null, false);
            $('#selectAll').prop('checked', false);
            toggleBulkButton();
        });
    };

    $(document).on('click', '.delete-item', function() {
        let id = $(this).data('id');
        if(confirm('Delete this entry?')) {
            $.ajax({
                url: "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/newsletter-signups/" + id,
                type: 'DELETE',
                data: { _token: "{{ csrf_token() }}" },
                success: function() {
                    table.ajax.reload(null, false);
                }
            });
        }
    });
});
</script>
@endpush
