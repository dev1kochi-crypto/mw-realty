{{-- CRM › Integrations › Property Finder (Portal\Crm\PropertyFinderController). An agency / independent
     agent connects with its API key + secret and imports its listings; an agency agent sees the agency's
     connection and may sync it; Super Admin reviews every imported listing before it can go live. --}}
@php
    $pf = $pfConnection;
    $pfSyncing = $pf?->syncInProgress();
    // Where the account is in the flow (the step strip): 1 connect · 2 import · 3 review · 4 live.
    $pfStep = match (true) {
        !$pf => 1,
        !$pf->last_full_sync_at || $pfSyncing => 2,
        $pfPending > 0 => 3,
        default => 4,
    };
    $pfSteps = [
        1 => ['fas fa-plug', 'Connect', 'API key & secret'],
        2 => ['fas fa-cloud-arrow-down', 'Import', 'Live listings come in'],
        3 => ['fas fa-user-shield', 'Review', 'MW Realty checks them'],
        4 => ['fas fa-globe', 'Live', 'On the website'],
    ];
@endphp
@push('styles')
<style>
    .pf { --pf: #ef5e4e; --pf-dark: #c9402f; --pf-soft: #fff1ef; }
    .pf-card { border-radius: 18px; overflow: hidden; }
    /* Hero */
    .pf-hero { position: relative; padding: 24px 26px 0; background: radial-gradient(circle at 100% 0%, #ffe3de 0%, transparent 45%), linear-gradient(180deg, var(--pf-soft) 0%, #fff 100%); border-bottom: 1px solid var(--portal-border); }
    .pf-hero__top { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .pf-hero__logo { display: grid; place-items: center; flex-shrink: 0; width: 56px; height: 56px; border-radius: 16px; background: var(--pf); color: #fff; font-size: 1.5rem; box-shadow: 0 10px 22px rgba(239, 94, 78, .32); }
    .pf-hero__name { font-size: 1.25rem; font-weight: 700; color: var(--portal-primary-dark); margin: 0; }
    .pf-hero__tag { font-size: .86rem; color: var(--portal-muted); margin: 2px 0 0; max-width: 640px; }
    .pf-hero__status { margin-left: auto; }
    /* Step strip */
    .pf-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 22px; }
    .pf-step { position: relative; display: flex; align-items: center; gap: 10px; padding: 12px 10px 14px 0; min-width: 0; color: var(--portal-muted); }
    .pf-step::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: #f1dcd8; }
    .pf-step.is-done::after, .pf-step.is-current::after { background: var(--pf); }
    .pf-step__icon { display: grid; place-items: center; flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%; background: #fff; border: 1.5px solid #f1dcd8; font-size: .8rem; }
    .pf-step.is-done .pf-step__icon { background: var(--pf); border-color: var(--pf); color: #fff; }
    .pf-step.is-current .pf-step__icon { border-color: var(--pf); color: var(--pf); box-shadow: 0 0 0 4px rgba(239, 94, 78, .14); }
    .pf-step__title { display: block; font-size: .84rem; font-weight: 700; color: var(--portal-primary-dark); }
    .pf-step:not(.is-done):not(.is-current) .pf-step__title { color: var(--portal-muted); }
    .pf-step__text { display: block; font-size: .74rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* Body */
    .pf-body { padding: 24px 26px; }
    .pf-grid { display: grid; grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); gap: 24px; align-items: start; }
    .pf-panel { padding: 22px; border: 1px solid var(--portal-border); border-radius: 16px; background: #fff; }
    .pf-panel__title { font-weight: 700; color: var(--portal-primary-dark); margin-bottom: 4px; }
    .pf-panel__sub { font-size: .82rem; color: var(--portal-muted); margin-bottom: 18px; }
    .pf-field { position: relative; margin-bottom: 14px; }
    .pf-field label { display: block; font-size: .78rem; font-weight: 700; color: var(--portal-primary-dark); margin-bottom: 6px; }
    .pf-field__icon { position: absolute; left: 14px; bottom: 13px; color: #b3b8cc; font-size: .85rem; pointer-events: none; }
    .pf-field input { height: 46px; padding-left: 40px; padding-right: 44px; border-radius: 12px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .88rem; }
    .pf-field input:focus { border-color: var(--pf); box-shadow: 0 0 0 3px rgba(239, 94, 78, .15); }
    .pf-field__eye { position: absolute; right: 6px; bottom: 6px; width: 34px; height: 34px; border: 0; border-radius: 8px; background: transparent; color: var(--portal-muted); }
    .pf-field__eye:hover { background: var(--portal-bg); color: var(--portal-primary-dark); }
    .pf-switch { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 4px 0 18px; padding: 12px 14px; border-radius: 12px; background: var(--portal-bg); cursor: pointer; }
    .pf-switch__text { font-size: .84rem; font-weight: 600; color: var(--portal-primary-dark); }
    .pf-switch__hint { display: block; font-size: .75rem; font-weight: 400; color: var(--portal-muted); }
    .pf-switch .form-check-input { width: 2.6em; height: 1.4em; margin: 0; flex-shrink: 0; cursor: pointer; }
    .pf-switch .form-check-input:checked { background-color: var(--pf); border-color: var(--pf); }
    .pf-btn { background: var(--pf); color: #fff; font-weight: 600; border: 0; }
    .pf-btn:hover, .pf-btn:focus { background: var(--pf-dark); color: #fff; }
    .pf-btn--block { width: 100%; height: 46px; border-radius: 12px; }
    .pf-secure { margin-top: 10px; text-align: center; font-size: .76rem; color: var(--portal-muted); }
    /* Side info */
    .pf-side { display: grid; gap: 14px; }
    .pf-info { padding: 18px 20px; border-radius: 16px; background: var(--portal-bg); }
    .pf-info__title { display: flex; align-items: center; gap: 8px; font-size: .86rem; font-weight: 700; color: var(--portal-primary-dark); margin-bottom: 10px; }
    .pf-info__title i { color: var(--pf); }
    .pf-checks { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 8px 12px; font-size: .8rem; }
    .pf-checks li { display: flex; align-items: center; gap: 7px; }
    .pf-checks i { color: #0f9d58; font-size: .75rem; }
    .pf-info p { margin: 0; font-size: .8rem; color: var(--portal-muted); }
    .pf-info--tip { background: #fffaf0; border: 1px dashed #f3d9a4; }
    .pf-info--tip .pf-info__title i { color: #c98a10; }
    /* Connected */
    .pf-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
    .pf-stat { padding: 14px 16px; border: 1px solid var(--portal-border); border-radius: 14px; background: #fff; min-width: 0; }
    .pf-stat__label { display: flex; align-items: center; gap: 6px; font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--portal-muted); }
    .pf-stat__value { margin-top: 4px; font-size: 1.15rem; font-weight: 700; color: var(--portal-primary-dark); overflow-wrap: anywhere; }
    .pf-stat__value--mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .9rem; }
    .pf-stat--warn { border-color: #f3d9a4; background: #fffaf0; }
    .pf-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 14px 16px; border: 1px solid var(--portal-border); border-radius: 14px; background: var(--portal-bg); }
    .pf-bar__meta { font-size: .8rem; color: var(--portal-muted); margin-right: auto; }
    .pf-result { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 14px; padding: 12px 16px; border-radius: 12px; font-size: .84rem; background: #eefaf3; color: #0f6b3d; }
    .pf-result.is-error { background: #fff5f6; color: #b8283a; }
    .pf-result.is-running { display: block; background: var(--pf-soft); color: var(--pf-dark); }
    .pf-result .fbbar span { background: linear-gradient(90deg, #ff9a8c, var(--pf)); }
    .pf-keys { margin-top: 14px; padding: 18px; border: 1px dashed var(--portal-border); border-radius: 14px; }
    .pf-keys .pf-field input { height: 40px; }
    .pf-keys .pf-field__icon { bottom: 11px; }
    /* Sync animation: listings flying from Property Finder into MW Realty */
    .pf-flow { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 0; width: 100%; max-width: 300px; margin: 0 auto; }
    .pf-flow__node { position: relative; z-index: 1; display: grid; place-items: center; flex-shrink: 0; width: 58px; height: 58px; border-radius: 18px; font-size: 1.35rem; color: #fff; }
    .pf-flow__node--pf { background: var(--pf); box-shadow: 0 10px 24px rgba(239, 94, 78, .35); animation: pfPulse 1.6s ease-in-out infinite; }
    .pf-flow__node--mw { background: var(--portal-primary); box-shadow: 0 10px 24px rgba(36, 67, 115, .3); }
    .pf-flow__node--mw::after { content: ''; position: absolute; inset: -6px; border-radius: 22px; border: 2px solid rgba(36, 67, 115, .25); animation: pfCatch 1.6s ease-out infinite .8s; opacity: 0; }
    .pf-flow__track { position: relative; flex: 1 1 auto; height: 58px; margin: 0 -4px; overflow: hidden; }
    .pf-flow__track::before { content: ''; position: absolute; left: 0; right: 0; top: 50%; height: 2px; background: repeating-linear-gradient(90deg, #f3b6ad 0 6px, transparent 6px 12px); background-size: 24px 2px; animation: pfDash .8s linear infinite; }
    .pf-flow__item { position: absolute; top: 50%; left: 0; display: grid; place-items: center; width: 26px; height: 22px; margin-top: -11px; border-radius: 6px; background: #fff; color: var(--pf); font-size: .62rem; box-shadow: 0 3px 10px rgba(24, 39, 75, .18); opacity: 0; animation: pfFly 2.4s cubic-bezier(.45, .05, .55, .95) infinite; }
    .pf-flow__item:nth-child(2) { animation-delay: .8s; }
    .pf-flow__item:nth-child(3) { animation-delay: 1.6s; }
    @keyframes pfFly { 0% { left: -10%; opacity: 0; transform: scale(.6) rotate(-8deg); } 15% { opacity: 1; } 50% { transform: scale(1) rotate(0) translateY(-8px); } 85% { opacity: 1; } 100% { left: 100%; opacity: 0; transform: scale(.6) rotate(8deg); } }
    @keyframes pfDash { to { background-position: 24px 0; } }
    @keyframes pfPulse { 50% { transform: scale(1.06); } }
    @keyframes pfCatch { 0% { opacity: .9; transform: scale(.9); } 100% { opacity: 0; transform: scale(1.25); } }
    .pf-msg { min-height: 1.3em; transition: opacity .3s; }
    .pf-msg.is-out { opacity: 0; }
    .pf-count { display: inline-block; min-width: 1.5ch; font-variant-numeric: tabular-nums; }
    .pf-count.is-bump { animation: pfBump .35s ease; }
    @keyframes pfBump { 40% { transform: scale(1.35); color: var(--pf); } }
    /* Running panel (in page) */
    .pf-running { max-width: 460px; margin: 4px auto 18px; padding: 30px 26px 24px; border-radius: 20px; background: #fff; text-align: center; border: 1px solid #f6d3cd; box-shadow: 0 18px 44px rgba(239, 94, 78, .12), 0 2px 8px rgba(24, 39, 75, .05); animation: pfRise .35s ease; }
    .pf-running__title { margin-top: 18px; font-weight: 700; font-size: 1.05rem; color: var(--portal-primary-dark); }
    .pf-running__msg { font-size: .84rem; color: var(--portal-muted); }
    .pf-running__nums { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin: 16px 0; }
    .pf-num { min-width: 96px; padding: 8px 12px; border-radius: 12px; background: var(--portal-bg); font-size: .74rem; color: var(--portal-muted); }
    .pf-num strong { display: block; font-size: 1.35rem; line-height: 1.2; color: var(--portal-primary-dark); }
    .pf-num--ok { background: #eefaf3; } .pf-num--ok strong { color: #0f7a43; }
    .pf-num--bad { background: #fff5f6; } .pf-num--bad strong { color: #b8283a; }
    .pf-num[hidden] { display: none; }
    .pf-running .fbbar span { background: linear-gradient(90deg, #ff9a8c, var(--pf)); }
    .pf-running__note { margin-top: 12px; font-size: .74rem; color: var(--portal-muted); }
    .pf-running__note--warn { color: #9a5b00; }
    .pf-running__note[hidden] { display: none; }
    /* Full-screen overlay on Sync / Import click */
    .pf-busy { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 16px; background: rgba(17, 27, 51, .5); backdrop-filter: blur(3px); animation: pfFade .2s ease; }
    .pf-busy[hidden] { display: none; }
    .pf-busy__box { width: min(420px, 100%); padding: 30px 26px 26px; border-radius: 20px; background: #fff; text-align: center; box-shadow: 0 24px 60px rgba(0, 0, 0, .25); animation: pfRise .3s ease; }
    .pf-busy__title { margin-top: 18px; font-weight: 700; font-size: 1.05rem; color: var(--portal-primary-dark); }
    .pf-busy__msg { font-size: .84rem; color: var(--portal-muted); }
    .pf-busy .fbbar { margin-top: 18px; }
    .pf-busy .fbbar span { background: linear-gradient(90deg, #ff9a8c, var(--pf)); }
    .pf-busy__note { margin-top: 12px; font-size: .74rem; color: var(--portal-muted); }
    @keyframes pfFade { from { opacity: 0; } }
    @keyframes pfRise { from { opacity: 0; transform: translateY(12px) scale(.97); } }
    @media (prefers-reduced-motion: reduce) { .pf-flow__item, .pf-flow__track::before, .pf-flow__node--pf, .pf-flow__node--mw::after { animation-duration: 6s; } }
    @media (max-width: 991.98px) { .pf-grid { grid-template-columns: 1fr; } .pf-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767.98px) {
        .pf-steps { grid-template-columns: repeat(4, minmax(64px, 1fr)); }
        .pf-step { flex-direction: column; align-items: flex-start; gap: 6px; }
        .pf-step__text { display: none; }
        .pf-checks { grid-template-columns: 1fr; }
    }
    @media (max-width: 575.98px) { .pf-hero, .pf-body { padding-left: 16px; padding-right: 16px; } .pf-hero__status { margin-left: 0; } }
</style>
@endpush

<div class="portal-card pf pf-card mb-3" id="property-finder">
    <div class="pf-hero">
        <div class="pf-hero__top">
            <span class="pf-hero__logo" aria-hidden="true"><i class="fas fa-house-chimney"></i></span>
            <div class="min-w-0">
                <h5 class="pf-hero__name">Property Finder</h5>
                <p class="pf-hero__tag">
                    @if($isAdmin)
                    Agencies and agents import their Property Finder listings. Each one waits for your review before it can go on the website.
                    @else
                    Bring your Property Finder listings into MW Realty — all of them once, then just the new ones.
                    @endif
                </p>
            </div>
            <span class="pf-hero__status">
                @if($isAdmin)
                    @if($pfPending)<span class="intg-status intg-status--warn"><i class="fas fa-hourglass-half"></i>{{ $pfPending }} to review</span>@else<span class="intg-status intg-status--off">{{ $pfAccounts }} account{{ $pfAccounts === 1 ? '' : 's' }} connected</span>@endif
                @elseif($pfSyncing)
                <span class="intg-status intg-status--warn"><i class="fas fa-rotate fa-spin"></i>Syncing</span>
                @elseif($pf)
                <span class="intg-status intg-status--ok"><i class="fas fa-circle-check"></i>Connected</span>
                @else
                <span class="intg-status intg-status--off">Not connected</span>
                @endif
            </span>
        </div>

        @unless($isAdmin)
        <div class="pf-steps" aria-label="Progress">
            @foreach($pfSteps as $n => [$icon, $title, $text])
            @php $state = $n < $pfStep || ($n === 4 && $pfStep === 4) ? 'is-done' : ($n === $pfStep ? 'is-current' : ''); @endphp
            <div class="pf-step {{ $state }}" @if($state === 'is-current') aria-current="step" @endif>
                <span class="pf-step__icon" aria-hidden="true"><i class="{{ $state === 'is-done' ? 'fas fa-check' : $icon }}"></i></span>
                <span class="min-w-0"><span class="pf-step__title">{{ $title }}</span><span class="pf-step__text">{{ $text }}</span></span>
            </div>
            @endforeach
        </div>
        @else
        <div class="pb-4"></div>
        @endunless
    </div>

    <div class="pf-body">
        @if($isAdmin)
            {{-- Super Admin: totals + the review queue. --}}
            <div class="pf-stats">
                <div class="pf-stat"><div class="pf-stat__label"><i class="fas fa-building"></i>Connected accounts</div><div class="pf-stat__value">{{ $pfAccounts }}</div></div>
                <div class="pf-stat {{ $pfPending ? 'pf-stat--warn' : '' }}"><div class="pf-stat__label"><i class="fas fa-hourglass-half"></i>Waiting for review</div><div class="pf-stat__value">{{ $pfPending }}</div></div>
            </div>
            <a href="{{ route('portal.crm.integrations.property-finder.review') }}" class="btn pf-btn"><i class="fas fa-list-check me-1"></i>Review imported listings @if($pfPending)({{ $pfPending }})@endif</a>

        @elseif(!$pf && !$pfCanManage)
            <div class="pf-info d-flex gap-3 align-items-start">
                <i class="fas fa-circle-info mt-1" style="color: var(--pf);"></i>
                <div><div class="fw-semibold mb-1">Your agency hasn't connected Property Finder yet</div><p>Once it does, you'll see the connection here and can sync its listings.</p></div>
            </div>

        @elseif(!$pf)
            {{-- Not connected: API key + secret. --}}
            <div class="pf-grid">
                <form method="POST" action="{{ route('portal.crm.integrations.property-finder.connect') }}" class="pf-panel js-pf-connect">
                    @csrf
                    <div class="pf-panel__title">Connect your Property Finder account</div>
                    <div class="pf-panel__sub">Paste the API credentials from your Property Finder account.</div>

                    <div class="pf-field">
                        <label for="pfApiKey">API key</label>
                        <i class="fas fa-key pf-field__icon" aria-hidden="true"></i>
                        <input type="text" name="api_key" id="pfApiKey" class="form-control" value="{{ old('api_key') }}" placeholder="Paste your API key" required autocomplete="off" spellcheck="false">
                    </div>
                    <div class="pf-field">
                        <label for="pfApiSecret">API secret</label>
                        <i class="fas fa-lock pf-field__icon" aria-hidden="true"></i>
                        <input type="password" name="api_secret" id="pfApiSecret" class="form-control" placeholder="Paste your API secret" required autocomplete="new-password" spellcheck="false">
                        <button type="button" class="pf-field__eye js-pf-eye" data-target="pfApiSecret" aria-label="Show secret"><i class="fas fa-eye"></i></button>
                    </div>

                    <div class="pf-switch" style="cursor: default;">
                        <span class="pf-switch__text"><i class="fas fa-hand-pointer me-1" style="color: var(--pf);"></i>Nothing is imported yet<span class="pf-switch__hint">After connecting, click <strong>Import all listings</strong> when you're ready.</span></span>
                    </div>

                    <button type="submit" class="btn pf-btn pf-btn--block"><i class="fas fa-plug me-2"></i>Verify &amp; connect</button>
                    <div class="pf-secure"><i class="fas fa-shield-halved me-1"></i>Checked with Property Finder before saving · stored encrypted</div>
                </form>

                <div class="pf-side">
                    <div class="pf-info">
                        <div class="pf-info__title"><i class="fas fa-box-open"></i>What gets imported</div>
                        <ul class="pf-checks">
                            <li><i class="fas fa-check"></i>Title &amp; description</li>
                            <li><i class="fas fa-check"></i>Price, sale or rent</li>
                            <li><i class="fas fa-check"></i>Beds, baths &amp; size</li>
                            <li><i class="fas fa-check"></i>Location &amp; map pin</li>
                            <li><i class="fas fa-check"></i>Photos (watermarked)</li>
                            <li><i class="fas fa-check"></i>Amenities &amp; permit no.</li>
                        </ul>
                    </div>
                    <div class="pf-info">
                        <div class="pf-info__title"><i class="fas fa-clone"></i>No duplicates</div>
                        <p>Each listing is imported once. Drafts are skipped, and so is any listing whose permit you already have here.</p>
                    </div>
                    <div class="pf-info pf-info--tip">
                        <div class="pf-info__title"><i class="fas fa-lightbulb"></i>Where to find your keys</div>
                        <p>In your Property Finder account (PF Expert) under API settings — or ask your Property Finder account manager for Enterprise API access.</p>
                    </div>
                </div>
            </div>

        @else
            {{-- Connected. --}}
            <div class="pf-stats">
                <div class="pf-stat"><div class="pf-stat__label"><i class="fas fa-key"></i>API key</div><div class="pf-stat__value pf-stat__value--mono">{{ $pf->maskedKey() }}</div></div>
                <div class="pf-stat"><div class="pf-stat__label"><i class="fas fa-house"></i>Imported</div><div class="pf-stat__value">{{ $pfImported }}</div></div>
                <div class="pf-stat {{ $pfPending ? 'pf-stat--warn' : '' }}"><div class="pf-stat__label"><i class="fas fa-hourglass-half"></i>In review</div><div class="pf-stat__value">{{ $pfPending }}</div></div>
                <div class="pf-stat"><div class="pf-stat__label"><i class="fas fa-clock"></i>Last sync</div><div class="pf-stat__value" style="font-size: .95rem;">{{ $pf->last_synced_at?->diffForHumans() ?? 'Never' }}</div></div>
            </div>

            @if($pfSyncing)
            {{-- Queued job running (or waiting for the worker): the same animated card as the click overlay, in the page. --}}
            <div class="pf-running js-pf-progress" role="status" aria-live="polite">
                @include('portal.crm.integrations._pf_flow')
                <div class="pf-running__title">{{ $pf->sync_mode === 'new' ? 'Checking for new listings…' : 'Importing your listings…' }}</div>
                <div class="pf-running__msg pf-msg js-pf-msg">{{ $pf->sync_status === 'queued' ? 'Queued — starting in a moment…' : 'Fetching your live listings…' }}</div>
                <div class="pf-running__nums">
                    <span class="pf-num pf-num--ok"><strong class="pf-count js-pf-added">{{ $pf->sync_added }}</strong>imported</span>
                    <span class="pf-num"><strong class="pf-count js-pf-skipped">{{ $pf->sync_skipped }}</strong>already here</span>
                    <span class="pf-num pf-num--bad js-pf-failed-wrap" @unless($pf->sync_failed) hidden @endunless><strong class="pf-count js-pf-failed">{{ $pf->sync_failed }}</strong>failed</span>
                </div>
                <div class="fbbar"><span></span></div>
                <div class="pf-running__note js-pf-note">
                    <i class="fas fa-circle-info me-1"></i>Runs in the background — you can leave this page; new listings wait for MW Realty's review.
                </div>
                <div class="pf-running__note pf-running__note--warn js-pf-waiting" @unless($pf->waitingForWorker()) hidden @endunless>
                    <i class="fas fa-hourglass-half me-1"></i>Waiting for the background worker to pick it up — this usually takes under a minute.
                </div>
            </div>
            @elseif($pf->sync_status === 'failed')
            <div class="pf-result is-error"><i class="fas fa-triangle-exclamation mt-1"></i><div>Last sync failed: {{ $pf->sync_error }}</div></div>
            @elseif($pf->sync_status)
            <div class="pf-result {{ $pf->sync_error ? 'is-error' : '' }}">
                <i class="fas {{ $pf->sync_error ? 'fa-circle-exclamation' : 'fa-circle-check' }} mt-1"></i>
                <div>
                    Last sync{{ $pf->sync_finished_at ? ' ' . $pf->sync_finished_at->diffForHumans() : '' }}: <strong>{{ $pf->sync_added }}</strong> new listing{{ $pf->sync_added === 1 ? '' : 's' }}, {{ $pf->sync_skipped }} already here{{ $pf->sync_failed ? ', ' . $pf->sync_failed . ' failed' : '' }}.
                    @if($pf->sync_added)New listings wait for MW Realty's review.@endif
                    @if($pf->sync_error)<div class="mt-1">{{ $pf->sync_error }}</div>@endif
                </div>
            </div>
            @endif

            <div class="pf-bar">
                <span class="pf-bar__meta">
                    Connected {{ $pf->created_at->format('d M Y') }}@if($pf->connected_by) by {{ $pf->connected_by }}@endif
                    @unless($pfCanManage) · managed by {{ $pf->owner?->displayName() ?? 'your agency' }}@endunless
                </span>
                @if($pf->last_full_sync_at)
                <form method="POST" action="{{ route('portal.crm.integrations.property-finder.sync') }}" class="m-0 js-pf-sync" data-mode="all">@csrf<input type="hidden" name="mode" value="all">
                    <button type="submit" class="btn btn-sm portal-btn-ghost" @disabled($pfSyncing) title="Go through every live listing — anything already imported is skipped"><i class="fas fa-clock-rotate-left me-1"></i>Re-check all</button>
                </form>
                <form method="POST" action="{{ route('portal.crm.integrations.property-finder.sync') }}" class="m-0 js-pf-sync" data-mode="new">@csrf<input type="hidden" name="mode" value="new">
                    <button type="submit" class="btn btn-sm pf-btn" @disabled($pfSyncing) title="Only listings added on Property Finder since the last sync"><i class="fas fa-rotate me-1"></i>Sync new listings</button>
                </form>
                @else
                <form method="POST" action="{{ route('portal.crm.integrations.property-finder.sync') }}" class="m-0 js-pf-sync" data-mode="all">@csrf<input type="hidden" name="mode" value="all">
                    <button type="submit" class="btn btn-sm pf-btn" @disabled($pfSyncing)><i class="fas fa-cloud-arrow-down me-1"></i>Import all listings</button>
                </form>
                @endif
                @if($pfCanManage)
                <div class="dropdown">
                    <button type="button" class="btn btn-sm portal-btn-ghost" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More options"><i class="fas fa-ellipsis"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button type="button" class="dropdown-item" data-bs-toggle="collapse" data-bs-target="#pfKeysForm"><i class="fas fa-key me-2"></i>Update API keys</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('portal.crm.integrations.property-finder.destroy') }}" class="m-0 js-pf-disconnect">@csrf @method('DELETE')
                                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-link-slash me-2"></i>Disconnect</button>
                            </form>
                        </li>
                    </ul>
                </div>
                @endif
            </div>

            @if($pfCanManage)
            <form method="POST" action="{{ route('portal.crm.integrations.property-finder.connect') }}" class="collapse pf-keys js-pf-connect" id="pfKeysForm">
                @csrf
                <div class="fw-semibold small mb-3">Replace the API key and secret</div>
                <div class="row g-3">
                    <div class="col-md-6 pf-field mb-0"><label for="pfNewKey">New API key</label><i class="fas fa-key pf-field__icon" aria-hidden="true"></i><input type="text" name="api_key" id="pfNewKey" class="form-control" required autocomplete="off" spellcheck="false"></div>
                    <div class="col-md-6 pf-field mb-0"><label for="pfNewSecret">New API secret</label><i class="fas fa-lock pf-field__icon" aria-hidden="true"></i><input type="password" name="api_secret" id="pfNewSecret" class="form-control" required autocomplete="new-password"><button type="button" class="pf-field__eye js-pf-eye" data-target="pfNewSecret" aria-label="Show secret"><i class="fas fa-eye"></i></button></div>
                </div>
                <div class="text-end mt-3"><button type="submit" class="btn btn-sm pf-btn">Verify &amp; save</button></div>
            </form>
            @endif
        @endif
    </div>
</div>

@if($pf)
{{-- Shown the moment Import / Sync is clicked, until the page comes back. --}}
<div class="pf-busy pf" id="pfBusy" hidden>
    <div class="pf-busy__box" role="status" aria-live="polite">
        @include('portal.crm.integrations._pf_flow')
        <div class="pf-busy__title" id="pfBusyTitle">Importing your listings…</div>
        <div class="pf-busy__msg pf-msg js-pf-msg">Connecting to Property Finder…</div>
        <div class="fbbar"><span></span></div>
        <div class="pf-busy__note">Duplicates are skipped automatically — this can take a minute with many photos.</div>
    </div>
</div>
@endif

@push('scripts')
<script>
    (function () {
        // Rotating status lines under the animation.
        var MESSAGES = ['Connecting to Property Finder…', 'Fetching your live listings…', 'Matching locations & amenities…', 'Downloading photos…', 'Skipping listings already here…', 'Sending new ones for review…'];
        function rotate(el) {
            var i = 0;
            return setInterval(function () {
                el.classList.add('is-out');
                setTimeout(function () { i = (i + 1) % MESSAGES.length; el.textContent = MESSAGES[i]; el.classList.remove('is-out'); }, 300);
            }, 2600);
        }
        function setCount(el, value) {
            if (!el || String(value) === el.textContent) return;
            el.textContent = value;
            el.classList.remove('is-bump'); void el.offsetWidth; el.classList.add('is-bump');
        }

        // Import / Sync: the overlay covers the moment until the page comes back with the queued job's card.
        var busy = document.getElementById('pfBusy');
        document.querySelectorAll('.js-pf-sync').forEach(function (form) {
            form.addEventListener('submit', function () {
                if (!busy) return;
                document.getElementById('pfBusyTitle').textContent = form.dataset.mode === 'new' ? 'Checking for new listings…' : 'Importing your listings…';
                busy.hidden = false;
                rotate(busy.querySelector('.js-pf-msg'));
                form.querySelector('[type="submit"]').disabled = true;
            });
        });

        // Live progress of a running sync; reload once it's done to show the result.
        var progress = document.querySelector('.js-pf-progress');
        if (progress) {
            var msg = progress.querySelector('.js-pf-msg'), rotating = null;
            var url = @json(route('portal.crm.integrations.property-finder.status'));
            (function poll() {
                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                    .then(function (d) {
                        if (d.status !== 'queued' && d.status !== 'running') { window.location.reload(); return; }
                        // Status lines rotate once the worker has really started.
                        if (d.status === 'running' && !rotating) { msg.textContent = MESSAGES[1]; rotating = rotate(msg); }
                        progress.querySelector('.js-pf-waiting').hidden = !d.waiting;
                        setCount(progress.querySelector('.js-pf-added'), d.added);
                        setCount(progress.querySelector('.js-pf-skipped'), d.skipped);
                        setCount(progress.querySelector('.js-pf-failed'), d.failed);
                        progress.querySelector('.js-pf-failed-wrap').hidden = !d.failed;
                        setTimeout(poll, 2500);
                    })
                    .catch(function () { setTimeout(poll, 10000); });
            })();
        }

        // Show / hide the secret.
        document.querySelectorAll('.js-pf-eye').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.target), show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.innerHTML = '<i class="fas ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
                btn.setAttribute('aria-label', show ? 'Hide secret' : 'Show secret');
            });
        });

        // "Verify & connect" talks to Property Finder first — show that it's working.
        document.querySelectorAll('.js-pf-connect').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Checking with Property Finder…';
            });
        });

        document.querySelectorAll('.js-pf-disconnect').forEach(function (form) {
            form.addEventListener('submit', async function (e) {
                if (form.dataset.confirmed) return;
                e.preventDefault();
                if (await window.portalConfirm({ title: 'Disconnect Property Finder?', message: 'Syncing stops. Listings already imported stay in your properties.', confirmText: 'Disconnect', tone: 'danger' })) {
                    form.dataset.confirmed = '1';
                    form.requestSubmit();
                }
            });
        });
    })();
</script>
@endpush
