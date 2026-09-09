@extends('portal.layouts.guest')

@section('title', 'Create Account')

@section('content')
<h1>Create your account</h1>
<p class="subtitle">List your properties and manage leads through your own dashboard.</p>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('portal.register') }}" method="POST">
    @csrf

    <div class="portal-type-toggle">
        <input type="radio" name="type" id="type_agent" value="agent" {{ old('type', 'agent') === 'agent' ? 'checked' : '' }}>
        <label for="type_agent"><i class="fas fa-id-badge me-1"></i> Agent</label>

        <input type="radio" name="type" id="type_company" value="company" {{ old('type') === 'company' ? 'checked' : '' }}>
        <label for="type_company"><i class="fas fa-building me-1"></i> Company</label>
    </div>

    <div class="mb-3">
        <label>Your Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
    </div>

    <div class="mb-3" id="companyNameField" style="{{ old('type') === 'company' ? '' : 'display:none;' }}">
        <label>Company Name</label>
        <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}">
    </div>

    <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
        </div>
        <div class="col-6">
            <label>License No. <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" name="license_no" class="form-control" value="{{ old('license_no') }}">
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="col-6">
            <label>Confirm Password</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>
    </div>

    <button type="submit" class="btn btn-portal-primary w-100 mb-3">Create Account</button>
    <p class="text-center text-muted" style="font-size:0.85rem;">
        Already have an account? <a href="{{ route('portal.login') }}">Log in</a>
    </p>
</form>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('input[name="type"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.getElementById('companyNameField').style.display = this.value === 'company' ? '' : 'none';
        });
    });
</script>
@endpush
