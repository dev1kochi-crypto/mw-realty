@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.portal-accounts.index', ['type' => $portalUser->type]) }}">{{ $portalUser->type === 'company' ? 'Companies' : 'Agents' }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $portalUser->type === 'company' ? ($portalUser->company_name ?: $portalUser->name) : $portalUser->name }}</li>
@endsection

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
    .profile-actions .btn { font-weight: 700; font-size: 0.83rem; }
    .profile-actions .btn-approve { background: #fff; color: var(--dash-green); border: none; }
    .profile-actions .btn-approve:hover { background: #eafff3; color: var(--dash-green); }
    .profile-actions .btn-reject { background: transparent; color: #fff; border: 1.5px solid rgba(255,255,255,0.5); }
    .profile-actions .btn-reject:hover { background: rgba(255,255,255,0.12); color: #fff; }

    .reject-modal-content { border: none; border-radius: 20px; box-shadow: 0 24px 60px rgba(28,35,64,0.22); }
    .reject-modal-icon {
        width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 1.1rem;
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        background: rgba(220,53,69,0.1); color: #dc3545;
    }
    #rejectReasonModal .modal-title, #rejectReasonModal h5 { font-family: 'Plus Jakarta Sans', sans-serif; }

    .stat-mini {
        border-radius: 14px; border: 1px solid var(--dash-border); background: #fff;
        box-shadow: 0 4px 14px rgba(28,35,64,0.05);
        padding: 0.9rem 1rem; display: flex; align-items: center; gap: 0.75rem; height: 100%;
    }
    .stat-mini-icon { width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; }
    .stat-mini-value { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.2rem; line-height: 1.1; color: var(--dash-ink); }
    .stat-mini-label { font-size: 0.68rem; font-weight: 700; color: var(--dash-muted); text-transform: uppercase; letter-spacing: 0.02em; }

    .dash-card { border-radius: 16px; border: 1px solid var(--dash-border); background: #fff; box-shadow: 0 4px 14px rgba(28,35,64,0.05); padding: 1.25rem 1.4rem; }
    .dash-card h6 { font-weight: 800; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 1.1rem; color: var(--dash-navy); display: flex; align-items: center; gap: 0.5rem; }
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
    .doc-verify-badge { font-size: 0.64rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; padding: 0.15rem 0.5rem; border-radius: 20px; display: inline-block; margin-top: 0.2rem; }
    .doc-verify-badge.tone-verified { background: rgba(15,157,88,0.12); color: var(--dash-green); }
    .doc-verify-badge.tone-rejected { background: rgba(220,53,69,0.1); color: #dc3545; }
    .doc-verify-badge.tone-pending { background: rgba(224,142,11,0.12); color: var(--dash-amber); }
    .doc-note { font-size: 0.7rem; color: #dc3545; margin-top: 0.1rem; }
    .doc-actions { flex-shrink: 0; }
    .doc-upload-label { cursor: pointer; margin-bottom: 0; }
    .doc-flag-modal-content { border: none; border-radius: 20px; box-shadow: 0 24px 60px rgba(28,35,64,0.22); }

    .request-changes-fab { position: fixed; right: 2rem; bottom: 2rem; z-index: 1030; display: inline-flex; align-items: center; gap: .55rem; padding: .85rem 1.15rem; border: 0; border-radius: 999px; color: #fff; font-weight: 800; background: linear-gradient(135deg, var(--dash-teal), #1685bf); box-shadow: 0 10px 28px rgba(4, 120, 180, .34); transition: transform .18s ease, box-shadow .18s ease; }
    .request-changes-fab:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 14px 32px rgba(4, 120, 180, .42); }
    .request-changes-modal .modal-content { border: 0; border-radius: 18px; box-shadow: 0 24px 60px rgba(28,35,64,.24); overflow: hidden; }
    .request-changes-modal .modal-header { padding: 1.1rem 1.4rem; color: #fff; background: linear-gradient(120deg, var(--dash-navy), #176e9e); border: 0; }
    .request-changes-modal .modal-title { font-weight: 800; font-size: 1rem; }
    .request-changes-modal .btn-close { filter: brightness(0) invert(1); opacity: .85; }
    .request-changes-modal .modal-body { padding: 1.25rem 1.4rem; }
    .request-changes-modal .modal-footer { padding: .9rem 1.4rem 1.2rem; border-top: 1px solid var(--dash-border); }
    .request-option { display: flex; align-items: flex-start; gap: .55rem; height: 100%; padding: .7rem .8rem; border: 1px solid var(--dash-border); border-radius: 10px; background: #fff; cursor: pointer; transition: border-color .15s ease, background .15s ease; }
    .request-option:hover { border-color: var(--dash-teal); background: #f5fbfd; }
    .request-option .form-check-input { flex: 0 0 auto; margin: .15rem 0 0; }
    .request-option .form-check-input:checked { background-color: var(--dash-teal); border-color: var(--dash-teal); }
    @media (max-width: 575.98px) { .request-changes-fab { right: 1rem; bottom: 1rem; padding: .75rem 1rem; } .request-changes-modal .modal-body { padding: 1rem; } }

    .agent-chip {
        display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
        border-radius: 12px; border: 1px solid var(--dash-border); padding: 0.6rem 0.9rem;
        text-decoration: none; color: var(--dash-ink); font-weight: 700; font-size: 0.85rem;
        transition: box-shadow 0.15s ease, transform 0.15s ease;
    }
    .agent-chip:hover { box-shadow: 0 6px 16px rgba(28,35,64,0.08); transform: translateY(-1px); color: var(--dash-ink); }

    .dash-card h6 { justify-content: flex-start; }
    .section-edit-btn { margin-left: auto; color: var(--dash-muted); background: none; border: none; font-size: 0.8rem; padding: 0.2rem 0.4rem; }
    .section-edit-btn:hover { color: var(--dash-teal); }
    .section-edit.d-none { display: none; }
    .section-edit .form-label { font-size: 0.72rem; color: var(--dash-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }

    .mini-table { width: 100%; font-size: 0.83rem; }
    .mini-table td, .mini-table th { padding: 0.6rem 0.4rem; vertical-align: middle; }
    .mini-table thead th { font-size: 0.66rem; text-transform: uppercase; letter-spacing: 0.02em; color: var(--dash-muted); border-bottom: 1.5px solid var(--dash-border); font-weight: 800; }
    .mini-table tbody tr { border-bottom: 1px solid #f5f6fa; }
    .mini-table tbody tr:last-child { border-bottom: none; }
</style>
@endpush

@section('content')
@php
    $statusMap = ['pending' => 'bg-warning text-dark', 'approved' => 'bg-success', 'rejected' => 'bg-danger'];
    $displayName = $portalUser->type === 'company' ? ($portalUser->company_name ?: $portalUser->name) : $portalUser->name;
    $initials = strtoupper(collect(preg_split('/\s+/', trim($displayName)))->filter()->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '?';
    $avatarFill = $portalUser->type === 'company' ? 'fill-navy' : 'fill-teal';

    $documents = [
        ['field' => 'emirates_id_document', 'label' => 'Emirates ID', 'icon' => 'fa-id-card', 'path' => $portalUser->emirates_id_document],
        ['field' => 'passport_document', 'label' => 'Passport', 'icon' => 'fa-passport', 'path' => $portalUser->passport_document],
        $portalUser->type === 'agent'
            ? ['field' => 'rera_card_document', 'label' => 'RERA Broker Card', 'icon' => 'fa-address-card', 'path' => $portalUser->rera_card_document]
            : ['field' => 'trade_license_document', 'label' => 'Trade License', 'icon' => 'fa-file-contract', 'path' => $portalUser->trade_license_document],
        $portalUser->type === 'agent'
            ? ['field' => 'trade_license_document', 'label' => 'Trade License', 'icon' => 'fa-file-contract', 'path' => $portalUser->trade_license_document]
            : null,
        $portalUser->type === 'company'
            ? ['field' => 'rera_certificate_document', 'label' => 'RERA Registration Certificate', 'icon' => 'fa-certificate', 'path' => $portalUser->rera_certificate_document]
            : null,
        $portalUser->type === 'agent'
            ? ['field' => 'rera_certificate_document', 'label' => 'RERA Registration Certificate', 'icon' => 'fa-certificate', 'path' => $portalUser->rera_certificate_document]
            : null,
    ];

    $documents = collect(array_filter($documents))->map(function ($doc) use ($portalUser) {
        $doc['verification'] = $portalUser->documentStatus($doc['field']);
        return $doc;
    })->values();
@endphp

@if($portalUser->status === 'rejected' && $portalUser->rejection_reason)
<div class="alert alert-danger d-flex align-items-center gap-2 shadow-sm mb-3" style="border-radius: 14px; font-size: 0.88rem;">
    <i class="fas fa-exclamation-circle"></i> <strong>Rejection reason:</strong> {{ $portalUser->rejection_reason }}
</div>
@endif

<div class="profile-hero">
    <div class="d-flex align-items-center gap-3">
        <div class="profile-avatar {{ $avatarFill }}">{{ $initials }}</div>
        <div>
            <div class="profile-name">{{ $displayName }}</div>
            <div class="profile-badges d-flex gap-2">
                <span class="badge bg-light text-dark border-0">{{ ucfirst($portalUser->type) }}</span>
                <span class="badge {{ $statusMap[$portalUser->status] ?? 'bg-secondary' }}">{{ ucfirst($portalUser->status) }}</span>
                <span class="badge bg-light text-dark border">KYC: {{ !$portalUser->kyc_user_submitted_at && $portalUser->status !== 'approved' ? 'Awaiting user submission' : ucfirst(str_replace('_', ' ', $portalUser->kyc_review_status)) }}</span>
            </div>
            <div class="profile-meta">
                <span><i class="fas fa-envelope"></i> {{ $portalUser->email }}</span>
                <span><i class="fas fa-phone"></i> {{ $portalUser->phone ?: '-' }}</span>
                <span><i class="fas fa-calendar"></i> Joined {{ $portalUser->created_at->format('d M Y') }}</span>
            </div>
        </div>
    </div>
    @can('portal-accounts.edit')
    <div class="profile-actions d-flex gap-2">
        @if($portalUser->status !== 'approved' && $portalUser->kyc_review_status === 'submitted' && $portalUser->kyc_user_submitted_at)
        <button type="button" class="btn btn-approve approve-item" data-id="{{ $portalUser->id }}"><i class="fas fa-check me-1"></i> Approve</button>
        @endif
        @if($portalUser->status !== 'rejected')
        <button type="button" class="btn btn-reject reject-item" data-id="{{ $portalUser->id }}"><i class="fas fa-ban me-1"></i> Reject</button>
        @endif
        <button type="button" class="btn btn-reject trigger-reset-password" data-id="{{ $portalUser->id }}" data-name="{{ $displayName ?? $portalUser->name }}"><i class="fas fa-key me-1"></i> Reset Password</button>
    </div>
    @endcan
</div>

@if($portalUser->kyc_review_status === 'changes_requested' && $portalUser->kyc_review_note)
<div class="alert alert-warning">Requested KYC changes: {{ $portalUser->kyc_review_note }}</div>
@endif
@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

@can('portal-accounts.edit')
<div class="modal fade" id="rejectReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content reject-modal-content">
            <div class="modal-body text-center p-4 pb-2">
                <div class="reject-modal-icon"><i class="fas fa-ban"></i></div>
                <h5 class="fw-bold mb-2">Reject this account?</h5>
                <p class="text-muted mb-3" style="font-size: 0.85rem;">
                    Optionally add a reason — it will be included in the email sent to
                    <strong>{{ $displayName ?? $portalUser->name }}</strong>.
                </p>
                <textarea id="rejectReasonInput" class="form-control" rows="3" placeholder="Reason for rejecting (optional)"></textarea>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4 pt-3">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" id="rejectReasonConfirmBtn">Reject Account</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="docFlagModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content doc-flag-modal-content">
            <div class="modal-body text-center p-4 pb-2">
                <div class="reject-modal-icon" style="background: rgba(224,142,11,0.12); color: var(--dash-amber);"><i class="fas fa-flag"></i></div>
                <h5 class="fw-bold mb-2">Flag this document?</h5>
                <p class="text-muted mb-3" style="font-size: 0.85rem;">Explain what's wrong so they know what to re-upload.</p>
                <textarea id="docFlagNoteInput" class="form-control" rows="3" placeholder="e.g. Image is blurry, please re-upload"></textarea>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4 pt-3">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning px-4" id="docFlagConfirmBtn">Flag Document</button>
            </div>
        </div>
    </div>
</div>

@endcan

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-teal"><i class="fas fa-building"></i></span>
            <div><div class="stat-mini-value">{{ $portalUser->properties_count }}</div><div class="stat-mini-label">Properties</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-amber"><i class="fas fa-address-book"></i></span>
            <div><div class="stat-mini-value">{{ $portalUser->leads_count }}</div><div class="stat-mini-label">Leads</div></div>
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
            <div><div class="stat-mini-value">{{ collect($documents)->filter(fn($d) => $d['path'])->count() }}/{{ count($documents) }}</div><div class="stat-mini-label">Docs Submitted</div></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8 d-flex flex-column gap-3">
        <div class="dash-card">
            <h6><i class="fas fa-id-badge"></i> Identity
                @can('portal-accounts.edit')
                <button type="button" class="section-edit-btn section-edit-toggle" data-section="identity" title="Edit"><i class="fas fa-pen"></i></button>
                @endcan
            </h6>
            <div class="section-view" data-section="identity">
                <div class="row">
                    <div class="col-md-4 info-row"><div class="info-label">Full Name</div><div class="info-value">{{ $portalUser->name }}</div></div>
                    @if($portalUser->type === 'company')
                    <div class="col-md-4 info-row"><div class="info-label">Company Name</div><div class="info-value">{{ $portalUser->company_name ?: '-' }}</div></div>
                    @endif
                    <div class="col-md-4 info-row"><div class="info-label">Email</div><div class="info-value">{{ $portalUser->email }}</div></div>
                    <div class="col-md-4 info-row"><div class="info-label">Phone</div><div class="info-value">{{ $portalUser->phone ?: '-' }}</div></div>
                    <div class="col-md-4 info-row"><div class="info-label">Nationality</div><div class="info-value">{{ $portalUser->nationality ?: '-' }}</div></div>
                    <div class="col-md-4 info-row"><div class="info-label">Emirates ID No.</div><div class="info-value">{{ $portalUser->emirates_id_no ?: '-' }}</div></div>
                    <div class="col-md-4 info-row"><div class="info-label">Passport No.</div><div class="info-value">{{ $portalUser->passport_no ?: '-' }}</div></div>
                    <div class="col-md-4 info-row"><div class="info-label">Passport Expiry</div><div class="info-value">{{ $portalUser->passport_expiry?->format('d M Y') ?: '-' }}</div></div>
                </div>
            </div>
            @can('portal-accounts.edit')
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
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control form-control-sm" value="{{ $portalUser->email }}">
                    </div>
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
            @endcan
        </div>

        @if($portalUser->type === 'agent')
        <div class="dash-card">
            <h6><i class="fas fa-user-tie"></i> RERA Broker Details
                @can('portal-accounts.edit')
                <button type="button" class="section-edit-btn section-edit-toggle" data-section="agent" title="Edit"><i class="fas fa-pen"></i></button>
                @endcan
            </h6>
            <div class="section-view" data-section="agent">
                <div class="row">
                    <div class="col-md-4 info-row"><div class="info-label">BRN (Broker Registration No.)</div><div class="info-value">{{ $portalUser->brn_number ?: '-' }}</div></div>
                    <div class="col-md-8 info-row">
                        <div class="info-label">Affiliated Brokerage</div>
                        <div class="info-value">
                            @if($portalUser->company)
                                <a href="{{ route('cms.portal-accounts.show', ['id' => $portalUser->company->id, 'type' => 'company']) }}">{{ $portalUser->company->company_name ?: $portalUser->company->name }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4 info-row"><div class="info-label">Trade License No.</div><div class="info-value">{{ $portalUser->trade_license_no ?: '-' }}</div></div>
                    <div class="col-md-4 info-row"><div class="info-label">Trade License Expiry</div><div class="info-value">{{ $portalUser->trade_license_expiry?->format('d M Y') ?: '-' }}</div></div>
                    <div class="col-md-4 info-row"><div class="info-label">TRN (VAT)</div><div class="info-value">{{ $portalUser->trn_number ?: '-' }}</div></div>
                    <div class="col-md-4 info-row"><div class="info-label">TRN Expiry</div><div class="info-value">{{ $portalUser->trn_expiry?->format('d M Y') ?: '-' }}</div></div>
                </div>
            </div>
            @can('portal-accounts.edit')
            <form class="section-edit d-none section-form" data-section="agent">
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
            @endcan
        </div>
        @else
        <div class="dash-card">
            <h6><i class="fas fa-building"></i> Company / RERA Office Details
                @can('portal-accounts.edit')
                <button type="button" class="section-edit-btn section-edit-toggle" data-section="company" title="Edit"><i class="fas fa-pen"></i></button>
                @endcan
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
            @can('portal-accounts.edit')
            <form class="section-edit d-none section-form" data-section="company">
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
            @endcan
        </div>
        @endif

        <div class="dash-card">
            <h6><i class="fas fa-globe"></i> Public Profile
                @can('portal-accounts.edit')
                <button type="button" class="section-edit-btn section-edit-toggle" data-section="about" title="Edit"><i class="fas fa-pen"></i></button>
                @endcan
            </h6>
            <p class="text-muted mb-3" style="font-size: 0.8rem;">For the future public Agent/Agency listing page. The client writes their bio once in their own language; it's auto-translated into the site's other languages and can be corrected here.</p>
            <div class="section-view" data-section="about">
                <div class="row">
                    <div class="col-md-12 info-row"><div class="info-label">Years of Experience</div><div class="info-value">{{ $portalUser->years_of_experience ?? '-' }}</div></div>
                    @foreach($languages as $lang)
                    <div class="col-md-12 info-row">
                        <div class="info-label">Bio ({{ $lang->name }})</div>
                        <div class="info-value" style="font-weight: 500; white-space: pre-line;">{{ $portalUser->getTranslation('bio', $lang->code) ?: '-' }}</div>
                    </div>
                    @endforeach
                    @if($portalUser->type === 'company')
                    <div class="col-md-4 info-row"><div class="info-label">Established</div><div class="info-value">{{ $portalUser->founding_year ?: '-' }}</div></div>
                    @endif
                    <div class="col-md-4 info-row"><div class="info-label">Website</div><div class="info-value">{{ $portalUser->website ?: '-' }}</div></div>
                    <div class="col-md-12 info-row"><div class="info-label">Preferred / Service Areas</div><div class="info-value">{{ $portalUser->preferred_areas ? implode(', ', $portalUser->preferred_areas) : '-' }}</div></div>
                    <div class="col-md-12 info-row">
                        <div class="info-label">Badges</div>
                        <div class="info-value">
                            @forelse($portalUser->badges ?? [] as $badge)
                                <span class="badge bg-primary me-1">{{ $badge }}</span>
                            @empty
                                -
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            @can('portal-accounts.edit')
            <form class="section-edit d-none section-form" data-section="about">
                <div class="section-form-error text-danger small d-none mb-2"></div>
                <div class="row g-3">
                    @foreach($languages as $lang)
                    <div class="col-md-12">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Bio ({{ $lang->name }})</span>
                            @if(!$lang->is_default)
                            <button type="button" class="btn btn-link btn-sm p-0 retranslate-bio-btn" data-locale="{{ $lang->code }}" style="font-size: 0.72rem;"><i class="fas fa-language me-1"></i>Re-translate from {{ $languages->firstWhere('is_default', true)?->name }}</button>
                            @endif
                        </label>
                        <textarea name="bio[{{ $lang->code }}]" class="form-control form-control-sm bio-input-{{ $lang->code }}" rows="3" maxlength="2000">{{ $portalUser->getTranslation('bio', $lang->code) }}</textarea>
                    </div>
                    @endforeach
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
                    <div class="col-md-12">
                        <label class="form-label d-block">Recognition Badges</label>
                        @foreach(\App\Models\PortalUser::BADGE_OPTIONS as $badgeOption)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="badges[]" value="{{ $badgeOption }}" id="badge-{{ \Illuminate\Support\Str::slug($badgeOption) }}" {{ in_array($badgeOption, $portalUser->badges ?? []) ? 'checked' : '' }}>
                            <label class="form-check-label" for="badge-{{ \Illuminate\Support\Str::slug($badgeOption) }}">{{ $badgeOption }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-sm btn-success">Save</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary section-edit-cancel">Cancel</button>
                </div>
            </form>
            @endcan
        </div>

        <div class="dash-card">
            <h6><i class="fas fa-folder-open"></i> Submitted Documents</h6>
            <div class="row g-2">
                @foreach($documents as $doc)
                @php $tone = ['verified' => 'tone-verified', 'rejected' => 'tone-rejected'][$doc['verification']['status']] ?? 'tone-pending'; @endphp
                <div class="col-md-6">
                    <div class="doc-card {{ $doc['path'] ? 'is-submitted' : '' }}" data-field="{{ $doc['field'] }}">
                        <span class="doc-icon {{ $doc['path'] ? 'fill-teal' : 'fill-slate' }}"><i class="fas {{ $doc['icon'] }}"></i></span>
                        <div class="flex-grow-1">
                            <div class="doc-name">{{ $doc['label'] }}</div>
                            @if($doc['path'])
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
                                <a href="{{ route('cms.portal-accounts.document', [$portalUser, $doc['field']]) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                @can('portal-accounts.edit')
                                @if($doc['verification']['status'] !== 'verified')
                                <button type="button" class="btn btn-sm btn-outline-success doc-verify-btn" title="Mark verified"><i class="fas fa-check"></i></button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-warning doc-flag-btn" title="Flag issue"><i class="fas fa-flag"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger doc-remove-btn" title="Remove"><i class="fas fa-trash"></i></button>
                                @endcan
                            @else
                                @can('portal-accounts.edit')
                                <label class="btn btn-sm btn-outline-primary mb-0 doc-upload-label">
                                    Add <input type="file" class="d-none doc-upload-input" accept=".jpg,.jpeg,.png,.pdf">
                                </label>
                                @endcan
                            @endif
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        @can('portal-accounts.edit')
        @if($portalUser->status !== 'approved' && in_array($portalUser->kyc_review_status, ['submitted', 'changes_requested'], true) && ($portalUser->kyc_review_status === 'changes_requested' || $portalUser->kyc_user_submitted_at))
        <button type="button" class="request-changes-fab" data-bs-toggle="modal" data-bs-target="#requestInfoModal"><i class="fas fa-comment-medical"></i> Request Changes</button>
        <div class="modal fade request-changes-modal" id="requestInfoModal" tabindex="-1" aria-labelledby="requestInfoModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('cms.portal-accounts.request-info', $portalUser->id) }}">
                        @csrf
                        <div class="modal-header">
                            <div><h5 class="modal-title" id="requestInfoModalTitle"><i class="fas fa-clipboard-check me-2"></i>Request Profile Updates</h5><div class="small opacity-75 mt-1">Select what {{ $displayName }} needs to update.</div></div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-3">The selected items and your note will be sent by email and portal notification.</p>
                            @php
                                $profileRequestOptions = [
                                    'identity' => 'Identity and contact details',
                                    'registration_details' => $portalUser->type === 'agent' ? 'BRN and brokerage details' : 'Company and office details',
                                    'tax_details' => 'Tax registration details (TRN)',
                                    'public_profile' => 'Public profile, bio, and service areas',
                                ];
                            @endphp
                            <div class="row g-2 mb-3">
                                @foreach($profileRequestOptions as $value => $label)
                                <div class="col-md-6">
                                    <label class="request-option" for="request-item-{{ $value }}">
                                        <input class="form-check-input" type="checkbox" name="request_items[]" value="{{ $value }}" id="request-item-{{ $value }}" {{ in_array($value, (array) old('request_items', []), true) ? 'checked' : '' }}>
                                        <span class="small fw-semibold">{{ $label }}</span>
                                    </label>
                                </div>
                                @endforeach
                                @foreach($documents as $doc)
                                <div class="col-md-6">
                                    <label class="request-option" for="request-item-{{ $doc['field'] }}">
                                        <input class="form-check-input" type="checkbox" name="request_items[]" value="{{ $doc['field'] }}" id="request-item-{{ $doc['field'] }}" {{ in_array($doc['field'], (array) old('request_items', []), true) ? 'checked' : '' }}>
                                        <span class="small fw-semibold">{{ $doc['label'] }} document</span>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                            @error('request_items')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            @error('request_items.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            <label class="form-label small fw-semibold" for="request-info-note">Additional instructions <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea id="request-info-note" name="message" class="form-control @error('message') is-invalid @enderror" rows="3" maxlength="1500" placeholder="Add specific guidance, such as what needs correcting.">{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Send Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @if($errors->has('request_items') || $errors->has('request_items.*') || $errors->has('message'))
        <script>document.addEventListener('DOMContentLoaded', function () { new bootstrap.Modal(document.getElementById('requestInfoModal')).show(); });</script>
        @endif
        @endif
        @endcan

        <div class="dash-card">
            <h6><i class="fas fa-receipt"></i> Payment History
                @if($portalUser->plan)
                <a href="{{ route('cms.plans.show', $portalUser->plan_id) }}" class="ms-auto" style="font-size: 0.72rem; font-weight: 700; text-transform: none; letter-spacing: normal; color: var(--dash-teal); text-decoration: none;">View "{{ $portalUser->plan->getTranslation('name') }}" plan &rarr;</a>
                @endif
            </h6>
            @if($payments->isEmpty())
                <div class="text-muted text-center py-3" style="font-size: 0.85rem;">No payments recorded yet. Mark them "Paid" from the Agents & Companies list to start a history.</div>
            @else
                <div class="table-responsive">
                    <table class="mini-table">
                        <thead><tr><th>Period</th><th>Plan</th><th>Paid On</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            @foreach($payments as $payment)
                            <tr>
                                <td class="fw-semibold">{{ $payment->period_label }}</td>
                                <td>{{ $payment->plan_name }}</td>
                                <td>{{ $payment->paid_at->format('d M Y') }}</td>
                                <td class="text-end fw-bold">AED {{ number_format($payment->amount) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end fw-bold text-muted" style="font-size: 0.78rem;">Total Paid ({{ $payments->total() }} payment{{ $payments->total() === 1 ? '' : 's' }})</td>
                                <td class="text-end fw-bold">AED {{ number_format($totalPaid) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if($payments->hasPages())
                <div class="mt-3">{{ $payments->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
                @endif
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        @if($portalUser->type === 'company')
        <div class="dash-card">
            <h6><i class="fas fa-people-group"></i> Affiliated Agents ({{ $portalUser->agents_count }})</h6>
            <div class="d-flex flex-column gap-2">
                @forelse($agents as $agent)
                <a href="{{ route('cms.portal-accounts.show', ['id' => $agent->id, 'type' => 'agent']) }}" class="agent-chip">
                    {{ $agent->name }}
                    <span class="badge {{ $statusMap[$agent->status] ?? 'bg-secondary' }}">{{ ucfirst($agent->status) }}</span>
                </a>
                @empty
                <div class="text-muted text-center py-3" style="font-size: 0.85rem;">No agents linked to this company yet.</div>
                @endforelse
            </div>
            @if($agents->hasPages())
            <div class="mt-3">{{ $agents->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
        @else
        <div class="dash-card">
            <h6><i class="fas fa-building"></i> Affiliated Company</h6>
            @if($portalUser->company)
                @php
                    $companyLabel = $portalUser->company->company_name ?: $portalUser->company->name;
                    $companyInitials = strtoupper(collect(preg_split('/\s+/', trim($companyLabel)))->filter()->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '?';
                @endphp
                <a href="{{ route('cms.portal-accounts.show', ['id' => $portalUser->company->id, 'type' => 'company']) }}" class="agent-chip" style="padding: 0.85rem 1rem;">
                    <span class="d-flex align-items-center gap-2">
                        <span class="profile-avatar fill-navy" style="width: 36px; height: 36px; font-size: 0.8rem; border: none;">{{ $companyInitials }}</span>
                        {{ $companyLabel }}
                    </span>
                    <span class="badge {{ $statusMap[$portalUser->company->status] ?? 'bg-secondary' }}">{{ ucfirst($portalUser->company->status) }}</span>
                </a>
            @else
                <div class="text-muted text-center py-3" style="font-size: 0.85rem;">Not yet affiliated with any company.</div>
            @endif
        </div>
        @endif
    </div>
</div>

@include('portal-accounts.partials.reset-password-modal')
@endsection

@push('scripts')
<script>
    (function () {
        const base = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/portal-accounts/";

        document.querySelectorAll('.approve-item').forEach(btn => btn.addEventListener('click', function () {
            const id = this.dataset.id;
            fetch(base + id + '/approve', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .then(() => window.location.reload());
        }));

        // Custom reject-reason modal — replaces the native browser prompt() popup
        const rejectModalEl = document.getElementById('rejectReasonModal');
        const rejectModal = rejectModalEl ? new bootstrap.Modal(rejectModalEl) : null;
        let pendingRejectId = null;

        document.querySelectorAll('.reject-item').forEach(btn => btn.addEventListener('click', function () {
            pendingRejectId = this.dataset.id;
            document.getElementById('rejectReasonInput').value = '';
            rejectModal.show();
        }));

        document.getElementById('rejectReasonConfirmBtn')?.addEventListener('click', function () {
            if (!pendingRejectId) return;
            const reason = document.getElementById('rejectReasonInput').value;
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Rejecting...';

            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('reason', reason);
            fetch(base + pendingRejectId + '/reject', { method: 'POST', body: formData })
                .then(() => window.location.reload())
                .catch(() => { btn.disabled = false; btn.innerHTML = originalHtml; });
        });

        const portalUserId = {{ $portalUser->id }};

        // Re-translate one language's bio from the default-language source, without saving the form first
        document.querySelectorAll('.retranslate-bio-btn').forEach(btn => btn.addEventListener('click', function () {
            const locale = this.dataset.locale;
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:0.7rem;height:0.7rem;"></span>';
            fetch(base + portalUserId + '/retranslate-bio', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                body: JSON.stringify({ locale: locale }),
            })
                .then(async res => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) throw new Error(data.message || 'Could not translate.');
                    document.querySelector('.bio-input-' + locale).value = data.text;
                })
                .catch(err => alert(err.message))
                .finally(() => { this.disabled = false; this.innerHTML = originalHtml; });
        }));

        // Per-section inline editing (Identity, RERA Broker Details, Company / RERA Office Details)
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
            fetch(base + portalUserId + '/update', {
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

        // Submitted documents — remove or upload directly from the profile page
        document.querySelectorAll('.doc-remove-btn').forEach(btn => btn.addEventListener('click', function () {
            const field = this.closest('.doc-card').dataset.field;
            if (!confirm('Remove this document?')) return;
            fetch(base + portalUserId + '/documents/' + field, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            }).then(() => window.location.reload());
        }));

        // Per-document verification — independent of the overall account status
        document.querySelectorAll('.doc-verify-btn').forEach(btn => btn.addEventListener('click', function () {
            const field = this.closest('.doc-card').dataset.field;
            fetch(base + portalUserId + '/documents/' + field + '/status', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: 'verified' }),
            }).then(() => window.location.reload());
        }));

        const docFlagModalEl = document.getElementById('docFlagModal');
        const docFlagModal = docFlagModalEl ? new bootstrap.Modal(docFlagModalEl) : null;
        let pendingFlagField = null;

        document.querySelectorAll('.doc-flag-btn').forEach(btn => btn.addEventListener('click', function () {
            pendingFlagField = this.closest('.doc-card').dataset.field;
            document.getElementById('docFlagNoteInput').value = '';
            docFlagModal.show();
        }));

        document.getElementById('docFlagConfirmBtn')?.addEventListener('click', function () {
            if (!pendingFlagField) return;
            const note = document.getElementById('docFlagNoteInput').value;
            fetch(base + portalUserId + '/documents/' + pendingFlagField + '/status', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: 'rejected', note: note }),
            }).then(() => window.location.reload());
        });

        document.querySelectorAll('.doc-upload-input').forEach(input => input.addEventListener('change', function () {
            if (!this.files.length) return;
            const field = this.closest('.doc-card').dataset.field;
            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('document', this.files[0]);
            fetch(base + portalUserId + '/documents/' + field, { method: 'POST', body: formData })
                .then(async res => {
                    if (!res.ok) {
                        const data = await res.json().catch(() => ({}));
                        throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not upload document.');
                    }
                    window.location.reload();
                })
                .catch(err => alert(err.message));
        }));

    })();
</script>
@endpush
