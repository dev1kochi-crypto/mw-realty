{{-- Listing Approvals (index + review page). Tones per compliance status: see $laTones in each view. --}}
@push('styles')
<style>
    .min-w-0 { min-width: 0; }
    .la-status-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
    @media (max-width: 1199.98px) { .la-status-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) { .la-status-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

    .la-status { --tone: #244373; --tone-soft: #eef2f8; position: relative; display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 14px; border: 1px solid var(--portal-border); background: var(--portal-surface); color: var(--portal-text); text-decoration: none; box-shadow: var(--portal-shadow); transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease; }
    .la-status:hover { transform: translateY(-2px); color: var(--portal-text); border-color: var(--tone); }
    .la-status__icon { flex-shrink: 0; width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; background: var(--tone-soft); color: var(--tone); font-size: 1.05rem; }
    .la-status__count { font-size: 1.45rem; font-weight: 800; line-height: 1; }
    .la-status__label { font-size: .8rem; font-weight: 600; color: var(--portal-muted); margin-top: 4px; }
    .la-status.is-active { background: var(--tone); border-color: var(--tone); color: #fff; box-shadow: 0 12px 26px color-mix(in srgb, var(--tone) 35%, transparent); }
    .la-status.is-active .la-status__icon { background: rgba(255, 255, 255, .18); color: #fff; }
    .la-status.is-active .la-status__label { color: rgba(255, 255, 255, .85); }
    .la-status.is-active::after { content: ''; position: absolute; left: 50%; bottom: -7px; width: 14px; height: 14px; background: var(--tone); transform: translateX(-50%) rotate(45deg); border-radius: 2px; }
    .la-status__alert { position: absolute; top: 10px; right: 10px; width: 9px; height: 9px; border-radius: 50%; background: var(--portal-accent); box-shadow: 0 0 0 0 rgba(202, 40, 68, .6); animation: laPulse 1.8s infinite; }
    .la-status.is-active .la-status__alert { background: #fff; }
    @keyframes laPulse { 70% { box-shadow: 0 0 0 9px rgba(202, 40, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(202, 40, 68, 0); } }
    @media (prefers-reduced-motion: reduce) { .la-status__alert { animation: none; } }

    .la-tone-draft { --tone: #b7791f; --tone-soft: #fdf3e1; }
    .la-tone-pending { --tone: #244373; --tone-soft: #e9eef6; }
    .la-tone-changes_requested { --tone: #c2410c; --tone-soft: #fdeee4; }
    .la-tone-approved { --tone: #0f8a4f; --tone-soft: #e5f5ec; }
    .la-tone-expired { --tone: #be123c; --tone-soft: #fde8ed; }

    .la-hint { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 12px; background: var(--tone-soft); color: var(--portal-text); font-size: .85rem; }
    .la-hint i { color: var(--tone); margin-top: 3px; }

    .la-row { display: grid; grid-template-columns: minmax(0, 2.2fr) minmax(0, 1.4fr) minmax(0, 1.3fr) minmax(0, 1fr) auto; gap: 16px; align-items: center; padding: 14px 4px; border-bottom: 1px solid var(--portal-border); }
    .la-row:last-child { border-bottom: 0; }
    .la-row--head { padding-top: 0; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--portal-muted); }
    @media (max-width: 991.98px) { .la-row { grid-template-columns: 1fr 1fr; } .la-row--head { display: none; } .la-row__action { grid-column: 1 / -1; } }
    .la-thumb { width: 64px; height: 48px; flex-shrink: 0; object-fit: cover; border-radius: 10px; background: #eef1f6; }
    .la-title { font-weight: 700; color: var(--portal-text); text-decoration: none; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .la-sub { font-size: .8rem; color: var(--portal-muted); }
    .la-chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: .74rem; font-weight: 600; background: #f1f3f8; color: #4a5070; }
    .la-chip--ok { background: #e5f5ec; color: #0b6b3d; }
    .la-chip--warn { background: #fdf3e1; color: #92590f; }
    .la-chip--bad { background: #fde8ed; color: #a3112f; }
    .la-permit { font-weight: 700; font-variant-numeric: tabular-nums; }
    .la-empty { padding: 48px 16px; text-align: center; color: var(--portal-muted); }
    .la-empty i { font-size: 2rem; color: var(--tone); opacity: .7; display: block; margin-bottom: 10px; }

    /* Review page */
    .la-section-title { display: flex; align-items: center; gap: 8px; font-weight: 700; margin-bottom: 14px; }
    .la-section-title i { color: var(--portal-primary); }
    .la-field-label { font-size: .75rem; color: var(--portal-muted); margin-bottom: 2px; }
    .la-field-value { font-weight: 700; }
    .la-qr { width: 150px; height: 150px; object-fit: contain; border: 1px solid var(--portal-border); border-radius: 12px; padding: 6px; background: #fff; }
    .la-compare { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    @media (max-width: 767.98px) { .la-compare { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .la-compare > div { padding: 10px 12px; border-radius: 10px; background: #f6f7fb; }
    .la-checklist { list-style: none; padding: 0; margin: 0; }
    .la-checklist li { display: flex; gap: 8px; padding: 6px 0; font-size: .86rem; }
    .la-checklist .fa-circle-check { color: #0f8a4f; margin-top: 3px; }
    .la-checklist .fa-circle-xmark { color: #be123c; margin-top: 3px; }
    .la-timeline { position: relative; padding-left: 22px; }
    .la-timeline::before { content: ''; position: absolute; left: 6px; top: 6px; bottom: 6px; width: 2px; background: var(--portal-border); }
    .la-timeline__item { position: relative; padding-bottom: 14px; font-size: .86rem; }
    .la-timeline__item::before { content: ''; position: absolute; left: -21px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: var(--tone); box-shadow: 0 0 0 3px var(--tone-soft); }
    .la-sticky { position: sticky; top: calc(var(--portal-topbar-h, 70px) + 16px); }
</style>
@endpush
