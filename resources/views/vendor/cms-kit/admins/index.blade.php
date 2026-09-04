@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Administrators</li>
@stop

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Admin Management</h2>
        <p class="text-muted">Manage system administrators and their roles.</p>
    </div>
    @can('users.create')
    <a href="{{ route('cms.admins.create') }}" class="btn btn-primary btn-premium">
        <i class="fas fa-user-plus me-2"></i> Add New Admin
    </a>
    @endcan
</div>

<div class="card glass-card">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table premium-table mb-0">
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th width="150" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(function() {
        $('.premium-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('cms.admins.index') }}",
            autoWidth: false,
            columns: [
                {data: 'id', name: 'id', width: '5%'},
                {data: 'name_info', name: 'name', orderable: true, width: '25%'},
                {data: 'email', name: 'email', width: '25%'},
                {data: 'roles_list', name: 'roles.name', orderable: false, width: '20%'},
                {data: 'status_toggle', name: 'is_active', className: 'text-center', width: '10%'},
                {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end pe-4', width: '15%'}
            ],
            order: [[0, 'desc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search admins..."
            }
        });
    });
</script>
@endpush
@stop
