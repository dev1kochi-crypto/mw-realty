@extends('portal.layouts.app')

@section('title', 'Dashboard')

@php
    $plural = fn ($w, $n) => \Illuminate\Support\Str::plural($w, $n);
    $leadsTotal = $stats['total_leads'];
    $name = $owner ? $owner->displayName() : ($cmsActor->name ?? 'Super Admin');
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $flow = $stagePipeline->where('is_closed', 0)->values();
    $winRate = $pipeline['win_rate'];
    $gaugeLen = round(($winRate ?? 0) / 100 * 125.66, 1); // r=40 half circle ≈ 125.66
    $bestDayIdx = array_sum($weekdayLeads) > 0 ? array_search(max($weekdayLeads), $weekdayLeads) : null;
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $memberId = $owner ? str_pad((string) $owner->id, 4, '0', STR_PAD_LEFT) : 'ADMIN';
    $openPct = $leadsTotal ? round($pipeline['open'] / $leadsTotal * 100) : 0;
    $wonPct = $leadsTotal ? round($pipeline['won'] / $leadsTotal * 100) : 0;
    $saleCount = (int) ($listingTypeBreakdown['sale'] ?? 0);
    $rentCount = (int) ($listingTypeBreakdown['rent'] ?? 0);
    $splitTotal = max($saleCount + $rentCount, 1);
    $typeMax = max($propertyTypeBreakdown->max() ?? 0, 1);
    $srcMax = max($sourceRows->max('total') ?? 0, 1);
    // Gallery leads with a listing that has a photo, so the large tile is never blank.
    $showcase = $showcase->sortBy(fn ($l) => empty($l->galleryImages()[0]['url'] ?? null) ? 1 : 0)->values();
    $dotColors = ['red' => '#ff6b86', 'navy' => '#8fb3ee', 'amber' => '#f5c26b', 'grey' => '#c3c7d6'];
