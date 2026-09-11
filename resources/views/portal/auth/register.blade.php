@extends('portal.layouts.guest')

@section('title', 'Create Account')

@push('styles')
<style>
    .portal-auth-card { max-width: 760px; }
    .portal-form-section {
        border-top: 1px solid var(--portal-border);
        margin-top: 1.5rem;
        padding-top: 1.25rem;
    }
    .portal-form-section-title {
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--portal-muted);
        margin-bottom: 1rem;
    }
    .portal-form-hint { font-size: 0.78rem; color: var(--portal-muted); margin-top: 0.25rem; }
</style>
@endpush

@section('content')
<h1>Create your account</h1>
<p class="subtitle">List your properties and manage leads through your own dashboard. Only your name, email, phone and password are required to get started — you can add license and identity details now or complete them later.</p>

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please fix the {{ $errors->count() === 1 ? 'field' : $errors->count() . ' fields' }} highlighted below.</strong>
    </div>
@endif

<form action="{{ route('portal.register') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="portal-type-toggle">
        <input type="radio" name="type" id="type_agent" value="agent" {{ old('type', 'agent') === 'agent' ? 'checked' : '' }}>
        <label for="type_agent"><i class="fas fa-id-badge me-1"></i> Agent</label>

        <input type="radio" name="type" id="type_company" value="company" {{ old('type') === 'company' ? 'checked' : '' }}>
        <label for="type_company"><i class="fas fa-building me-1"></i> Company</label>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label>Your Name</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6" id="companyNameField" style="{{ old('type') === 'company' ? '' : 'display:none;' }}">
            <label>Company Name</label>
            <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name') }}">
            @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label>Email</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label>Password</label>
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label>Confirm Password</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>
    </div>

    <div class="portal-form-section">
        <div class="portal-form-section-title">Identity <span class="fw-normal text-muted" style="text-transform:none;">(optional)</span></div>
        <div class="row g-3">
            <div class="col-md-4">
                <label>Nationality</label>
                <input type="text" name="nationality" class="form-control @error('nationality') is-invalid @enderror" value="{{ old('nationality') }}">
                @error('nationality')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label>Emirates ID No.</label>
                <input type="text" name="emirates_id_no" class="form-control @error('emirates_id_no') is-invalid @enderror" value="{{ old('emirates_id_no') }}" placeholder="784-XXXX-XXXXXXX-X">
                @error('emirates_id_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label>Emirates ID Copy</label>
                <input type="file" name="emirates_id_document" class="form-control @error('emirates_id_document') is-invalid @enderror">
                @error('emirates_id_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label>Passport No.</label>
                <input type="text" name="passport_no" class="form-control @error('passport_no') is-invalid @enderror" value="{{ old('passport_no') }}">
                @error('passport_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label>Passport Copy</label>
                <input type="file" name="passport_document" class="form-control @error('passport_document') is-invalid @enderror">
                @error('passport_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="portal-form-section" id="agentFields" style="{{ old('type', 'agent') === 'agent' ? '' : 'display:none;' }}">
        <div class="portal-form-section-title">RERA Broker Details <span class="fw-normal text-muted" style="text-transform:none;">(optional)</span></div>
        <div class="row g-3">
            <div class="col-md-6">
                <label>BRN <span class="text-muted fw-normal">(Broker Registration No.)</span></label>
                <input type="text" name="brn_number" class="form-control @error('brn_number') is-invalid @enderror" value="{{ old('brn_number') }}">
                @error('brn_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label>RERA Broker Card Copy</label>
                <input type="file" name="rera_card_document" class="form-control @error('rera_card_document') is-invalid @enderror">
                @error('rera_card_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-12">
                <label>Affiliated Brokerage / Company</label>
                <select name="company_id" class="form-select @error('company_id') is-invalid @enderror">
                    <option value="">Select a company&hellip;</option>
                    @foreach($companies as $company)
                    <option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>{{ $company->company_name ?: $company->name }}</option>
                    @endforeach
                </select>
                @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="portal-form-hint">Every individual broker (BRN holder) must be affiliated with a registered brokerage in Dubai. Don't see your company? Ask them to register first, or leave this blank and add it later.</div>
            </div>
        </div>
    </div>

    <div class="portal-form-section" id="companyFields" style="{{ old('type') === 'company' ? '' : 'display:none;' }}">
        <div class="portal-form-section-title">Company / RERA Office Details <span class="fw-normal text-muted" style="text-transform:none;">(optional)</span></div>
        <div class="row g-3">
            <div class="col-md-6">
                <label>Trade License No.</label>
                <input type="text" name="trade_license_no" class="form-control @error('trade_license_no') is-invalid @enderror" value="{{ old('trade_license_no') }}">
                @error('trade_license_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label>Trade License Copy</label>
                <input type="file" name="trade_license_document" class="form-control @error('trade_license_document') is-invalid @enderror">
                @error('trade_license_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label>Trade License Expiry</label>
                <input type="date" name="trade_license_expiry" class="form-control @error('trade_license_expiry') is-invalid @enderror" value="{{ old('trade_license_expiry') }}">
                @error('trade_license_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label>ORN <span class="text-muted fw-normal">(Office Registration No.)</span></label>
                <input type="text" name="orn_number" class="form-control @error('orn_number') is-invalid @enderror" value="{{ old('orn_number') }}">
                @error('orn_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-12">
                <label>RERA Registration Certificate Copy</label>
                <input type="file" name="rera_certificate_document" class="form-control @error('rera_certificate_document') is-invalid @enderror">
                @error('rera_certificate_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-12">
                <label>Registered Office Address</label>
                <input type="text" name="office_address" class="form-control @error('office_address') is-invalid @enderror" value="{{ old('office_address') }}">
                @error('office_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label>TRN <span class="text-muted fw-normal">(VAT No.)</span></label>
                <input type="text" name="trn_number" class="form-control @error('trn_number') is-invalid @enderror" value="{{ old('trn_number') }}">
                @error('trn_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label>Authorized Signatory</label>
                <input type="text" name="authorized_signatory_name" class="form-control @error('authorized_signatory_name') is-invalid @enderror" value="{{ old('authorized_signatory_name') }}">
                @error('authorized_signatory_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label>Landline</label>
                <input type="text" name="landline" class="form-control @error('landline') is-invalid @enderror" value="{{ old('landline') }}">
                @error('landline')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-portal-primary w-100 mt-4 mb-3">Create Account</button>
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
            document.getElementById('agentFields').style.display = this.value === 'agent' ? '' : 'none';
            document.getElementById('companyFields').style.display = this.value === 'company' ? '' : 'none';
        });
    });
</script>
@endpush
