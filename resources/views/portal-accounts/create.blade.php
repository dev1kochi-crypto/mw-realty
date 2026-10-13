@extends('cms-kit::layouts.cms')

@php
    $heading = $type === 'agent' ? 'Add Agent' : ($type === 'company' ? 'Add Agency' : 'Add Agency / Agent');
    $listLabel = $type === 'agent' ? 'Agents' : ($type === 'company' ? 'Agencies' : 'Agencies & Agents');
    $indexUrl = route('cms.portal-accounts.index', array_filter(['type' => $type]));
    $current = old('type', $type ?: 'agent');
@endphp

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ $indexUrl }}">{{ $listLabel }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $heading }}</li>
@endsection

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<style>
    .ac { --ac-navy: var(--secondary-color, #264373); --ac-teal: var(--primary-color, #04a1cc); --ac-ink: #1c2340; --ac-muted: #838aa3; --ac-soft: #f7f8fc; --ac-border: rgba(28, 35, 64, .08); }
    .ac-head { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem; }
    .ac-head h4 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--ac-ink); margin: 0; }
    .ac-head p { color: var(--ac-muted); font-size: .85rem; margin: .2rem 0 0; }
    .ac-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 1.5rem; align-items: start; }
    @media (max-width: 1199.98px) { .ac-grid { grid-template-columns: 1fr; } }

    .ac-types { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; margin-bottom: 1.25rem; }
    .ac-type { position: relative; display: flex; gap: .9rem; align-items: center; padding: 1rem 1.15rem; border-radius: 16px; border: 2px solid var(--ac-border); background: #fff; cursor: pointer; transition: all .2s; }
    .ac-type input { position: absolute; opacity: 0; }
    .ac-type__icon { width: 46px; height: 46px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--ac-soft); color: var(--ac-muted); font-size: 1.1rem; transition: all .2s; }
    .ac-type strong { display: block; color: var(--ac-ink); font-size: .95rem; }
    .ac-type span.sub { font-size: .76rem; color: var(--ac-muted); }
    .ac-type:has(input:checked) { border-color: var(--ac-teal); box-shadow: 0 0 0 4px rgba(4, 161, 204, .1); }
    .ac-type:has(input:checked) .ac-type__icon { background: linear-gradient(135deg, var(--ac-teal), var(--ac-navy)); color: #fff; }

    .ac-section { background: #fff; border: 1px solid var(--ac-border); border-radius: 18px; box-shadow: 0 6px 20px rgba(28, 35, 64, .05); padding: 1.35rem 1.5rem; margin-bottom: 1.25rem; }
    .ac-section__head { display: flex; gap: .85rem; align-items: flex-start; margin-bottom: 1.1rem; }
    .ac-section__icon { width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; background: linear-gradient(135deg, var(--ac-navy), #3a5794); }
    .ac-section__icon.is-teal { background: linear-gradient(135deg, var(--ac-teal), #2fc4e8); }
    .ac-section__icon.is-purple { background: linear-gradient(135deg, #6d28d9, #8b5cf6); }
    .ac-section__icon.is-green { background: linear-gradient(135deg, #0f9d58, #34c880); }
    .ac-section__title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.02rem; color: var(--ac-ink); }
    .ac-section__title small { font-family: inherit; font-weight: 600; font-size: .74rem; color: var(--ac-muted); margin-left: .35rem; }
    .ac-section__hint { font-size: .8rem; color: var(--ac-muted); margin-top: .1rem; }
    .ac .form-label { font-size: .8rem; font-weight: 700; color: #3d4460; margin-bottom: .35rem; }
    .ac .form-control, .ac .form-select { border-radius: 10px; }
    .ac-help { font-size: .74rem; color: var(--ac-muted); margin-top: .3rem; }
    .ac-input-icon { position: relative; }
    .ac-input-icon > i { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); color: var(--ac-muted); font-size: .82rem; pointer-events: none; }
    .ac-input-icon > .form-control { padding-left: 2.3rem; }
    .ac-pw-toggle { position: absolute; right: .5rem; top: 50%; transform: translateY(-50%); border: 0; background: none; color: var(--ac-muted); padding: .25rem .4rem; }

    .ac-status { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; }
    .ac-status label { position: relative; display: flex; gap: .6rem; align-items: flex-start; padding: .75rem .85rem; border: 1.5px solid var(--ac-border); border-radius: 12px; cursor: pointer; transition: all .2s; }
    .ac-status input { position: absolute; opacity: 0; }
    .ac-status i { margin-top: .15rem; color: var(--ac-muted); }
    .ac-status strong { display: block; font-size: .84rem; color: var(--ac-ink); }
    .ac-status span { font-size: .74rem; color: var(--ac-muted); }
    .ac-status label:has(input:checked) { border-color: var(--ac-teal); background: #f3fbfe; }
    .ac-status label:has(input[value="approved"]:checked) i { color: #0f9d58; }
    .ac-status label:has(input[value="pending"]:checked) i { color: #e08e0b; }

    .ac-file { position: relative; display: flex; align-items: center; gap: .7rem; padding: .65rem .85rem; border: 1.5px dashed #d5d9e6; border-radius: 12px; background: var(--ac-soft); cursor: pointer; transition: all .2s; min-height: 58px; }
    .ac-file:hover { border-color: var(--ac-teal); background: #f3fbfe; }
    .ac-file input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .ac-file > i { width: 34px; height: 34px; flex-shrink: 0; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: #fff; color: var(--ac-teal); box-shadow: 0 2px 6px rgba(28, 35, 64, .08); }
    .ac-file strong { display: block; font-size: .8rem; color: var(--ac-ink); }
    .ac-file .ac-file__name { font-size: .72rem; color: var(--ac-muted); word-break: break-all; }
    .ac-file.has-file { border-style: solid; border-color: rgba(15, 157, 88, .45); background: #f2fbf6; }
    .ac-file.has-file > i { color: #0f9d58; }
    .ac-file.is-invalid { border-color: #dc3545; }

    .ac-aside { position: sticky; top: 90px; display: grid; gap: 1rem; }
    .ac-aside .ac-card { background: #fff; border: 1px solid var(--ac-border); border-radius: 18px; box-shadow: 0 6px 20px rgba(28, 35, 64, .05); padding: 1.1rem 1.2rem; }
    .ac-aside__title { font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--ac-muted); margin-bottom: .75rem; }
    .ac-preview { display: flex; align-items: center; gap: .8rem; }
    .ac-avatar { width: 52px; height: 52px; border-radius: 16px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: #fff; font-size: 1.05rem; background: linear-gradient(135deg, var(--ac-teal), #2fc4e8); }
    .ac-avatar.is-agency { background: linear-gradient(135deg, var(--ac-navy), #3a5794); }
    .ac-preview strong { display: block; color: var(--ac-ink); font-size: .95rem; word-break: break-word; }
    .ac-preview span { font-size: .76rem; color: var(--ac-muted); word-break: break-all; }
    .ac-check { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
    .ac-check li { display: flex; gap: .55rem; align-items: center; font-size: .82rem; color: #4b5065; }
    .ac-check li i { width: 18px; height: 18px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: .55rem; border: 1.5px solid #cfd3e1; color: transparent; flex-shrink: 0; transition: all .2s; }
    .ac-check li.is-done i { background: #0f9d58; border-color: #0f9d58; color: #fff; }
    .ac-steps { counter-reset: s; list-style: none; padding: 0; margin: 0; display: grid; gap: .6rem; }
    .ac-steps li { counter-increment: s; display: flex; gap: .6rem; font-size: .8rem; color: #4b5065; }
    .ac-steps li::before { content: counter(s); width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: .66rem; font-weight: 800; color: #fff; background: var(--ac-navy); }

    .ac-bar { position: sticky; bottom: 1rem; z-index: 20; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; padding: .8rem 1.1rem; margin-top: .5rem;
        border-radius: 16px; background: rgba(255, 255, 255, .96); border: 1px solid var(--ac-border); box-shadow: 0 14px 34px rgba(28, 35, 64, .15); backdrop-filter: blur(6px); }
    .ac-bar__text { font-size: .8rem; color: var(--ac-muted); }
</style>
@endpush

@section('content')
<form action="{{ route('cms.portal-accounts.store') }}" method="POST" enctype="multipart/form-data" class="ac" id="accountForm">
    @csrf

    <div class="ac-head">
        <div>
            <h4>{{ $heading }}</h4>
            <p>Create the account yourself instead of waiting for them to sign up. Only the login details are required — licences and documents can be added now or later.</p>
        </div>
        <a href="{{ $indexUrl }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> {{ $listLabel }}</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><i class="fas fa-triangle-exclamation me-1"></i><strong>Please fix the {{ $errors->count() === 1 ? 'field' : $errors->count() . ' fields' }} highlighted below.</strong></div>
    @endif

    <div class="ac-grid">
        <div>
            @if($type)
                <input type="hidden" name="type" value="{{ $type }}">
            @else
            <div class="ac-types" role="radiogroup" aria-label="Account type">
                <label class="ac-type">
                    <input type="radio" name="type" value="company" @checked($current === 'company')>
                    <span class="ac-type__icon"><i class="fas fa-building"></i></span>
                    <span><strong>Agency</strong><span class="sub">A brokerage that lists properties and can add its own agents.</span></span>
                </label>
                <label class="ac-type">
                    <input type="radio" name="type" value="agent" @checked($current === 'agent')>
                    <span class="ac-type__icon"><i class="fas fa-user-tie"></i></span>
                    <span><strong>Agent</strong><span class="sub">An individual broker — independent or part of an agency.</span></span>
                </label>
            </div>
            @error('type')<div class="text-danger small mb-3">{{ $message }}</div>@enderror
            @endif

            {{-- 1. Account --}}
            <div class="ac-section">
                <div class="ac-section__head">
                    <span class="ac-section__icon"><i class="fas fa-user-lock"></i></span>
                    <div><div class="ac-section__title">Account &amp; login</div><div class="ac-section__hint">They sign in to the CRM with this email and password.</div></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="acName"><span class="ac-when-agency">Contact person</span><span class="ac-when-agent">Full name</span> <span class="text-danger">*</span></label>
                        <div class="ac-input-icon"><i class="fas fa-user"></i><input type="text" id="acName" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required></div>
                        @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 ac-agency-only" id="companyNameField">
                        <label class="form-label" for="acCompany">Agency name</label>
                        <div class="ac-input-icon"><i class="fas fa-building"></i><input type="text" id="acCompany" name="company_name" class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name') }}" placeholder="As shown on the website"></div>
                        @error('company_name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="acEmail">Email <span class="text-danger">*</span></label>
                        <div class="ac-input-icon"><i class="fas fa-envelope"></i><input type="email" id="acEmail" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required></div>
                        @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="acPhone">Phone <span class="text-danger">*</span></label>
                        <div class="ac-input-icon"><i class="fas fa-phone"></i><input type="text" id="acPhone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="+971 5X XXX XXXX" required></div>
                        @error('phone')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="acPassword">Password <span class="text-danger">*</span></label>
                        <div class="ac-input-icon">
                            <i class="fas fa-key"></i>
                            <input type="password" id="acPassword" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8" autocomplete="new-password">
                            <button type="button" class="ac-pw-toggle" id="acPwToggle" aria-label="Show password"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="ac-help">At least 8 characters. Share it with them securely — they can change it after signing in.</div>
                        @error('password')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Approval</label>
                        <div class="ac-status">
                            <label><input type="radio" name="status" value="approved" @checked(old('status', 'approved') === 'approved')><i class="fas fa-circle-check"></i><span><strong>Approved</strong><span>Can sign in and list properties right away.</span></span></label>
                            <label><input type="radio" name="status" value="pending" @checked(old('status') === 'pending')><i class="fas fa-hourglass-half"></i><span><strong>Waiting for approval</strong><span>They complete KYC first; you approve it later.</span></span></label>
                        </div>
                        @error('status')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- 2. Identity --}}
            <div class="ac-section">
                <div class="ac-section__head">
                    <span class="ac-section__icon is-teal"><i class="fas fa-id-card"></i></span>
                    <div><div class="ac-section__title">Identity <small>optional</small></div><div class="ac-section__hint"><span class="ac-when-agency">The authorised person's ID — used for KYC.</span><span class="ac-when-agent">The agent's ID — used for KYC.</span></div></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Nationality</label>
                        <input type="text" name="nationality" class="form-control @error('nationality') is-invalid @enderror" value="{{ old('nationality') }}">
                        @error('nationality')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Emirates ID no.</label>
                        <input type="text" name="emirates_id_no" class="form-control @error('emirates_id_no') is-invalid @enderror" value="{{ old('emirates_id_no') }}" placeholder="784-XXXX-XXXXXXX-X">
                        @error('emirates_id_no')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Passport no.</label>
                        <input type="text" name="passport_no" class="form-control @error('passport_no') is-invalid @enderror" value="{{ old('passport_no') }}">
                        @error('passport_no')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Passport expiry</label>
                        <input type="date" name="passport_expiry" class="form-control @error('passport_expiry') is-invalid @enderror" value="{{ old('passport_expiry') }}">
                        @error('passport_expiry')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Emirates ID copy</label>
                        <label class="ac-file @error('emirates_id_document') is-invalid @enderror"><input type="file" name="emirates_id_document"><i class="fas fa-id-card"></i><span><strong>Upload</strong><span class="ac-file__name">PDF or image</span></span></label>
                        @error('emirates_id_document')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Passport copy</label>
                        <label class="ac-file @error('passport_document') is-invalid @enderror"><input type="file" name="passport_document"><i class="fas fa-passport"></i><span><strong>Upload</strong><span class="ac-file__name">PDF or image</span></span></label>
                        @error('passport_document')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- 3a. Agent: RERA broker details --}}
            <div class="ac-section" id="agentFields">
                <div class="ac-section__head">
                    <span class="ac-section__icon is-purple"><i class="fas fa-user-tie"></i></span>
                    <div><div class="ac-section__title">Broker licence <small>optional</small></div><div class="ac-section__hint">RERA registration and the agency they work for.</div></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Agency</label>
                        <select name="company_id" class="form-select @error('company_id') is-invalid @enderror">
                            <option value="">Independent (no agency)</option>
                            @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>
                                {{ $company->company_name ?: $company->name }}{{ $company->status !== 'approved' ? ' (' . ($company->status === 'pending' ? 'waiting for approval' : $company->status) . ')' : '' }}
                            </option>
                            @endforeach
                        </select>
                        <div class="ac-help">Agency not listed? <a href="{{ route('cms.portal-accounts.create', ['type' => 'company']) }}">Add the agency first</a>.</div>
                        @error('company_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">BRN <span class="text-muted fw-normal">(Broker Registration No.)</span></label>
                        <input type="text" name="brn_number" class="form-control @error('brn_number') is-invalid @enderror" value="{{ old('brn_number') }}">
                        @error('brn_number')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Trade licence no.</label>
                        <input type="text" name="trade_license_no" class="form-control @error('trade_license_no') is-invalid @enderror" value="{{ old('trade_license_no') }}">
                        @error('trade_license_no')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Trade licence expiry</label>
                        <input type="date" name="trade_license_expiry" class="form-control @error('trade_license_expiry') is-invalid @enderror" value="{{ old('trade_license_expiry') }}">
                        @error('trade_license_expiry')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">TRN <span class="text-muted fw-normal">(VAT no.)</span></label>
                        <input type="text" name="trn_number" class="form-control @error('trn_number') is-invalid @enderror" value="{{ old('trn_number') }}">
                        @error('trn_number')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">TRN expiry</label>
                        <input type="date" name="trn_expiry" class="form-control @error('trn_expiry') is-invalid @enderror" value="{{ old('trn_expiry') }}">
                        @error('trn_expiry')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">RERA broker card</label>
                        <label class="ac-file @error('rera_card_document') is-invalid @enderror"><input type="file" name="rera_card_document"><i class="fas fa-address-card"></i><span><strong>Upload</strong><span class="ac-file__name">PDF or image</span></span></label>
                        @error('rera_card_document')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">RERA certificate</label>
                        <label class="ac-file @error('rera_certificate_document') is-invalid @enderror"><input type="file" name="rera_certificate_document"><i class="fas fa-certificate"></i><span><strong>Upload</strong><span class="ac-file__name">PDF or image</span></span></label>
                        @error('rera_certificate_document')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Trade licence copy</label>
                        <label class="ac-file @error('trade_license_document') is-invalid @enderror"><input type="file" name="trade_license_document"><i class="fas fa-file-contract"></i><span><strong>Upload</strong><span class="ac-file__name">PDF or image</span></span></label>
                        @error('trade_license_document')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- 3b. Agency: licence & office --}}
            <div class="ac-section" id="companyFields">
                <div class="ac-section__head">
                    <span class="ac-section__icon is-green"><i class="fas fa-building"></i></span>
                    <div><div class="ac-section__title">Agency licence &amp; office <small>optional</small></div><div class="ac-section__hint">Trade licence, RERA office registration and the registered office.</div></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Trade licence no.</label>
                        <input type="text" name="trade_license_no" class="form-control @error('trade_license_no') is-invalid @enderror" value="{{ old('trade_license_no') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Trade licence expiry</label>
                        <input type="date" name="trade_license_expiry" class="form-control @error('trade_license_expiry') is-invalid @enderror" value="{{ old('trade_license_expiry') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ORN <span class="text-muted fw-normal">(Office Registration No.)</span></label>
                        <input type="text" name="orn_number" class="form-control @error('orn_number') is-invalid @enderror" value="{{ old('orn_number') }}">
                        @error('orn_number')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Registered office address</label>
                        <div class="ac-input-icon"><i class="fas fa-location-dot"></i><input type="text" name="office_address" class="form-control @error('office_address') is-invalid @enderror" value="{{ old('office_address') }}"></div>
                        @error('office_address')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">TRN <span class="text-muted fw-normal">(VAT no.)</span></label>
                        <input type="text" name="trn_number" class="form-control @error('trn_number') is-invalid @enderror" value="{{ old('trn_number') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">TRN expiry</label>
                        <input type="date" name="trn_expiry" class="form-control @error('trn_expiry') is-invalid @enderror" value="{{ old('trn_expiry') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Landline</label>
                        <input type="text" name="landline" class="form-control @error('landline') is-invalid @enderror" value="{{ old('landline') }}">
                        @error('landline')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Authorised signatory</label>
                        <input type="text" name="authorized_signatory_name" class="form-control @error('authorized_signatory_name') is-invalid @enderror" value="{{ old('authorized_signatory_name') }}">
                        @error('authorized_signatory_name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Trade licence copy</label>
                        <label class="ac-file"><input type="file" name="trade_license_document"><i class="fas fa-file-contract"></i><span><strong>Upload</strong><span class="ac-file__name">PDF or image</span></span></label>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">RERA certificate</label>
                        <label class="ac-file"><input type="file" name="rera_certificate_document"><i class="fas fa-certificate"></i><span><strong>Upload</strong><span class="ac-file__name">PDF or image</span></span></label>
                    </div>
                </div>
            </div>
        </div>

        <aside class="ac-aside">
            <div class="ac-card">
                <div class="ac-aside__title">New account</div>
                <div class="ac-preview">
                    <span class="ac-avatar" id="acAvatar">?</span>
                    <div class="min-w-0"><strong id="acPreviewName">Name</strong><span id="acPreviewSub">email@example.com</span></div>
                </div>
            </div>
            <div class="ac-card">
                <div class="ac-aside__title">Required</div>
                <ul class="ac-check" id="acChecklist">
                    <li data-field="name"><i class="fas fa-check"></i> Name</li>
                    <li data-field="email"><i class="fas fa-check"></i> Email</li>
                    <li data-field="phone"><i class="fas fa-check"></i> Phone</li>
                    <li data-field="password"><i class="fas fa-check"></i> Password (8+ characters)</li>
                </ul>
            </div>
            <div class="ac-card">
                <div class="ac-aside__title">What happens next</div>
                <ol class="ac-steps">
                    <li>The account is created and starts on the default Free plan — change the plan from the list.</li>
                    <li>Approved: they can sign in to the CRM straight away. Waiting: they complete KYC, then you approve it.</li>
                    <li>Anything left empty here they can fill in themselves under My Profile.</li>
                </ol>
            </div>
        </aside>
    </div>

    <div class="ac-bar">
        <span class="ac-bar__text"><i class="fas fa-circle-info me-1"></i> Fields marked <span class="text-danger">*</span> are required.</span>
        <div class="d-flex gap-2">
            <a href="{{ $indexUrl }}" class="btn btn-outline-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-user-plus me-1"></i> <span id="acSubmitLabel">Create account</span></button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('accountForm');
    const typeValue = () => form.querySelector('input[name="type"]:checked')?.value || form.querySelector('input[type="hidden"][name="type"]')?.value || 'agent';

    // Agent and agency share some field names (trade licence, TRN…): only the visible set is sent.
    function syncType() {
        const isAgency = typeValue() === 'company';
        document.getElementById('agentFields').style.display = isAgency ? 'none' : '';
        document.getElementById('companyFields').style.display = isAgency ? '' : 'none';
        document.getElementById('companyNameField').style.display = isAgency ? '' : 'none';
        document.querySelectorAll('#agentFields input, #agentFields select').forEach((el) => { el.disabled = isAgency; });
        document.querySelectorAll('#companyFields input, #companyFields select').forEach((el) => { el.disabled = !isAgency; });
        document.querySelectorAll('.ac-when-agency').forEach((el) => { el.style.display = isAgency ? '' : 'none'; });
        document.querySelectorAll('.ac-when-agent').forEach((el) => { el.style.display = isAgency ? 'none' : ''; });
        document.getElementById('acAvatar').classList.toggle('is-agency', isAgency);
        document.getElementById('acSubmitLabel').textContent = isAgency ? 'Create agency' : 'Create agent';
        preview();
    }

    function preview() {
        const isAgency = typeValue() === 'company';
        const name = (isAgency && form.company_name.value.trim()) || form.name.value.trim();
        const initials = name.split(/\s+/).filter(Boolean).map((w) => w[0]).slice(0, 2).join('').toUpperCase();
        document.getElementById('acAvatar').textContent = initials || '?';
        document.getElementById('acPreviewName').textContent = name || (isAgency ? 'Agency name' : 'Agent name');
        document.getElementById('acPreviewSub').textContent = [isAgency ? 'Agency' : 'Agent', form.email.value.trim()].filter(Boolean).join(' · ');
        const done = { name: form.name.value.trim(), email: form.email.checkValidity() && form.email.value, phone: form.phone.value.trim(), password: form.password.value.length >= 8 };
        document.querySelectorAll('#acChecklist li').forEach((li) => li.classList.toggle('is-done', !!done[li.dataset.field]));
    }

    form.addEventListener('change', (e) => {
        if (e.target.name === 'type') syncType();
        if (e.target.type === 'file') {
            const box = e.target.closest('.ac-file');
            const file = e.target.files?.[0];
            box.classList.toggle('has-file', !!file);
            box.querySelector('strong').textContent = file ? 'Attached' : 'Upload';
            box.querySelector('.ac-file__name').textContent = file ? file.name : 'PDF or image';
        }
    });
    form.addEventListener('input', preview);
    document.getElementById('acPwToggle').addEventListener('click', function () {
        const input = document.getElementById('acPassword');
        input.type = input.type === 'password' ? 'text' : 'password';
        this.innerHTML = `<i class="fas fa-eye${input.type === 'password' ? '' : '-slash'}"></i>`;
    });
    syncType();
})();
</script>
@endpush
