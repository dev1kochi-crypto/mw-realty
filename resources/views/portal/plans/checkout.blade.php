@extends('portal.layouts.app')

@section('title', 'Checkout')

@php
    $unit = $interval === 'yearly' ? 'year' : 'month';
    $money = fn ($n) => number_format((float) $n, 2);
    $coupon = $pricing['coupon'] ?? null;
    $discount = (float) ($pricing['discount'] ?? 0);
    // Coupons that repeat keep discounting the renewals; a one-time coupon only covers today.
    $renewAmount = $coupon && $coupon->duration !== 'once' ? $total : $price;
    $renewsOn = now()->add($interval === 'yearly' ? '1 year' : '1 month')->format('d M Y');
    $highlights = collect($plan->entitlementLines())->filter(fn ($l) => $l[1])->take(3)->pluck(0);
    $initials = collect(preg_split('/\s+/', trim($owner->displayName())))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp

@push('styles')
<style>
    .co-wrap { max-width: 1080px; margin: 0 auto; }

    /* Header: brand + steps */
    .co-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.1rem; }
    .co-brand { display: flex; align-items: center; gap: 0.6rem; font-weight: 800; color: var(--portal-text); letter-spacing: 0.02em; }
    .co-brand__icon { width: 2.2rem; height: 2.2rem; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; background: linear-gradient(135deg, var(--portal-primary), var(--portal-accent)); box-shadow: 0 8px 18px rgba(36, 67, 115, 0.25); }
    .co-brand small { font-weight: 700; color: var(--portal-muted); letter-spacing: 0.14em; text-transform: uppercase; font-size: 0.7rem; margin-left: 0.2rem; }
    .co-steps { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; font-weight: 700; color: var(--portal-muted); flex-wrap: wrap; }
    .co-step { display: inline-flex; align-items: center; gap: 0.4rem; }
    .co-step__dot { width: 1.35rem; height: 1.35rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.62rem; background: #16a34a; color: #fff; }
    .co-step.is-current { color: var(--portal-primary); }
    .co-step.is-current .co-step__dot { background: var(--portal-primary); box-shadow: 0 0 0 4px rgba(36, 67, 115, 0.14); animation: co-pulse 2s ease-in-out infinite; }
    .co-step__line { width: 1.6rem; height: 2px; border-radius: 2px; background: #16a34a; }
    .co-back { color: var(--portal-muted); font-weight: 700; font-size: 0.85rem; text-decoration: none; }
    .co-back:hover { color: var(--portal-primary); }

    /* Shell */
    .co-shell { position: relative; display: grid; grid-template-columns: 1.05fr 1fr; border-radius: 24px; overflow: hidden; background: var(--portal-surface); border: 1px solid var(--portal-border); box-shadow: 0 30px 70px rgba(36, 67, 115, 0.14); animation: co-rise 0.6s cubic-bezier(.2, .8, .2, 1) both; }
    .co-left { position: relative; padding: 2.2rem 2rem 2rem; background: radial-gradient(120% 90% at 0% 0%, #eef3ff 0%, #f5f6fb 55%, #fdf2f5 100%); border-right: 1px solid var(--portal-border); overflow: hidden; }
    .co-left::before, .co-left::after { content: ''; position: absolute; border-radius: 50%; filter: blur(40px); opacity: 0.55; pointer-events: none; }
    .co-left::before { width: 260px; height: 260px; top: -80px; right: -60px; background: rgba(36, 67, 115, 0.18); animation: co-drift 9s ease-in-out infinite alternate; }
    .co-left::after { width: 220px; height: 220px; bottom: -90px; left: -60px; background: rgba(202, 40, 68, 0.14); animation: co-drift 11s ease-in-out infinite alternate-reverse; }
    .co-right { padding: 2.2rem 2rem 2rem; }

    /* Card preview — front/back, tilts with the pointer, flips while the CVC is focused */
    .co-stage { position: relative; z-index: 1; perspective: 1400px; display: flex; justify-content: center; margin-bottom: 1.75rem; }
    .co-card { position: relative; width: 100%; max-width: 360px; aspect-ratio: 1.586; transform-style: preserve-3d; transition: transform 0.8s cubic-bezier(.2, .8, .2, 1); animation: co-float 6s ease-in-out infinite; }
    .co-card.is-flipped .co-card__inner { transform: rotateY(180deg); }
    .co-card__inner { position: absolute; inset: 0; transform-style: preserve-3d; transition: transform 0.8s cubic-bezier(.2, .8, .2, 1); }
    .co-face { position: absolute; inset: 0; border-radius: 18px; backface-visibility: hidden; -webkit-backface-visibility: hidden; overflow: hidden; color: var(--portal-primary-dark);
        background: linear-gradient(135deg, #ffffff 0%, #e9f0ff 45%, #d6e2fb 100%); border: 1px solid rgba(255, 255, 255, 0.9);
        box-shadow: 0 24px 45px rgba(36, 67, 115, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.9); transition: background 0.6s ease, box-shadow 0.4s ease; }
    .co-face::before { content: ''; position: absolute; inset: 0; background: repeating-linear-gradient(115deg, rgba(36, 67, 115, 0.05) 0 2px, transparent 2px 9px); mask-image: linear-gradient(115deg, transparent 35%, #000 70%); -webkit-mask-image: linear-gradient(115deg, transparent 35%, #000 70%); }
    .co-face::after { content: ''; position: absolute; inset: -40% -60%; background: linear-gradient(105deg, transparent 40%, rgba(255, 255, 255, 0.75) 50%, transparent 60%); transform: translateX(-60%); animation: co-sheen 5.5s ease-in-out infinite; pointer-events: none; }
    .co-card[data-brand="mastercard"] .co-face { background: linear-gradient(135deg, #fff 0%, #fff1e6 45%, #fde0cf 100%); }
    .co-card[data-brand="amex"] .co-face { background: linear-gradient(135deg, #fff 0%, #e6f8f6 45%, #cdeeea 100%); }
    .co-card.is-complete .co-face { box-shadow: 0 24px 45px rgba(36, 67, 115, 0.22), 0 0 0 2px rgba(22, 163, 74, 0.35), 0 0 36px rgba(22, 163, 74, 0.25); }
    .co-card.is-error .co-face { box-shadow: 0 24px 45px rgba(36, 67, 115, 0.22), 0 0 0 2px rgba(220, 38, 38, 0.4), 0 0 36px rgba(220, 38, 38, 0.2); }
    .co-card.is-error { animation: co-shake 0.45s ease; }
    /* backface-visibility alone leaks the mirrored front in some browsers (Firefox): also swap
       visibility at the halfway point of the flip, when the card is edge-on. */
    .co-face__front { transform: rotateY(0deg); visibility: visible; transition: background 0.6s ease, box-shadow 0.4s ease, visibility 0s linear 0.4s; }
    .co-face__back { transform: rotateY(180deg); visibility: hidden; transition: background 0.6s ease, box-shadow 0.4s ease, visibility 0s linear 0.4s; }
    .co-card.is-flipped .co-face__front { visibility: hidden; }
    .co-card.is-flipped .co-face__back { visibility: visible; }

    .co-front { position: relative; z-index: 1; height: 100%; padding: 1.2rem 1.35rem; display: flex; flex-direction: column; }
    .co-front__top { display: flex; justify-content: space-between; align-items: flex-start; }
    .co-chip { width: 2.6rem; height: 1.95rem; border-radius: 7px; background: linear-gradient(135deg, #e7e9ef, #b9bfcc); position: relative; box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08); }
    .co-chip::before { content: ''; position: absolute; inset: 30% 0; border-top: 1px solid rgba(0, 0, 0, 0.15); border-bottom: 1px solid rgba(0, 0, 0, 0.15); }
    .co-contactless { margin-left: 0.6rem; font-size: 1rem; color: rgba(36, 67, 115, 0.45); transform: rotate(90deg); display: inline-block; }
    .co-issuer { text-align: right; font-weight: 800; letter-spacing: 0.14em; font-size: 0.8rem; line-height: 1.1; }
    .co-issuer small { display: block; font-size: 0.5rem; letter-spacing: 0.3em; color: var(--portal-muted); font-weight: 700; }
    .co-number { margin-top: auto; font-size: clamp(1.05rem, 2.6vw, 1.4rem); font-weight: 700; letter-spacing: 0.14em; font-variant-numeric: tabular-nums; display: flex; gap: 0.9rem; }
    .co-number span { transition: color 0.3s ease, transform 0.3s ease, opacity 0.3s ease; opacity: 0.35; }
    .co-number.is-typing span { opacity: 1; }
    .co-number.is-typing span:nth-child(1) { animation: co-blink 1.4s ease-in-out infinite; }
    .co-number.is-typing span:nth-child(2) { animation: co-blink 1.4s 0.15s ease-in-out infinite; }
    .co-number.is-typing span:nth-child(3) { animation: co-blink 1.4s 0.3s ease-in-out infinite; }
    .co-number.is-typing span:nth-child(4) { animation: co-blink 1.4s 0.45s ease-in-out infinite; }
    .co-number.is-done span { opacity: 1; animation: none; color: var(--portal-primary-dark); }
    .co-front__bottom { display: flex; align-items: flex-end; justify-content: space-between; gap: 0.8rem; margin-top: 0.9rem; }
    .co-field-label { font-size: 0.5rem; font-weight: 800; letter-spacing: 0.16em; text-transform: uppercase; color: var(--portal-muted); }
    .co-field-value { font-size: 0.82rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 190px; }
    .co-brand-logo { font-size: 2.1rem; line-height: 1; color: var(--portal-primary-dark); transition: transform 0.4s cubic-bezier(.2, 1.4, .4, 1); }
    .co-brand-logo.is-pop { animation: co-pop 0.5s cubic-bezier(.2, 1.4, .4, 1); }

    /* Back: magnetic stripe, signature panel with the CVC box, brand bottom-right */
    .co-back { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; }
    .co-stripe { height: 22%; margin-top: 9%; background: linear-gradient(180deg, #14172b, #2a2e48 55%, #14172b); box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.08); }
    .co-sig { display: flex; align-items: center; gap: 0; margin: 7% 1.35rem 0; }
    .co-sig__strip { flex: 1; height: 2.3rem; border-radius: 4px 0 0 4px; background: repeating-linear-gradient(-45deg, #ffffff 0 5px, #edf1f9 5px 10px); box-shadow: inset 0 0 0 1px rgba(36, 67, 115, 0.08); }
    .co-sig__cvc { position: relative; min-width: 3.6rem; height: 2.3rem; padding: 0 0.6rem; border-radius: 0 4px 4px 0; background: #fff; display: flex; align-items: center; justify-content: center; font-style: italic; font-weight: 800; font-size: 0.95rem; letter-spacing: 0.18em; color: #9aa0bd; box-shadow: inset 0 0 0 1px rgba(36, 67, 115, 0.12); transition: color 0.3s ease, box-shadow 0.3s ease; }
    .co-card.is-flipped .co-sig__cvc { box-shadow: inset 0 0 0 1px rgba(36, 67, 115, 0.12), 0 0 0 2px var(--portal-primary), 0 0 16px rgba(36, 67, 115, 0.25); }
    .co-sig__cvc.is-typing { color: var(--portal-primary-dark); animation: co-blink 1.2s ease-in-out infinite; }
    .co-sig__cvc.is-done { color: var(--portal-primary-dark); }
    .co-sig__label { margin: 0.35rem 1.35rem 0; font-size: 0.5rem; font-weight: 800; letter-spacing: 0.16em; text-transform: uppercase; color: var(--portal-muted); display: flex; justify-content: space-between; }
    .co-back__foot { margin-top: auto; padding: 0 1.35rem 1rem; display: flex; align-items: flex-end; justify-content: space-between; }
    .co-back__foot span { font-size: 0.55rem; font-weight: 700; letter-spacing: 0.1em; color: var(--portal-muted); }
    .co-back-logo { font-size: 1.9rem; line-height: 1; color: var(--portal-primary-dark); }

    /* Order summary */
    .co-order { position: relative; z-index: 1; padding: 1.2rem 1.25rem; border-radius: 18px; background: rgba(255, 255, 255, 0.78); backdrop-filter: blur(6px); border: 1px solid rgba(255, 255, 255, 0.9); box-shadow: 0 10px 30px rgba(36, 67, 115, 0.08); animation: co-rise 0.6s 0.15s cubic-bezier(.2, .8, .2, 1) both; }
    .co-order__label { font-size: 0.66rem; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; color: var(--portal-muted); margin-bottom: 0.6rem; }
    .co-line { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; padding: 0.4rem 0; font-size: 0.88rem; color: #3b3f5c; }
    .co-line strong { color: var(--portal-text); white-space: nowrap; }
    .co-line small { display: block; font-size: 0.75rem; color: var(--portal-muted); }
    .co-line.is-good strong, .co-line.is-good { color: #16a34a; }
    .co-perks { list-style: none; padding: 0; margin: 0.2rem 0 0.4rem; display: flex; flex-wrap: wrap; gap: 0.35rem; }
    .co-perks li { font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 50px; background: rgba(36, 67, 115, 0.07); color: var(--portal-primary); }
    .co-total { display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; padding-top: 0.8rem; border-top: 1px dashed var(--portal-border); }
    .co-total span { font-weight: 700; color: var(--portal-muted); font-size: 0.88rem; }
    .co-total strong { font-size: 1.55rem; font-weight: 800; color: var(--portal-text); letter-spacing: -0.02em; }
    .co-total strong small { font-size: 0.8rem; color: var(--portal-muted); margin-right: 0.25rem; }
    .co-renew { font-size: 0.75rem; color: var(--portal-muted); margin-top: 0.35rem; }

    /* Form */
    .co-title { font-size: 1.35rem; font-weight: 800; color: var(--portal-text); margin: 0; }
    .co-sub { color: var(--portal-muted); font-size: 0.88rem; margin: 0.2rem 0 1.4rem; }
    .co-group { margin-bottom: 1rem; animation: co-rise 0.5s cubic-bezier(.2, .8, .2, 1) both; }
    .co-group:nth-of-type(2) { animation-delay: 0.08s; } .co-group:nth-of-type(3) { animation-delay: 0.16s; } .co-group:nth-of-type(4) { animation-delay: 0.24s; }
    .co-label { display: flex; justify-content: space-between; font-size: 0.68rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: var(--portal-muted); margin-bottom: 0.4rem; }
    .co-label em { font-style: normal; font-weight: 600; letter-spacing: 0; text-transform: none; color: #9aa0bd; }
    .co-input { position: relative; display: flex; align-items: center; gap: 0.6rem; height: 3rem; padding: 0 0.95rem; border-radius: 12px; background: #fff; border: 1.5px solid var(--portal-border); transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease; }
    .co-input > i { color: #9aa0bd; font-size: 0.9rem; width: 1rem; text-align: center; transition: color 0.2s ease; }
    .co-input input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font-weight: 600; color: var(--portal-text); font-size: 0.95rem; }
    .co-input input::placeholder { color: #aab0c8; font-weight: 500; }
    .co-input .co-el { flex: 1; min-width: 0; }
    .co-input.is-focus { border-color: var(--portal-primary); box-shadow: 0 0 0 4px rgba(36, 67, 115, 0.12); transform: translateY(-1px); }
    .co-input.is-focus > i { color: var(--portal-primary); }
    .co-input.is-invalid { border-color: #dc2626; box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1); }
    .co-input.is-invalid > i { color: #dc2626; }
    .co-input.is-complete > i { color: #16a34a; }
    .co-input__brand { font-size: 1.6rem; color: var(--portal-primary-dark); opacity: 0.35; transition: opacity 0.3s ease; }
    .co-input__brand.is-known { opacity: 1; }
    .co-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
    .co-hint { min-height: 1.1rem; margin-top: 0.3rem; font-size: 0.75rem; font-weight: 600; color: #dc2626; }

    .co-save { display: flex; gap: 0.7rem; align-items: flex-start; padding: 0.8rem 0.9rem; border-radius: 12px; background: var(--portal-bg); border: 1px solid var(--portal-border); font-size: 0.84rem; color: #3b3f5c; margin-bottom: 1.1rem; }
    .co-save__tick { flex-shrink: 0; width: 1.25rem; height: 1.25rem; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; color: #fff; background: var(--portal-primary); margin-top: 0.05rem; }
    .co-save strong { color: var(--portal-text); }
    .co-save small { display: block; color: var(--portal-muted); font-size: 0.75rem; }

    .co-alert { display: none; align-items: flex-start; gap: 0.55rem; padding: 0.7rem 0.9rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.85rem; font-weight: 600; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .co-alert.is-shown { display: flex; animation: co-rise 0.3s ease both; }

    .co-pay { position: relative; overflow: hidden; width: 100%; height: 3.3rem; border: 0; border-radius: 14px; font-weight: 800; font-size: 1rem; color: #fff; background: linear-gradient(135deg, var(--portal-primary), var(--portal-primary-dark)); box-shadow: 0 16px 30px rgba(36, 67, 115, 0.32); transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 0.55rem; }
    .co-pay::after { content: ''; position: absolute; top: 0; bottom: 0; width: 40%; left: -60%; background: linear-gradient(105deg, transparent, rgba(255, 255, 255, 0.28), transparent); animation: co-shine 3.2s ease-in-out infinite; }
    .co-pay:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 20px 36px rgba(36, 67, 115, 0.38); }
    .co-pay:disabled { opacity: 0.75; cursor: progress; }
    .co-pay .fa-arrow-right { transition: transform 0.2s ease; }
    .co-pay:hover:not(:disabled) .fa-arrow-right { transform: translateX(4px); }
    .co-trust { display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; margin-top: 0.9rem; font-size: 0.72rem; font-weight: 700; color: var(--portal-muted); }
    .co-trust i { color: #16a34a; margin-right: 0.25rem; }
    .co-trust-note { text-align: center; font-size: 0.7rem; color: #9aa0bd; margin-top: 0.3rem; }

    /* Paid state: the card moves to the centre with a green glow */
    .co-done { display: none; position: absolute; inset: 0; z-index: 5; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; text-align: center; background: radial-gradient(120% 90% at 50% 0%, #eef3ff 0%, #f5f6fb 60%, #fdf2f5 100%); }
    .co-shell.is-paid .co-done { display: flex; animation: co-fade 0.5s ease both; }
    .co-done .co-card { max-width: 330px; animation: co-land 0.9s cubic-bezier(.2, .8, .2, 1) both, co-float 6s 0.9s ease-in-out infinite; }
    .co-done .co-face { box-shadow: 0 24px 45px rgba(36, 67, 115, 0.2), 0 0 0 2px rgba(34, 197, 94, 0.45), 0 0 60px rgba(34, 197, 94, 0.45); animation: co-glow 2.4s ease-in-out infinite; }
    .co-check { width: 3.4rem; height: 3.4rem; margin: 1.8rem auto 0.8rem; }
    .co-check circle { fill: none; stroke: #16a34a; stroke-width: 2.5; stroke-dasharray: 160; stroke-dashoffset: 160; animation: co-draw 0.7s 0.6s ease forwards; }
    .co-check path { fill: none; stroke: #16a34a; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 40; stroke-dashoffset: 40; animation: co-draw 0.4s 1.15s ease forwards; }
    .co-done h2 { font-size: 1.6rem; font-weight: 800; color: var(--portal-text); margin: 0; animation: co-rise 0.5s 1.2s both; }
    .co-done p { color: var(--portal-muted); margin: 0.3rem 0 1.2rem; animation: co-rise 0.5s 1.3s both; }
    .co-done .btn { animation: co-rise 0.5s 1.4s both; border-radius: 50px; padding: 0.55rem 1.4rem; }

    .co-processing .co-card { animation: co-float 1.6s ease-in-out infinite; }
    .co-processing .co-face::after { animation-duration: 1.2s; }

    @keyframes co-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
    @keyframes co-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes co-float { 0%, 100% { translate: 0 0; } 50% { translate: 0 -8px; } }
    @keyframes co-sheen { 0%, 55% { transform: translateX(-60%); } 100% { transform: translateX(60%); } }
    @keyframes co-shine { 0%, 60% { left: -60%; } 100% { left: 130%; } }
    @keyframes co-drift { from { transform: translate(0, 0); } to { transform: translate(-30px, 25px); } }
    @keyframes co-pulse { 0%, 100% { box-shadow: 0 0 0 4px rgba(36, 67, 115, 0.14); } 50% { box-shadow: 0 0 0 7px rgba(36, 67, 115, 0.06); } }
    @keyframes co-blink { 0%, 100% { opacity: 0.45; } 50% { opacity: 1; } }
    @keyframes co-pop { 0% { transform: scale(0.4) rotate(-12deg); } 100% { transform: scale(1) rotate(0); } }
    @keyframes co-shake { 0%, 100% { transform: translateX(0); } 20%, 60% { transform: translateX(-8px); } 40%, 80% { transform: translateX(8px); } }
    @keyframes co-land { from { opacity: 0; transform: scale(0.7) translateY(40px) rotateX(25deg); } to { opacity: 1; transform: none; } }
    @keyframes co-glow { 0%, 100% { box-shadow: 0 24px 45px rgba(36, 67, 115, 0.2), 0 0 0 2px rgba(34, 197, 94, 0.45), 0 0 40px rgba(34, 197, 94, 0.35); } 50% { box-shadow: 0 24px 45px rgba(36, 67, 115, 0.2), 0 0 0 2px rgba(34, 197, 94, 0.6), 0 0 70px rgba(34, 197, 94, 0.55); } }
    @keyframes co-draw { to { stroke-dashoffset: 0; } }

    @media (max-width: 900px) {
        .co-shell { grid-template-columns: 1fr; }
        .co-left { border-right: 0; border-bottom: 1px solid var(--portal-border); padding: 1.5rem 1.1rem; }
        .co-right { padding: 1.5rem 1.1rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .co-wrap *, .co-wrap *::before, .co-wrap *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
    }
</style>
@endpush

@section('content')
<div class="co-wrap">
    <div class="co-head">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <a href="{{ route('portal.plans.index') }}" class="co-back"><i class="fas fa-arrow-left me-1"></i>Plans</a>
            <div class="co-brand"><span class="co-brand__icon"><i class="fas fa-shield-halved"></i></span>MW Realty<small>Checkout</small></div>
        </div>
        <div class="co-steps" aria-label="Checkout steps">
            <span class="co-step"><span class="co-step__dot"><i class="fas fa-check"></i></span>Choose plan</span>
            <span class="co-step__line"></span>
            <span class="co-step"><span class="co-step__dot"><i class="fas fa-check"></i></span>Review</span>
            <span class="co-step__line"></span>
            <span class="co-step is-current" id="coStepPay"><span class="co-step__dot">3</span>Payment</span>
        </div>
    </div>

    <div class="co-shell" id="coShell">
        {{-- Left: live card preview + order --}}
        <div class="co-left">
            <div class="co-stage" id="coStage">
                <div class="co-card" id="coCard" data-brand="unknown">
                    <div class="co-card__inner">
                        <div class="co-face co-face__front">
                            <div class="co-front">
                                <div class="co-front__top">
                                    <div class="d-flex align-items-center"><span class="co-chip"></span><i class="fas fa-wifi co-contactless"></i></div>
                                    <div class="co-issuer">MW REALTY<small>{{ strtoupper($plan->getTranslation('name')) }}</small></div>
                                </div>
                                <div class="co-number" id="coNumber"><span>••••</span><span>••••</span><span>••••</span><span>••••</span></div>
                                <div class="co-front__bottom">
                                    <div class="d-flex gap-4 min-w-0">
                                        <div class="min-w-0">
                                            <div class="co-field-label">Cardholder</div>
                                            <div class="co-field-value" id="coNameOut">{{ $owner->displayName() }}</div>
                                        </div>
                                        <div>
                                            <div class="co-field-label">Expires</div>
                                            <div class="co-field-value" id="coExpOut">MM/YY</div>
                                        </div>
                                    </div>
                                    <i class="fab fa-cc-visa co-brand-logo d-none" id="coBrandLogo"></i>
                                    <i class="fas fa-credit-card co-brand-logo" id="coBrandFallback" style="opacity:.35"></i>
                                </div>
                            </div>
                        </div>
                        <div class="co-face co-face__back">
                            <div class="co-back">
                                <div class="co-stripe"></div>
                                <div class="co-sig"><span class="co-sig__strip"></span><span class="co-sig__cvc" id="coCvcOut">CVC</span></div>
                                <div class="co-sig__label"><span>Authorized signature</span><span>Security code</span></div>
                                <div class="co-back__foot">
                                    <span>MW REALTY · SECURED BY STRIPE</span>
                                    <i class="fas fa-credit-card co-back-logo" id="coBackLogo"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="co-order">
                <div class="co-order__label">Your order</div>
                <div class="co-line">
                    <div>{{ $plan->getTranslation('name') }} plan<small>Billed {{ $interval }} · renews automatically</small></div>
                    <strong>{{ $currency }} {{ $money($price) }}</strong>
                </div>
                @if($highlights->isNotEmpty())
                <ul class="co-perks">@foreach($highlights as $perk)<li>{{ $perk }}</li>@endforeach</ul>
                @endif
                @if($coupon && $discount > 0)
                <div class="co-line is-good">
                    <div><i class="fas fa-ticket-alt me-1"></i>Coupon {{ $coupon->code }}<small>{{ $coupon->discountLabel() }} {{ $coupon->durationLabel() }}</small></div>
                    <strong>−{{ $currency }} {{ $money($discount) }}</strong>
                </div>
                @endif
                <div class="co-total">
                    <span>Total due today</span>
                    <strong><small>{{ $currency }}</small>{{ $money($total) }}</strong>
                </div>
                <div class="co-renew"><i class="fas fa-rotate me-1"></i>Then {{ $currency }} {{ $money($renewAmount) }} every {{ $unit }} from {{ $renewsOn }}. Cancel auto-renew any time.</div>
            </div>
        </div>

        {{-- Right: payment form (card fields are Stripe Elements iframes) --}}
        <div class="co-right">
            <h1 class="co-title">Payment details</h1>
            <p class="co-sub">Enter your card to activate the {{ $plan->getTranslation('name') }} plan.</p>

            <div class="co-alert" id="coAlert" role="alert"><i class="fas fa-circle-exclamation mt-1"></i><span id="coAlertText"></span></div>

            <form id="coForm" data-no-spinner="1" novalidate>
                <div class="co-group">
                    <label class="co-label" for="coName">Cardholder name</label>
                    <div class="co-input" data-field="name">
                        <i class="far fa-user"></i>
                        <input type="text" id="coName" autocomplete="cc-name" placeholder="Name on card" value="{{ $owner->displayName() }}" maxlength="60">
                    </div>
                    <div class="co-hint" data-hint="name"></div>
                </div>

                <div class="co-group">
                    <span class="co-label">Card number</span>
                    <div class="co-input" data-field="number">
                        <i class="far fa-credit-card"></i>
                        <div class="co-el" id="coNumberEl"></div>
                        <i class="fas fa-credit-card co-input__brand" id="coInputBrand"></i>
                    </div>
                    <div class="co-hint" data-hint="number"></div>
                </div>

                <div class="co-group">
                    <div class="co-row">
                        <div>
                            <span class="co-label">Expiry date</span>
                            <div class="co-input" data-field="expiry">
                                <i class="far fa-calendar"></i>
                                <div class="co-el" id="coExpiryEl"></div>
                            </div>
                            <div class="co-hint" data-hint="expiry"></div>
                        </div>
                        <div>
                            <span class="co-label">CVC <em>back of card</em></span>
                            <div class="co-input" data-field="cvc">
                                <i class="fas fa-lock"></i>
                                <div class="co-el" id="coCvcEl"></div>
                            </div>
                            <div class="co-hint" data-hint="cvc"></div>
                        </div>
                    </div>
                </div>

                <div class="co-save">
                    <span class="co-save__tick"><i class="fas fa-check"></i></span>
                    <div><strong>Card saved for renewals</strong><small>Stored securely by Stripe, never by MW Realty.</small></div>
                </div>

                <button type="submit" class="co-pay" id="coPay">
                    <i class="fas fa-lock"></i><span id="coPayText">Pay {{ $currency }} {{ $money($total) }}</span><i class="fas fa-arrow-right"></i>
                </button>
                <div class="co-trust">
                    <span><i class="fas fa-shield-halved"></i>PCI-DSS Level 1</span>
                    <span><i class="fas fa-lock"></i>256-bit TLS</span>
                    <span><i class="fab fa-stripe-s"></i>Powered by Stripe</span>
                </div>
                <div class="co-trust-note">MW Realty never stores your card number or CVC.</div>
            </form>
        </div>

        {{-- Paid --}}
        <div class="co-done" id="coDone" aria-live="polite">
            <div class="co-stage w-100 mb-0"><div class="co-card" id="coDoneCard"></div></div>
            <svg class="co-check" viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="24"/><path d="M15 27l7 7 15-15"/></svg>
            <h2>Paid {{ $currency }} {{ $money($total) }}</h2>
            <p id="coDoneText">Welcome to the {{ $plan->getTranslation('name') }} plan.</p>
            <a href="{{ route('portal.plans.index') }}" class="btn btn-portal-primary" id="coDoneBtn">Go to my plan <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
    const $ = id => document.getElementById(id);
    const csrf = '{{ csrf_token() }}';
    const order = @json(['plan_id' => $plan->id, 'interval' => $interval, 'coupon_code' => $coupon?->code]);
    const card = $('coCard'), shell = $('coShell');
    const brandIcons = { visa: 'fa-cc-visa', mastercard: 'fa-cc-mastercard', amex: 'fa-cc-amex', discover: 'fa-cc-discover', diners: 'fa-cc-diners-club', jcb: 'fa-cc-jcb' };

    const alertBox = $('coAlert');
    const showError = msg => { $('coAlertText').textContent = msg; alertBox.classList.add('is-shown'); card.classList.remove('is-error'); void card.offsetWidth; card.classList.add('is-error'); };
    const clearError = () => alertBox.classList.remove('is-shown');

    if (typeof Stripe === 'undefined' || !@json($stripeKey)) {
        showError('Card payments are unavailable right now. Please try again later.');
        $('coPay').disabled = true;
        return;
    }

    const stripe = Stripe(@json($stripeKey));
    const elements = stripe.elements({ fonts: [{ cssSrc: 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700&display=swap' }] });
    const style = {
        base: { fontFamily: "'Plus Jakarta Sans', 'Segoe UI', sans-serif", fontSize: '15px', fontWeight: '600', color: '#1f2340', letterSpacing: '0.02em', '::placeholder': { color: '#aab0c8', fontWeight: '500' } },
        invalid: { color: '#dc2626', iconColor: '#dc2626' },
    };
    const fields = {
        number: elements.create('cardNumber', { style, showIcon: false, placeholder: '1234 1234 1234 1234' }),
        expiry: elements.create('cardExpiry', { style }),
        cvc: elements.create('cardCvc', { style, placeholder: '•••' }),
    };
    fields.number.mount('#coNumberEl'); fields.expiry.mount('#coExpiryEl'); fields.cvc.mount('#coCvcEl');

    const state = { number: false, expiry: false, cvc: false };
    const box = key => document.querySelector(`[data-field="${key}"]`);
    const hint = (key, msg) => { document.querySelector(`[data-hint="${key}"]`).textContent = msg || ''; box(key).classList.toggle('is-invalid', !!msg); };

    // Stripe keeps the digits inside its iframe, so the preview reflects progress, not the number itself.
    Object.entries(fields).forEach(([key, el]) => {
        el.on('focus', () => { box(key).classList.add('is-focus'); card.classList.toggle('is-flipped', key === 'cvc'); });
        el.on('blur', () => { box(key).classList.remove('is-focus'); if (key === 'cvc') card.classList.remove('is-flipped'); });
        el.on('change', e => {
            state[key] = e.complete;
            hint(key, e.error?.message);
            box(key).classList.toggle('is-complete', e.complete);
            clearError();
            if (key === 'number') {
                const n = $('coNumber');
                n.classList.toggle('is-typing', !e.empty && !e.complete);
                n.classList.toggle('is-done', e.complete);
                setBrand(e.brand);
            }
            if (key === 'expiry') $('coExpOut').textContent = e.complete ? '••/••' : (e.empty ? 'MM/YY' : '••/··');
            if (key === 'cvc') {
                const out = $('coCvcOut');
                out.textContent = e.empty ? 'CVC' : '•••';
                out.classList.toggle('is-typing', !e.empty && !e.complete);
                out.classList.toggle('is-done', e.complete);
            }
            card.classList.toggle('is-complete', state.number && state.expiry && state.cvc);
        });
    });

    let lastBrand = 'unknown';
    function setBrand(brand) {
        brand = brandIcons[brand] ? brand : 'unknown';
        if (brand === lastBrand) return;
        lastBrand = brand;
        card.dataset.brand = brand;
        const logo = $('coBrandLogo'), fallback = $('coBrandFallback'), inputBrand = $('coInputBrand'), back = $('coBackLogo');
        if (brand === 'unknown') {
            logo.classList.add('d-none'); fallback.classList.remove('d-none');
            inputBrand.className = 'fas fa-credit-card co-input__brand'; back.className = 'fas fa-credit-card co-back-logo';
            return;
        }
        logo.className = `fab ${brandIcons[brand]} co-brand-logo is-pop`;
        fallback.classList.add('d-none');
        inputBrand.className = `fab ${brandIcons[brand]} co-input__brand is-known`;
        back.className = `fab ${brandIcons[brand]} co-back-logo`;
    }

    // Name: our own input, mirrored onto the card
    const nameInput = $('coName');
    nameInput.addEventListener('input', () => { $('coNameOut').textContent = nameInput.value.trim() || 'Your name'; hint('name'); clearError(); });
    nameInput.addEventListener('focus', () => { box('name').classList.add('is-focus'); card.classList.remove('is-flipped'); });
    nameInput.addEventListener('blur', () => box('name').classList.remove('is-focus'));

    // Gentle tilt toward the pointer
    const stage = $('coStage');
    stage.addEventListener('pointermove', e => {
        if (card.classList.contains('is-flipped')) return;
        const r = stage.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
        card.style.transform = `rotateY(${x * 14}deg) rotateX(${-y * 12}deg)`;
    });
    stage.addEventListener('pointerleave', () => { card.style.transform = ''; });

    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    }).then(async r => {
        const d = await r.json().catch(() => ({}));
        if (!r.ok || d.success === false) throw new Error(d.message || Object.values(d.errors || {})[0]?.[0] || 'Something went wrong. Please try again.');
        return d;
    });

    // The unpaid subscription is kept for retries: after a decline the same payment is confirmed with the corrected card.
    let intent = null;
    const payBtn = $('coPay'), payText = $('coPayText'), payLabel = payText.textContent;
    const busy = on => {
        payBtn.disabled = on;
        shell.classList.toggle('co-processing', on);
        payText.innerHTML = on ? '<span class="spinner-border spinner-border-sm me-2"></span>Processing payment…' : payLabel;
    };

    $('coForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        clearError();
        const name = nameInput.value.trim();
        let ok = true;
        if (!name) { hint('name', 'Enter the name shown on the card.'); ok = false; }
        if (!state.number) { hint('number', document.querySelector('[data-hint="number"]').textContent || 'Enter your card number.'); ok = false; }
        if (!state.expiry) { hint('expiry', document.querySelector('[data-hint="expiry"]').textContent || 'Enter the expiry date.'); ok = false; }
        if (!state.cvc) { hint('cvc', document.querySelector('[data-hint="cvc"]').textContent || 'Enter the CVC.'); ok = false; }
        if (!ok) { showError('Please check the highlighted fields.'); return; }

        busy(true);
        try {
            intent ??= await post(@json(route('portal.plans.checkout.intent')), order);

            if (intent.client_secret) {
                const { error } = await stripe.confirmCardPayment(intent.client_secret, {
                    payment_method: { card: fields.number, billing_details: { name, email: @json($owner->email) } },
                });
                if (error) throw new Error(error.message);
            }

            const result = await post(@json(route('portal.plans.checkout.complete')), { subscription_id: intent.subscription_id });
            paid(result);
        } catch (err) {
            busy(false);
            showError(err.message);
        }
    });

    function paid(result) {
        const done = $('coDoneCard');
        done.dataset.brand = card.dataset.brand;
        done.innerHTML = card.querySelector('.co-card__inner').outerHTML;
        done.classList.remove('is-flipped');
        if (result.card) {
            const groups = done.querySelectorAll('.co-number span');
            groups[3].textContent = result.card.last4;
            done.querySelector('.co-number').classList.add('is-done');
            const brand = result.card.brand.charAt(0).toUpperCase() + result.card.brand.slice(1);
            $('coDoneText').textContent = `${brand} •••• ${result.card.last4} · welcome to the ${result.plan} plan.`;
        }
        if (!result.activated) $('coDoneText').textContent += ' Your plan is being activated — this takes a few seconds.';
        $('coStepPay').querySelector('.co-step__dot').innerHTML = '<i class="fas fa-check"></i>';
        $('coStepPay').classList.remove('is-current');
        shell.classList.add('is-paid');
        Object.values(fields).forEach(f => f.unmount());
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
})();
</script>
@endpush
