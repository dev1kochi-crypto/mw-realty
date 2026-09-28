@push('styles')
<style>
    .tk-stat { border-radius: 14px; border: 1px solid rgba(28,35,64,0.07); background: #fff; box-shadow: 0 4px 14px rgba(28,35,64,0.05); padding: 0.9rem 1rem; display: flex; align-items: center; gap: 0.75rem; height: 100%; }
    .tk-stat-icon { width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; }
    .tk-stat-value { font-weight: 800; font-size: 1.2rem; line-height: 1.1; color: #1c2340; }
    .tk-stat-label { font-size: 0.68rem; font-weight: 700; color: #838aa3; text-transform: uppercase; letter-spacing: 0.02em; }
    .tk-ref { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; font-size: 0.78rem; color: #264373; }
    .tk-prio { font-size: 0.78rem; font-weight: 700; color: #64748b; }
    .tk-prio--high { color: #c2410c; }
    .tk-prio--urgent { color: #dc2626; }
    .tk-status--info { background: rgba(4,161,204,0.14); color: #0284a8; }
    .tk-status--primary { background: rgba(38,67,115,0.12); color: #264373; }
    .tk-status--warning { background: rgba(245,158,11,0.18); color: #b45309; }
    .tk-status--success { background: rgba(22,163,74,0.14); color: #16a34a; }
    .tk-status--secondary { background: rgba(100,116,139,0.14); color: #475569; }

    .tk-thread { display: flex; flex-direction: column; gap: 1rem; }
    .tk-msg { display: flex; gap: 0.75rem; max-width: 88%; }
    .tk-msg--admin { align-self: flex-end; flex-direction: row-reverse; }
    .tk-msg-avatar { width: 36px; height: 36px; flex-shrink: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; background: #eef2fb; color: #264373; }
    .tk-msg--admin .tk-msg-avatar { background: linear-gradient(135deg, #264373, #3a5794); color: #fff; }
    .tk-msg-bubble { padding: 0.8rem 1rem; border-radius: 14px; background: #fff; border: 1px solid #e8ebf3; }
    .tk-msg--admin .tk-msg-bubble { background: #f3f6fd; }
    .tk-msg-who { font-size: 0.78rem; font-weight: 700; color: #1c2340; }
    .tk-msg-when { font-weight: 500; color: #838aa3; margin-left: 0.4rem; }
    .tk-msg-body { margin-top: 0.3rem; white-space: pre-line; word-break: break-word; font-size: 0.9rem; }
    .tk-attach { display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.5rem; padding: 0.3rem 0.7rem; border-radius: 8px; background: #f1f3f9; font-size: 0.78rem; font-weight: 600; text-decoration: none; }
    .tk-event { align-self: center; font-size: 0.75rem; color: #838aa3; background: #f5f6fa; border-radius: 50px; padding: 0.25rem 0.8rem; text-align: center; }
    .tk-detail dt { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #838aa3; }
    .tk-detail dd { margin-bottom: 0.75rem; font-weight: 600; }
</style>
@endpush
