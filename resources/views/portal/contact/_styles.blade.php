{{-- Shared by the Contact Us / support ticket screens (index, create, show). --}}
@push('styles')
<style>
    .st-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.5rem; }
    .st-head__title { font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--portal-text); }
    .st-head__sub { color: var(--portal-muted); margin: 0.2rem 0 0; font-size: 0.9rem; }

    .st-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .st-stat { display: flex; align-items: center; gap: 0.9rem; padding: 1rem 1.2rem; border-radius: 16px; background: var(--portal-surface); border: 1px solid var(--portal-border); box-shadow: var(--portal-shadow); }
    .st-stat__icon { width: 2.6rem; height: 2.6rem; flex-shrink: 0; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(36, 67, 115, 0.08); color: var(--portal-primary); }
    .st-stat__value { font-size: 1.3rem; font-weight: 800; line-height: 1.1; color: var(--portal-text); }
    .st-stat__label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--portal-muted); }

    .st-tabs { display: flex; gap: 0.4rem; flex-wrap: wrap; }
    .st-tab { padding: 0.35rem 0.85rem; border-radius: 50px; font-size: 0.8rem; font-weight: 700; color: var(--portal-muted); background: var(--portal-bg); border: 1px solid var(--portal-border); text-decoration: none; }
    .st-tab:hover { color: var(--portal-primary); }
    .st-tab.is-active { background: var(--portal-primary); border-color: var(--portal-primary); color: #fff; }

    .st-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; padding: 1rem 1.2rem; border-radius: 14px; background: var(--portal-surface); border: 1px solid var(--portal-border); box-shadow: 0 4px 14px rgba(36, 67, 115, 0.05); margin-bottom: 0.6rem; color: inherit; text-decoration: none; transition: transform 0.15s ease, box-shadow 0.15s ease; }
    .st-row:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(36, 67, 115, 0.1); color: inherit; }
    .st-row--attention { border-left: 4px solid #f59e0b; }
    .st-row__main { flex: 1; min-width: 200px; }
    .st-row__subject { font-weight: 700; color: var(--portal-text); }
    .st-row__meta { font-size: 0.78rem; color: var(--portal-muted); display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-top: 0.15rem; }
    .st-ref { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; font-size: 0.75rem; color: var(--portal-primary); }

    .st-badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.22rem 0.65rem; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
    .st-badge--info { background: rgba(4, 161, 204, 0.12); color: #0284a8; }
    .st-badge--primary { background: rgba(36, 67, 115, 0.1); color: var(--portal-primary); }
    .st-badge--warning { background: rgba(245, 158, 11, 0.15); color: #b45309; }
    .st-badge--success { background: rgba(22, 163, 74, 0.12); color: #16a34a; }
    .st-badge--secondary { background: rgba(100, 116, 139, 0.12); color: #475569; }
    .st-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.12rem 0.5rem; border-radius: 6px; background: var(--portal-bg); font-size: 0.72rem; font-weight: 600; }
    .st-prio--high { color: #c2410c; }
    .st-prio--urgent { color: #dc2626; }

    .st-empty { text-align: center; padding: 3rem 1rem; border-radius: 18px; background: var(--portal-surface); border: 1px dashed var(--portal-border); color: var(--portal-muted); }
    .st-empty i { font-size: 2.4rem; opacity: 0.35; margin-bottom: 0.75rem; }

    .st-contact li { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.9rem; }
    .st-contact li:last-child { margin-bottom: 0; }

    /* Conversation thread */
    .st-thread { display: flex; flex-direction: column; gap: 1rem; }
    .st-msg { display: flex; gap: 0.75rem; max-width: 88%; }
    .st-msg--mine { align-self: flex-end; flex-direction: row-reverse; }
    .st-msg__avatar { width: 2.3rem; height: 2.3rem; flex-shrink: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; background: rgba(36, 67, 115, 0.1); color: var(--portal-primary); }
    .st-msg--support .st-msg__avatar { background: linear-gradient(135deg, var(--portal-primary-dark), var(--portal-primary)); color: #fff; }
    .st-msg__bubble { padding: 0.8rem 1rem; border-radius: 14px; background: var(--portal-surface); border: 1px solid var(--portal-border); }
    .st-msg--mine .st-msg__bubble { background: rgba(36, 67, 115, 0.06); }
    .st-msg__who { font-size: 0.78rem; font-weight: 700; color: var(--portal-text); display: flex; gap: 0.5rem; align-items: baseline; flex-wrap: wrap; }
    .st-msg__when { font-weight: 500; color: var(--portal-muted); }
    .st-msg__body { margin-top: 0.3rem; white-space: pre-line; word-break: break-word; font-size: 0.9rem; }
    .st-attach { display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.5rem; padding: 0.3rem 0.7rem; border-radius: 8px; background: var(--portal-bg); font-size: 0.78rem; font-weight: 600; text-decoration: none; }
    .st-event { align-self: center; font-size: 0.75rem; color: var(--portal-muted); background: var(--portal-bg); border-radius: 50px; padding: 0.25rem 0.8rem; text-align: center; }

    .st-detail dt { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--portal-muted); }
    .st-detail dd { margin-bottom: 0.85rem; font-weight: 600; }

    @media (max-width: 575.98px) { .st-msg { max-width: 100%; } }
</style>
@endpush