@endphp

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<style>
    .dl { --red: #ca2844; --red-d: #8f1c33; --navy: #1b3358; --ink: #1a1f36; --ink-2: #4b5068; --muted: #7c8199; --line: #ebedf4; --gold: #c9a55c; --good: #2f855a; }
    .dl .num { font-weight: 800; letter-spacing: -0.02em; }
    .dl .serif { font-family: 'Playfair Display', Georgia, serif; }
    .dl .row { --bs-gutter-x: 0.85rem; --bs-gutter-y: 0.85rem; }
    @keyframes dlUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    .dl-anim { animation: dlUp 0.5s cubic-bezier(0.22, 1, 0.36, 1) both; }
    @media (prefers-reduced-motion: reduce) { .dl-anim { animation: none; } }

    /* Account status notice */
    .dl-notice { display: flex; gap: 0.8rem; align-items: flex-start; border-radius: 14px; padding: 0.85rem 1rem; margin-bottom: 0.85rem; border: 1px solid; font-size: 0.84rem; }
    .dl-notice.pending { background: #fffaf2; border-color: #f0dfc1; }
    .dl-notice.rejected { background: #fff7f8; border-color: #f1c9d0; }
    .dl-notice .ic { width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
    .dl-notice.pending .ic { background: rgba(183,121,31,0.12); color: #b7791f; }
    .dl-notice.rejected .ic { background: rgba(184,50,50,0.1); color: #b83232; }

    /* ---------- Photo hero ---------- */
    .dl-hero { position: relative; border-radius: 20px; margin-bottom: 3rem; color: #fff; }
    .dl-hero-bg { position: absolute; inset: 0; border-radius: 20px; overflow: hidden; background: #1b3358 center / cover no-repeat; box-shadow: 0 16px 36px rgba(27,51,88,0.22); }
    .dl-hero-bg::before { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, rgba(14,24,46,0.95) 0%, rgba(20,36,66,0.84) 45%, rgba(27,51,88,0.4) 78%, rgba(143,28,51,0.4) 100%); }
    .dl-hero-inner { position: relative; z-index: 1; display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; padding: 1.2rem 1.5rem 3.4rem; flex-wrap: wrap; }
    .dl-eyebrow { display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.64rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.14em; color: var(--gold); }
    .dl-eyebrow::before { content: ''; width: 20px; height: 1px; background: var(--gold); }
    .dl-hello { font-size: 1.65rem; line-height: 1.15; margin: 0.35rem 0 0.4rem; font-weight: 700; }
    .dl-hello em { font-style: italic; color: #ffd6dd; }
    .dl-lede { font-size: 0.82rem; opacity: 0.88; max-width: 560px; margin: 0; line-height: 1.5; }
    .dl-lede strong { color: #fff; }
    .dl-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.7rem; }
    .dl-chip { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.7rem; font-weight: 700; color: #fff; text-decoration: none; padding: 0.28rem 0.7rem; border-radius: 50px; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.22); backdrop-filter: blur(8px); transition: background 0.15s ease; }
    .dl-chip:hover { background: rgba(255,255,255,0.22); color: #fff; }
    .dl-chip .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--c, #fff); }

    /* Membership card */
    .dl-card { width: 270px; max-width: 100%; aspect-ratio: 1.7; border-radius: 16px; padding: 0.9rem 1rem; position: relative; overflow: hidden; color: #fff; display: flex; flex-direction: column; background: linear-gradient(135deg, #b8233f 0%, #7d1a2d 48%, #1b3358 100%); box-shadow: 0 14px 30px rgba(0,0,0,0.32), inset 0 1px 0 rgba(255,255,255,0.25); transform: rotate(-2deg); transition: transform 0.3s ease; }
    .dl-card:hover { transform: rotate(0deg) translateY(-3px); }
    .dl-card::before { content: ''; position: absolute; right: -50px; top: -50px; width: 160px; height: 160px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.22), transparent 70%); }
    .dl-card::after { content: ''; position: absolute; left: -30%; bottom: -70%; width: 160%; height: 120%; background: repeating-linear-gradient(115deg, rgba(255,255,255,0.04) 0 2px, transparent 2px 12px); }
    .dl-card > * { position: relative; z-index: 1; }
    .dl-card-top { display: flex; justify-content: space-between; align-items: center; }
    .dl-card-brand { font-size: 0.58rem; font-weight: 800; letter-spacing: 0.14em; opacity: 0.9; }
    .dl-chipicon { width: 30px; height: 22px; border-radius: 5px; background: linear-gradient(135deg, #f3dca0, #c9a55c); position: relative; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.15); }
    .dl-chipicon::before { content: ''; position: absolute; inset: 5px 6px; border: 1px solid rgba(0,0,0,0.25); border-radius: 2px; }
    .dl-card-plan { font-size: 1.25rem; margin-top: auto; line-height: 1; }
    .dl-card-sub { font-size: 0.58rem; letter-spacing: 0.1em; text-transform: uppercase; opacity: 0.78; font-weight: 700; margin-top: 0.25rem; }
    .dl-card-bar { height: 4px; border-radius: 50px; background: rgba(255,255,255,0.2); overflow: hidden; margin: 0.45rem 0 0.35rem; }
    .dl-card-bar span { display: block; height: 100%; border-radius: 50px; background: linear-gradient(90deg, #f3dca0, #fff); }
    .dl-card-foot { display: flex; justify-content: space-between; align-items: flex-end; font-size: 0.62rem; }
    .dl-card-foot .holder { font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%; }
    .dl-card-foot .id { font-family: ui-monospace, monospace; opacity: 0.8; letter-spacing: 0.1em; }
    .dl-card-link { position: absolute; right: 0.9rem; top: 2.3rem; z-index: 2; font-size: 0.58rem; font-weight: 800; color: #fff; text-decoration: none; background: rgba(255,255,255,0.18); border-radius: 50px; padding: 0.15rem 0.5rem; }
    .dl-card-link:hover { background: rgba(255,255,255,0.3); color: #fff; }
    .dl-card-row { display: flex; justify-content: space-between; font-size: 0.7rem; padding: 0.18rem 0; border-bottom: 1px solid rgba(255,255,255,0.14); }

    /* Shortcut dock */
    .dl-dock { position: absolute; left: 50%; bottom: -2.3rem; transform: translateX(-50%); z-index: 3; display: flex; gap: 0.15rem; padding: 0.35rem; border-radius: 16px; background: rgba(255,255,255,0.97); box-shadow: 0 12px 28px rgba(27,51,88,0.18); max-width: calc(100% - 2rem); overflow-x: auto; }
    .dl-dock a { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; min-width: 70px; padding: 0.35rem 0.3rem; border-radius: 11px; text-decoration: none; color: var(--ink-2); font-size: 0.62rem; font-weight: 700; text-align: center; line-height: 1.15; transition: background 0.15s ease; }
    .dl-dock a i { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.8rem; background: var(--g); box-shadow: 0 4px 10px rgba(0,0,0,0.12); transition: transform 0.18s ease; }
    .dl-dock a:hover { background: #f5f6fb; color: var(--ink); }
    .dl-dock a:hover i { transform: translateY(-3px) scale(1.05); }
    .g-red { --g: linear-gradient(135deg, #e0486a, #a8233b); } .g-navy { --g: linear-gradient(135deg, #3a6bb5, #1b3358); } .g-teal { --g: linear-gradient(135deg, #34b3a8, #16706b); }
    .g-green { --g: linear-gradient(135deg, #4cc28a, #22704a); } .g-amber { --g: linear-gradient(135deg, #f0b95a, #b7791f); } .g-violet { --g: linear-gradient(135deg, #9a7de0, #5a3f9e); } .g-grey { --g: linear-gradient(135deg, #a3a8bf, #6b7094); }

    /* ---------- Compact stat cards ---------- */
    .dl-stat { position: relative; overflow: hidden; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 0.75rem 0.85rem 2.1rem; height: 100%; text-decoration: none; color: inherit; display: block; box-shadow: 0 3px 12px rgba(27,51,88,0.04); transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .dl-stat:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(27,51,88,0.1); color: inherit; }
    .dl-stat-top { display: flex; justify-content: space-between; align-items: center; }
    .dl-stat-ic { width: 30px; height: 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.75rem; background: var(--g); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .dl-delta { font-size: 0.6rem; font-weight: 800; padding: 0.12rem 0.45rem; border-radius: 50px; }
    .dl-delta.up { color: var(--good); background: rgba(47,133,90,0.1); } .dl-delta.down { color: #b83232; background: rgba(184,50,50,0.1); } .dl-delta.flat { color: var(--muted); background: #f3f4f8; }
    .dl-delta.warn { color: #b83232; background: rgba(184,50,50,0.1); }
    .dl-stat-value { font-size: 1.45rem; color: var(--ink); line-height: 1; margin-top: 0.55rem; }
    .dl-stat-label { font-size: 0.72rem; font-weight: 700; color: var(--ink-2); margin-top: 0.2rem; }
    .dl-stat-note { font-size: 0.64rem; color: var(--muted); margin-top: 0.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dl-stat-spark { position: absolute; left: 0; right: 0; bottom: 0; height: 32px; }
    .dl-stat-meter { position: absolute; left: 0.85rem; right: 0.85rem; bottom: 0.8rem; height: 5px; border-radius: 50px; background: #f0f1f6; overflow: hidden; }
    .dl-stat-meter span { display: block; height: 100%; border-radius: 50px; background: var(--g); }

    /* ---------- Panels ---------- */
    .dl-panel { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 0.9rem 1rem; height: 100%; box-shadow: 0 3px 12px rgba(27,51,88,0.04); }
    .dl-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.6rem; margin-bottom: 0.7rem; }
    .dl-title { font-size: 0.98rem; font-weight: 700; color: var(--ink); margin: 0; line-height: 1.2; }
    .dl-sub { font-size: 0.66rem; color: var(--muted); margin: 0.1rem 0 0; }
    .dl-link { font-size: 0.68rem; font-weight: 800; color: var(--red); text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.3rem; }
    .dl-link:hover { color: var(--red-d); }
    .dl-link i { transition: transform 0.15s ease; } .dl-link:hover i { transform: translateX(3px); }
    .dl-empty { text-align: center; color: var(--muted); font-size: 0.78rem; padding: 1rem 0; }
    .dl-chart { position: relative; height: 245px; }
    .dl-legend { display: flex; gap: 0.75rem; }
    .dl-legend span { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.66rem; font-weight: 700; color: var(--ink-2); }
    .dl-legend i { width: 14px; height: 3px; border-radius: 3px; display: inline-block; }

    /* Gauge */
    .dl-gauge { position: relative; width: 165px; margin: 0 auto; }
    .dl-gauge svg { width: 100%; display: block; }
    .dl-gauge .c { position: absolute; left: 0; right: 0; bottom: 0; text-align: center; }
    .dl-gauge .c .num { font-size: 1.6rem; color: var(--ink); line-height: 1; display: block; }
    .dl-gauge .c small { font-size: 0.58rem; color: var(--muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
    .dl-outcomes { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.35rem; margin-top: 0.7rem; }
    .dl-outcomes div { text-align: center; border-radius: 10px; padding: 0.4rem 0.2rem; background: var(--b); }
    .dl-outcomes .num { font-size: 1.05rem; color: var(--ink); display: block; line-height: 1.1; }
    .dl-outcomes small { font-size: 0.56rem; font-weight: 800; color: var(--ink-2); text-transform: uppercase; letter-spacing: 0.05em; }
    .dl-conv-foot { font-size: 0.66rem; color: var(--muted); text-align: center; margin-top: 0.5rem; }

    /* Buyer insights */
    .dl-best { display: flex; align-items: center; gap: 0.6rem; padding: 0.45rem 0.6rem; border-radius: 11px; background: linear-gradient(135deg, #fdf0f2, #eef2fa); margin-bottom: 0.4rem; }
    .dl-best i { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #fff; color: var(--red); font-size: 0.75rem; }
    .dl-best .l { font-size: 0.56rem; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
    .dl-best .v { font-size: 0.8rem; font-weight: 800; color: var(--ink); }
    .dl-wd { position: relative; height: 92px; }
    .dl-src { display: flex; align-items: center; gap: 0.5rem; padding: 0.16rem 0; font-size: 0.7rem; font-weight: 600; color: var(--ink-2); }
    .dl-src .bar { flex: 1; height: 5px; border-radius: 50px; background: #f0f1f6; overflow: hidden; }
    .dl-src .bar span { display: block; height: 100%; border-radius: 50px; }
    .dl-src strong { color: var(--ink); min-width: 16px; text-align: right; }
    .dl-src .name { width: 92px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Chevron pipeline */
    .dl-pipe-head { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .dl-flow { display: flex; gap: 3px; flex: 1; min-width: 0; }
    .dl-chev { flex: 1 1 0; min-width: 0; position: relative; padding: 0.45rem 1.2rem 0.45rem 1.35rem; color: #fff; background: var(--sc); clip-path: polygon(0 0, calc(100% - 12px) 0, 100% 50%, calc(100% - 12px) 100%, 0 100%, 12px 50%); text-decoration: none; transition: filter 0.15s ease; display: flex; align-items: baseline; justify-content: space-between; gap: 0.4rem; }
    .dl-chev:first-child { clip-path: polygon(0 0, calc(100% - 12px) 0, 100% 50%, calc(100% - 12px) 100%, 0 100%); padding-left: 0.85rem; border-radius: 10px 0 0 10px; }
    .dl-chev:hover { filter: brightness(1.08); color: #fff; }
    .dl-chev .n { font-size: 0.66rem; font-weight: 700; opacity: 0.95; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dl-chev .v { font-size: 1rem; font-weight: 800; line-height: 1.1; white-space: nowrap; }
    .dl-chev .v small { font-size: 0.6rem; font-weight: 600; opacity: 0.85; margin-left: 0.15rem; }
    .dl-flow-end { display: flex; gap: 0.35rem; flex-wrap: wrap; }
    .dl-flow-end span { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.7rem; font-weight: 700; color: var(--ink-2); padding: 0.3rem 0.6rem; border-radius: 9px; background: #f6f7fb; }
    .dl-flow-end strong { color: var(--ink); font-size: 0.82rem; }

    /* Enquiries */
    .dl-lead { display: flex; align-items: center; gap: 0.6rem; padding: 0.38rem 0.4rem; border-radius: 10px; text-decoration: none; color: inherit; transition: background 0.15s ease; }
    .dl-lead:hover { background: #f7f8fc; color: inherit; }
    .dl-av { width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 0.7rem; background: var(--g); position: relative; }
    .dl-av::after { content: ''; position: absolute; right: -1px; bottom: -1px; width: 9px; height: 9px; border-radius: 50%; background: var(--sc, #9aa1b8); border: 2px solid #fff; }
    .dl-lead .n { font-size: 0.78rem; font-weight: 700; color: var(--ink); line-height: 1.25; }
    .dl-lead .p { font-size: 0.66rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dl-lead .p i { color: var(--red); }
    .dl-lead .st { font-size: 0.6rem; font-weight: 800; padding: 0.14rem 0.5rem; border-radius: 50px; white-space: nowrap; }
    .dl-lead .t { font-size: 0.62rem; color: var(--muted); white-space: nowrap; min-width: 48px; text-align: right; }

    /* Gallery (compact 2x2) */
    .dl-gallery { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-rows: 118px; gap: 0.5rem; }
    .dl-gallery.n-1 { grid-template-columns: 1fr; grid-auto-rows: 240px; }
    .dl-gallery.n-3 .dl-gcard:first-child { grid-column: span 2; }
    .dl-gcard { position: relative; border-radius: 12px; overflow: hidden; text-decoration: none; background: linear-gradient(135deg, #e8edf7, #fbecef); }
    .dl-gcard img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease; }
    .dl-gcard:hover img { transform: scale(1.06); }
    .dl-gcard .ph { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #b9bfd3; }
    .dl-gcard .ov { position: absolute; inset: auto 0 0 0; padding: 1.4rem 0.6rem 0.45rem; background: linear-gradient(180deg, transparent, rgba(10,16,34,0.88)); color: #fff; }
    .dl-gcard .t { font-size: 0.74rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dl-gcard .m { display: flex; justify-content: space-between; gap: 0.4rem; font-size: 0.62rem; opacity: 0.92; }
    .dl-gcard .price { font-weight: 800; color: #ffd6dd; white-space: nowrap; }
    .dl-gcard .tag { position: absolute; left: 0.45rem; top: 0.45rem; display: flex; gap: 0.25rem; }
    .dl-gcard .tag span { font-size: 0.54rem; font-weight: 800; padding: 0.14rem 0.45rem; border-radius: 50px; background: rgba(255,255,255,0.92); color: var(--ink); text-transform: uppercase; letter-spacing: 0.04em; }
    .dl-gcard .tag span.hot { background: var(--red); color: #fff; }

    /* Inventory */
    .dl-split { display: flex; height: 8px; border-radius: 50px; overflow: hidden; gap: 2px; margin: 0.3rem 0 0.25rem; }
    .dl-split-l { display: flex; justify-content: space-between; font-size: 0.68rem; font-weight: 600; color: var(--ink-2); }
    .dl-split-l strong { color: var(--ink); }
    .dl-type { display: grid; grid-template-columns: minmax(70px, 42%) 1fr auto; gap: 0.5rem; align-items: center; padding: 0.16rem 0; font-size: 0.7rem; color: var(--ink-2); font-weight: 600; }
    .dl-type .tr { height: 5px; border-radius: 50px; background: #f0f1f6; overflow: hidden; }
    .dl-type .tr span { display: block; height: 100%; border-radius: 50px; background: linear-gradient(90deg, #34b3a8, #16706b); }
    .dl-type strong { color: var(--ink); min-width: 14px; text-align: right; }
    .dl-type .l { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dl-mini { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.35rem; margin-top: 0.6rem; }
    .dl-mini div { border-radius: 9px; padding: 0.35rem 0.4rem; background: #f6f7fb; text-align: center; }
    .dl-mini .num { font-size: 0.95rem; color: var(--ink); display: block; line-height: 1.1; }
    .dl-mini small { font-size: 0.54rem; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; }

    @media (max-width: 1199.98px) {
        .dl-flow { flex-wrap: wrap; flex-basis: 100%; } .dl-chev { flex-basis: 30%; }
    }
    @media (max-width: 767.98px) {
        .dl-hello { font-size: 1.35rem; }
        .dl-hero-inner { padding: 1rem 1rem 4.2rem; }
        .dl-dock { left: 0.75rem; right: 0.75rem; transform: none; }
        .dl-card { transform: none; }
    }
</style>
@endpush

@section('content')
<div class="dl">

@if($owner && $owner->status !== 'approved')
    <div class="dl-notice {{ $owner->status === 'rejected' ? 'rejected' : 'pending' }}">
        <span class="ic"><i class="fas {{ $owner->status === 'rejected' ? 'fa-ban' : 'fa-lock' }}"></i></span>
        <div class="flex-grow-1">
            @if($owner->status === 'pending')
                <div class="fw-bold mb-1">Your CRM access is locked for now</div>
                <p class="text-muted mb-2">Leads, Reports and property listing unlock once your KYC documents are reviewed and Super Admin approves your account. Complete your profile and upload your documents now so approval isn't held up.</p>
            @else
                <div class="fw-bold mb-1">Your account application was rejected</div>
                <p class="text-muted mb-2">@if($owner->rejection_reason) Reason: {{ $owner->rejection_reason }}. @endif Fix your profile/documents and resubmit. CRM access unlocks once Super Admin approves you.</p>
            @endif
            <a href="{{ route('portal.profile.edit') }}" class="btn btn-portal-primary btn-sm">{{ $owner->status === 'rejected' ? 'Fix & Resubmit' : 'Complete Profile' }}</a>
        </div>
    </div>
@endif

{{-- ============ Hero · membership card · shortcut dock ============ --}}
<div class="dl-hero dl-anim">
    <div class="dl-hero-bg" style="background-image: url('{{ $heroImage }}');"></div>
    <div class="dl-hero-inner">
        <div>
            <span class="dl-eyebrow">{{ now()->format('l, d F Y') }}{{ $isAdmin ? ' · Global view' : '' }}</span>
            <h1 class="dl-hello serif">{{ $greeting }}, <em>{{ $name }}</em></h1>
            <p class="dl-lede">
                <strong>{{ $trends['leads']['current'] }} new {{ $plural('enquiry', $trends['leads']['current']) }}</strong>
                and <strong>{{ $trends['properties']['current'] }} {{ $plural('listing', $trends['properties']['current']) }}</strong> in the last 30 days,
                with <strong>{{ $pipeline['open'] }} {{ $plural('deal', $pipeline['open']) }}</strong> in motion.
            </p>
            <div class="dl-chips">
                @forelse($alerts as $a)
                    <a href="{{ $a['url'] }}" class="dl-chip" style="--c: {{ $dotColors[$a['tone']] ?? '#fff' }};"><span class="dot"></span>{{ $a['count'] }} {{ $a['label'] }}</a>
                @empty
                    <span class="dl-chip" style="--c: #6fd39b;"><span class="dot"></span>Everything is up to date</span>
                @endforelse
            </div>
        </div>

        @if($planUsage)
            <div class="dl-card">
                <div class="dl-card-top"><span class="dl-card-brand">MW REALTY · PARTNER</span><span class="dl-chipicon"></span></div>
                <a href="{{ route('portal.plans.index') }}" class="dl-card-link">{{ $planUsage['remaining'] === 0 ? 'UPGRADE' : 'MANAGE' }}</a>
                <div class="dl-card-plan serif">{{ $planUsage['name'] }}</div>
                <div class="dl-card-sub">
                    {{ $planUsage['limit'] !== null ? $planUsage['used'] . ' of ' . $planUsage['limit'] . ' listings' : $planUsage['used'] . ' listings · unlimited' }}
                    · Reports {{ $planUsage['reports'] ? 'on' : 'off' }}{{ $planUsage['agents'] !== null ? ' · ' . $planUsage['agents'] . ' ' . $plural('agent', $planUsage['agents']) : '' }}
                </div>
                <div class="dl-card-bar"><span style="width: {{ $planUsage['pct'] ?? 100 }}%;"></span></div>
                <div class="dl-card-foot"><span class="holder">{{ $name }}</span><span class="id">•••• {{ $memberId }}</span></div>
            </div>
        @else
            <div class="dl-card">
                <div class="dl-card-top"><span class="dl-card-brand">MW REALTY · TOP PARTNERS</span><span class="dl-chipicon"></span></div>
                <div class="mt-auto">
                    @forelse($topOwners->take(3) as $i => $row)
                        <div class="dl-card-row"><span class="text-truncate me-2">{{ $i + 1 }}. {{ $row->type === 'company' ? ($row->company_name ?: $row->name) : $row->name }}</span><strong>{{ $row->total }}</strong></div>
                    @empty
                        <div style="opacity: 0.8; font-size: 0.72rem;">No leads assigned yet.</div>
                    @endforelse
                </div>
                <div class="dl-card-foot mt-2"><span class="holder">Global view</span><span class="id">•••• {{ $memberId }}</span></div>
            </div>
        @endif
    </div>

    <nav class="dl-dock" aria-label="Shortcuts">
        @foreach($shortcuts as $s)
            <a href="{{ $s['url'] }}" class="g-{{ $s['tone'] }}"><i class="fas {{ $s['icon'] }}"></i>{{ $s['label'] }}</a>
        @endforeach
    </nav>
</div>

{{-- ============ 6 compact KPIs ============ --}}
<div class="row mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('portal.crm.leads.index') }}" class="dl-stat g-red">
            <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-address-book"></i></span><span class="dl-delta {{ $deltas['leads']['dir'] }}">{{ $deltas['leads']['text'] }}</span></div>
            <div class="dl-stat-value num"><span class="dz-count" data-to="{{ $leadsTotal }}">{{ $leadsTotal }}</span></div>
            <div class="dl-stat-label">Total enquiries</div>
            <div class="dl-stat-note">{{ $trends['leads']['current'] }} in the last 30 days</div>
            <div class="dl-stat-spark"><canvas class="dz-spark" data-values='@json($activity['leads']['data'])' data-color="#ca2844"></canvas></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('portal.crm.leads.index', ['status' => 'active']) }}" class="dl-stat g-navy">
            <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-bolt"></i></span><span class="dl-delta flat">{{ $openPct }}%</span></div>
            <div class="dl-stat-value num"><span class="dz-count" data-to="{{ $pipeline['open'] }}">{{ $pipeline['open'] }}</span></div>
            <div class="dl-stat-label">Deals in progress</div>
            <div class="dl-stat-note">of all enquiries still open</div>
            <div class="dl-stat-meter"><span style="width: {{ $openPct }}%;"></span></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('portal.crm.leads.index') }}" class="dl-stat g-green">
            <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-trophy"></i></span><span class="dl-delta up">{{ $winRate !== null ? $winRate . '%' : '—' }}</span></div>
            <div class="dl-stat-value num"><span class="dz-count" data-to="{{ $pipeline['won'] }}">{{ $pipeline['won'] }}</span></div>
            <div class="dl-stat-label">Deals won</div>
            <div class="dl-stat-note">{{ $pipeline['lost'] }} lost · {{ $winRate !== null ? $winRate . '% win rate' : 'none closed yet' }}</div>
            <div class="dl-stat-meter"><span style="width: {{ $winRate ?? 0 }}%;"></span></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('portal.crm.leads.index') }}" class="dl-stat g-amber">
            <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-hourglass-half"></i></span>@if($staleLeads > 0)<span class="dl-delta warn">Act now</span>@endif</div>
            <div class="dl-stat-value num"><span class="dz-count" data-to="{{ $staleLeads }}">{{ $staleLeads }}</span></div>
            <div class="dl-stat-label">Need follow-up</div>
            <div class="dl-stat-note">untouched for 2+ days</div>
            <div class="dl-stat-meter"><span style="width: {{ $leadsTotal ? round($staleLeads / $leadsTotal * 100) : 0 }}%;"></span></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('portal.properties.index') }}" class="dl-stat g-teal">
            <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-building"></i></span><span class="dl-delta {{ $deltas['properties']['dir'] }}">{{ $deltas['properties']['text'] }}</span></div>
            <div class="dl-stat-value num"><span class="dz-count" data-to="{{ $stats['total_properties'] }}">{{ $stats['total_properties'] }}</span></div>
            <div class="dl-stat-label">Listings</div>
            <div class="dl-stat-note">{{ $stats['active_properties'] }} active · {{ $stats['inactive_properties'] }} inactive</div>
            <div class="dl-stat-spark"><canvas class="dz-spark" data-values='@json($activity['properties']['data'])' data-color="#1f8a8a"></canvas></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('portal.properties.index') }}" class="dl-stat g-violet">
            <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-star"></i></span>@if($featuredExpiring > 0)<span class="dl-delta warn">{{ $featuredExpiring }} expiring</span>@endif</div>
            <div class="dl-stat-value num"><span class="dz-count" data-to="{{ $stats['featured_properties'] }}">{{ $stats['featured_properties'] }}</span></div>
            <div class="dl-stat-label">Featured</div>
            <div class="dl-stat-note">{{ $stats['total_properties'] ? round($stats['featured_properties'] / $stats['total_properties'] * 100) : 0 }}% of listings promoted</div>
            <div class="dl-stat-meter"><span style="width: {{ $stats['total_properties'] ? round($stats['featured_properties'] / $stats['total_properties'] * 100) : 0 }}%;"></span></div>
        </a>
    </div>
</div>

{{-- ============ Performance · conversion · buyer insights ============ --}}
<div class="row mb-3">
    <div class="col-xl-6">
        <div class="dl-panel">
            <div class="dl-head">
                <div><h6 class="dl-title serif">Performance</h6><p class="dl-sub">Weekly enquiries &amp; new listings, last 12 weeks</p></div>
                <div class="dl-legend"><span><i style="background: #ca2844;"></i> Enquiries</span><span><i style="background: #1b3358;"></i> Listings</span></div>
            </div>
            <div class="dl-chart"><canvas id="dzTrend" data-type="line" data-listing-color="#1b3358"></canvas></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="dl-panel d-flex flex-column align-items-center justify-content-between">
            <div class="dl-head w-100"><div><h6 class="dl-title serif">Conversion</h6><p class="dl-sub">Win rate on closed deals</p></div></div>
            <div class="dl-gauge">
                <svg viewBox="0 0 100 58" aria-hidden="true">
                    <defs><linearGradient id="dlGauge" x1="0" x2="1"><stop offset="0" stop-color="#ca2844"/><stop offset="1" stop-color="#2f855a"/></linearGradient></defs>
                    <path d="M10 50 A40 40 0 0 1 90 50" fill="none" stroke="#eef0f6" stroke-width="9" stroke-linecap="round"/>
                    <path d="M10 50 A40 40 0 0 1 90 50" fill="none" stroke="url(#dlGauge)" stroke-width="9" stroke-linecap="round" stroke-dasharray="{{ $gaugeLen }} 200"/>
                </svg>
                <div class="c"><span class="num">{!! $winRate !== null ? '<span class="dz-count" data-to="' . $winRate . '" data-suffix="%">' . $winRate . '%</span>' : '—' !!}</span><small>Win rate</small></div>
            </div>
            <div class="dl-outcomes w-100">
                <div style="--b: #edf6f1;"><span class="num">{{ $pipeline['won'] }}</span><small>Won</small></div>
                <div style="--b: #fbeff1;"><span class="num">{{ $pipeline['lost'] }}</span><small>Lost</small></div>
                <div style="--b: #eef2fa;"><span class="num">{{ $pipeline['open'] }}</span><small>Open</small></div>
            </div>
            <div class="dl-conv-foot">{{ $wonPct }}% of all enquiries converted to a won deal</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="dl-panel">
            <div class="dl-head"><div><h6 class="dl-title serif">Buyer Insights</h6><p class="dl-sub">When &amp; where enquiries come from</p></div></div>
            @if($bestDayIdx !== null)
                <div class="dl-best"><i class="fas fa-calendar-day"></i><div><div class="l">Busiest day · 90 days</div><div class="v">{{ $days[$bestDayIdx] }} · {{ $weekdayLeads[$bestDayIdx] }} {{ $plural('enquiry', $weekdayLeads[$bestDayIdx]) }}</div></div></div>
                <div class="dl-wd"><canvas id="dzWeekday" data-color="#dfe4f0" data-peak="#ca2844"></canvas></div>
            @endif
            <div class="mt-1">
                @forelse($sourceRows->take(5) as $r)
                    <div class="dl-src"><span class="name">{{ $r['name'] }}</span><span class="bar"><span style="width: {{ round($r['total'] / $srcMax * 100) }}%; background: {{ $r['color'] }};"></span></span><strong>{{ $r['total'] }}</strong></div>
                @empty
                    <div class="dl-empty">No enquiries yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ============ Pipeline strip ============ --}}
<div class="dl-panel mb-3" style="height: auto;">
    <div class="dl-pipe-head">
        <div style="min-width: 150px;"><h6 class="dl-title serif">Deal Pipeline</h6><p class="dl-sub">{{ $isAdmin ? 'All partners, by stage' : 'Your enquiries by stage' }}</p></div>
        @if($flow->isEmpty() && $unstagedLeads === 0 && $pipeline['won'] + $pipeline['lost'] === 0)
            <div class="dl-empty flex-grow-1">No enquiries yet.</div>
        @else
            <div class="dl-flow">
                @if($unstagedLeads > 0)
                    <a href="{{ route('portal.crm.leads.index') }}" class="dl-chev" style="--sc: #9aa1b8;"><span class="n">No stage</span><span class="v">{{ $unstagedLeads }}</span></a>
                @endif
                @foreach($flow as $stage)
                    <a href="{{ route('portal.crm.leads.index') }}" class="dl-chev" style="--sc: {{ $stage->color ?: '#1b3358' }};" title="{{ $stage->name }}: {{ $stage->total }}">
                        <span class="n">{{ $stage->name }}</span>
                        <span class="v">{{ $stage->total }}<small>{{ $leadsTotal ? round($stage->total / $leadsTotal * 100) : 0 }}%</small></span>
                    </a>
                @endforeach
            </div>
            <div class="dl-flow-end">
                <span><i class="fas fa-circle-check" style="color: #2f855a;"></i> Won <strong>{{ $pipeline['won'] }}</strong></span>
                <span><i class="fas fa-circle-xmark" style="color: #b83232;"></i> Lost <strong>{{ $pipeline['lost'] }}</strong></span>
            </div>
            <a href="{{ route('portal.crm.leads.index') }}" class="dl-link">Work leads <i class="fas fa-arrow-right"></i></a>
        @endif
    </div>
</div>

{{-- ============ Enquiries · properties · inventory ============ --}}
<div class="row mb-3">
    <div class="col-xl-5">
        <div class="dl-panel">
            <div class="dl-head">
                <div><h6 class="dl-title serif">Latest Enquiries</h6><p class="dl-sub">Respond fast, fresh leads convert best</p></div>
                <a href="{{ route('portal.crm.leads.index') }}" class="dl-link">All <i class="fas fa-arrow-right"></i></a>
            </div>
            @php $avatarG = ['g-red', 'g-navy', 'g-teal', 'g-amber', 'g-violet', 'g-green']; @endphp
            @forelse($recentLeads as $i => $lead)
                @php $c = $lead->stage?->color ?: '#9aa1b8'; @endphp
                <a href="{{ route('portal.crm.leads.show', $lead->id) }}" class="dl-lead">
                    <span class="dl-av {{ $avatarG[$i % count($avatarG)] }}" style="--sc: {{ $c }};">{{ mb_strtoupper(mb_substr(trim((string) $lead->name) ?: '?', 0, 1)) }}</span>
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="n">{{ $lead->name ?: 'Unknown' }}</div>
                        <div class="p"><i class="fas fa-house me-1"></i>{{ $lead->property?->getTranslation('title') ?? 'General enquiry' }}{{ $lead->source ? ' · ' . $lead->source->name : '' }}</div>
                    </div>
                    <span class="st" style="color: {{ $c }}; background: {{ $c }}1a;">{{ $lead->stage?->name ?? ucfirst($lead->status) }}</span>
                    <span class="t">{{ $lead->created_at?->diffForHumans(null, true) }}</span>
                </a>
            @empty
                <div class="dl-empty">No enquiries yet.</div>
            @endforelse
        </div>
    </div>
    <div class="col-md-7 col-xl-4">
        <div class="dl-panel">
            <div class="dl-head">
                <div><h6 class="dl-title serif">{{ $topListings->isNotEmpty() ? 'Most Desired Properties' : 'Latest Properties' }}</h6><p class="dl-sub">{{ $topListings->isNotEmpty() ? 'Ranked by buyer enquiries' : 'Recently added' }}</p></div>
                <a href="{{ route('portal.properties.index') }}" class="dl-link">Portfolio <i class="fas fa-arrow-right"></i></a>
            </div>
            @if($showcase->isEmpty())
                <div class="dl-empty">No properties yet.@if($isApproved) <a href="{{ route('portal.properties.create') }}">List your first property</a>.@endif</div>
            @else
                <div class="dl-gallery n-{{ $showcase->count() }}">
                    @foreach($showcase as $l)
                        @php $thumb = $l->galleryImages()[0]['url'] ?? null; @endphp
                        <a href="{{ route('portal.properties.edit', $l->id) }}" class="dl-gcard">
                            @if($thumb)<img src="{{ $thumb }}" alt="" loading="lazy">@else<span class="ph"><i class="fas fa-building"></i></span>@endif
                            <div class="tag">
                                @if($l->listing_type)<span>{{ $l->filterLabel('listing_type') ?: ucfirst($l->listing_type) }}</span>@endif
                                @if(($l->leads_count ?? 0) > 0)<span class="hot"><i class="fas fa-fire me-1"></i>{{ $l->leads_count }}</span>@endif
                            </div>
                            <div class="ov">
                                <div class="t">{{ $l->getTranslation('title') }}</div>
                                <div class="m"><span class="text-truncate">{{ $l->location ?: '—' }}</span><span class="price">{{ $l->price ? number_format($l->price) . ' ' . $l->currency : 'On request' }}</span></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-5 col-xl-3">
        <div class="dl-panel">
            <div class="dl-head"><div><h6 class="dl-title serif">Inventory</h6><p class="dl-sub">{{ $stats['total_properties'] }} {{ $plural('listing', $stats['total_properties']) }} by purpose &amp; type</p></div></div>
            @if($stats['total_properties'] > 0)
                <div class="dl-split-l"><span>For sale <strong>{{ $saleCount }}</strong></span><span>For rent <strong>{{ $rentCount }}</strong></span></div>
                <div class="dl-split">
                    <span style="width: {{ $saleCount / $splitTotal * 100 }}%; background: #1b3358;"></span>
                    <span style="width: {{ $rentCount / $splitTotal * 100 }}%; background: #ca2844;"></span>
                </div>
                <div class="dl-split-l mb-2" style="font-size: 0.6rem; color: var(--muted);"><span>{{ round($saleCount / $splitTotal * 100) }}%</span><span>{{ round($rentCount / $splitTotal * 100) }}%</span></div>
                @foreach($propertyTypeBreakdown->take(6) as $type => $total)
                    <div class="dl-type"><span class="l">{{ ucfirst(str_replace('_', ' ', $type)) }}</span><span class="tr"><span style="width: {{ round($total / $typeMax * 100) }}%;"></span></span><strong>{{ $total }}</strong></div>
                @endforeach
                <div class="dl-mini">
                    <div><span class="num">{{ $stats['active_properties'] }}</span><small>Active</small></div>
                    <div><span class="num">{{ $stats['inactive_properties'] }}</span><small>Inactive</small></div>
                    <div><span class="num">{{ $stats['featured_properties'] }}</span><small>Featured</small></div>
                </div>
            @else
                <div class="dl-empty">No properties yet.</div>
            @endif
        </div>
    </div>
</div>
</div>
@endsection

@include('portal.dashboard._charts')
