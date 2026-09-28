@extends('portal.layouts.app')

@section('title', 'Add Agent')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="portal-section-title mb-0">Add Agent</div>
    <a href="{{ route('portal.agents.index') }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

@if($duplicate = session('duplicateAgent'))
<div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><i class="fas fa-user-check me-2"></i><strong>{{ $duplicate['name'] }}</strong> — {{ $duplicate['message'] }}</div>
    @if($duplicate['can_invite'])
    <form method="POST" action="{{ route('portal.agents.invite') }}" class="m-0">
        @csrf
        <input type="hidden" name="identifier" value="{{ $duplicate['identifier'] }}">
        <button type="submit" class="btn btn-sm btn-portal-primary"><i class="fas fa-paper-plane me-1"></i> Send agency invitation</button>
    </form>
    @endif
</div>
@endif

{{-- Existing agents (e.g. independent agents already on MW Realty) are invited, never re-created. --}}
<div class="portal-card p-4 mb-3" style="border:1px solid #d9e3f1;background:linear-gradient(135deg,#f4f7fc,#fff 72%);">
    <div class="d-flex align-items-start gap-3 mb-3">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white flex-shrink-0" style="width:44px;height:44px;background:#203f68"><i class="fas fa-paper-plane"></i></span>
        <div><div class="fw-bold mb-1">Invite an existing agent</div>
        <div class="portal-muted small">Find them by email, mobile number or agent ID. Their account, listings and history stay with them. After they accept and Super Admin approves, they join your agency.</div></div>
    </div>
    <form method="POST" action="{{ route('portal.agents.invite') }}" class="d-flex gap-2 flex-wrap align-items-start">
        @csrf
        <input type="text" name="identifier" class="form-control @error('identifier') is-invalid @enderror" style="max-width: 400px;" placeholder="Email, mobile number, or agent ID" value="{{ old('identifier') }}" required>
        <button type="submit" class="btn btn-portal-primary"><i class="fas fa-paper-plane me-1"></i> Send Invitation</button>
        @error('identifier')<div class="invalid-feedback d-block w-100">{{ $message }}</div>@enderror
    </form>
</div>

<div class="portal-card p-4">
    <div class="fw-bold mb-1">Add a new agent</div>
    <div class="portal-muted small mb-3">Creates an agent account that becomes active after Super Admin approval. The agent will receive a secure email link to set their password.</div>
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('portal.agents.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Email / Login Username <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Phone <span class="text-danger">*</span></label>
                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required>
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">WhatsApp Number</label>
                <input type="text" name="whatsapp_number" class="form-control" value="{{ old('whatsapp_number') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Years of Experience</label>
                <input type="number" min="0" max="80" name="years_of_experience" class="form-control" value="{{ old('years_of_experience') }}">
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label class="form-label fw-bold">Account Status</label>
                <input type="text" class="form-control" value="Pending Super Admin approval" disabled>
            </div>
            <div class="col-md-8 d-flex align-items-end">
                <div class="small text-muted pb-2">The email address is the agent's username. For security, you don't set or receive their password; after approval, the agent sets one using a single-use link sent to their email.</div>
            </div>
        </div>

        <div class="border-top mt-4 pt-4">
            <h6 class="fw-bold mb-3">Identity &amp; broker details <span class="text-muted fw-normal">(optional)</span></h6>
            <div class="row g-3">
                @foreach([
                    ['nationality','Nationality','text'], ['emirates_id_no','Emirates ID No.','text'],
                    ['emirates_id_document','Emirates ID Copy','file'], ['passport_no','Passport No.','text'],
                    ['passport_document','Passport Copy','file'], ['passport_expiry','Passport Expiry','date'],
                    ['brn_number','BRN (Broker Registration No.)','text'], ['rera_card_document','RERA Broker Card Copy','file'],
                    ['rera_certificate_document','RERA Registration Certificate Copy','file'], ['trade_license_no','Trade License No.','text'],
                    ['trade_license_document','Trade License Copy','file'], ['trade_license_expiry','Trade License Expiry','date'],
                    ['trn_number','TRN (VAT No.)','text'], ['trn_expiry','TRN Expiry','date'],
                ] as [$field, $label, $type])
                <div class="col-md-6">
                    <label class="form-label fw-bold">{{ $label }}</label>
                    <input type="{{ $type }}" name="{{ $field }}" class="form-control @error($field) is-invalid @enderror" @if($type !== 'file') value="{{ old($field) }}" @endif @if($type === 'file') accept=".jpg,.jpeg,.png,.pdf" @endif>
                    @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if($type === 'file')<small class="text-muted">JPG, PNG or PDF; max 4 MB.</small>@endif
                </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4 pt-3 border-top">
            <button type="submit" class="btn btn-portal-primary"><i class="fas fa-save me-1"></i> Save Agent</button>
        </div>
    </form>
</div>
@endsection
