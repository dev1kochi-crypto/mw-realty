@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.admins.index') }}">Administrators</a></li>
    <li class="breadcrumb-item active">{{ isset($admin) ? 'Edit Admin' : 'Create Admin' }}</li>
@stop

@section('content')
<div class="mb-4">
    <h2 class="fw-bold mb-0">{{ isset($admin) ? 'Edit Admin: ' . $admin->name : 'Create New Administrator' }}</h2>
    <p class="text-muted">Fill in the details and assign roles to this administrator.</p>
</div>

<form action="{{ isset($admin) ? route('cms.admins.update', $admin->id) : route('cms.admins.store') }}" method="POST">
    @csrf
    @if(isset($admin)) @method('PUT') @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-4">Account Information</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $admin->name ?? '') }}" placeholder="Enter full name">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                                   value="{{ old('email', $admin->email ?? '') }}" placeholder="email@example.com">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Password {{ isset($admin) ? '(Leave blank to keep current)' : '' }}</label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" 
                                   placeholder="Min 6 characters">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control" 
                                   placeholder="Re-type password">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card glass-card">
                <div class="card-body">
                    <h5 class="fw-bold mb-4">Assign Roles</h5>
                    <div class="row">
                        @foreach($roles as $role)
                        <div class="col-md-4 mb-3">
                            <div class="card border h-100 {{ (isset($adminRoles) && in_array($role->name, $adminRoles)) || (old('roles') && in_array($role->name, old('roles'))) ? 'border-primary bg-primary-subtle' : '' }}">
                                <div class="card-body p-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="roles[]" 
                                               value="{{ $role->name }}" id="role_{{ $role->id }}"
                                               {{ (isset($adminRoles) && in_array($role->name, $adminRoles)) || (old('roles') && in_array($role->name, old('roles'))) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-bold" for="role_{{ $role->id }}">
                                            {{ ucfirst($role->name) }}
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        {{ $role->name === 'superadmin' ? 'Total system access' : 'Limited module access' }}
                                    </small>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card glass-card position-sticky" style="top: 100px;">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Save Changes</h5>
                    <p class="small text-muted mb-4">Ensure all data is correct before saving. Password must be strong.</p>
                    
                    <button type="submit" class="btn btn-primary btn-premium w-100 mb-3">
                        <i class="fas fa-save me-2"></i> {{ isset($admin) ? 'Update Administrator' : 'Create Administrator' }}
                    </button>
                    <a href="{{ route('cms.admins.index') }}" class="btn btn-light border w-100">
                        <i class="fas fa-times me-2"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>
@stop
