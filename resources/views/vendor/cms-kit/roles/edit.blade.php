@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.roles.index') }}">Roles</a></li>
    <li class="breadcrumb-item active">{{ isset($role) ? 'Edit Role' : 'Create Role' }}</li>
@stop

@section('content')
<div class="mb-4">
    <h2 class="fw-bold mb-0">{{ isset($role) ? 'Edit Role: ' . ucfirst($role->name) : 'Create New Role' }}</h2>
    <p class="text-muted">Define the role name and assign granular permissions.</p>
</div>

<form action="{{ isset($role) ? route('cms.roles.update', $role->id) : route('cms.roles.store') }}" method="POST">
    @csrf
    @if(isset($role)) @method('PUT') @endif

    <div class="row">
        <div class="col-lg-4">
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">General Information</h5>
                    <div class="mb-3">
                        <label class="form-label">Role Name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                               value="{{ old('name', $role->name ?? '') }}" placeholder="e.g. manager, editor"
                               {{ isset($role) && $role->name === 'superadmin' ? 'readonly' : '' }}>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="card glass-card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Quick Actions</h5>
                    <button type="submit" class="btn btn-primary btn-premium w-100 mb-3">
                        {{ isset($role) ? 'Update Role' : 'Create Role' }}
                    </button>
                    <a href="{{ route('cms.roles.index') }}" class="btn btn-light border w-100">Cancel</a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card glass-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Permissions Matrix</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label" for="selectAll">Select All</label>
                        </div>
                    </div>

                    @foreach($permissions as $module => $modulePermissions)
                    <div class="module-group mb-4 pb-3 border-bottom">
                        <h6 class="text-uppercase fw-bold text-primary small mb-3">{{ str_replace('-', ' ', $module) }}</h6>
                        <div class="row">
                            @foreach($modulePermissions as $permission)
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input permission-check" type="checkbox" name="permissions[]" 
                                           value="{{ $permission->name }}" id="perm_{{ $permission->id }}"
                                           {{ (isset($rolePermissions) && in_array($permission->name, $rolePermissions)) || old('permissions') && in_array($permission->name, old('permissions')) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="perm_{{ $permission->id }}">
                                        {{ Str::title(explode('.', $permission->name)[1] ?? $permission->name) }}
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    $('#selectAll').on('change', function() {
        $('.permission-check').prop('checked', $(this).prop('checked'));
    });
</script>
@endpush
@stop
