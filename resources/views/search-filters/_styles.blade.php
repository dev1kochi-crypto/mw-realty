@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<style>
    .sf { --sf-navy: var(--secondary-color, #264373); --sf-teal: var(--primary-color, #04a1cc); --sf-ink: #1c2340; --sf-muted: #838aa3; --sf-soft: #f7f8fc; --sf-border: rgba(28, 35, 64, .08); --sf-red: #ca2844; }
    .sf-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
    .sf-head h4 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--sf-ink); margin: 0; }
    .sf-head p { color: var(--sf-muted); font-size: .85rem; margin: .25rem 0 0; max-width: 640px; }
    .sf-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
    .sf-btn { display: inline-flex; align-items: center; gap: .45rem; border-radius: 11px; font-weight: 700; font-size: .84rem; padding: .55rem 1rem; border: 1.5px solid transparent; transition: all .2s; text-decoration: none; cursor: pointer; }
    .sf-btn--primary { background: linear-gradient(135deg, var(--sf-teal), #2fc4e8); color: #fff; box-shadow: 0 6px 16px rgba(4, 161, 204, .25); }
    .sf-btn--primary:hover { color: #fff; transform: translateY(-1px); }
    .sf-btn--ghost { background: #fff; color: var(--sf-navy); border-color: var(--sf-border); }
    .sf-btn--ghost:hover { border-color: var(--sf-teal); color: var(--sf-teal); }

    /* Page tabs */
    .sf-pages { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1.25rem; }
    @media (max-width: 1199.98px) { .sf-pages { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 767.98px) { .sf-pages { grid-template-columns: repeat(2, 1fr); } }
    .sf-page { display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem; border-radius: 16px; background: #fff; border: 1.5px solid var(--sf-border); text-decoration: none; transition: all .2s; }
    .sf-page:hover { border-color: rgba(4, 161, 204, .4); }
    .sf-page.is-active { border-color: var(--sf-teal); box-shadow: 0 0 0 4px rgba(4, 161, 204, .1); }
    .sf-page__icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: var(--sf-soft); color: var(--sf-navy); flex-shrink: 0; }
    .sf-page.is-active .sf-page__icon { background: linear-gradient(135deg, var(--sf-teal), #2fc4e8); color: #fff; }
    .sf-page strong { display: block; color: var(--sf-ink); font-size: .92rem; }
    .sf-page span { font-size: .74rem; color: var(--sf-muted); }

    .sf-note { display: flex; gap: .75rem; align-items: flex-start; padding: .8rem 1rem; border-radius: 14px; background: rgba(4, 161, 204, .06); border: 1px solid rgba(4, 161, 204, .18); color: #3d4460; font-size: .82rem; margin-bottom: 1.25rem; }
    .sf-note i { color: var(--sf-teal); margin-top: .15rem; }

    .sf-card { background: #fff; border: 1px solid var(--sf-border); border-radius: 18px; box-shadow: 0 6px 20px rgba(28, 35, 64, .05); }
    .sf-card__head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--sf-border); }
    .sf-card__title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--sf-ink); font-size: 1rem; }
    .sf-card__sub { font-size: .76rem; color: var(--sf-muted); }

    /* Filter rows */
    .sf-list { list-style: none; margin: 0; padding: .5rem; }
    .sf-row { display: grid; grid-template-columns: 28px 44px minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 1.2fr) 70px 92px; align-items: center; gap: .85rem; padding: .7rem .75rem; border-radius: 14px; transition: background .15s; }
    .sf-row + .sf-row { border-top: 1px dashed var(--sf-border); }
    .sf-row:hover { background: var(--sf-soft); }
    .sf-row.is-off { opacity: .55; }
    .sf-row.sortable-ghost { background: rgba(4, 161, 204, .08); }
    @media (max-width: 991.98px) { .sf-row { grid-template-columns: 28px 44px minmax(0, 1fr) 70px 92px; } .sf-row .sf-col-type, .sf-row .sf-col-options { display: none; } }
    .sf-drag { color: #c0c5d6; cursor: grab; text-align: center; }
    .sf-drag:active { cursor: grabbing; }
    .sf-type-icon { width: 44px; height: 44px; border-radius: 13px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; }
    .sf-type-icon.t-select { background: linear-gradient(135deg, var(--sf-navy), #3a5794); }
    .sf-type-icon.t-radio { background: linear-gradient(135deg, #6d28d9, #8b5cf6); }
    .sf-type-icon.t-checkbox { background: linear-gradient(135deg, #0f9d58, #34c880); }
    .sf-type-icon.t-text { background: linear-gradient(135deg, #e08e0b, #f5a623); }
    .sf-type-icon.t-number { background: linear-gradient(135deg, var(--sf-red), #e5546d); }
    .sf-type-icon.t-range { background: linear-gradient(135deg, var(--sf-teal), #2fc4e8); }
    .sf-name strong { display: block; color: var(--sf-ink); font-size: .9rem; }
    .sf-name code { font-size: .72rem; color: var(--sf-red); background: rgba(202, 40, 68, .06); padding: .05rem .4rem; border-radius: 6px; }
    .sf-name small { color: var(--sf-muted); font-size: .72rem; margin-left: .35rem; }
    .sf-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .65rem; border-radius: 99px; font-size: .74rem; font-weight: 700; background: var(--sf-soft); color: #3d4460; }
    .sf-chip.is-db { background: rgba(4, 161, 204, .08); color: #037c9e; }
    .sf-chip.is-custom { background: rgba(109, 40, 217, .08); color: #6d28d9; }
    .sf-row-actions { display: flex; gap: .35rem; justify-content: flex-end; }
    .sf-icon-btn { width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--sf-border); background: #fff; display: inline-flex; align-items: center; justify-content: center; color: var(--sf-navy); transition: all .15s; }
    .sf-icon-btn:hover { border-color: var(--sf-teal); color: var(--sf-teal); }
    .sf-icon-btn.is-danger:hover { border-color: var(--sf-red); color: var(--sf-red); }
    .sf .form-switch .form-check-input { width: 2.4em; height: 1.25em; cursor: pointer; }

    .sf-empty { text-align: center; padding: 3rem 1rem; }
    .sf-empty__icon { width: 64px; height: 64px; border-radius: 20px; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; background: var(--sf-soft); color: var(--sf-teal); font-size: 1.5rem; }
    .sf-empty h6 { font-weight: 800; color: var(--sf-ink); }
    .sf-empty p { color: var(--sf-muted); font-size: .85rem; }
    .sf-api { display: flex; align-items: center; gap: .6rem; padding: .75rem 1.25rem; border-top: 1px solid var(--sf-border); font-size: .76rem; color: var(--sf-muted); flex-wrap: wrap; }
    .sf-api code { color: var(--sf-navy); background: var(--sf-soft); padding: .15rem .5rem; border-radius: 6px; }

    /* Form */
    .sf-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 1.5rem; align-items: start; }
    @media (max-width: 1199.98px) { .sf-grid { grid-template-columns: 1fr; } }
    .sf-section { background: #fff; border: 1px solid var(--sf-border); border-radius: 18px; box-shadow: 0 6px 20px rgba(28, 35, 64, .05); padding: 1.35rem 1.5rem; margin-bottom: 1.25rem; }
    .sf-section__head { display: flex; gap: .85rem; align-items: flex-start; margin-bottom: 1.1rem; }
    .sf-step { width: 34px; height: 34px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: .85rem; background: linear-gradient(135deg, var(--sf-navy), #3a5794); }
    .sf-section__title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.02rem; color: var(--sf-ink); }
    .sf-section__hint { font-size: .8rem; color: var(--sf-muted); margin-top: .1rem; }
    .sf .form-label { font-size: .8rem; font-weight: 700; color: #3d4460; margin-bottom: .35rem; }
    .sf .form-control, .sf .form-select { border-radius: 10px; }
    .sf-help { font-size: .74rem; color: var(--sf-muted); margin-top: .3rem; }

    .sf-field-group + .sf-field-group { margin-top: 1rem; }
    .sf-field-group__name { font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--sf-muted); margin-bottom: .5rem; }
    .sf-choices { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: .5rem; }
    .sf-choice { position: relative; display: flex; align-items: center; gap: .6rem; border: 1.5px solid var(--sf-border); border-radius: 12px; padding: .6rem .75rem; cursor: pointer; transition: all .15s; background: #fff; margin: 0; }
    .sf-choice input { position: absolute; opacity: 0; pointer-events: none; }
    .sf-choice strong { display: block; font-size: .84rem; color: var(--sf-ink); line-height: 1.25; }
    .sf-choice span { font-size: .71rem; color: var(--sf-muted); }
    .sf-choice i.sf-choice__icon { width: 30px; height: 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center; background: var(--sf-soft); color: var(--sf-navy); flex-shrink: 0; }
    .sf-choice:hover { border-color: rgba(4, 161, 204, .45); }
    .sf-choice:has(input:checked) { border-color: var(--sf-teal); box-shadow: 0 0 0 3px rgba(4, 161, 204, .12); }
    .sf-choice:has(input:checked) i.sf-choice__icon { background: var(--sf-teal); color: #fff; }
    .sf-choice.is-disabled { opacity: .4; cursor: not-allowed; background: var(--sf-soft); }
    .sf-choice__tag { position: absolute; top: -8px; right: 8px; font-size: .62rem; font-weight: 800; padding: .05rem .45rem; border-radius: 99px; background: #fff; border: 1px solid var(--sf-border); color: var(--sf-muted); }

    .sf-langs { display: inline-flex; gap: .25rem; padding: .25rem; border-radius: 12px; background: var(--sf-soft); margin-bottom: 1rem; }
    .sf-langs .nav-link { border-radius: 9px; padding: .35rem .9rem; font-size: .82rem; font-weight: 700; color: var(--sf-muted); }
    .sf-langs .nav-link.active { background: #fff; color: var(--sf-navy); box-shadow: 0 2px 8px rgba(28, 35, 64, .1); }

    .sf-db-preview { display: flex; flex-wrap: wrap; gap: .4rem; padding: .85rem; border-radius: 12px; background: var(--sf-soft); min-height: 52px; }
    .sf-db-preview .sf-chip { background: #fff; border: 1px solid var(--sf-border); }
    .sf-db-preview .sf-chip em { font-style: normal; color: var(--sf-muted); font-weight: 600; }

    .sf-options { width: 100%; }
    .sf-options th { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: var(--sf-muted); font-weight: 800; padding: .4rem .5rem; }
    .sf-options td { padding: .35rem .5rem; vertical-align: middle; }
    .sf-options tr.sortable-ghost td { background: rgba(4, 161, 204, .08); }
    .sf-options .form-control { font-size: .84rem; }

    /* Live preview */
    .sf-preview { position: sticky; top: 90px; }
    .sf-preview__frame { border-radius: 16px; padding: 1.25rem; background: #fff; border: 1px solid var(--sf-border); }
    .sf-preview__label { font-weight: 800; font-size: .95rem; color: #1a1a2e; margin-bottom: .75rem; }
    .sf-preview__pills { display: flex; flex-wrap: wrap; gap: .4rem; }
    .sf-preview__pills span { padding: .4rem .9rem; border-radius: 99px; border: 1px solid #dfe2ea; font-size: .8rem; color: #3d4460; }
    .sf-preview__pills span.is-on { background: #1a1a2e; border-color: #1a1a2e; color: #fff; }
    .sf-preview__checks { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
    .sf-preview__checks label { display: flex; gap: .45rem; align-items: center; font-size: .8rem; color: #3d4460; margin: 0; }
    .sf-preview__range { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
    .sf-preview__range div { border: 1px solid #dfe2ea; border-radius: 10px; padding: .45rem .7rem; font-size: .8rem; color: var(--sf-muted); }
    .sf-preview__range div b { display: block; font-size: .65rem; text-transform: uppercase; letter-spacing: .06em; color: #3d4460; }
    .sf-preview__params { margin-top: 1rem; padding-top: .9rem; border-top: 1px dashed var(--sf-border); font-size: .74rem; color: var(--sf-muted); }
    .sf-preview__params code { color: var(--sf-navy); background: var(--sf-soft); padding: .1rem .4rem; border-radius: 6px; word-break: break-all; }

    .sf-foot { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
</style>
@endpush
