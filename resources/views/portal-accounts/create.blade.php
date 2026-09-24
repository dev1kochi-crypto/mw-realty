@extends('cms-kit::layouts.cms')

@php
    $heading = $type === 'agent' ? 'Add Agent' : ($type === 'company' ? 'Add Company' : 'Add Agent / Company');
    $indexUrl = route('cms.portal-accounts.index', array_filter(['type' => $type]));
@endphp

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ $indexUrl }}">{{ $type === 'agent' ? 'Agents' : ($type === 'company' ? 'Companies' : 'Agents & Companies') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $heading }}</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">{{ $heading }}</h5>
    </div>
    <div class="card-body p-4">
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the {{ $errors->count() === 1 ? 'field' : $errors->count() . ' fields' }} highlighted below.</strong>
            </div>
        @endif

        <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
            <i class="fas fa-info-circle text-primary me-2"></i>
            Only name, email, phone and password are required <span class="text-danger">*</span>. License and identity
            details are optional and can be added or updated later.
        </div>

        <form action="{{ route('cms.portal-accounts.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                @if($type)
                    <input type="hidden" name="type" value="{{ $type }}">
                @else
                <div class="col-md-3">
                    <label class="form-label fw-bold">Type <span class="text-danger">*</span></label>
                    <select name="type" id="accountType" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="agent" {{ old('type', 'agent') === 'agent' ? 'selected' : '' }}>Agent</option>
                        <option value="company" {{ old('type') === 'company' ? 'selected' : '' }}>Company</option>
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @endif
                <div class="col-md-4">
                    <label class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5" id="companyNameField" style="{{ old('type', $type) === 'company' ? '' : 'display:none;' }}">
                    <label class="form-label fw-bold">Company Name</label>
                    <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name') }}">
                    @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required>
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Account Status</label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="approved" {{ old('status', 'approved') === 'approved' ? 'selected' : '' }}>Approved (can log in immediately)</option>
                        <option value="pending" {{ old('status') === 'pending' ? 'selected' : '' }}>Pending (needs review)</option>
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="border-top mt-4 pt-4">
                <h6 class="fw-bold mb-3">Identity <span class="text-muted fw-normal">(optional)</span></h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Nationality</label>
                        <input type="text" name="nationality" class="form-control @error('nationality') is-invalid @enderror" value="{{ old('nationality') }}">
                        @error('nationality')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Emirates ID No.</label>
                        <input type="text" name="emirates_id_no" class="form-control @error('emirates_id_no') is-invalid @enderror" value="{{ old('emirates_id_no') }}" placeholder="784-XXXX-XXXXXXX-X">
                        @error('emirates_id_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Emirates ID Copy</label>
                        <input type="file" name="emirates_id_document" class="form-control @error('emirates_id_document') is-invalid @enderror">
                        @error('emirates_id_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Passport No.</label>
                        <input type="text" name="passport_no" class="form-control @error('passport_no') is-invalid @enderror" value="{{ old('passport_no') }}">
                        @error('passport_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Passport Copy</label>
                        <input type="file" name="passport_document" class="form-control @error('passport_document') is-invalid @enderror">
                        @error('passport_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Passport Expiry</label>
                        <input type="date" name="passport_expiry" class="form-control @error('passport_expiry') is-invalid @enderror" value="{{ old('passport_expiry') }}">
                        @error('passport_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="border-top mt-4 pt-4" id="agentFields" style="{{ old('type', $type ?? 'agent') === 'agent' ? '' : 'display:none;' }}">
                <h6 class="fw-bold mb-3">RERA Broker Details <span class="text-muted fw-normal">(optional)</span></h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">BRN <span class="text-muted fw-normal">(Broker Registration No.)</span></label>
                        <input type="text" name="brn_number" class="form-control @error('brn_number') is-invalid @enderror" value="{{ old('brn_number') }}">
                        @error('brn_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">RERA Broker Card Copy</label>
                        <input type="file" name="rera_card_document" class="form-control @error('rera_card_document') is-invalid @enderror">
                        @error('rera_card_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">RERA Registration Certificate Copy</label>
                        <input type="file" name="rera_certificate_document" class="form-control @error('rera_certificate_document') is-invalid @enderror">
                        @error('rera_certificate_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Affiliated Brokerage / Company</label>
                        <select name="company_id" class="form-select @error('company_id') is-invalid @enderror">
                            <option value="">Select a company&hellip;</option>
                            @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>
                                {{ $company->company_name ?: $company->name }}{{ $company->status !== 'approved' ? ' (' . ucfirst($company->status) . ')' : '' }}
                            </option>
                            @endforeach
                        </select>
                        @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted d-block mt-1">Don't see the company? <a href="{{ route('cms.portal-accounts.create', ['type' => 'company']) }}">Add it first</a>.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Trade License No.</label>
                        <input type="text" name="trade_license_no" class="form-control @error('trade_license_no') is-invalid @enderror" value="{{ old('trade_license_no') }}">
                        @error('trade_license_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Trade License Copy</label>
                        <input type="file" name="trade_license_document" class="form-control @error('trade_license_document') is-invalid @enderror">
                        @error('trade_license_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Trade License Expiry</label>
                        <input type="date" name="trade_license_expiry" class="form-control @error('trade_license_expiry') is-invalid @enderror" value="{{ old('trade_license_expiry') }}">
                        @error('trade_license_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">TRN (VAT No.)</label>
                        <input type="text" name="trn_number" class="form-control @error('trn_number') is-invalid @enderror" value="{{ old('trn_number') }}">
                        @error('trn_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">TRN Expiry</label>
                        <input type="date" name="trn_expiry" class="form-control @error('trn_expiry') is-invalid @enderror" value="{{ old('trn_expiry') }}">
                        @error('trn_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="border-top mt-4 pt-4" id="companyFields" style="{{ old('type', $type) === 'company' ? '' : 'display:none;' }}">
                <h6 class="fw-bold mb-3">Company / RERA Office Details <span class="text-muted fw-normal">(optional)</span></h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Trade License No.</label>
                        <input type="text" name="trade_license_no" class="form-control @error('trade_license_no') is-invalid @enderror" value="{{ old('trade_license_no') }}">
                        @error('trade_license_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Trade License Copy</label>
                        <input type="file" name="trade_license_document" class="form-control @error('trade_license_document') is-invalid @enderror">
                        @error('trade_license_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Trade License Expiry</label>
                        <input type="date" name="trade_license_expiry" class="form-control @error('trade_license_expiry') is-invalid @enderror" value="{{ old('trade_license_expiry') }}">
                        @error('trade_license_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ORN <span class="text-muted fw-normal">(Office Registration No.)</span></label>
                        <input type="text" name="orn_number" class="form-control @error('orn_number') is-invalid @enderror" value="{{ old('orn_number') }}">
                        @error('orn_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">RERA Registration Certificate Copy</label>
                        <input type="file" name="rera_certificate_document" class="form-control @error('rera_certificate_document') is-invalid @enderror">
                        @error('rera_certificate_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Registered Office Address</label>
                        <input type="text" name="office_address" class="form-control @error('office_address') is-invalid @enderror" value="{{ old('office_address') }}">
                        @error('office_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">TRN <span class="text-muted fw-normal">(VAT No.)</span></label>
                        <input type="text" name="trn_number" class="form-control @error('trn_number') is-invalid @enderror" value="{{ old('trn_number') }}">
                        @error('trn_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">TRN Expiry</label>
                        <input type="date" name="trn_expiry" class="form-control @error('trn_expiry') is-invalid @enderror" value="{{ old('trn_expiry') }}">
                        @error('trn_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Authorized Signatory</label>
                        <input type="text" name="authorized_signatory_name" class="form-control @error('authorized_signatory_name') is-invalid @enderror" value="{{ old('authorized_signatory_name') }}">
                        @error('authorized_signatory_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Landline</label>
                        <input type="text" name="landline" class="form-control @error('landline') is-invalid @enderror" value="{{ old('landline') }}">
                        @error('landline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Create Account</button>
                <a href="{{ $indexUrl }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const accountTypeEl = document.getElementById('accountType');
    const syncAccountTypeFields = () => {
        const type = accountTypeEl?.value || document.querySelector('input[name="type"]')?.value || 'agent';
        const isCompany = type === 'company';
        document.getElementById('companyNameField').style.display = isCompany ? '' : 'none';
        document.getElementById('agentFields').style.display = isCompany ? 'none' : '';
        document.getElementById('companyFields').style.display = isCompany ? '' : 'none';
        document.querySelectorAll('#agentFields input, #agentFields select, #companyFields input, #companyFields select').forEach((field) => {
            field.disabled = field.closest('#agentFields') ? isCompany : !isCompany;
        });
    };
    if (accountTypeEl) {
        accountTypeEl.addEventListener('change', syncAccountTypeFields);
    }
    syncAccountTypeFields();
</script>
@endpush
