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
        /* Stays in view just under the sticky portal header; compacts once stuck (like the admin view). */
        position: sticky; top: calc(var(--portal-topbar-h, 80px) + 0.75rem); z-index: 900;
        transition: padding 0.2s ease, box-shadow 0.2s ease;
    }
    .profile-hero::before {
        content: ''; position: absolute; left: -2rem; right: -2rem; bottom: 100%;
        height: calc(0.75rem + 1px); background: var(--portal-bg, #f4f5fb); pointer-events: none;
    }
    .profile-hero.is-stuck { padding: 0.7rem 1.5rem; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18); }
    .profile-hero.is-stuck .profile-avatar { width: 40px; height: 40px; font-size: 0.95rem; }
    .profile-hero.is-stuck .profile-meta { display: none !important; }
    .profile-hero.is-stuck .profile-name { font-size: 1.1rem; margin-bottom: 0.2rem; }

    /* KYC progress: one card for "where am I" instead of stacked warnings. */
    .kyc-progress { border-radius: 16px; border: 1px solid var(--dash-border); background: #fff; box-shadow: 0 4px 14px rgba(28,35,64,0.05); padding: 1.1rem 1.4rem; margin-bottom: 1.25rem; }
    .kyc-progress__head { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .kyc-progress__icon { width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
    .kyc-progress__icon.tone-amber { background: rgba(245,158,11,0.13); color: #d97706; }
    .kyc-progress__icon.tone-blue { background: rgba(4,161,204,0.12); color: #0487ab; }
    .kyc-progress__icon.tone-red { background: rgba(220,38,38,0.1); color: #dc2626; }
    .kyc-progress__title { font-weight: 800; font-size: 0.95rem; color: var(--dash-ink); }
    .kyc-progress__text { font-size: 0.83rem; color: var(--dash-muted); }
    .kyc-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.5rem; }
    .kyc-step { position: relative; padding-top: 0.75rem; }
    .kyc-step::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px; border-radius: 5px; background: #eceef4; }
    .kyc-step.is-done::before { background: var(--dash-green, #16a34a); }
    .kyc-step.is-current::before { background: linear-gradient(90deg, var(--dash-teal, #04a1cc), var(--dash-navy, #264373)); }
    .kyc-step.is-problem::before { background: #dc2626; }
    .kyc-step__label { font-size: 0.78rem; font-weight: 700; color: var(--dash-ink); display: flex; align-items: center; gap: 0.35rem; }
    .kyc-step:not(.is-done):not(.is-current):not(.is-problem) .kyc-step__label { color: #9aa0b4; }
    .kyc-step__sub { font-size: 0.7rem; color: var(--dash-muted); }
    .kyc-note { margin-top: 1rem; padding: 0.75rem 0.9rem; border-radius: 12px; font-size: 0.84rem; white-space: pre-line; }
    .kyc-note.tone-info { background: rgba(4,161,204,0.07); border: 1px solid rgba(4,161,204,0.2); color: #0b4f63; }
    .kyc-note.tone-red { background: rgba(220,38,38,0.06); border: 1px solid rgba(220,38,38,0.2); color: #8f1d1d; }
    @media (max-width: 575.98px) { .kyc-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); row-gap: 0.9rem; } }
    .profile-avatar-wrap { position: relative; flex-shrink: 0; }
    .profile-avatar--upload { position: relative; cursor: pointer; overflow: visible; margin: 0; }
    .profile-avatar--upload img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
    .profile-avatar--upload img.is-logo { object-fit: contain; background: #fff; padding: 6px; }
    .profile-avatar__cam { position: absolute; right: -2px; bottom: -2px; width: 24px; height: 24px; border-radius: 50%; background: #fff; color: var(--dash-navy); display: flex; align-items: center; justify-content: center; font-size: 0.65rem; box-shadow: 0 3px 8px rgba(0,0,0,0.25); }
    .profile-avatar__spin { position: absolute; inset: 0; border-radius: 50%; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; color: #fff; }
    .profile-avatar__remove { position: absolute; top: -4px; right: -4px; width: 20px; height: 20px; border-radius: 50%; border: 0; background: #dc2626; color: #fff; font-size: 0.6rem; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .15s; }
    .profile-avatar-wrap:hover .profile-avatar__remove, .profile-avatar__remove:focus { opacity: 1; }
    .profile-hero.is-stuck .profile-avatar__cam, .profile-hero.is-stuck .profile-avatar__remove { display: none !important; }
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

    /* Affiliated Brokerage: registered agencies as suggestions, free text allowed. */
    .brokerage-combo { position: relative; }
    .brokerage-combo__list { position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 30; max-height: 240px; overflow-y: auto; background: #fff; border: 1px solid var(--dash-border); border-radius: 10px; box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14); }
    .brokerage-combo__item { display: flex; align-items: center; gap: 0.55rem; width: 100%; padding: 0.55rem 0.8rem; border: 0; border-bottom: 1px solid #f2f3f7; background: none; text-align: left; font-size: 0.85rem; color: var(--dash-ink); }
    .brokerage-combo__item:hover, .brokerage-combo__item.is-active { background: var(--dash-bg-soft, #f7f8fc); }
    .brokerage-combo__item i { color: var(--dash-teal); width: 16px; }
    .brokerage-combo__item--typed { color: var(--dash-muted); }
    .brokerage-combo__status { padding: 0.6rem 0.8rem; font-size: 0.78rem; color: var(--dash-muted); text-align: center; }

    .email-change-step .form-control { max-width: 280px; }
    .email-change-step .alert-error-box { font-weight: 600; }
    .profile-email-change { margin-left: 0.35rem; padding: 0.1rem 0.55rem; border: 1px solid rgba(255, 255, 255, 0.45); border-radius: 50px; background: rgba(255, 255, 255, 0.12); color: #fff; font-size: 0.72rem; font-weight: 700; line-height: 1.5; cursor: pointer; transition: background 0.15s ease; }
    .profile-email-change:hover { background: rgba(255, 255, 255, 0.25); }
    .profile-email-change i { font-size: 0.65rem; margin-right: 0.15rem; }

    /* Sections: menu on the left, one section at a time on the right */
    .profile-layout { display: grid; grid-template-columns: 290px minmax(0, 1fr); gap: 1.25rem; align-items: start; }
    .profile-nav { position: sticky; top: calc(var(--portal-topbar-h, 80px) + 1rem); display: flex; flex-direction: column; gap: 0.2rem; padding: 0.6rem; background: var(--dash-card, #fff); border: 1px solid var(--dash-border); border-radius: 16px; }
    .profile-nav-link { display: flex; align-items: center; gap: 0.65rem; width: 100%; padding: 0.65rem 0.75rem; border: 0; border-radius: 11px; background: transparent; color: var(--dash-ink); font-weight: 600; font-size: 0.88rem; text-align: left; transition: background 0.15s ease, color 0.15s ease; }
    .profile-nav-link i { width: 18px; text-align: center; color: var(--dash-muted); }
    .profile-nav-link:hover { background: var(--portal-bg, #f4f6fb); }
    .profile-nav-link.active { background: var(--portal-primary); color: #fff; }
    .profile-nav-link.active i { color: #fff; }
    .pf-nav-state { margin-left: auto; padding: 0.05rem 0.5rem; border-radius: 50px; font-size: 0.7rem; font-weight: 700; color: var(--dash-muted); white-space: nowrap; }
    .pf-nav-state.is-todo { background: #fef3c7; color: #92400e; }
    .pf-nav-state.is-done { width: 20px; height: 20px; padding: 0; display: inline-flex; align-items: center; justify-content: center; background: #dcfce7; color: #15803d; font-size: 0.62rem; }
    .profile-nav-link.active .pf-nav-state { background: rgba(255, 255, 255, 0.2); color: #fff; }

    /* ── Cover ── */
    .pf-cover { position: relative; margin-bottom: 1.25rem; border-radius: 20px; overflow: hidden; background: #fff; border: 1px solid var(--dash-border); box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06); }
    .pf-cover-banner { position: relative; height: 64px; background:
        radial-gradient(circle at 15% 120%, rgba(201, 40, 68, 0.55), transparent 55%),
        radial-gradient(circle at 85% -20%, rgba(14, 116, 144, 0.7), transparent 55%),
        linear-gradient(120deg, #1d2f57 0%, #203f68 55%, #0f5f86 100%); }
    .pf-cover-banner::after { content: ""; position: absolute; inset: 0; opacity: 0.12; background-image: radial-gradient(rgba(255, 255, 255, 0.9) 1px, transparent 1px); background-size: 18px 18px; }
    .pf-cover-cta { position: absolute; z-index: 1; top: 1rem; right: 1rem; border: 0; border-radius: 50px; padding: 0.5rem 1rem; background: #fff; color: #1d2f57; font-weight: 700; font-size: 0.85rem; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18); }
    .pf-cover-body { display: flex; align-items: flex-start; gap: 1rem; padding: 0 1.5rem 0.85rem; margin-top: -34px; position: relative; }
    .pf-cover-body > .pf-cover-main { margin-top: 42px; }
    .pf-cover-body > .pf-ring { margin-top: 12px; }
    .pf-cover-avatar .profile-avatar { width: 76px; height: 76px; font-size: 1.4rem; border: 4px solid #fff; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18); }
    .pf-cover-main { flex: 1; min-width: 0; }
    .pf-cover-name { display: flex; flex-wrap: wrap; align-items: center; gap: 0.45rem; margin-bottom: 0.25rem; }
    .pf-cover-name h1 { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--dash-ink); }
    .pf-tag { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.65rem; border-radius: 50px; background: var(--portal-bg, #f1f4f9); color: var(--dash-ink); font-size: 0.72rem; font-weight: 700; }
    .pf-status i { font-size: 0.45rem; }
    .pf-status.is-approved { background: #dcfce7; color: #166534; }
    .pf-status.is-pending { background: #fef3c7; color: #92400e; }
    .pf-status.is-rejected { background: #fee2e2; color: #991b1b; }
    .pf-cover-meta { display: flex; flex-wrap: wrap; gap: 0.4rem 1.25rem; color: var(--dash-muted); font-size: 0.85rem; }
    .pf-cover-meta i { margin-right: 0.4rem; color: var(--portal-primary); opacity: 0.75; }
    .pf-login-email { display: inline-flex; align-items: center; color: var(--dash-ink); font-weight: 600; }
    .pf-link-btn { margin-left: 0.5rem; padding: 0; border: 0; background: none; color: var(--portal-primary); font-weight: 700; font-size: 0.8rem; text-decoration: underline; text-underline-offset: 3px; }
    .pf-ring { position: relative; flex: 0 0 auto; width: 66px; height: 66px; padding: 3px; border-radius: 50%; background: #fff; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12); }
    .pf-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
    .pf-ring circle { fill: none; stroke-width: 3.2; }
    .pf-ring-bg { stroke: var(--portal-bg, #eef1f6); }
    .pf-ring-val { stroke: var(--portal-primary); stroke-linecap: round; transition: stroke-dasharray 0.6s ease; }
    .pf-ring-text { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1.05; }
    .pf-ring-text strong { font-size: 0.92rem; font-weight: 800; color: var(--dash-ink); }
    .pf-ring-text span { font-size: 0.5rem; font-weight: 700; color: var(--dash-muted); text-transform: uppercase; letter-spacing: 0.04em; }
    .pf-cover-stats { display: grid; grid-template-columns: repeat(4, 1fr); border-top: 1px solid var(--dash-border); }
    .pf-cover-stats > div { padding: 0.6rem 1.5rem; display: flex; align-items: baseline; gap: 0.5rem; }
    .pf-cover-stats > div + div { border-left: 1px solid var(--dash-border); }
    .pf-cover-stats strong { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1rem; font-weight: 800; color: var(--dash-ink); }
    .pf-cover-stats strong.is-todo { color: #b45309; }
    .pf-cover-stats strong.is-done { color: #15803d; }
    .pf-cover-stats span { font-size: 0.72rem; font-weight: 700; color: var(--dash-muted); text-transform: uppercase; letter-spacing: 0.03em; }

    /* ── Section card: label → value list ── */
    .pf-card { padding: 1.5rem 1.75rem !important; border-radius: 18px !important; }
    .pf-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 0.5rem; }
    .pf-card-title { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--dash-ink); }
    .pf-card-hint { margin: 0.25rem 0 0; font-size: 0.82rem; color: var(--dash-muted); max-width: 640px; }
    .pf-card-tools { display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0; }
    .pf-progress { display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; font-weight: 700; color: var(--dash-muted); }
    .pf-progress-bar { width: 64px; height: 6px; border-radius: 50px; background: var(--portal-bg, #eef1f6); overflow: hidden; }
    .pf-progress-bar i { display: block; height: 100%; border-radius: inherit; background: var(--portal-primary); }
    .pf-edit-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 1rem; border-radius: 50px; border: 1.5px solid var(--portal-primary); background: #fff; color: var(--portal-primary); font-weight: 700; font-size: 0.82rem; transition: background 0.15s ease, color 0.15s ease; }
    .pf-edit-btn:hover { background: var(--portal-primary); color: #fff; }
    .pf-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 2.5rem; margin: 0; }
    .pf-item { display: grid; grid-template-columns: 170px minmax(0, 1fr); gap: 1rem; align-items: baseline; padding: 0.95rem 0; border-bottom: 1px solid var(--dash-border); }
    .pf-item.is-wide { grid-column: 1 / -1; }
    .pf-item dt { margin: 0; font-size: 0.82rem; font-weight: 600; color: var(--dash-muted); }
    .pf-item dd { margin: 0; font-size: 0.92rem; font-weight: 600; color: var(--dash-ink); overflow-wrap: anywhere; }
    .pf-item dd.is-text { font-weight: 500; white-space: pre-line; line-height: 1.55; }
    .pf-item dd a { color: var(--portal-primary); text-decoration: none; }
    .pf-chip { display: inline-block; margin: 0 0.3rem 0.3rem 0; padding: 0.15rem 0.6rem; border-radius: 50px; background: rgba(79, 70, 229, 0.08); color: var(--portal-primary); font-size: 0.78rem; font-weight: 700; }
    .pf-badge { display: inline-block; margin-left: 0.5rem; padding: 0.1rem 0.5rem; border-radius: 50px; font-size: 0.7rem; font-weight: 700; vertical-align: 1px; }
    .pf-badge.is-ok { background: #dcfce7; color: #166534; }
    .pf-badge.is-warn { background: #fef3c7; color: #92400e; }
    .pf-badge.is-bad { background: #fee2e2; color: #991b1b; }
    .pf-add { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0; border: 0; background: none; color: #a0a8b8; font-size: 0.85rem; font-weight: 500; font-style: italic; }
    .pf-add span { font-style: normal; font-weight: 700; color: var(--portal-primary); opacity: 0; transition: opacity 0.15s ease; }
    .pf-item:hover .pf-add span, .pf-add:focus-visible span { opacity: 1; }
    .pf-empty { color: #a0a8b8; }
    .pf-note { margin-top: 0.35rem; font-size: 0.78rem; font-weight: 500; color: var(--dash-muted); }
    .pf-card .section-edit { margin-top: 1rem; }

    /* Below the section: to-do + public page preview */
    .pf-extras { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 1.25rem; margin-top: 1.25rem; }
    .pf-todo-pct { flex-shrink: 0; padding: 0.3rem 0.75rem; border-radius: 50px; background: rgba(79, 70, 229, 0.1); color: var(--portal-primary); font-weight: 800; font-size: 0.85rem; }
    .pf-todo-list { list-style: none; margin: 0.5rem 0 0; padding: 0; }
    .pf-todo-list li { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0; border-bottom: 1px solid var(--dash-border); }
    .pf-todo-list li:last-child { border-bottom: 0; }
    .pf-todo-icon { flex: 0 0 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; background: #fef3c7; color: #b45309; font-size: 0.8rem; }
    .pf-todo-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .pf-todo-text strong { font-size: 0.86rem; color: var(--dash-ink); }
    .pf-todo-text small { font-size: 0.74rem; color: var(--dash-muted); }
    .pf-todo-go { flex-shrink: 0; padding: 0.3rem 0.85rem; border-radius: 50px; border: 1.5px solid var(--portal-primary); background: #fff; color: var(--portal-primary); font-size: 0.76rem; font-weight: 700; transition: background 0.15s ease, color 0.15s ease; }
    .pf-todo-go:hover { background: var(--portal-primary); color: #fff; }
    .pf-todo-more { margin-top: 0.6rem; font-size: 0.78rem; color: var(--dash-muted); }
    .pf-todo-done { display: flex; align-items: center; gap: 0.5rem; padding: 0.9rem 1rem; border-radius: 12px; background: #dcfce7; color: #166534; font-weight: 600; font-size: 0.88rem; }
    .pf-public-card { margin-top: 0.75rem; padding: 1.1rem; border: 1px solid var(--dash-border); border-radius: 14px; background: linear-gradient(180deg, var(--portal-bg, #f6f8fc), #fff); }
    .pf-public-top { display: flex; align-items: center; gap: 0.8rem; }
    .pf-public-avatar { flex: 0 0 52px; height: 52px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; overflow: hidden; }
    .pf-public-avatar img { width: 100%; height: 100%; object-fit: cover; background: #fff; }
    .pf-public-name { font-weight: 800; color: var(--dash-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pf-public-sub { font-size: 0.8rem; color: var(--dash-muted); }
    .pf-public-bio { margin: 0.8rem 0 0.6rem; font-size: 0.84rem; line-height: 1.55; color: var(--dash-ink); }
    .pf-public-chips { display: flex; flex-wrap: wrap; }
    .pf-public-link { display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.9rem; font-weight: 700; font-size: 0.85rem; color: var(--portal-primary); text-decoration: none; }
    @media (max-width: 1199.98px) { .pf-extras { grid-template-columns: minmax(0, 1fr); } }
    .pf-card .section-edit .btn-success { padding: 0.5rem 1.4rem; border-radius: 50px; border: 0; background: var(--portal-primary); font-weight: 700; }
    .pf-card .section-edit .btn-success { display: inline-flex; align-items: center; gap: 0.45rem; min-width: 108px; justify-content: center; transition: background 0.2s ease, transform 0.15s ease; }
    .pf-card .section-edit .btn-success.is-saving { opacity: 0.85; cursor: progress; }
    .pf-card .section-edit .btn-success.is-saved { background: #16a34a; animation: pf-pop 0.35s ease; }
    .pf-spinner { width: 14px; height: 14px; border-radius: 50%; border: 2px solid rgba(255, 255, 255, 0.35); border-top-color: #fff; animation: pf-spin 0.7s linear infinite; }
    @keyframes pf-spin { to { transform: rotate(360deg); } }
    @keyframes pf-pop { 0% { transform: scale(1); } 45% { transform: scale(1.07); } 100% { transform: scale(1); } }
    @media (prefers-reduced-motion: reduce) { .pf-spinner { animation-duration: 2s; } .pf-card .section-edit .btn-success.is-saved { animation: none; } }
    .pf-card .section-edit .btn-outline-secondary { padding: 0.5rem 1.2rem; border-radius: 50px; border-color: var(--dash-border); color: var(--dash-muted); font-weight: 700; }
    .pf-card .section-edit .btn-outline-secondary:hover { background: var(--portal-bg, #f1f4f9); color: var(--dash-ink); }
    .profile-nav-link span:not(.pf-nav-state) { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .profile-panes .dash-card > h6 { display: flex; align-items: center; gap: 0.6rem; font-size: 1.1rem; text-transform: none; letter-spacing: 0; color: var(--dash-ink); padding-bottom: 0.9rem; margin-bottom: 1rem; border-bottom: 1px solid var(--dash-border); }
    .profile-panes .dash-card { padding: 1.5rem 1.75rem; border-radius: 18px; }

    @media (max-width: 991.98px) {
        .profile-layout { grid-template-columns: minmax(0, 1fr); }
        .profile-nav { position: static; flex-direction: row; overflow-x: auto; scrollbar-width: none; }
        .profile-nav::-webkit-scrollbar { display: none; }
        .profile-nav-link { flex: 0 0 auto; width: auto; white-space: nowrap; }
        .pf-list { grid-template-columns: minmax(0, 1fr); }
        .pf-item, .pf-item.is-wide { grid-template-columns: 150px minmax(0, 1fr); }
    }
    @media (max-width: 767.98px) {
        .pf-cover-body { flex-wrap: wrap; padding: 0 1.1rem 1.1rem; }
        .pf-cover-avatar .profile-avatar { width: 64px; height: 64px; }
        .pf-ring { width: 58px; height: 58px; }
        .pf-cover-stats { grid-template-columns: repeat(2, 1fr); }
        .pf-cover-stats > div:nth-child(3) { border-left: 0; }
        .pf-cover-stats > div:nth-child(n + 3) { border-top: 1px solid var(--dash-border); }
        .pf-card-head { flex-direction: column; }
        .pf-item, .pf-item.is-wide { grid-template-columns: minmax(0, 1fr); gap: 0.2rem; }
    }
</style>
@endpush

@section('content')
@php
    $u = $portalUser;
    $isAgent = $u->type === 'agent';
    $displayName = $isAgent ? $u->name : ($u->company_name ?: $u->name);
    $initials = strtoupper(collect(preg_split('/\s+/', trim($displayName)))->filter()->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '?';
    $avatarFill = $isAgent ? 'fill-teal' : 'fill-navy';
    $statusMap = ['pending' => 'is-pending', 'approved' => 'is-approved', 'rejected' => 'is-rejected'];
    $bioAr = $u->translations['bio']['ar'] ?? '';
    $spoken = \App\Http\Controllers\Portal\PortalProfileController::SPOKEN_LANGUAGES;

    // ── Profile sections (menu + panes): title, icon, hint, fields — fields as in profile/_section ──
    $profileSections = $isAgent ? [
        'public' => ['Public details', 'fa-id-card', 'How buyers see and reach you on your listings and agent page.', [
            ['name' => 'name', 'label' => 'Displayed name', 'value' => $u->name, 'col' => 12],
            ['name' => 'public_email', 'label' => 'Public email', 'type' => 'email', 'value' => $u->public_email, 'col' => 6, 'help' => 'Leave empty to show your login email.'],
            ['name' => 'phone', 'label' => 'Public number', 'type' => 'phone', 'value' => $u->phone, 'col' => 6],
            ['name' => 'secondary_phone', 'label' => 'Secondary phone number', 'type' => 'tel', 'value' => $u->secondary_phone, 'col' => 6, 'placeholder' => '+971 50 123 4567'],
            ['name' => 'whatsapp_number', 'label' => 'WhatsApp', 'type' => 'tel', 'value' => $u->whatsapp_number, 'col' => 6, 'placeholder' => '+971 50 123 4567'],
        ]],
        'compliance' => ['Compliance', 'fa-shield-halved', 'Your broker licenses — shown on your listings and checked with your permits.', [
            ['name' => 'brn_number', 'label' => 'Dubai Broker License (BRN)', 'value' => $u->brn_number, 'col' => 6],
            ['name' => 'adrec_license_no', 'label' => 'Abu Dhabi Broker License (BLN)', 'value' => $u->adrec_license_no, 'col' => 6],
            ['name' => 'other_license', 'label' => 'Other license', 'value' => $u->other_license, 'col' => 6],
            ['name' => 'other_license_expiry', 'label' => 'Other license expiry date', 'type' => 'date', 'expiry' => true, 'value' => $u->other_license_expiry, 'col' => 6],
            ['name' => 'affiliated_brokerage', 'label' => 'Affiliated brokerage', 'type' => 'brokerage', 'value' => $u->affiliated_brokerage, 'col' => 12,
                'note' => $u->company
                    ? '<i class="fas fa-building me-1"></i>Agency member: <strong>' . e($u->company->displayName()) . '</strong> (their plan applies)'
                    : '<i class="fas fa-user me-1"></i>Independent agent on your own plan'],
            ['name' => 'trade_license_no', 'label' => 'Trade License No.', 'value' => $u->trade_license_no, 'col' => 6, 'label_hint' => 'independent agents'],
            ['name' => 'trade_license_expiry', 'label' => 'Trade License Expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->trade_license_expiry, 'col' => 6],
            ['name' => 'trn_number', 'label' => 'TRN (VAT)', 'value' => $u->trn_number, 'col' => 6],
            ['name' => 'trn_expiry', 'label' => 'TRN Expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->trn_expiry, 'col' => 6],
        ]],
        'about' => ['About me', 'fa-user', 'Shown on your public agent page. Write the description in your own language — it\'s translated automatically for other site languages unless you write the Arabic one yourself.', [
            ['name' => 'experience_since', 'label' => 'Experience since', 'type' => 'year', 'value' => $u->experience_since, 'col' => 4,
                'display' => $u->experience_since ? $u->experience_since . ' · ' . max(0, now()->year - $u->experience_since) . ' years' : null],
            ['name' => 'spoken_languages', 'label' => 'Spoken languages', 'type' => 'multiselect', 'options' => $spoken, 'value' => $u->spoken_languages ?? [], 'col' => 8],
            ['name' => 'nationality', 'label' => 'Nationality', 'value' => $u->nationality, 'col' => 4],
            ['name' => 'position', 'label' => 'Position', 'value' => $u->position, 'col' => 4, 'placeholder' => 'e.g. Senior Property Consultant'],
            ['name' => 'linkedin_url', 'label' => 'LinkedIn', 'type' => 'url', 'value' => $u->linkedin_url, 'col' => 4, 'placeholder' => 'https://linkedin.com/in/…'],
            ['name' => 'website', 'label' => 'Website', 'type' => 'url', 'value' => $u->website, 'col' => 4, 'placeholder' => 'https://'],
            ['name' => 'preferred_areas', 'label' => 'Preferred areas', 'value' => $u->preferred_areas ? implode(', ', $u->preferred_areas) : '', 'col' => 8, 'label_hint' => 'comma separated', 'placeholder' => 'Downtown Dubai, Business Bay'],
            ['name' => 'bio', 'label' => 'Description', 'type' => 'textarea', 'value' => $bio, 'col' => 12],
            ['name' => 'bio_ar', 'label' => 'Arabic description', 'type' => 'textarea', 'value' => $bioAr, 'col' => 12, 'rtl' => true, 'placeholder' => 'التفاصيل باللغة العربية'],
        ]],
    ] : [
        'contact' => ['Contact information', 'fa-address-book', 'How buyers and partners reach your agency.', [
            ['name' => 'phone', 'label' => 'Phone number', 'type' => 'phone', 'value' => $u->phone, 'col' => 4],
            ['name' => 'landline', 'label' => 'Landline', 'value' => $u->landline, 'col' => 4],
            ['name' => 'whatsapp_number', 'label' => 'WhatsApp', 'type' => 'tel', 'value' => $u->whatsapp_number, 'col' => 4, 'placeholder' => '+971 50 123 4567'],
            ['name' => 'public_email', 'label' => 'Public email', 'type' => 'email', 'value' => $u->public_email, 'col' => 4, 'help' => 'Leave empty to show the login email.'],
            ['name' => 'city', 'label' => 'City', 'value' => $u->city, 'col' => 4, 'placeholder' => 'Dubai'],
            ['name' => 'website', 'label' => 'Website URL', 'type' => 'url', 'value' => $u->website, 'col' => 4, 'placeholder' => 'https://'],
            ['name' => 'office_address', 'label' => 'Address', 'value' => $u->office_address, 'col' => 12, 'placeholder' => 'Office, building, area, city, PO Box'],
        ]],
        'other' => ['Other information', 'fa-circle-info', 'Your account and billing details.', [
            ['name' => 'account_no', 'label' => 'Account number', 'type' => 'static', 'value' => str_pad((string) $u->id, 6, '0', STR_PAD_LEFT), 'locked_help' => 'Your MW Realty account number — fixed.'],
            ['name' => 'client_type', 'label' => 'Client type', 'type' => 'static', 'value' => 'Broker', 'locked_help' => 'Set from your account type.'],
            ['name' => 'login_email', 'label' => 'Default email address', 'type' => 'static', 'value' => $u->email, 'locked_help' => 'Your login email — changed with a code sent to the new address.', 'locked_action' => '#emailChangeModal'],
            ['name' => 'company_name', 'label' => 'Display client name', 'value' => $u->company_name, 'col' => 6],
            ['name' => 'name', 'label' => 'Contact person', 'value' => $u->name, 'col' => 6],
            ['name' => 'trn_number', 'label' => 'VAT number (TRN)', 'value' => $u->trn_number],
            ['name' => 'trn_expiry', 'label' => 'TRN expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->trn_expiry],
            ['name' => 'authorized_signatory_name', 'label' => 'Authorized signatory', 'value' => $u->authorized_signatory_name],
        ]],
        'licenses' => ['Corporate licenses', 'fa-file-contract', 'The ORN validates Dubai (RERA) permits, the ADREC number Abu Dhabi / Al Ain permits.', [
            ['name' => 'trade_license_no', 'label' => 'Trade license number', 'value' => $u->trade_license_no, 'col' => 6],
            ['name' => 'trade_license_expiry', 'label' => 'Trade license expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->trade_license_expiry, 'col' => 6],
            ['name' => 'orn_number', 'label' => 'ORN number', 'value' => $u->orn_number, 'col' => 6, 'label_hint' => 'RERA, Dubai'],
            ['name' => 'orn_expiry', 'label' => 'ORN expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->orn_expiry, 'col' => 6],
            ['name' => 'adrec_license_no', 'label' => 'ADREC brokerage registration no.', 'value' => $u->adrec_license_no, 'col' => 6, 'label_hint' => 'Abu Dhabi'],
            ['name' => 'adrec_license_expiry', 'label' => 'ADREC expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->adrec_license_expiry, 'col' => 6],
        ]],
        'about' => ['About', 'fa-globe', 'Shown on your public agency page. Write the description in your own language — it\'s translated automatically for other site languages unless you write the Arabic one yourself.', [
            ['name' => 'founding_year', 'label' => 'Established', 'type' => 'year', 'value' => $u->founding_year, 'col' => 4],
            ['name' => 'linkedin_url', 'label' => 'LinkedIn', 'type' => 'url', 'value' => $u->linkedin_url, 'col' => 4, 'placeholder' => 'https://linkedin.com/company/…'],
            ['name' => 'preferred_areas', 'label' => 'Service areas', 'value' => $u->preferred_areas ? implode(', ', $u->preferred_areas) : '', 'col' => 4, 'label_hint' => 'comma separated', 'placeholder' => 'Downtown Dubai, Business Bay'],
            ['name' => 'bio', 'label' => 'Description', 'type' => 'textarea', 'value' => $bio, 'col' => 12],
            ['name' => 'bio_ar', 'label' => 'Arabic description', 'type' => 'textarea', 'value' => $bioAr, 'col' => 12, 'rtl' => true, 'placeholder' => 'التفاصيل باللغة العربية'],
        ]],
    ];
    $profileSections['identity'] = [$isAgent ? 'Identity' : 'Signatory identity', 'fa-id-badge', 'Private — only used by MW Realty to verify your account, never shown publicly.', [
        ['name' => 'emirates_id_no', 'label' => 'Emirates ID No.', 'value' => $u->emirates_id_no],
        ['name' => 'passport_no', 'label' => 'Passport No.', 'value' => $u->passport_no],
        ['name' => 'passport_expiry', 'label' => 'Passport expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->passport_expiry],
    ]];

    // How complete each section is (static fields don't count) and the profile as a whole (incl. documents).
    $isFilled = fn (array $f) => filled(is_array($f['value'] ?? null) ? array_filter($f['value']) : ($f['value'] ?? null));
    $sectionStats = collect($profileSections)->map(function ($s) use ($isFilled) {
        $editable = array_filter($s[3], fn ($f) => ($f['type'] ?? 'text') !== 'static');
        return ['done' => count(array_filter($editable, $isFilled)), 'total' => count($editable)];
    });
    $docsDoneCount = $documents->filter(fn ($d) => $d['path'])->count();
    $docsTotal = $documents->count();
    $allDone = $sectionStats->sum('done') + $docsDoneCount;
    $allTotal = max(1, $sectionStats->sum('total') + $docsTotal);
    $completeness = (int) round($allDone / $allTotal * 100);
    $avatarLabel = $isAgent ? 'profile photo' : 'logo';
@endphp

{{-- Cover: banner, avatar / logo, name, login email (+ change), completeness ring, key numbers --}}
<div class="pf-cover" id="profileHero">
    <div class="pf-cover-banner">
        @if($u->status !== 'approved' && (in_array($u->kyc_review_status, ['draft', 'changes_requested'], true) || !$u->kyc_user_submitted_at))
        <button type="button" id="resubmitBtn" class="pf-cover-cta"><i class="fas fa-paper-plane me-1"></i> {{ $u->kyc_review_status === 'changes_requested' || $u->kyc_user_submitted_at ? 'Resubmit for Approval' : 'Submit for Approval' }}</button>
        @endif
    </div>
    <div class="pf-cover-body">
        {{-- Profile photo / agency logo — shown on the public agent / agency pages. Click to change. --}}
        <div class="profile-avatar-wrap pf-cover-avatar">
            <label class="profile-avatar {{ $avatarFill }} profile-avatar--upload" for="avatarInput" title="Change {{ $avatarLabel }}">
                <img id="avatarImg" src="{{ $u->avatar ? media_url($u->avatar) : '' }}" alt="" class="{{ $u->avatar ? '' : 'd-none' }} {{ $isAgent ? '' : 'is-logo' }}">
                <span id="avatarInitials" class="{{ $u->avatar ? 'd-none' : '' }}">{{ $initials }}</span>
                <span class="profile-avatar__cam" aria-hidden="true"><i class="fas fa-camera"></i></span>
                <span class="profile-avatar__spin d-none" id="avatarSpin"><span class="spinner-border spinner-border-sm"></span></span>
            </label>
            <input type="file" id="avatarInput" accept="image/png,image/jpeg,image/webp" class="d-none" aria-label="Upload {{ $avatarLabel }}">
            <button type="button" id="avatarRemove" class="profile-avatar__remove {{ $u->avatar ? '' : 'd-none' }}" title="Remove {{ $avatarLabel }}" aria-label="Remove {{ $avatarLabel }}"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="pf-cover-main">
            <div class="pf-cover-name">
                <h1>{{ $displayName }}</h1>
                <span class="pf-tag">{{ $isAgent ? 'Agent' : 'Agency' }}</span>
                <span class="pf-tag pf-status {{ $statusMap[$u->status] ?? '' }}"><i class="fas fa-circle"></i>{{ ucfirst($u->status) }}</span>
            </div>
            <div class="pf-cover-meta">
                <span class="pf-login-email"><i class="fas fa-envelope"></i>{{ $u->email }}
                    <button type="button" class="pf-link-btn" id="emailChangeToggle" data-bs-toggle="modal" data-bs-target="#emailChangeModal">Change</button></span>
                @if($u->phone)<span><i class="fas fa-phone"></i>{{ $u->phone }}</span>@endif
                <span><i class="fas fa-calendar"></i>Joined {{ $u->created_at->format('d M Y') }}</span>
            </div>
        </div>

        <div class="pf-ring" style="--pct: {{ $completeness }}" role="img" aria-label="Profile {{ $completeness }}% complete">
            <svg viewBox="0 0 36 36" aria-hidden="true"><circle class="pf-ring-bg" cx="18" cy="18" r="15.9"/><circle class="pf-ring-val" cx="18" cy="18" r="15.9" pathLength="100" style="stroke-dasharray: {{ $completeness }} 100"/></svg>
            <div class="pf-ring-text"><strong>{{ $completeness }}%</strong><span>complete</span></div>
        </div>
    </div>
    <div class="pf-cover-stats">
        <div><strong>{{ $u->properties()->count() }}</strong><span>Properties</span></div>
        <div><strong>{{ $u->leads()->count() }}</strong><span>Leads</span></div>
        <div><strong>{{ $u->effectivePlan()?->getTranslation('name') ?? 'No plan' }}</strong><span>{{ $u->isOnAgencyPlan() ? 'Plan · via agency' : 'Plan' }}</span></div>
        <div><strong class="{{ $docsDoneCount < $docsTotal ? 'is-todo' : 'is-done' }}">{{ $docsDoneCount }}/{{ $docsTotal }}</strong><span>Documents</span></div>
    </div>
</div>

@if($portalUser->status !== 'approved')
@php
    $docsDone = $documents->filter(fn ($d) => $d['path'])->count();
    $docsTotal = $documents->count();
    $isRejected = $portalUser->status === 'rejected';
    $changesRequested = !$isRejected && $portalUser->kyc_review_status === 'changes_requested';
    $inReview = !$isRejected && !$changesRequested && $portalUser->kyc_review_status === 'submitted' && $portalUser->kyc_user_submitted_at;
    // Which of the 4 steps we're on: 1 prepare, 2 submit, 3 review (4 = approved, never shown here).
    $current = $inReview ? 3 : ($docsDone >= $docsTotal && $docsTotal > 0 ? 2 : 1);
    $state = fn (int $step) => match (true) {
        ($isRejected || $changesRequested) && $step === 1 => 'is-problem',
        $step < $current => 'is-done',
        $step === $current => 'is-current',
        default => '',
    };
    [$tone, $icon, $title, $text] = match (true) {
        $isRejected => ['tone-red', 'fa-ban', 'Your application needs changes', 'Update the details below, then resubmit for approval. CRM access unlocks once you are approved.'],
        $changesRequested => ['tone-amber', 'fa-comment-medical', 'Our team asked for a few updates', 'Make the changes below, then resubmit for approval.'],
        $inReview => ['tone-blue', 'fa-hourglass-half', 'KYC submitted — waiting for review', 'Our team is reviewing your documents. Leads, Reports and listings unlock as soon as you are approved.'],
        default => ['tone-amber', 'fa-clipboard-check', 'Finish your KYC to unlock the CRM', 'Complete your details and upload your documents, then submit them for approval.'],
    };
@endphp
<div class="kyc-progress">
    <div class="kyc-progress__head">
        <span class="kyc-progress__icon {{ $tone }}"><i class="fas {{ $icon }}"></i></span>
        <div class="min-w-0 flex-grow-1">
            <div class="kyc-progress__title">{{ $title }}</div>
            <div class="kyc-progress__text">{{ $text }}</div>
        </div>
    </div>
    <div class="kyc-steps">
        <div class="kyc-step {{ $state(1) }}">
            <div class="kyc-step__label"><i class="fas {{ $state(1) === 'is-done' ? 'fa-check' : 'fa-id-card' }}"></i>Details &amp; documents</div>
            <div class="kyc-step__sub">{{ $docsDone }}/{{ $docsTotal }} documents uploaded</div>
        </div>
        <div class="kyc-step {{ $state(2) }}">
            <div class="kyc-step__label"><i class="fas {{ $state(2) === 'is-done' ? 'fa-check' : 'fa-paper-plane' }}"></i>Submit for approval</div>
            <div class="kyc-step__sub">{{ $portalUser->kyc_user_submitted_at ? 'Sent ' . $portalUser->kyc_user_submitted_at->format('d M Y') : 'Use the button above' }}</div>
        </div>
        <div class="kyc-step {{ $state(3) }}">
            <div class="kyc-step__label"><i class="fas fa-user-shield"></i>Admin review</div>
            <div class="kyc-step__sub">{{ $inReview ? 'In progress' : 'Usually within 1–2 working days' }}</div>
        </div>
        <div class="kyc-step">
            <div class="kyc-step__label"><i class="fas fa-circle-check"></i>Approved</div>
            <div class="kyc-step__sub">Leads, Reports &amp; listings unlock</div>
        </div>
    </div>
    @if($isRejected && $portalUser->rejection_reason)
    <div class="kyc-note tone-red"><strong><i class="fas fa-exclamation-circle me-1"></i>Reason:</strong> {{ $portalUser->rejection_reason }}</div>
    @elseif($changesRequested && $portalUser->kyc_review_note)
    <div class="kyc-note tone-info"><strong><i class="fas fa-comment-medical me-1"></i>Requested by our team:</strong>
{{ $portalUser->kyc_review_note }}</div>
    @endif
</div>
@endif

<div class="profile-layout">
    <nav class="profile-nav" role="tablist" aria-label="Profile sections">
        @foreach($profileSections as $navKey => [$navLabel, $navIcon])
        @php $st = $sectionStats[$navKey]; $missing = $st['total'] - $st['done']; @endphp
        <button type="button" class="profile-nav-link" id="pf-nav-{{ $navKey }}" data-bs-toggle="pill" data-bs-target="#pf-tab-{{ $navKey }}" role="tab" aria-controls="pf-tab-{{ $navKey }}">
            <i class="fas {{ $navIcon }}"></i><span>{{ $navLabel }}</span>
            @if($missing)<span class="pf-nav-state is-todo" title="{{ $missing }} not filled in">{{ $missing }} missing</span>
            @else<span class="pf-nav-state is-done" title="Complete"><i class="fas fa-check"></i></span>@endif
        </button>
        @endforeach
        <button type="button" class="profile-nav-link" id="pf-nav-documents" data-bs-toggle="pill" data-bs-target="#pf-tab-documents" role="tab" aria-controls="pf-tab-documents">
            <i class="fas fa-folder-open"></i><span>Documents</span>
            @if($docsDoneCount < $docsTotal)<span class="pf-nav-state is-todo">{{ $docsDoneCount }}/{{ $docsTotal }}</span>
            @else<span class="pf-nav-state is-done"><i class="fas fa-check"></i></span>@endif
        </button>
        <button type="button" class="profile-nav-link" id="pf-nav-seo" data-bs-toggle="pill" data-bs-target="#pf-tab-seo" role="tab" aria-controls="pf-tab-seo">
            <i class="fas fa-search"></i><span>SEO</span><span class="pf-nav-state">Optional</span>
        </button>
    </nav>
    <div class="profile-panes tab-content">
@foreach($profileSections as $secKey => [$secTitle, $secIcon, $secHint, $secFields])
<div class="tab-pane fade" id="pf-tab-{{ $secKey }}" role="tabpanel" aria-labelledby="pf-nav-{{ $secKey }}">
@include('portal.profile._section', ['key' => $secKey, 'title' => $secTitle, 'icon' => $secIcon, 'hint' => $secHint, 'fields' => $secFields] + $sectionStats[$secKey])
</div>
@endforeach

<style>
    .profile-chip-picks { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .profile-chip-pick { position: relative; cursor: pointer; margin: 0; }
    .profile-chip-pick input { position: absolute; opacity: 0; pointer-events: none; }
    .profile-chip-pick span { display: inline-block; padding: 0.3rem 0.75rem; border-radius: 50px; border: 1.5px solid var(--portal-border); font-size: 0.8rem; font-weight: 600; color: var(--portal-muted); transition: all 0.15s ease; }
    .profile-chip-pick input:checked + span { border-color: var(--portal-primary); background: var(--portal-primary); color: #fff; }
    .profile-chip-pick input:focus-visible + span { outline: 2px solid var(--portal-primary); outline-offset: 2px; }
</style>

<div class="tab-pane fade" id="pf-tab-seo" role="tabpanel" aria-labelledby="pf-nav-seo">
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
                    <img src="{{ media_url($meta['og_image']) }}" class="mt-2 rounded" style="height:50px;">
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

</div>

<div class="tab-pane fade" id="pf-tab-documents" role="tabpanel" aria-labelledby="pf-nav-documents">
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
</div>
{{-- Below every section: what's still missing (one click to fill it in) + how the public page looks. --}}
@php
    $missingFields = collect($profileSections)->flatMap(fn ($s, $key) => collect($s[3])
        ->filter(fn ($f) => ($f['type'] ?? 'text') !== 'static' && !$isFilled($f))
        ->map(fn ($f) => ['section' => $key, 'sectionTitle' => $s[0], 'icon' => $s[1], 'name' => $f['name'], 'label' => $f['label']]))->values();
    $missingDocs = $documents->filter(fn ($d) => !$d['path'])->values();
    $todoCount = $missingFields->count() + $missingDocs->count();
    $publicUrl = $u->slug ? url(($isAgent ? '/agent-details/' : '/agency-details/') . $u->slug) : null;
    $publicLive = $u->status === 'approved' && $u->is_active;
    $bioText = trim(strip_tags((string) $bio));
    $chips = $isAgent ? ($u->spoken_languages ?? []) : ($u->preferred_areas ?? []);
@endphp
<div class="pf-extras">
    <div class="dash-card pf-card pf-todo">
        <div class="pf-card-head">
            <div>
                <h2 class="pf-card-title">{{ $todoCount ? 'Complete your profile' : 'Profile complete' }}</h2>
                <p class="pf-card-hint">{{ $todoCount ? "{$todoCount} thing" . ($todoCount === 1 ? '' : 's') . ' left — complete profiles get more enquiries.' : 'Everything is filled in. Nice work!' }}</p>
            </div>
            <span class="pf-todo-pct">{{ $completeness }}%</span>
        </div>
        @if($todoCount)
        <ul class="pf-todo-list">
            @foreach($missingFields->take(6) as $m)
            <li>
                <span class="pf-todo-icon"><i class="fas {{ $m['icon'] }}"></i></span>
                <span class="pf-todo-text"><strong>{{ $m['label'] }}</strong><small>{{ $m['sectionTitle'] }}</small></span>
                <button type="button" class="pf-todo-go" data-goto-section="{{ $m['section'] }}" data-goto-field="{{ $m['name'] }}">Add</button>
            </li>
            @endforeach
            @foreach($missingDocs->take(max(0, 6 - $missingFields->count())) as $d)
            <li>
                <span class="pf-todo-icon"><i class="fas fa-folder-open"></i></span>
                <span class="pf-todo-text"><strong>{{ $d['label'] }}</strong><small>Documents</small></span>
                <button type="button" class="pf-todo-go" data-goto-section="documents">Upload</button>
            </li>
            @endforeach
        </ul>
        @if($todoCount > 6)<div class="pf-todo-more">+ {{ $todoCount - 6 }} more — see the sections marked “missing” on the left.</div>@endif
        @else
        <div class="pf-todo-done"><i class="fas fa-circle-check"></i> All sections and documents are complete.</div>
        @endif
    </div>

    <div class="dash-card pf-card pf-public">
        <div class="pf-card-head">
            <div>
                <h2 class="pf-card-title">Your public page</h2>
                <p class="pf-card-hint">{{ $publicLive ? 'How buyers see you on the website.' : 'Goes live on the website once your account is approved.' }}</p>
            </div>
        </div>
        <div class="pf-public-card">
            <div class="pf-public-top">
                <span class="pf-public-avatar {{ $avatarFill }}">
                    @if($u->avatar)<img src="{{ media_url($u->avatar) }}" alt="">@else{{ $initials }}@endif
                </span>
                <div class="min-w-0">
                    <div class="pf-public-name">{{ $displayName }}</div>
                    <div class="pf-public-sub">{{ $isAgent ? ($u->position ?: 'Real estate agent') : 'Real estate agency' }}@if($u->city) · {{ $u->city }}@endif</div>
                </div>
            </div>
            <p class="pf-public-bio">{{ $bioText !== '' ? \Illuminate\Support\Str::limit($bioText, 150) : 'Add a description in About to introduce yourself to buyers.' }}</p>
            @if($chips)
            <div class="pf-public-chips">@foreach(array_slice($chips, 0, 4) as $chip)<span class="pf-chip">{{ $chip }}</span>@endforeach</div>
            @endif
        </div>
        @if($publicUrl && $publicLive)
        <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="pf-public-link">View public page <i class="fas fa-arrow-up-right-from-square"></i></a>
        @endif
    </div>
</div>
</div>{{-- /.profile-panes --}}
</div>{{-- /.profile-layout --}}

{{-- Change login email: a code goes to the new address first (PortalProfileController::requestEmailChange). --}}
<div class="modal fade" id="emailChangeModal" tabindex="-1" aria-labelledby="emailChangeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailChangeModalTitle"><i class="fas fa-envelope me-2"></i>Change login email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Current: <strong>{{ $portalUser->email }}</strong></p>
                <div class="email-change-block" id="emailChangeBlock">
        <div id="emailChangeView">
            
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
        </div>
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
// Profile photo / agency logo: pick a file → upload right away → show it (or back to initials on remove).
(function () {
    const input = document.getElementById('avatarInput');
    if (!input) return;
    const img = document.getElementById('avatarImg'), initials = document.getElementById('avatarInitials');
    const spin = document.getElementById('avatarSpin'), removeBtn = document.getElementById('avatarRemove');
    const headers = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' };
    const toast = (type, msg) => window.portalToast ? window.portalToast(type, msg) : alert(msg);
    const show = url => {
        img.src = url || '';
        img.classList.toggle('d-none', !url);
        initials.classList.toggle('d-none', !!url);
        removeBtn.classList.toggle('d-none', !url);
    };

    input.addEventListener('change', function () {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 4 * 1024 * 1024) { toast('danger', 'Please choose an image under 4 MB.'); input.value = ''; return; }
        const body = new FormData();
        body.append('avatar', file);
        spin.classList.remove('d-none');
        fetch(@json(route('portal.profile.avatar.upload')), { method: 'POST', headers, body })
            .then(async r => { const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.errors?.avatar?.[0] || d.message || 'Could not upload the image.'); return d; })
            .then(d => { show(d.avatar_url); toast('success', d.message); })
            .catch(err => toast('danger', err.message))
            .finally(() => { spin.classList.add('d-none'); input.value = ''; });
    });

    removeBtn.addEventListener('click', async function () {
        const ok = window.portalConfirm
            ? await window.portalConfirm({ title: 'Remove this image?', message: 'Your public profile will show a placeholder instead.', confirmText: 'Remove', tone: 'danger' })
            : confirm('Remove this image?');
        if (!ok) return;
        fetch(@json(route('portal.profile.avatar.remove')), { method: 'DELETE', headers })
            .then(r => { if (!r.ok) throw new Error(); show(null); toast('success', 'Image removed.'); })
            .catch(() => toast('danger', 'Could not remove the image.'));
    });
})();

// Affiliated Brokerage: suggests registered agencies (server search, 20 at a time, more on scroll);
// any typed name is kept as-is. Picking one only fills the text — it never links the account.
(function () {
    const input = document.getElementById('brokerageInput');
    const list = document.getElementById('brokerageList');
    if (!input || !list) return;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let page = 1, more = false, loading = false, term = '', timer = null, requestNo = 0;

    const open = () => { list.classList.remove('d-none'); input.setAttribute('aria-expanded', 'true'); };
    const close = () => { list.classList.add('d-none'); input.setAttribute('aria-expanded', 'false'); };

    function load(reset) {
        if (loading && !reset) return;
        if (reset) { page = 1; list.innerHTML = ''; }
        loading = true;
        const mine = ++requestNo;
        const status = document.createElement('div');
        status.className = 'brokerage-combo__status';
        status.textContent = 'Searching…';
        list.appendChild(status);

        fetch(`${input.dataset.searchUrl}?q=${encodeURIComponent(term)}&page=${page}`, { headers: { Accept: 'application/json' } })
            .then(r => r.ok ? r.json() : { results: [], pagination: { more: false } })
            .then(data => {
                if (mine !== requestNo) return;
                status.remove();
                if (page === 1 && term && !data.results.some(a => a.text.toLowerCase() === term.toLowerCase())) {
                    list.insertAdjacentHTML('beforeend', `<button type="button" class="brokerage-combo__item brokerage-combo__item--typed" data-value="${esc(term)}"><i class="fas fa-pen"></i>Use “${esc(term)}”</button>`);
                }
                data.results.forEach(a => list.insertAdjacentHTML('beforeend',
                    `<button type="button" class="brokerage-combo__item" data-value="${esc(a.text)}"><i class="fas fa-building"></i>${esc(a.text)}</button>`));
                if (page === 1 && !data.results.length && !term) {
                    list.insertAdjacentHTML('beforeend', '<div class="brokerage-combo__status">No agencies yet — type your brokerage name.</div>');
                }
                more = !!(data.pagination && data.pagination.more);
                page++;
            })
            .catch(() => { status.textContent = 'Could not load agencies — you can still type the name.'; })
            .finally(() => { if (mine === requestNo) loading = false; });
    }

    input.addEventListener('focus', () => { term = input.value.trim(); load(true); open(); });
    input.addEventListener('input', () => {
        open();
        clearTimeout(timer);
        timer = setTimeout(() => { term = input.value.trim(); load(true); }, 250);
    });
    // Close on a click outside / Esc / Tab — not on blur, so switching windows (e.g. a screenshot) keeps it open.
    document.addEventListener('pointerdown', e => { if (!e.target.closest('.brokerage-combo')) close(); });
    input.addEventListener('keydown', e => { if (e.key === 'Escape' || e.key === 'Tab') close(); });
    list.addEventListener('mousedown', e => e.preventDefault()); // keep focus so the click lands
    list.addEventListener('click', e => {
        const item = e.target.closest('.brokerage-combo__item');
        if (!item) return;
        input.value = item.dataset.value;
        close();
    });
    list.addEventListener('scroll', () => {
        if (more && !loading && list.scrollTop + list.clientHeight >= list.scrollHeight - 30) load(false);
    });
})();

// Profile card: compact once it's stuck under the sticky portal header (the marker above it has scrolled past).
(function () {
    const hero = document.getElementById('profileHero');
    const sentinel = document.getElementById('profileHeroSentinel');
    if (!hero || !sentinel) return;
    const check = () => {
        const topbar = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--portal-topbar-h')) || 80;
        hero.classList.toggle('is-stuck', sentinel.getBoundingClientRect().top < topbar + 12);
    };
    check();
    window.addEventListener('scroll', check, { passive: true });
    window.addEventListener('resize', check);
})();

// Profile sections menu: open the section from the URL (#documents) or the one open before the
// last save (saving reloads the page), else the first one.
(function () {
    const KEY = 'portal-profile-tab';
    const links = [...document.querySelectorAll('.profile-nav-link')];
    if (!links.length) return;
    const byKey = key => links.find(l => l.dataset.bsTarget === '#pf-tab-' + key);
    let stored = null;
    try { stored = sessionStorage.getItem(KEY); } catch (e) {}
    const start = byKey(location.hash.replace('#', '')) || byKey(stored) || links[0];
    bootstrap.Tab.getOrCreateInstance(start).show();
    // "Complete your profile" → open that section and its edit form at the missing field.
    document.querySelectorAll('[data-goto-section]').forEach(btn => btn.addEventListener('click', () => {
        const link = byKey(btn.dataset.gotoSection);
        if (!link) return;
        bootstrap.Tab.getOrCreateInstance(link).show();
        const pane = document.getElementById('pf-tab-' + btn.dataset.gotoSection);
        const add = btn.dataset.gotoField && pane.querySelector('.pf-add[data-focus="' + btn.dataset.gotoField + '"]');
        if (add) add.click();
        pane.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
    links.forEach(link => link.addEventListener('shown.bs.tab', () => {
        try { sessionStorage.setItem(KEY, link.dataset.bsTarget.replace('#pf-tab-', '')); } catch (e) {}
        if (window.matchMedia('(max-width: 991.98px)').matches) link.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }));
})();

(function () {
    const base = "{{ url('/portal/profile') }}";

    document.querySelectorAll('.section-edit-toggle').forEach(btn => btn.addEventListener('click', function () {
        const section = this.dataset.section;
        const card = this.closest('.dash-card');
        card.querySelector('.section-view[data-section="' + section + '"]').classList.add('d-none');
        const editForm = card.querySelector('.section-edit[data-section="' + section + '"]');
        editForm.classList.remove('d-none');
        // "Not added · Add" on a field opens the form at that field.
        const target = this.dataset.focus && editForm.querySelector('[name="' + this.dataset.focus + '"], [name="' + this.dataset.focus + '[]"]');
        (target || editForm.querySelector('input:not([type=hidden]), select, textarea'))?.focus();
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

        // Save button: Saving… (spinner) → Saved ✓ → page refresh; back to Save on an error.
        const saveBtn = form.querySelector('button[type="submit"]');
        const cancelBtn = form.querySelector('.section-edit-cancel');
        const saveLabel = saveBtn ? saveBtn.innerHTML : '';
        const setSave = (state) => {
            if (!saveBtn) return;
            saveBtn.classList.remove('is-saving', 'is-saved');
            if (state) saveBtn.classList.add('is-' + state);
            saveBtn.disabled = !!state;
            if (cancelBtn) cancelBtn.disabled = !!state;
            saveBtn.innerHTML = state === 'saving' ? '<span class="pf-spinner" aria-hidden="true"></span>Saving…'
                : state === 'saved' ? '<i class="fas fa-check"></i>Saved' : saveLabel;
        };
        setSave('saving');

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
            .then(data => {
                if (!data.success) return setSave(null);
                setSave('saved');
                setTimeout(() => window.location.reload(), 650);
            })
            .catch(err => {
                setSave(null);
                if (errorBox) { errorBox.textContent = err.message; errorBox.classList.remove('d-none'); }
                else alert(err.message);
                if (window.restoreSubmitButtons) window.restoreSubmitButtons(form);
            });
    }));

    document.querySelectorAll('.doc-remove-btn').forEach(btn => btn.addEventListener('click', async function () {
        const field = this.closest('.doc-card').dataset.field;
        if (!(await window.portalConfirm({ title: 'Remove this document?', message: 'You can upload it again later.', confirmText: 'Remove', tone: 'danger' }))) return;
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
    // The steps live in a modal (opened from the header): Cancel closes it, closing it resets it.
    const emailModalEl = document.getElementById('emailChangeModal');
    const closeEmailModal = () => bootstrap.Modal.getInstance(emailModalEl)?.hide();
    document.getElementById('emailChangeCancel1')?.addEventListener('click', closeEmailModal);
    document.getElementById('emailChangeCancel2')?.addEventListener('click', closeEmailModal);
    emailModalEl?.addEventListener('hidden.bs.modal', resetEmailChangeWidget);
    emailModalEl?.addEventListener('shown.bs.modal', () => newEmailInput.focus());

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
