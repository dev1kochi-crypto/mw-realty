{{-- Card / status / progress styles shared by the Integrations pages (the Facebook page has its own copy). --}}
@once
@push('styles')
<style>
    .intg-card { border-radius: 16px; overflow: hidden; }
    .intg-card__head { display: flex; gap: 16px; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--portal-border); }
    .intg-logo { flex-shrink: 0; display: grid; place-items: center; width: 52px; height: 52px; border-radius: 14px; background: #1877f2; color: #fff; font-size: 1.6rem; box-shadow: 0 8px 18px rgba(24, 119, 242, .28); }
    .intg-card__title { font-weight: 700; color: var(--portal-primary-dark); margin: 0; }
    .intg-card__sub { font-size: .85rem; color: var(--portal-muted); margin: 2px 0 0; }
    .intg-card__body { padding: 20px 24px; }
    .intg-status { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: .75rem; font-weight: 700; }
    .intg-status--ok { background: #e7f6ee; color: #0f7a43; }
    .intg-status--warn { background: #fff4e0; color: #9a5b00; }
    .intg-status--off { background: #eef0f6; color: var(--portal-muted); }
    .fbbar { position: relative; height: 4px; border-radius: 999px; background: #e3ecfa; overflow: hidden; }
    .fbbar span { position: absolute; top: 0; left: -40%; width: 40%; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #4f8ef7, #1877f2); animation: fbbar 1.2s ease-in-out infinite; }
    @keyframes fbbar { to { left: 100%; } }
    .fbimport { margin-top: 8px; max-width: 260px; }
    .fbimport__label { display: flex; align-items: center; gap: 8px; font-size: .78rem; font-weight: 600; color: #1360c6; margin-bottom: 5px; }
    .fbimport__spin { width: 12px; height: 12px; border-radius: 50%; border: 2px solid #c6dafc; border-top-color: #1877f2; animation: fbspin .8s linear infinite; }
    @keyframes fbspin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .fbbar span, .fbimport__spin { animation-duration: 3s; } }
</style>
@endpush
@endonce
