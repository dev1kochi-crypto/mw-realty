@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('portal.properties.index') }}">Properties</a></li>
    <li class="breadcrumb-item active" aria-current="page">Filters</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0">Search Filters</h5>
                @if($cmsUser->can('filters.create'))
                <a href="{{ route('cms.filters.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Filter
                </a>
                @endif
            </div>
            <div class="card-body p-4">
                <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                    <i class="fas fa-info-circle text-primary me-2"></i>
                    These filters power the search bar on the home banner and the properties listing page.
                    Turn a filter's status off to hide it from the frontend without deleting it. For "Select" type filters,
                    open the filter to manage its option values (e.g. the list of property types or locations).
                </div>
                <div class="table-responsive">
                    <table class="table premium-table mb-0 w-100">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Key</th>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Options</th>
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
            ajax: "{{ route('cms.filters.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'key', name: 'key'},
                {data: 'label', name: 'label'},
                {data: 'type', name: 'type'},
                {data: 'values_count', name: 'values_count', orderable: false},
                {data: 'order', name: 'order', className: 'text-center'},
                {data: 'status', name: 'status', className: 'text-center'},
                {data: 'action', name: 'action', orderable: false, searchable: false}
            ],
            order: [[5, 'asc']]
        });

        $(document).on('change', '.toggle-status', function() {
            const id = $(this).data('id');
            const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/filters/" + id + "/toggle-status";
            $.post(url, { _token: '{{ csrf_token() }}' }).done(() => table.ajax.reload(null, false));
        });

        $(document).on('change', '.reorder-input', function() {
            const id = $(this).data('id');
            const order = $(this).val();
            $.post("{{ route('cms.filters.reorder') }}", { _token: '{{ csrf_token() }}', id: id, order_index: order })
                .done(() => table.ajax.reload(null, false));
        });

        $(document).on('click', '.delete-item', function() {
            if (confirm('Delete this filter? Its options will be removed too.')) {
                const id = $(this).data('id');
                const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/filters/" + id;
                $.ajax({ url: url, type: 'DELETE', data: { _token: '{{ csrf_token() }}' }, success: () => table.ajax.reload(null, false) });
            }
        });
    });
</script>
@endpush
