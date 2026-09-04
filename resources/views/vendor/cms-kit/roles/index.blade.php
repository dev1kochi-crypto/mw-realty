@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Roles</li>
@stop

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Role Management</h2>
        <p class="text-muted">Manage system roles and their permissions.</p>
    </div>
    <div class="d-flex gap-2">
        @can('permissions.create')
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addPermissionModal">
            <i class="fas fa-key me-2"></i> Add Permission
        </button>
        @endcan
        @can('roles.create')
        <a href="{{ route('cms.roles.create') }}" class="btn btn-primary btn-premium">
            <i class="fas fa-plus me-2"></i> Add New Role
        </a>
        @endcan
    </div>
</div>

<!-- Add Permission Modal -->
<div class="modal fade" id="addPermissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('cms.permissions.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Create New Permission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Permission Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. blog.view, reports.export" required>
                        <small class="text-muted">Use dot notation for grouping (e.g., module.action)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Permission</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card glass-card">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table premium-table mb-0">
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th>Role Name</th>
                        <th>Permissions Count</th>
                        <th>Created At</th>
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
            ajax: "{{ route('cms.roles.index') }}",
            autoWidth: false,
            columns: [
                {data: 'id', name: 'id', width: '5%'},
                {data: 'name', name: 'name', render: function(data, type, row) {
                    let badge = row.name === 'superadmin' ? '<span class="badge bg-danger ms-2">System</span>' : '';
                    return '<span class="fw-bold">' + data.charAt(0).toUpperCase() + data.slice(1) + '</span>' + badge;
                }, width: '30%'},
                {data: 'permissions_count', name: 'permissions_count', searchable: false, orderable: false, render: function(data) {
                    return '<span class="badge bg-info text-dark">' + data + '</span>';
                }, width: '20%', className: 'text-center'},
                {data: 'created_at_fmt', name: 'created_at', width: '20%'},
                {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end pe-4', width: '25%'}
            ],
            order: [[0, 'asc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search roles..."
            }
        });
    });
</script>
@endpush
@stop
