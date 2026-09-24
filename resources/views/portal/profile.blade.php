@extends('portal.layouts.app')

@section('title', 'My Profile')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --dash-navy: var(--secondary-color, #264373);
        --dash-teal: var(--primary-color, #04a1cc);
        --dash-green: #0f9d58;
        --dash-green-2: #34c880;
        --dash-amber: #e08e0b;
        --dash-amber-2: #f5a623;
        --dash-slate: #5b6478;
        --dash-slate-2: #8891a8;
        --dash-ink: #1c2340;
        --dash-muted: #838aa3;
        --dash-bg-soft: #f7f8fc;
        --dash-border: rgba(28, 35, 64, 0.07);
    }
    .fill-teal  { background: linear-gradient(135deg, var(--dash-teal), #2fc4e8); }
    .fill-navy  { background: linear-gradient(135deg, var(--dash-navy), #3a5794); }
    .fill-green { background: linear-gradient(135deg, var(--dash-green), var(--dash-green-2)); }
    .fill-amber { background: linear-gradient(135deg, var(--dash-amber), var(--dash-amber-2)); }
    .fill-slate { background: linear-gradient(135deg, var(--dash-slate), var(--dash-slate-2)); }

    .kyc-submit-modal .modal-content { border: 0; border-radius: 20px; box-shadow: 0 24px 60px rgba(31, 35, 64, 0.22); overflow: hidden; }
    .kyc-submit-modal .modal-dialog { max-width: 560px; }
    .kyc-submit-modal .modal-body { padding: 2.25rem 2rem 0.5rem; text-align: center; }
    .kyc-submit-modal .modal-footer { border-top: 0; display: flex; flex-wrap: nowrap; justify-content: center; align-items: center; gap: .75rem; padding: 1.5rem 2rem 2rem; }
    .kyc-submit-modal .modal-footer .btn { flex: 0 1 230px; width: 230px; max-width: calc(50% - .375rem); min-width: 0; min-height: 48px; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; border-radius: 12px; font-weight: 700; padding: .65rem .75rem; }
    .kyc-submit-modal .modal-footer .btn-primary { color: #fff; background: linear-gradient(135deg, var(--dash-teal), #2fc4e8); border: 0; box-shadow: 0 8px 18px rgba(4, 161, 204, .22); }
    .kyc-submit-modal .modal-footer .btn-primary:hover { color: #fff; filter: brightness(.96); }
    .kyc-submit-modal .modal-footer .btn-outline-secondary { color: var(--dash-navy); border-color: rgba(38, 67, 115, .35); }
    .kyc-submit-modal .modal-footer .btn-outline-secondary:hover { color: #fff; background: var(--dash-navy); border-color: var(--dash-navy); }
    .kyc-submit-icon { width: 64px; height: 64px; margin: 0 auto 1.1rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; background: linear-gradient(135deg, var(--dash-teal), #2fc4e8); box-shadow: 0 8px 20px rgba(4, 161, 204, .22); }
    .kyc-submit-icon-success { background: linear-gradient(135deg, var(--dash-green), var(--dash-green-2)); box-shadow: 0 8px 20px rgba(15, 157, 88, .22); }
    .kyc-submit-title { color: var(--dash-ink); font-weight: 800; font-size: 1.15rem; margin-bottom: .5rem; }
    .kyc-submit-text { color: var(--dash-muted); font-size: .9rem; line-height: 1.55; max-width: 380px; margin: 0 auto; }
    .kyc-submit-error { text-align: left; }
    @media (max-width: 575.98px) {
        .kyc-submit-modal .modal-body { padding: 2rem 1.25rem .5rem; }
        .kyc-submit-modal .modal-footer { flex-direction: column; padding: 1.25rem; gap: .6rem; }
        .kyc-submit-modal .modal-footer .btn { flex: 0 0 auto; width: 100%; max-width: 100%; }
    }

    .profile-hero {
        background: linear-gradient(120deg, var(--dash-navy) 0%, #16294f 55%, var(--dash-teal) 145%);
        border-radius: 16px; padding: 1.75rem 2rem; color: #fff; margin-bottom: 1.25rem;
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;
    }
    .profile-avatar {
        width: 64px; height: 64px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.4rem; color: #fff;
        border: 2px solid rgba(255,255,255,0.35);
    }
    .profile-name { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.35rem; margin-bottom: 0.3rem; }
    .profile-badges .badge { font-weight: 700; font-size: 0.7rem; letter-spacing: 0.02em; padding: 0.4em 0.7em; }
    .profile-meta { display: flex; flex-wrap: wrap; gap: 1.1rem; margin-top: 0.55rem; font-size: 0.83rem; opacity: 0.88; }
    .profile-meta span i { width: 16px; opacity: 0.8; }
    .profile-actions .btn { font-weight: 700; font-size: 0.83rem; background: #fff; color: var(--dash-navy); border: none; }
    .profile-actions .btn:hover { opacity: 0.92; color: var(--dash-navy); }

    .stat-mini {
        border-radius: 14px; border: 1px solid var(--dash-border); background: #fff;
        box-shadow: 0 4px 14px rgba(28,35,64,0.05);
        padding: 0.9rem 1rem; display: flex; align-items: center; gap: 0.75rem; height: 100%;
    }
    .stat-mini-icon { width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; }
    .stat-mini-value { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.2rem; line-height: 1.1; color: var(--dash-ink); }
    .stat-mini-label { font-size: 0.68rem; font-weight: 700; color: var(--dash-muted); text-transform: uppercase; letter-spacing: 0.02em; }

    .dash-card { border-radius: 16px; border: 1px solid var(--dash-border); background: #fff; box-shadow: 0 4px 14px rgba(28,35,64,0.05); padding: 1.25rem 1.4rem; }
    .dash-card h6 { font-weight: 800; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 1.1rem; color: var(--dash-navy); display: flex; align-items: center; gap: 0.5rem; justify-content: flex-start; }
    .dash-card h6 i { color: var(--dash-muted); font-size: 0.85rem; }

    .info-row { margin-bottom: 1rem; }
    .info-row:last-child { margin-bottom: 0; }
    .info-label { font-size: 0.72rem; color: var(--dash-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; margin-bottom: 0.15rem; }
    .info-value { font-size: 0.92rem; font-weight: 600; color: var(--dash-ink); }
    .info-value a { color: var(--dash-teal); text-decoration: none; }
    .info-value a:hover { text-decoration: underline; }

    .doc-card {
        border-radius: 12px; border: 1px solid var(--dash-border); background: var(--dash-bg-soft);
        padding: 0.85rem 1rem; display: flex; align-items: center; gap: 0.75rem; height: 100%;
    }
    .doc-card.is-submitted { background: #fff; }
    .doc-icon { width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.85rem; }
    .doc-name { font-weight: 700; font-size: 0.83rem; color: var(--dash-ink); }
    .doc-status { font-size: 0.72rem; color: var(--dash-muted); font-weight: 600; }
    .doc-verify-badge { font-size: 0.66rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; padding: 0.15rem 0.5rem; border-radius: 20px; display: inline-block; margin-top: 0.2rem; }
    .doc-verify-badge.tone-verified { background: rgba(15,157,88,0.12); color: var(--dash-green); }
    .doc-verify-badge.tone-rejected { background: rgba(220,53,69,0.1); color: #dc3545; }
    .doc-verify-badge.tone-pending { background: rgba(224,142,11,0.12); color: var(--dash-amber); }
    .doc-note { font-size: 0.72rem; color: #dc3545; margin-top: 0.15rem; }
    .doc-actions { flex-shrink: 0; }
    .doc-upload-label { cursor: pointer; margin-bottom: 0; }

    .section-edit-btn { margin-left: auto; color: var(--dash-muted); background: none; border: none; font-size: 0.8rem; padding: 0.2rem 0.4rem; }
    .section-edit-btn:hover { color: var(--dash-teal); }
    .section-edit.d-none { display: none; }
    .section-edit .form-label { font-size: 0.72rem; color: var(--dash-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }

    /* Every profile edit-form input, made a bit more inviting than a stock Bootstrap field. */
    .section-edit .form-control,
    .section-edit .form-select {
        border-radius: 10px;
        border: 1.5px solid var(--dash-border);
        background: var(--dash-bg-soft);
        padding: 0.55rem 0.9rem;
        font-size: 0.86rem;
        color: var(--dash-ink);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }
    .section-edit .form-control:hover,
    .section-edit .form-select:hover { border-color: rgba(4, 161, 204, 0.35); }
    .section-edit .form-control:focus,
    .section-edit .form-select:focus {
        border-color: var(--dash-teal);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(4, 161, 204, 0.14);
        outline: none;
    }
    .section-edit .form-control::placeholder { color: #adb4c4; }

    .email-change-block { border-top: 1px dashed var(--dash-border); margin-top: 1.1rem; padding-top: 1rem; }
    .email-change-step .form-control { max-width: 280px; }
    .email-change-step .alert-error-box { font-weight: 600; }
</style>
@endpush

@section('content')
@php
    $displayName = $portalUser->type === 'company' ? ($portalUser->company_name ?: $portalUser->name) : $portalUser->name;
    $initials = strtoupper(collect(preg_split('/\s+/', trim($displayName)))->filter()->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '?';
    $avatarFill = $portalUser->type === 'company' ? 'fill-navy' : 'fill-teal';
    $statusMap = ['pending' => 'bg-warning text-dark', 'approved' => 'bg-success', 'rejected' => 'bg-danger'];
@endphp

<div class="profile-hero">
    <div class="d-flex align-items-center gap-3">
        <div class="profile-avatar {{ $avatarFill }}">{{ $initials }}</div>
        <div>
            <div class="profile-name">{{ $displayName }}</div>
            <div class="profile-badges d-flex gap-2 align-items-center">
                <span class="badge bg-light text-dark">{{ ucfirst($portalUser->type) }}</span>
                <span class="badge {{ $statusMap[$portalUser->status] ?? 'bg-secondary' }}">{{ ucfirst($portalUser->status) }}</span>
            </div>
            <div class="profile-meta">
                <span><i class="fas fa-envelope"></i> {{ $portalUser->email }}</span>
                <span><i class="fas fa-phone"></i> {{ $portalUser->phone ?: '-' }}</span>
                <span><i class="fas fa-calendar"></i> Joined {{ $portalUser->created_at->format('d M Y') }}</span>
            </div>
        </div>
    </div>
    @if($portalUser->status !== 'approved' && (in_array($portalUser->kyc_review_status, ['draft', 'changes_requested'], true) || !$portalUser->kyc_user_submitted_at))
    <div class="profile-actions">
        <button type="button" id="resubmitBtn" class="btn"><i class="fas fa-paper-plane me-1"></i> {{ $portalUser->kyc_review_status === 'changes_requested' || $portalUser->kyc_user_submitted_at ? 'Resubmit for Approval' : 'Submit for Approval' }}</button>
    </div>
    @endif
</div>

@if($portalUser->status === 'rejected' && $portalUser->rejection_reason)
<div class="alert alert-danger d-flex align-items-center gap-2 shadow-sm mb-3" style="border-radius: 14px; font-size: 0.88rem;">
    <i class="fas fa-exclamation-circle"></i> <strong>Rejection reason:</strong> {{ $portalUser->rejection_reason }}
</div>
@endif

@if($portalUser->kyc_review_status === 'changes_requested' && $portalUser->kyc_review_note)
<div class="alert alert-info d-flex align-items-center gap-2 shadow-sm mb-3" style="border-radius: 14px; font-size: 0.88rem;">
    <i class="fas fa-comment-medical"></i> <strong>Admin requested updates:</strong> {{ $portalUser->kyc_review_note }}
</div>
@elseif($portalUser->kyc_review_status === 'draft' || ($portalUser->status !== 'approved' && !$portalUser->kyc_user_submitted_at))
<div class="alert alert-warning d-flex align-items-center gap-2 shadow-sm mb-3" style="border-radius: 14px; font-size: 0.88rem;">
    <i class="fas fa-clipboard-check"></i> Complete your profile and upload the requested KYC documents, then submit them for approval.
</div>
@elseif($portalUser->kyc_review_status === 'submitted')
<div class="alert alert-warning d-flex align-items-center gap-2 shadow-sm mb-3" style="border-radius: 14px; font-size: 0.88rem;">
    <i class="fas fa-clock"></i> Your KYC has been submitted and is waiting for Super Admin review.
</div>
@endif

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-teal"><i class="fas fa-building"></i></span>
            <div><div class="stat-mini-value">{{ $portalUser->properties()->count() }}</div><div class="stat-mini-label">Properties</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-amber"><i class="fas fa-address-book"></i></span>
            <div><div class="stat-mini-value">{{ $portalUser->leads()->count() }}</div><div class="stat-mini-label">Leads</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-green"><i class="fas fa-layer-group"></i></span>
            <div><div class="stat-mini-value">{{ $portalUser->plan?->getTranslation('name') ?? 'No Plan' }}</div><div class="stat-mini-label">Plan</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-slate"><i class="fas fa-file-alt"></i></span>
            <div><div class="stat-mini-value">{{ $documents->filter(fn($d) => $d['path'])->count() }}/{{ $documents->count() }}</div><div class="stat-mini-label">Docs Submitted</div></div>
        </div>
    </div>
</div>

<div class="dash-card mb-3">
    <h6><i class="fas fa-id-badge"></i> Identity
        <button type="button" class="section-edit-btn section-edit-toggle" data-section="identity" title="Edit"><i class="fas fa-pen"></i></button>
    </h6>
    <div class="section-view" data-section="identity">
        <div class="row">
            <div class="col-md-4 info-row"><div class="info-label">Full Name</div><div class="info-value">{{ $portalUser->name }}</div></div>
            @if($portalUser->type === 'company')
            <div class="col-md-4 info-row"><div class="info-label">Company Name</div><div class="info-value">{{ $portalUser->company_name ?: '-' }}</div></div>
            @endif
            <div class="col-md-4 info-row">
                <div class="info-label">Login Email</div>
                <div class="info-value">{{ $portalUser->email }}</div>
            </div>
            <div class="col-md-4 info-row"><div class="info-label">Phone</div><div class="info-value">{{ $portalUser->phone ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">Nationality</div><div class="info-value">{{ $portalUser->nationality ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">Emirates ID No.</div><div class="info-value">{{ $portalUser->emirates_id_no ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">Passport No.</div><div class="info-value">{{ $portalUser->passport_no ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">Passport Expiry</div><div class="info-value">{{ $portalUser->passport_expiry?->format('d M Y') ?: '-' }}</div></div>
        </div>

        <div class="email-change-block mt-2" id="emailChangeBlock">
            <div id="emailChangeView">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="emailChangeToggle"><i class="fas fa-envelope me-1"></i> Change Login Email</button>
            </div>
            <div class="email-change-step d-none" id="emailChangeStep1">
                <div class="alert-error-box text-danger small d-none mb-2" id="emailStep1Error"></div>
                <label class="form-label">New Email Address</label>
                <div class="d-flex gap-2 flex-wrap">
                    <input type="email" class="form-control form-control-sm" id="newEmailInput" placeholder="new.email@example.com" style="max-width:280px;">
                    <button type="button" class="btn btn-sm btn-primary" id="sendEmailCodeBtn">Send Code</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="emailChangeCancel1">Cancel</button>
                </div>
            </div>
            <div class="email-change-step d-none" id="emailChangeStep2">
                <div class="alert-error-box text-danger small d-none mb-2" id="emailStep2Error"></div>
                <p class="small text-muted mb-2">Enter the 4-digit code sent to <strong id="pendingEmailLabel"></strong>. You'll be logged out and need to sign back in with your new email once verified.</p>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <input type="text" class="form-control form-control-sm" id="emailOtpInput" maxlength="4" inputmode="numeric" pattern="[0-9]*" placeholder="1234" style="max-width:110px; letter-spacing:0.3em; text-align:center;">
                    <button type="button" class="btn btn-sm btn-success" id="verifyEmailCodeBtn">Verify &amp; Update</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="emailChangeCancel2">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    <form class="section-edit d-none section-form" data-section="identity">
        <div class="section-form-error text-danger small d-none mb-2"></div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control form-control-sm" value="{{ $portalUser->name }}">
            </div>
            @if($portalUser->type === 'company')
            <div class="col-md-4">
                <label class="form-label">Company Name</label>
                <input type="text" name="company_name" class="form-control form-control-sm" value="{{ $portalUser->company_name }}">
            </div>
            @endif
            <div class="col-md-4">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control form-control-sm" value="{{ $portalUser->phone }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Nationality</label>
                <input type="text" name="nationality" class="form-control form-control-sm" value="{{ $portalUser->nationality }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Emirates ID No.</label>
                <input type="text" name="emirates_id_no" class="form-control form-control-sm" value="{{ $portalUser->emirates_id_no }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Passport No.</label>
                <input type="text" name="passport_no" class="form-control form-control-sm" value="{{ $portalUser->passport_no }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Passport Expiry</label>
                <input type="date" name="passport_expiry" class="form-control form-control-sm" value="{{ $portalUser->passport_expiry?->format('Y-m-d') }}">
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-sm btn-success">Save</button>
            <button type="button" class="btn btn-sm btn-outline-secondary section-edit-cancel">Cancel</button>
        </div>
    </form>
</div>

@if($portalUser->type === 'agent')
<div class="dash-card mb-3">
    <h6><i class="fas fa-user-tie"></i> RERA Broker Details
        <button type="button" class="section-edit-btn section-edit-toggle" data-section="agent" title="Edit"><i class="fas fa-pen"></i></button>
    </h6>
    <div class="section-view" data-section="agent">
        <div class="row">
            <div class="col-md-4 info-row"><div class="info-label">BRN (Broker Registration No.)</div><div class="info-value">{{ $portalUser->brn_number ?: '-' }}</div></div>
            <div class="col-md-8 info-row">
                <div class="info-label">Affiliated Brokerage</div>
                <div class="info-value">{{ $portalUser->company ? ($portalUser->company->company_name ?: $portalUser->company->name) : 'Unaffiliated' }}</div>
            </div>
            <div class="col-md-4 info-row"><div class="info-label">Trade License No.</div><div class="info-value">{{ $portalUser->trade_license_no ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">Trade License Expiry</div><div class="info-value">{{ $portalUser->trade_license_expiry?->format('d M Y') ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">TRN (VAT)</div><div class="info-value">{{ $portalUser->trn_number ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">TRN Expiry</div><div class="info-value">{{ $portalUser->trn_expiry?->format('d M Y') ?: '-' }}</div></div>
        </div>
    </div>
    <form class="section-edit d-none section-form" data-section="agent">
        <div class="section-form-error text-danger small d-none mb-2"></div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">BRN (Broker Registration No.)</label>
                <input type="text" name="brn_number" class="form-control form-control-sm" value="{{ $portalUser->brn_number }}">
            </div>
            <div class="col-md-8">
                <label class="form-label">Affiliated Brokerage</label>
                <select name="company_id" class="form-select form-select-sm">
                    <option value="">Unaffiliated</option>
                    @foreach($companies as $company)
                    <option value="{{ $company->id }}" {{ $portalUser->company_id === $company->id ? 'selected' : '' }}>{{ $company->company_name ?: $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Trade License No.</label><input type="text" name="trade_license_no" class="form-control form-control-sm" value="{{ $portalUser->trade_license_no }}"></div>
            <div class="col-md-4"><label class="form-label">Trade License Expiry</label><input type="date" name="trade_license_expiry" class="form-control form-control-sm" value="{{ $portalUser->trade_license_expiry?->format('Y-m-d') }}"></div>
            <div class="col-md-4"><label class="form-label">TRN (VAT)</label><input type="text" name="trn_number" class="form-control form-control-sm" value="{{ $portalUser->trn_number }}"></div>
            <div class="col-md-4"><label class="form-label">TRN Expiry</label><input type="date" name="trn_expiry" class="form-control form-control-sm" value="{{ $portalUser->trn_expiry?->format('Y-m-d') }}"></div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-sm btn-success">Save</button>
            <button type="button" class="btn btn-sm btn-outline-secondary section-edit-cancel">Cancel</button>
        </div>
    </form>
</div>
@else
<div class="dash-card mb-3">
    <h6><i class="fas fa-building"></i> Company / RERA Office Details
        <button type="button" class="section-edit-btn section-edit-toggle" data-section="company" title="Edit"><i class="fas fa-pen"></i></button>
    </h6>
    <div class="section-view" data-section="company">
        <div class="row">
            <div class="col-md-4 info-row"><div class="info-label">Trade License No.</div><div class="info-value">{{ $portalUser->trade_license_no ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">Trade License Expiry</div><div class="info-value">{{ $portalUser->trade_license_expiry?->format('d M Y') ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">ORN</div><div class="info-value">{{ $portalUser->orn_number ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">TRN (VAT)</div><div class="info-value">{{ $portalUser->trn_number ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">TRN Expiry</div><div class="info-value">{{ $portalUser->trn_expiry?->format('d M Y') ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">Authorized Signatory</div><div class="info-value">{{ $portalUser->authorized_signatory_name ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">Landline</div><div class="info-value">{{ $portalUser->landline ?: '-' }}</div></div>
            <div class="col-md-12 info-row"><div class="info-label">Registered Office Address</div><div class="info-value">{{ $portalUser->office_address ?: '-' }}</div></div>
        </div>
    </div>
    <form class="section-edit d-none section-form" data-section="company">
        <div class="section-form-error text-danger small d-none mb-2"></div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Trade License No.</label>
                <input type="text" name="trade_license_no" class="form-control form-control-sm" value="{{ $portalUser->trade_license_no }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Trade License Expiry</label>
                <input type="date" name="trade_license_expiry" class="form-control form-control-sm" value="{{ $portalUser->trade_license_expiry?->format('Y-m-d') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">ORN</label>
                <input type="text" name="orn_number" class="form-control form-control-sm" value="{{ $portalUser->orn_number }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">TRN (VAT)</label>
                <input type="text" name="trn_number" class="form-control form-control-sm" value="{{ $portalUser->trn_number }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">TRN Expiry</label>
                <input type="date" name="trn_expiry" class="form-control form-control-sm" value="{{ $portalUser->trn_expiry?->format('Y-m-d') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Authorized Signatory</label>
                <input type="text" name="authorized_signatory_name" class="form-control form-control-sm" value="{{ $portalUser->authorized_signatory_name }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Landline</label>
                <input type="text" name="landline" class="form-control form-control-sm" value="{{ $portalUser->landline }}">
            </div>
            <div class="col-md-12">
                <label class="form-label">Registered Office Address</label>
                <input type="text" name="office_address" class="form-control form-control-sm" value="{{ $portalUser->office_address }}">
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-sm btn-success">Save</button>
            <button type="button" class="btn btn-sm btn-outline-secondary section-edit-cancel">Cancel</button>
        </div>
    </form>
</div>
@endif

<div class="dash-card mb-3">
    <h6><i class="fas fa-globe"></i> Public Profile
        <button type="button" class="section-edit-btn section-edit-toggle" data-section="about" title="Edit"><i class="fas fa-pen"></i></button>
    </h6>
    <p class="text-muted mb-3" style="font-size: 0.8rem;">
        Shown on your public listing page once it's live. Write your bio in your own language —
        it's automatically translated for other site languages; you (or admin) can always correct
        the wording later if the translation isn't quite right.
    </p>
    <div class="section-view" data-section="about">
        <div class="row">
            <div class="col-md-12 info-row"><div class="info-label">About / Bio</div><div class="info-value" style="font-weight: 500; white-space: pre-line;">{{ $bio ?: '-' }}</div></div>
            <div class="col-md-4 info-row"><div class="info-label">Years of Experience</div><div class="info-value">{{ $portalUser->years_of_experience ?? '-' }}</div></div>
            @if($portalUser->type === 'company')
            <div class="col-md-4 info-row"><div class="info-label">Established</div><div class="info-value">{{ $portalUser->founding_year ?: '-' }}</div></div>
            @endif
            <div class="col-md-4 info-row"><div class="info-label">Website</div><div class="info-value">{{ $portalUser->website ?: '-' }}</div></div>
            <div class="col-md-12 info-row"><div class="info-label">Preferred / Service Areas</div><div class="info-value">{{ $portalUser->preferred_areas ? implode(', ', $portalUser->preferred_areas) : '-' }}</div></div>
        </div>
    </div>
    <form class="section-edit d-none section-form" data-section="about">
        <div class="section-form-error text-danger small d-none mb-2"></div>
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">About / Bio</label>
                <textarea name="bio" class="form-control form-control-sm" rows="4" maxlength="2000">{{ $bio }}</textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Years of Experience</label>
                <input type="number" name="years_of_experience" class="form-control form-control-sm" min="0" max="80" value="{{ $portalUser->years_of_experience }}">
            </div>
            @if($portalUser->type === 'company')
            <div class="col-md-4">
                <label class="form-label">Established (Year)</label>
                <input type="number" name="founding_year" class="form-control form-control-sm" min="1900" max="{{ now()->year }}" value="{{ $portalUser->founding_year }}">
            </div>
            @endif
            <div class="col-md-4">
                <label class="form-label">Website</label>
                <input type="url" name="website" class="form-control form-control-sm" placeholder="https://" value="{{ $portalUser->website }}">
            </div>
            <div class="col-md-12">
                <label class="form-label">Preferred / Service Areas <span class="text-muted fw-normal">(comma separated)</span></label>
                <input type="text" name="preferred_areas" class="form-control form-control-sm" placeholder="Downtown Dubai, Business Bay" value="{{ $portalUser->preferred_areas ? implode(', ', $portalUser->preferred_areas) : '' }}">
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-sm btn-success">Save</button>
            <button type="button" class="btn btn-sm btn-outline-secondary section-edit-cancel">Cancel</button>
        </div>
    </form>
</div>

@php $meta = $portalUser->metadata ?? []; @endphp
<div class="dash-card mb-3">
    <h6><i class="fas fa-search"></i> SEO Metadata
        <button type="button" class="section-edit-btn section-edit-toggle" data-section="seo" title="Edit"><i class="fas fa-pen"></i></button>
    </h6>
    <p class="text-muted mb-3" style="font-size: 0.8rem;">
        Used on your public profile page. If left blank, the page falls back to your name and bio.
    </p>
    <div class="section-view" data-section="seo">
        <div class="row">
            <div class="col-md-6 info-row"><div class="info-label">Meta Title</div><div class="info-value">{{ $meta['meta_title'] ?? '-' }}</div></div>
            <div class="col-md-6 info-row"><div class="info-label">Canonical URL</div><div class="info-value">{{ $meta['canonical_url'] ?? '-' }}</div></div>
            <div class="col-md-12 info-row"><div class="info-label">Meta Description</div><div class="info-value">{{ $meta['meta_description'] ?? '-' }}</div></div>
        </div>
    </div>
    <form class="section-edit d-none section-form" data-section="seo" enctype="multipart/form-data">
        <div class="section-form-error text-danger small d-none mb-2"></div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Meta Title</label>
                <input type="text" name="metadata[meta_title]" class="form-control form-control-sm" maxlength="255" placeholder="Page title for search engines" value="{{ $meta['meta_title'] ?? '' }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Canonical URL</label>
                <input type="url" name="metadata[canonical_url]" class="form-control form-control-sm" maxlength="2048" placeholder="https://mightywarnersrealty.com/agent-details/{{ $portalUser->slug }}" value="{{ $meta['canonical_url'] ?? '' }}">
            </div>
            <div class="col-md-12">
                <label class="form-label">Meta Description</label>
                <textarea name="metadata[meta_description]" class="form-control form-control-sm" rows="2" maxlength="500" placeholder="Page description">{{ $meta['meta_description'] ?? '' }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Meta Keywords</label>
                <input type="text" name="metadata[meta_keywords]" class="form-control form-control-sm" maxlength="500" placeholder="keyword1, keyword2" value="{{ $meta['meta_keywords'] ?? '' }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">OG Image</label>
                <input type="file" name="metadata_og_image" class="form-control form-control-sm" accept="image/*">
                @if(!empty($meta['og_image']))
                    <img src="{{ asset('storage/' . $meta['og_image']) }}" class="mt-2 rounded" style="height:50px;">
                    <div class="form-check mt-1">
                        <input type="checkbox" name="remove_metadata_og_image" value="1" class="form-check-input" id="removeMetaOgImage">
                        <label class="form-check-label small" for="removeMetaOgImage">Remove image</label>
                    </div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label">OG Title</label>
                <input type="text" name="metadata[og_title]" class="form-control form-control-sm" maxlength="255" value="{{ $meta['og_title'] ?? '' }}">
            </div>
            <div class="col-md-12">
                <label class="form-label">OG Description</label>
                <textarea name="metadata[og_description]" class="form-control form-control-sm" rows="2" maxlength="500">{{ $meta['og_description'] ?? '' }}</textarea>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-sm btn-success">Save</button>
            <button type="button" class="btn btn-sm btn-outline-secondary section-edit-cancel">Cancel</button>
        </div>
    </form>
</div>

<div class="dash-card">
    <h6><i class="fas fa-folder-open"></i> My Documents</h6>
    <div class="row g-2">
        @foreach($documents as $doc)
        <div class="col-md-6">
            <div class="doc-card {{ $doc['path'] ? 'is-submitted' : '' }}" data-field="{{ $doc['field'] }}">
                <span class="doc-icon {{ $doc['path'] ? 'fill-teal' : 'fill-slate' }}"><i class="fas {{ $doc['icon'] }}"></i></span>
                <div class="flex-grow-1">
                    <div class="doc-name">{{ $doc['label'] }}</div>
                    @if($doc['path'])
                        @php $tone = ['verified' => 'tone-verified', 'rejected' => 'tone-rejected'][$doc['verification']['status']] ?? 'tone-pending'; @endphp
                        <span class="doc-verify-badge {{ $tone }}">
                            @if($doc['verification']['status'] === 'verified') <i class="fas fa-check-circle"></i> Verified
                            @elseif($doc['verification']['status'] === 'rejected') <i class="fas fa-times-circle"></i> Needs Re-upload
                            @else <i class="fas fa-hourglass-half"></i> Awaiting Review
                            @endif
                        </span>
                        @if($doc['verification']['status'] === 'rejected' && $doc['verification']['note'])
                        <div class="doc-note">{{ $doc['verification']['note'] }}</div>
                        @endif
                    @else
                        <span class="doc-status">Not submitted</span>
                    @endif
                </div>
                <span class="doc-actions d-flex align-items-center gap-1">
                    @if($doc['path'])
                        <a href="{{ route('portal.profile.document', $doc['field']) }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                        <label class="btn btn-sm btn-outline-secondary mb-0 doc-upload-label" title="Replace">
                            <i class="fas fa-sync-alt"></i><input type="file" class="d-none doc-upload-input" accept=".jpg,.jpeg,.png,.pdf">
                        </label>
                        <button type="button" class="btn btn-sm btn-outline-danger doc-remove-btn" title="Remove"><i class="fas fa-trash"></i></button>
                    @else
                        <label class="btn btn-sm btn-outline-primary mb-0 doc-upload-label">
                            Add <input type="file" class="d-none doc-upload-input" accept=".jpg,.jpeg,.png,.pdf">
                        </label>
                    @endif
                </span>
            </div>
        </div>
        @endforeach
    </div>
</div>
<div class="modal fade kyc-submit-modal" id="kycSubmitModal" tabindex="-1" aria-labelledby="kycSubmitModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body">
                <div class="kyc-submit-icon" id="kycSubmitModalIcon"><i class="fas fa-paper-plane"></i></div>
                <h5 class="kyc-submit-title" id="kycSubmitModalTitle">Submit your KYC for approval?</h5>
                <p class="kyc-submit-text" id="kycSubmitModalText" aria-live="polite">Your profile and documents will be sent to the Super Admin for review. You can continue updating your profile after submission.</p>
                <div class="alert alert-danger d-none mt-3 mb-0 kyc-submit-error" id="kycSubmitModalError" role="alert"></div>
            </div>
            <div class="modal-footer" id="kycSubmitModalActions">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="kycSubmitConfirmBtn"><i class="fas fa-paper-plane me-1"></i>Submit for Approval</button>
            </div>
            <div class="modal-footer d-none" id="kycSubmitSuccessActions">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const base = "{{ url('/portal/profile') }}";

    document.querySelectorAll('.section-edit-toggle').forEach(btn => btn.addEventListener('click', function () {
        const section = this.dataset.section;
        const card = this.closest('.dash-card');
        card.querySelector('.section-view[data-section="' + section + '"]').classList.add('d-none');
        card.querySelector('.section-edit[data-section="' + section + '"]').classList.remove('d-none');
    }));

    document.querySelectorAll('.section-edit-cancel').forEach(btn => btn.addEventListener('click', function () {
        const card = this.closest('.dash-card');
        const section = this.closest('.section-edit').dataset.section;
        card.querySelector('.section-edit[data-section="' + section + '"]').classList.add('d-none');
        card.querySelector('.section-view[data-section="' + section + '"]').classList.remove('d-none');
    }));

    document.querySelectorAll('.section-form').forEach(form => form.addEventListener('submit', function (e) {
        e.preventDefault();
        const errorBox = form.querySelector('.section-form-error');
        if (errorBox) errorBox.classList.add('d-none');
        const formData = new FormData(form);
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('section', form.dataset.section);
        fetch(base, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' },
        })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const message = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Could not save changes.');
                    throw new Error(message);
                }
                return data;
            })
            .then(data => { if (data.success) window.location.reload(); })
            .catch(err => {
                if (errorBox) { errorBox.textContent = err.message; errorBox.classList.remove('d-none'); }
                else alert(err.message);
                if (window.restoreSubmitButtons) window.restoreSubmitButtons(form);
            });
    }));

    document.querySelectorAll('.doc-remove-btn').forEach(btn => btn.addEventListener('click', function () {
        const field = this.closest('.doc-card').dataset.field;
        if (!confirm('Remove this document?')) return;
        fetch(base + '/documents/' + field, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        }).then(() => window.location.reload());
    }));

    document.querySelectorAll('.doc-upload-input').forEach(input => input.addEventListener('change', function () {
        if (!this.files.length) return;
        const field = this.closest('.doc-card').dataset.field;
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('document', this.files[0]);
        fetch(base + '/documents/' + field, { method: 'POST', body: formData })
            .then(async res => {
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not upload document.');
                }
                window.location.reload();
            })
            .catch(err => alert(err.message));
    }));

    const resubmitBtn = document.getElementById('resubmitBtn');
    if (resubmitBtn) {
        const kycSubmitModalEl = document.getElementById('kycSubmitModal');
        const kycSubmitModal = kycSubmitModalEl ? new bootstrap.Modal(kycSubmitModalEl) : null;
        const kycSubmitConfirmBtn = document.getElementById('kycSubmitConfirmBtn');
        const kycSubmitModalTitle = document.getElementById('kycSubmitModalTitle');
        const kycSubmitModalText = document.getElementById('kycSubmitModalText');
        const kycSubmitModalError = document.getElementById('kycSubmitModalError');
        const kycSubmitModalIcon = document.getElementById('kycSubmitModalIcon');
        const kycSubmitModalActions = document.getElementById('kycSubmitModalActions');
        const kycSubmitSuccessActions = document.getElementById('kycSubmitSuccessActions');
        let submissionCompleted = false;
        const isResubmission = @json($portalUser->kyc_review_status === 'changes_requested' || $portalUser->kyc_user_submitted_at !== null);

        kycSubmitModalEl?.addEventListener('hidden.bs.modal', function () {
            if (submissionCompleted) window.location.reload();
        });

        resubmitBtn.addEventListener('click', function () {
            kycSubmitModalIcon.classList.remove('kyc-submit-icon-success');
            kycSubmitModalIcon.innerHTML = '<i class="fas fa-paper-plane"></i>';
            kycSubmitModalTitle.textContent = isResubmission ? 'Resubmit your KYC for review?' : 'Submit your KYC for approval?';
            kycSubmitModalText.textContent = isResubmission
                ? 'Your updated profile and documents will be sent to the Super Admin for another review.'
                : 'Your profile and documents will be sent to the Super Admin for review. You can continue updating your profile after submission.';
            kycSubmitModalActions.classList.remove('d-none');
            kycSubmitSuccessActions.classList.add('d-none');
            kycSubmitConfirmBtn.disabled = false;
            kycSubmitConfirmBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>' + (isResubmission ? 'Resubmit for Approval' : 'Submit for Approval');
            kycSubmitModalError.textContent = '';
            kycSubmitModalError.classList.add('d-none');
            kycSubmitModal?.show();
        });

        kycSubmitConfirmBtn?.addEventListener('click', function () {
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';
            fetch(base + '/resubmit', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            })
                .then(async res => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data.message || 'Could not submit your KYC for approval.');
                    submissionCompleted = true;
                    kycSubmitModalIcon.classList.add('kyc-submit-icon-success');
                    kycSubmitModalIcon.innerHTML = '<i class="fas fa-check"></i>';
                    kycSubmitModalTitle.textContent = 'KYC submitted successfully';
                    kycSubmitModalText.textContent = 'Your KYC documents have been submitted. Our team will review them and get back to you soon. We’ll notify you once the review is complete.';
                    kycSubmitModalActions.classList.add('d-none');
                    kycSubmitSuccessActions.classList.remove('d-none');
                })
                .catch(err => {
                    kycSubmitModalError.textContent = err.message;
                    kycSubmitModalError.classList.remove('d-none');
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                });
        });
    }

    // --- Change Login Email (2-step: request a code to the new address, then verify it) ---
    const emailView = document.getElementById('emailChangeView');
    const emailStep1 = document.getElementById('emailChangeStep1');
    const emailStep2 = document.getElementById('emailChangeStep2');
    const newEmailInput = document.getElementById('newEmailInput');
    const emailOtpInput = document.getElementById('emailOtpInput');
    const emailStep1Error = document.getElementById('emailStep1Error');
    const emailStep2Error = document.getElementById('emailStep2Error');
    const pendingEmailLabel = document.getElementById('pendingEmailLabel');

    function showEmailError(box, message) {
        box.textContent = message;
        box.classList.remove('d-none');
    }

    document.getElementById('emailChangeToggle')?.addEventListener('click', function () {
        emailView.classList.add('d-none');
        emailStep1.classList.remove('d-none');
        newEmailInput.focus();
    });

    function resetEmailChangeWidget() {
        emailStep1.classList.add('d-none');
        emailStep2.classList.add('d-none');
        emailStep1Error.classList.add('d-none');
        emailStep2Error.classList.add('d-none');
        newEmailInput.value = '';
        emailOtpInput.value = '';
        emailView.classList.remove('d-none');
    }
    document.getElementById('emailChangeCancel1')?.addEventListener('click', resetEmailChangeWidget);
    document.getElementById('emailChangeCancel2')?.addEventListener('click', resetEmailChangeWidget);

    document.getElementById('sendEmailCodeBtn')?.addEventListener('click', function () {
        emailStep1Error.classList.add('d-none');
        const email = newEmailInput.value.trim();
        if (!email) { showEmailError(emailStep1Error, 'Please enter an email address.'); return; }

        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch("{{ route('portal.profile.email.request') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ new_email: email }),
        })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Could not send the code.'));
                pendingEmailLabel.textContent = email;
                emailStep1.classList.add('d-none');
                emailStep2.classList.remove('d-none');
                emailOtpInput.focus();
            })
            .catch(err => showEmailError(emailStep1Error, err.message))
            .finally(() => { btn.disabled = false; btn.innerHTML = originalHtml; });
    });

    document.getElementById('verifyEmailCodeBtn')?.addEventListener('click', function () {
        emailStep2Error.classList.add('d-none');
        const code = emailOtpInput.value.trim();
        if (!code) { showEmailError(emailStep2Error, 'Please enter the code.'); return; }

        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch("{{ route('portal.profile.email.verify') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ code }),
        })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'Invalid or expired code.');
                window.location.href = data.redirect;
            })
            .catch(err => {
                showEmailError(emailStep2Error, err.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
    });
})();
</script>
@endpush
