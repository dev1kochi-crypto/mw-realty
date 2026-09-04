@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Vacancies</li>
@endsection

@section('content')
@php
    $vacancyColumns = config('cms-kit.database.careers.items.columns', []);
@endphp
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0">Vacancies</h5>
                <div class="d-flex gap-2">
                    @if(auth('cms')->user()->can('careers.edit') || auth('cms')->user()->can('careers.delete'))
                    <div class="dropdown" id="bulkActions" style="display: none;">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Bulk Actions (<span id="selectedCount">0</span>)
                        </button>
                        <ul class="dropdown-menu">
                            @if(auth('cms')->user()->can('careers.edit'))
                            <li><button class="dropdown-item" type="button" onclick="bulkAction('active')"><i class="fas fa-check-circle text-success me-2"></i> Mark Active</button></li>
                            <li><button class="dropdown-item" type="button" onclick="bulkAction('inactive')"><i class="fas fa-times-circle text-secondary me-2"></i> Mark Inactive</button></li>
                            @endif
                            @if(auth('cms')->user()->can('careers.delete'))
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item" type="button" onclick="bulkAction('delete')"><i class="fas fa-trash text-danger me-2"></i> Delete Selected</button></li>
                            @endif
                        </ul>
                    </div>
                    @endif
                    @if(auth('cms')->user()->can('careers.create'))
                    <a href="{{ route('cms.careers.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> Add Vacancy
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table premium-table mb-0 w-100" id="careersTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                                <th style="width: 40px;">#</th>
                                @if($vacancyColumns['title'] ?? true)
                                <th>Title</th>
                                @endif
                                @if($vacancyColumns['job_type'] ?? true)
                                <th>Job Type</th>
                                @endif
                                @if($vacancyColumns['department'] ?? true)
                                <th>Department</th>
                                @endif
                                @if($vacancyColumns['location'] ?? true)
                                <th>Location</th>
                                @endif
                                @if($vacancyColumns['published_date'] ?? true)
                                <th style="width: 130px;">Published</th>
                                @endif
                                @if($vacancyColumns['order'] ?? true)
                                <th style="width: 100px;">Order</th>
                                @endif
                                @if($vacancyColumns['status'] ?? true)
                                <th style="width: 100px;" class="text-center">Status</th>
                                @endif
                                <th style="width: 100px;" class="text-end pe-4">Actions</th>
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
        const columns = [
            {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            @if($vacancyColumns['title'] ?? true)
            {data: 'title', name: 'title'},
            @endif
            @if($vacancyColumns['job_type'] ?? true)
            {data: 'job_type', name: 'job_type'},
            @endif
            @if($vacancyColumns['department'] ?? true)
            {data: 'department', name: 'department'},
            @endif
            @if($vacancyColumns['location'] ?? true)
            {data: 'location', name: 'location'},
            @endif
            @if($vacancyColumns['published_date'] ?? true)
            {data: 'published_date', name: 'published_date'},
            @endif
            @if($vacancyColumns['order'] ?? true)
            {data: 'order', name: 'order'},
            @endif
            @if($vacancyColumns['status'] ?? true)
            {data: 'status', name: 'status', className: 'text-center'},
            @endif
            {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end pe-4'}
        ];

        const orderIndex = columns.findIndex((column) => column.data === 'order');
        const table = $('#careersTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('cms.careers.vacancies.index') }}",
            columns: columns,
            order: [[orderIndex > -1 ? orderIndex : 1, 'asc']],
            drawCallback: function() {
                updateBulkVisibility();
            }
        });

        $('#selectAll').on('change', function() {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
            updateBulkVisibility();
        });

        $(document).on('change', '.row-checkbox', function() {
            updateBulkVisibility();
        });

        function updateBulkVisibility() {
            const count = $('.row-checkbox:checked').length;
            $('#selectedCount').text(count);
            $('#bulkActions').toggle(count > 0);
            $('#selectAll').prop('checked', count > 0 && count === $('.row-checkbox').length && $('.row-checkbox').length > 0);
        }

        window.bulkAction = function(action) {
            if (action === 'delete' && !confirm('Delete selected vacancies?')) {
                return;
            }

            const ids = $('.row-checkbox:checked').map(function() { return $(this).val(); }).get();
            $.post("{{ route('cms.careers.bulk-action') }}", {
                ids: ids,
                action: action,
                _token: "{{ csrf_token() }}"
            }, function() {
                table.ajax.reload(null, false);
                $('#selectAll').prop('checked', false);
                updateBulkVisibility();
            });
        };

        $(document).on('click', '.delete-item', function() {
            const id = $(this).data('id');
            if (confirm('Delete this vacancy?')) {
                $.ajax({
                    url: "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/careers/" + id,
                    type: 'DELETE',
                    data: { _token: "{{ csrf_token() }}" },
                    success: function() {
                        table.ajax.reload(null, false);
                    }
                });
            }
        });

        $(document).on('change', '.toggle-status', function() {
            const id = $(this).data('id');
            $.post("{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/careers/" + id + "/toggle-status", {
                _token: "{{ csrf_token() }}"
            }).fail(function() {
                table.ajax.reload(null, false);
            });
        });

        $(document).on('change', '.reorder-input', function() {
            const id = $(this).data('id');
            $.post("{{ route('cms.careers.reorder') }}", {
                id: id,
                order_index: $(this).val(),
                _token: "{{ csrf_token() }}"
            }, function() {
                table.ajax.reload(null, false);
            });
        });
    });
</script>
@endpush
