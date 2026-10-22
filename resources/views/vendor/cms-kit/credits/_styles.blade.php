{{-- Shared look of Admin › Credits and Admin › Credit History (same family as Payments). --}}
@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<style>
    .cr-stat { border-radius: 14px; border: 1px solid rgba(28,35,64,0.07); background: #fff; box-shadow: 0 4px 14px rgba(28,35,64,0.05); padding: 0.9rem 1rem; display: flex; align-items: center; gap: 0.75rem; height: 100%; }
    .cr-stat-icon { width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; }
    .cr-stat-value { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.2rem; line-height: 1.1; color: #1c2340; }
    .cr-stat-label { font-size: 0.68rem; font-weight: 700; color: #838aa3; text-transform: uppercase; letter-spacing: 0.02em; }
    .cr-panel { border-radius: 14px; background: #fff; border: 1px solid rgba(28,35,64,0.07); box-shadow: 0 4px 14px rgba(28,35,64,0.05); }
    .cr-panel .form-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: #838aa3; margin-bottom: 0.3rem; }
    .cr-title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: #1c2340; font-size: 1.05rem; margin-bottom: 0.15rem; }
    .cr-title i { color: #04a1cc; width: 1.3rem; }
    .cr-step { display: flex; gap: 0.75rem; align-items: flex-start; padding: 0.9rem 1rem; border-radius: 12px; background: #f6f8fc; height: 100%; }
    .cr-step-num { width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: #264373; color: #fff; font-weight: 800; font-size: 0.8rem; }
    .cr-switch { display: flex; align-items: center; gap: 0.9rem; padding: 0.75rem 1rem; border-radius: 12px; border: 1px solid; }
    .cr-switch--on { background: rgba(15,157,88,0.07); border-color: rgba(15,157,88,0.25); }
    .cr-switch--off { background: #f6f7fa; border-color: rgba(28,35,64,0.12); }
    .cr-switch .form-check-input { width: 2.8em; height: 1.45em; cursor: pointer; margin: 0; }
    .cr-switch-state { font-weight: 800; font-size: 0.95rem; }
    .cr-avatar { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 0.78rem; }
    .cr-type { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border-radius: 50px; font-size: 0.72rem; font-weight: 700; }
    .cr-type--topup { background: rgba(15,157,88,0.1); color: #0f9d58; }
    .cr-type--grant { background: rgba(4,161,204,0.1); color: #0386aa; }
    .cr-type--spend { background: rgba(38,67,115,0.1); color: #264373; }
    .cr-type--refund { background: rgba(224,142,11,0.12); color: #b8730a; }
    .cr-type--expire { background: #f1f3f8; color: #5b6478; }
    .cr-credits { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; }
    .cr-quick a { font-size: 0.75rem; font-weight: 700; text-decoration: none; padding: 0.25rem 0.65rem; border-radius: 50px; background: #f1f3f8; color: #5b6478; }
    .cr-quick a:hover, .cr-quick a.active { background: #e3e8f4; color: #264373; }
    .cr-modal-content { border: none; border-radius: 20px; box-shadow: 0 24px 60px rgba(28,35,64,0.22); }
    .cr-modal-icon { width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 1.1rem; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .cr-modal-icon.tone-success { background: rgba(15,157,88,0.1); color: #0f9d58; }
    .cr-modal-icon.tone-warning { background: rgba(224,142,11,0.12); color: #e08e0b; }
    .cr-modal-icon.tone-primary { background: rgba(4,161,204,0.1); color: #04a1cc; }
</style>
@endpush
