@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Permissions</li>
@stop

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Permission Management</h2>
        <p class="text-muted">Manage granular system access strings.</p>
    </div>
    @can('permissions.create')
    <button type="button" class="btn btn-primary btn-premium" data-bs-toggle="modal" data-bs-target="#addPermissionModal">
        <i class="fas fa-plus me-2"></i> Create Permission
    </button>
    @endcan
</div>

<div class="card glass-card">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table premium-table mb-0">
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th>Permission Name</th>
                        <th>Module</th>
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
            ajax: "{{ route('cms.permissions.index') }}",
            autoWidth: false,
            columns: [
                {data: 'id', name: 'id', width: '5%'},
                {data: 'name_code', name: 'name', width: '30%'},
                {data: 'module_badge', name: 'name', orderable: false, searchable: false, width: '20%', className: 'text-center'},
                {data: 'created_at_fmt', name: 'created_at', width: '20%'},
                {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end pe-4', width: '25%'}
            ],
            order: [[0, 'asc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search..."
            }
        });

        $(document).on('click', '.edit-permission', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const form = $('#dynamicEditPermissionModal form');
            let updateUrl = "{{ route('cms.permissions.update', ':id') }}";
            form.attr('action', updateUrl.replace(':id', id));
            form.find('input[name="name"]').val(name);
            
            new bootstrap.Modal(document.getElementById('dynamicEditPermissionModal')).show();
        });
    });
</script>

<div class="modal fade" id="dynamicEditPermissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Permission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Permission Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Permission</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush
@stop

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
                        <input type="text" name="name" class="form-control" placeholder="e.g. blog.view" required>
                        <small class="text-muted">Use dot notation: module.action</small>
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
