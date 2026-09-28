@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.market-insights.index') }}">Market Insights</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $meta['plural'] }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0">Market Insight {{ $meta['plural'] }}</h5>
                <div class="d-flex gap-2">
                    @if($cmsUser->can('market-insights.delete'))
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
                    @if($cmsUser->can('market-insights.create'))
                    <a href="{{ route($meta['route'] . '.create') }}" class="btn btn-primary btn-sm px-3">
                        <i class="fas fa-plus me-1"></i> Add {{ $meta['label'] }}
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                    <i class="fas fa-info-circle text-primary me-2"></i>
                    Active {{ strtolower($meta['plural']) }} appear in the {{ $meta['label'] }} dropdown when adding an insight, and as
                    {{ $meta['type'] === 'topic' ? 'topic filter buttons' : 'the market filter' }} on the public Market Insights page (only those used by at least one insight).
                    One that's still used by an insight can't be deleted — switch it off instead.
                </div>
                <div class="table-responsive">
                    <table class="table premium-table mb-0 w-100" id="termsTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                                <th>{{ $meta['label'] }} (English)</th>
                                <th>Other languages</th>
                                <th style="width: 170px;">Slug</th>
                                <th style="width: 80px;" class="text-center">Insights</th>
                                <th style="width: 100px;">Order</th>
                                <th style="width: 90px;" class="text-center">Status</th>
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
    $(function () {
        const base = "{{ route($meta['route'] . '.index') }}/";
        const token = "{{ csrf_token() }}";

        const table = $('#termsTable').DataTable({
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: "{{ route($meta['route'] . '.index') }}",
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'title', name: 'title'},
                {data: 'translated', name: 'translated', orderable: false, searchable: false},
                {data: 'slug', name: 'slug'},
                {data: 'posts', name: 'posts', orderable: false, searchable: false, className: 'text-center'},
                {data: 'order', name: 'order', searchable: false},
                {data: 'status', name: 'status', className: 'text-center', searchable: false},
                {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end pe-4'}
            ],
            order: [[5, 'asc']],
            drawCallback: updateBulkButton
        });

        function updateBulkButton() {
            const count = $('.row-checkbox:checked').length;
            $('#selectedCount').text(count);
            $('#bulkActions').toggle(count > 0);
            $('#selectAll').prop('checked', count > 0 && count === $('.row-checkbox').length);
        }

        function showError(xhr) {
            alert(xhr.responseJSON?.message || 'Something went wrong. Please try again.');
            table.ajax.reload(null, false);
        }

        $('#selectAll').on('change', function () {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
            updateBulkButton();
        });
        $(document).on('change', '.row-checkbox', updateBulkButton);

        window.bulkAction = function (action) {
            if (action === 'delete' && !confirm('Delete the selected {{ strtolower($meta['plural']) }}?')) return;
            const ids = $('.row-checkbox:checked').map(function () { return $(this).val(); }).get();
            $.post("{{ route($meta['route'] . '.bulk-action') }}", {ids, action, _token: token}, function () {
                table.ajax.reload(null, false);
                $('#selectAll').prop('checked', false);
                updateBulkButton();
            }).fail(showError);
        };

        $(document).on('click', '.delete-item', function () {
            if (!confirm('Delete this {{ strtolower($meta['label']) }}?')) return;
            $.ajax({url: base + $(this).data('id'), type: 'DELETE', data: {_token: token}, success: () => table.ajax.reload(null, false), error: showError});
        });

        $(document).on('change', '.toggle-status', function () {
            $.post(base + $(this).data('id') + '/toggle-status', {_token: token}).fail(showError);
        });

        $(document).on('change', '.reorder-input', function () {
            $.post("{{ route($meta['route'] . '.reorder') }}", {id: $(this).data('id'), order_index: $(this).val(), _token: token}, () => table.ajax.reload(null, false)).fail(showError);
        });
    });
</script>
@endpush
