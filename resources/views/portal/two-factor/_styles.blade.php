@push('styles')
<style>
    /* Shared by the Security page and the 2FA set-up page. */
    .sec-page { max-width: 1040px; }
    .sec-head { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
    .sec-head__icon { width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #fff; background: linear-gradient(135deg, var(--portal-primary), var(--portal-primary-dark)); box-shadow: 0 10px 22px rgba(36, 67, 115, .25); }
    .sec-head__eyebrow { font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--portal-accent); margin-bottom: .15rem; }
    .sec-head__title { font-size: 1.45rem; font-weight: 800; color: var(--portal-text); margin: 0; }
    .sec-head__sub { color: var(--portal-muted); font-size: .9rem; margin: .15rem 0 0; }

    .sec-card { background: var(--portal-surface); border: 1px solid var(--portal-border); border-radius: var(--portal-radius); box-shadow: var(--portal-shadow); padding: 1.5rem 1.6rem; margin-bottom: 1.25rem; }
    .sec-card__title { display: flex; align-items: center; justify-content: space-between; gap: 1rem; font-size: 1.05rem; font-weight: 800; color: var(--portal-text); padding-bottom: .9rem; margin-bottom: 1rem; border-bottom: 1px solid var(--portal-border); }
    .sec-card__text { color: var(--portal-muted); font-size: .88rem; margin-bottom: 1.1rem; }
    .sec-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }

    .sec-pill { font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; border-radius: 50px; padding: .4rem .9rem; white-space: nowrap; }
    .sec-pill.is-on { background: var(--portal-primary); color: #fff; }
    .sec-pill.is-off { background: #f0f1f7; color: var(--portal-muted); }
    .sec-chip { font-size: .6rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; border-radius: 5px; padding: .18rem .45rem; margin-left: .45rem; vertical-align: middle; }
    .sec-chip.is-green { background: #e3f6ec; color: #0b7a44; }
    .sec-chip.is-grey { background: #f0f1f7; color: var(--portal-muted); }

    .sec-methods { border: 1px solid var(--portal-border); border-radius: 14px; padding: .4rem 1.2rem; }
    .sec-methods__label { font-size: .85rem; font-weight: 800; color: var(--portal-text); padding: .8rem 0 .2rem; }
    .sec-method { display: flex; align-items: center; gap: .9rem; padding: .95rem 0; }
    .sec-method + .sec-method { border-top: 1px solid var(--portal-border); }
    .sec-method__icon { width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: #eef1f8; color: var(--portal-primary); }
    .sec-method__body { flex: 1; min-width: 0; }
    .sec-method__name { font-weight: 700; color: var(--portal-text); }
    .sec-method__desc { font-size: .82rem; color: var(--portal-muted); }

    .sec-switch { width: 2.9em !important; height: 1.55em !important; cursor: pointer; margin: 0 !important; }
    .sec-switch:checked { background-color: var(--portal-primary); border-color: var(--portal-primary); }

    .sec-device { display: flex; align-items: center; gap: .8rem; padding: .7rem 0; font-size: .85rem; }
    .sec-device + .sec-device { border-top: 1px solid var(--portal-border); }
    .sec-device__icon { width: 34px; height: 34px; border-radius: 9px; background: #f3f4fa; color: var(--portal-muted); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

    /* Security page: main column + "How it works" side panel */
    .sec-page:has(.sec-layout) { max-width: 1320px; }
    .sec-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 1.25rem; align-items: start; }
    .sec-aside { position: sticky; top: calc(var(--portal-topbar-h, 80px) + 1rem); }
    .sec-flow { list-style: none; counter-reset: flow; padding: 0; margin: 0; }
    .sec-flow li { counter-increment: flow; position: relative; padding: 0 0 1.1rem 2.5rem; font-size: .84rem; }
    .sec-flow li:last-child { padding-bottom: 0; }
    .sec-flow li::before { content: counter(flow); position: absolute; left: 0; top: 0; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 800; color: var(--portal-muted); background: #f0f1f7; }
    .sec-flow li:not(:last-child)::after { content: ''; position: absolute; left: 12px; top: 30px; bottom: 4px; width: 2px; background: var(--portal-border); }
    .sec-flow li.is-current::before { background: var(--portal-primary); color: #fff; box-shadow: 0 0 0 4px rgba(36, 67, 115, .12); }
    .sec-flow li.is-done::before { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; background: #e3f6ec; color: #0b7a44; }
    .sec-flow strong { display: block; color: var(--portal-text); margin-bottom: .15rem; }
    .sec-flow span { color: var(--portal-muted); line-height: 1.5; }
    .sec-tip { background: linear-gradient(160deg, #f6f8fd, #eef2fa); box-shadow: none; }
    @media (max-width: 1199.98px) {
        .sec-layout { grid-template-columns: 1fr; }
        .sec-aside { position: static; }
    }

    /* Set-up page */
    .tfa-grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); }
    .tfa-steps { padding: 2rem; }
    .tfa-step { display: flex; gap: 1rem; }
    .tfa-step + .tfa-step { margin-top: 1.6rem; }
    .tfa-step__num { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .85rem; color: var(--portal-primary); background: #eef1f8; border: 2px solid #dfe4f0; }
    .tfa-step__title { font-weight: 800; color: var(--portal-text); margin-bottom: .2rem; }
    .tfa-step__text { font-size: .85rem; color: var(--portal-muted); }
    .tfa-apps { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .6rem; }
    .tfa-apps span { font-size: .75rem; font-weight: 700; color: var(--portal-primary); background: #f3f5fb; border: 1px solid var(--portal-border); border-radius: 50px; padding: .25rem .7rem; }
    .tfa-qr-panel { background: linear-gradient(160deg, #f6f8fd, #eef2fa); border-left: 1px solid var(--portal-border); padding: 2rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; border-radius: 0 var(--portal-radius) var(--portal-radius) 0; }
    .tfa-qr { background: #fff; padding: 14px; border-radius: 16px; box-shadow: 0 12px 30px rgba(36, 67, 115, .12); }
    .tfa-qr svg { display: block; width: 196px; height: 196px; }
    .tfa-qr-label { font-size: .78rem; font-weight: 700; color: var(--portal-muted); margin: 1rem 0 .4rem; }
    .tfa-key { display: inline-flex; align-items: center; gap: .5rem; background: #fff; border: 1px dashed #c4ccdd; border-radius: 10px; padding: .45rem .5rem .45rem .8rem; font-family: 'Courier New', monospace; font-weight: 700; font-size: .85rem; letter-spacing: .08em; color: var(--portal-primary); word-break: break-all; }
    .tfa-key button { border: 0; background: #eef1f8; color: var(--portal-primary); border-radius: 7px; width: 30px; height: 30px; flex-shrink: 0; }
    .tfa-key button:hover { background: var(--portal-primary); color: #fff; }

    .tfa-otp { display: flex; gap: .5rem; margin: .7rem 0 1rem; }
    .tfa-otp input { width: 46px; height: 54px; text-align: center; font-size: 1.35rem; font-weight: 800; color: var(--portal-text); border: 1.5px solid var(--portal-border); border-radius: 12px; background: #fbfcfe; transition: border-color .15s, box-shadow .15s; }
    .tfa-otp input:focus { outline: 0; border-color: var(--portal-primary); box-shadow: 0 0 0 4px rgba(36, 67, 115, .12); background: #fff; }
    .tfa-otp.is-invalid input { border-color: #dc3545; }
    .tfa-otp .tfa-otp__gap { width: .4rem; }
    .tfa-error { color: #dc3545; font-size: .82rem; margin: -.4rem 0 .9rem; }

    .tfa-codes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .55rem; margin: 1.2rem 0 1.4rem; }
    .tfa-codes span { font-family: 'Courier New', monospace; font-weight: 700; font-size: 1rem; letter-spacing: .06em; text-align: center; color: var(--portal-primary); background: #f6f8fd; border: 1px solid var(--portal-border); border-radius: 10px; padding: .6rem; }
    .tfa-success-icon { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; color: #fff; background: linear-gradient(135deg, #0f9d58, #34c880); box-shadow: 0 10px 24px rgba(15, 157, 88, .25); margin-bottom: 1rem; }
    .tfa-footer-link { color: var(--portal-muted); font-weight: 600; font-size: .88rem; text-decoration: none; }
    .tfa-footer-link:hover { color: var(--portal-primary); text-decoration: underline; }

    @media (max-width: 991.98px) {
        .tfa-grid { grid-template-columns: 1fr; }
        .tfa-qr-panel { order: -1; border-left: 0; border-bottom: 1px solid var(--portal-border); border-radius: var(--portal-radius) var(--portal-radius) 0 0; }
    }
    @media (max-width: 575.98px) {
        .tfa-steps, .tfa-qr-panel { padding: 1.3rem; }
        .tfa-otp input { width: 40px; height: 48px; font-size: 1.15rem; }
        .sec-card { padding: 1.2rem; }
        .sec-method { flex-wrap: wrap; }
    }
</style>
@endpush
