@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Plans</li>
@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #4e73df !important;">
            <div class="card-body">
                <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Plans</div>
                <div class="h4 mb-0 fw-bold">{{ $planStats['total_plans'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #1cc88a !important;">
            <div class="card-body">
                <div class="text-xs fw-bold text-success text-uppercase mb-1">Active Subscribers</div>
                <div class="h4 mb-0 fw-bold">{{ $planStats['active_subscribers'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #f6c23e !important;">
            <div class="card-body">
                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Est. Monthly Revenue</div>
                <div class="h4 mb-0 fw-bold">AED {{ number_format($planStats['monthly_revenue']) }}</div>
                <small class="text-muted">From currently assigned monthly plans</small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0">Plans</h5>
        @if($cmsUser->can('plans.create'))
        <a href="{{ route('cms.plans.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Plan
        </a>
        @endif
    </div>
    <div class="card-body p-4">
        <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
            <i class="fas fa-info-circle text-primary me-2"></i>
            Assign a plan to an Agent or Company from <a href="{{ route('cms.portal-accounts.index') }}">Agents & Companies</a>.
            A plan's property limit is enforced when they add new listings from their portal.
        </div>
        <div class="table-responsive">
            <table class="table premium-table mb-0 w-100">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Property Limit</th>
                        <th>Subscribers</th>
                        <th></th>
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
            ajax: "{{ route('cms.plans.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'price', name: 'price'},
                {data: 'limit', name: 'limit', orderable: false},
                {data: 'subscribers_count', name: 'subscribers_count'},
                {data: 'popular', name: 'popular', orderable: false},
                {data: 'order', name: 'order', className: 'text-center'},
                {data: 'status', name: 'status', className: 'text-center'},
                {data: 'action', name: 'action', orderable: false, searchable: false}
            ],
            order: [[6, 'asc']]
        });

        $(document).on('change', '.toggle-status', function() {
            const id = $(this).data('id');
            const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/plans/" + id + "/toggle-status";
            $.post(url, { _token: '{{ csrf_token() }}' }).done(() => table.ajax.reload(null, false));
        });

        $(document).on('change', '.reorder-input', function() {
            const id = $(this).data('id');
            const order = $(this).val();
            $.post("{{ route('cms.plans.reorder') }}", { _token: '{{ csrf_token() }}', id: id, order_index: order })
                .done(() => table.ajax.reload(null, false));
        });

        $(document).on('click', '.delete-item', function() {
            if (confirm('Delete this plan? Agents/Companies currently on it will keep their access but lose the plan reference.')) {
                const id = $(this).data('id');
                const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/plans/" + id;
                $.ajax({ url: url, type: 'DELETE', data: { _token: '{{ csrf_token() }}' }, success: () => table.ajax.reload(null, false) });
            }
        });
    });
</script>
@endpush
