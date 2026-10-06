@extends('portal.crm._layout')

@section('title', 'Integrations')

{{-- CRM › Integrations (Portal\Crm\IntegrationController): Facebook Lead Ads → Leads. Super Admin
     connects Pages and assigns each to an agency / agent; agencies / independent agents connect their own. --}}
@push('styles')
@if($isAdmin && $pendingPages)
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css">
@endif
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
    /* Empty state: nothing connected yet */
    .fbempty { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); border: 1px solid var(--portal-border); border-radius: 16px; overflow: hidden; }
    .fbempty__main { padding: 32px 28px; text-align: center; background: radial-gradient(circle at 50% 0%, #eef4ff 0%, #fff 70%); }
    .fbempty__art { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 18px; }
    .fbempty__bubble { display: grid; place-items: center; width: 56px; height: 56px; border-radius: 16px; font-size: 1.4rem; box-shadow: 0 10px 22px rgba(24, 39, 75, .12); }
    .fbempty__bubble--fb { background: #1877f2; color: #fff; }
    .fbempty__bubble--crm { background: #fff; color: var(--portal-primary); border: 1px solid var(--portal-border); }
    .fbempty__line { width: 54px; height: 2px; background: repeating-linear-gradient(90deg, #9cbcf3 0 6px, transparent 6px 11px); background-size: 22px 2px; animation: fbflow 1s linear infinite; }
    @keyframes fbflow { to { background-position: 22px 0; } }
    .fbempty__title { font-weight: 700; color: var(--portal-primary-dark); margin-bottom: 6px; }
    .fbempty__text { font-size: .85rem; color: var(--portal-muted); max-width: 420px; margin: 0 auto 18px; }
    .fbempty__btn { background: #1877f2; color: #fff; font-weight: 600; padding: 10px 22px; border-radius: 10px; box-shadow: 0 8px 18px rgba(24, 119, 242, .28); }
    .fbempty__btn:hover { background: #166fe0; color: #fff; }
    .fbempty__note { margin-top: 12px; font-size: .75rem; color: var(--portal-muted); }
    .fbempty__steps { list-style: none; margin: 0; padding: 28px 26px; display: flex; flex-direction: column; justify-content: center; gap: 18px; background: var(--portal-bg); border-left: 1px solid var(--portal-border); font-size: .85rem; }
    .fbempty__steps li { position: relative; display: flex; gap: 14px; }
    .fbempty__steps li:not(:last-child)::after { content: ''; position: absolute; left: 13px; top: 30px; bottom: -16px; width: 2px; background: #d9e3f4; }
    .fbempty__num { position: relative; z-index: 1; display: grid; place-items: center; flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%; background: var(--portal-primary); color: #fff; font-size: .78rem; font-weight: 700; }
    @media (max-width: 767.98px) { .fbempty { grid-template-columns: 1fr; } .fbempty__steps { border-left: 0; border-top: 1px solid var(--portal-border); } }
    /* Bulk disconnect bar + checkbox column */
    .fbcheck-col { width: 36px; }
    .fbbulk { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; padding: 10px 14px; border: 1px solid #f3c7cc; border-radius: 12px; background: #fff5f6; }
    .fbbulk[hidden] { display: none; }
    .fbbulk__count { font-size: .85rem; font-weight: 600; color: #b8283a; margin-right: auto; }
    /* Connection guide */
    .fbguide { margin-top: 18px; border: 1px solid var(--portal-border); border-radius: 14px; background: var(--portal-bg); }
    .fbguide summary { padding: 14px 18px; font-weight: 600; color: var(--portal-primary-dark); cursor: pointer; list-style: none; }
    .fbguide summary::-webkit-details-marker { display: none; }
    .fbguide summary::after { content: '\f078'; font-family: 'Font Awesome 6 Free', 'Font Awesome 5 Free'; font-weight: 900; float: right; font-size: .75rem; color: var(--portal-muted); transition: transform .2s; }
    .fbguide[open] summary::after { transform: rotate(180deg); }
    .fbguide__hint { font-weight: 400; font-size: .82rem; color: var(--portal-muted); }
    .fbguide__body { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; padding: 4px 18px 18px; font-size: .84rem; }
    .fbguide__heading { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--portal-muted); margin-bottom: 6px; }
    .fbguide ul, .fbguide ol { margin: 0; padding-left: 18px; }
    .fbguide li + li { margin-top: 6px; }
    /* Toolbar above the connected Pages table */
    .fbtoolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .fbtoolbar__title { font-weight: 700; color: var(--portal-primary-dark); }
    .fbtoolbar__count { display: inline-grid; place-items: center; min-width: 22px; height: 22px; padding: 0 6px; margin-left: 4px; border-radius: 999px; background: #e7f0fe; color: #1877f2; font-size: .75rem; }
    .fbtoolbar__search { position: relative; }
    .fbtoolbar__search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--portal-muted); font-size: .8rem; }
    .fbtoolbar__search input { padding-left: 30px; min-width: 230px; border-radius: 8px; }
    /* Pages picker after Facebook Login */
    .fbpick { border: 1px solid #d6e4fb; border-radius: 16px; background: linear-gradient(180deg, #f5f9ff 0%, #fff 70%); overflow: hidden; }
    .fbpick__head { display: flex; gap: 14px; align-items: center; padding: 18px 20px; border-bottom: 1px solid #e3ecfa; }
    .fbpick__head-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; background: #e7f0fe; color: #1877f2; font-size: 1.1rem; flex-shrink: 0; }
    .fbpick__title { font-weight: 700; color: var(--portal-primary-dark); }
    .fbpick__sub { font-size: .82rem; color: var(--portal-muted); }
    .fbpick__all { display: inline-flex; align-items: center; gap: 8px; font-size: .82rem; font-weight: 600; color: var(--portal-primary-dark); white-space: nowrap; cursor: pointer; padding: 6px 12px; border: 1px solid var(--portal-border); border-radius: 999px; background: #fff; }
    .fbpick__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; padding: 18px 20px; }
    .fbpick__card { position: relative; display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1.5px solid var(--portal-border); border-radius: 14px; background: #fff; transition: border-color .15s, box-shadow .15s; }
    .fbpick__card:hover { box-shadow: 0 6px 18px rgba(24, 39, 75, .07); }
    .fbpick__card.is-selected { border-color: #1877f2; box-shadow: 0 0 0 3px rgba(24, 119, 242, .1); }
    .fbpick__card.is-blocked { background: #fafbfd; }
    .fbpick__card.is-blocked .fbpick__main { opacity: .6; cursor: not-allowed; }
    .fbpick__main { display: flex; align-items: center; gap: 12px; min-width: 0; margin: 0; padding-right: 26px; cursor: pointer; }
    .fbpick__check { position: absolute; opacity: 0; pointer-events: none; }
    .fbpick__tick { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; border: 2px solid #c5cfdf; color: transparent; font-size: .65rem; flex-shrink: 0; transition: all .15s; }
    .fbpick__check:checked + .fbpick__tick { background: #1877f2; border-color: #1877f2; color: #fff; }
    .fbpick__check:focus-visible + .fbpick__tick { outline: 2px solid #1877f2; outline-offset: 2px; }
    .fbpick__pic { width: 46px; height: 46px; border-radius: 12px; object-fit: cover; flex-shrink: 0; background: #e7edf7; }
    .fbpick__pic--letter { display: grid; place-items: center; font-weight: 700; font-size: 1.1rem; color: #fff; background: linear-gradient(135deg, #4f8ef7, #1877f2); }
    .fbpick__text { flex: 1 1 auto; min-width: 0; }
    .fbpick__name { display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; font-weight: 600; line-height: 1.3; color: var(--portal-primary-dark); }
    .fbpick__id { display: block; margin-top: 2px; font-size: .75rem; color: var(--portal-muted); }
    .fbpick__remove { position: absolute; top: 8px; right: 8px; width: 26px; height: 26px; border: 0; border-radius: 50%; background: transparent; color: #9aa3b5; display: grid; place-items: center; }
    .fbpick__remove:hover { background: #fdecec; color: #dc3545; }
    .fbpick__chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: .72rem; font-weight: 600; background: #eef2f8; color: #5b6478; }
    .fbpick__chip--ok { background: #e7f6ee; color: #0f7a43; }
    .fbpick__chip--blocked { background: #fdecec; color: #b8283a; }
    .fbpick__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 14px 20px; border-top: 1px solid #e3ecfa; background: #fff; }
    .fbpick__count { font-size: .82rem; font-weight: 600; color: var(--portal-muted); }
    .intg-reconnect { background: #fff8ec; }
    .fbpick__import { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin: 0 20px 18px; padding: 14px 16px; border: 1px dashed #c9d8f2; border-radius: 12px; background: #fff; }
    .fbpick__switch { display: flex; align-items: flex-start; gap: 12px; margin: 0; cursor: pointer; flex: 1 1 360px; font-size: .85rem; }
    .fbpick__switch input { position: absolute; opacity: 0; }
    .fbpick__switch-track { position: relative; flex-shrink: 0; width: 38px; height: 22px; margin-top: 1px; border-radius: 999px; background: #cfd6e3; transition: background .2s; }
    .fbpick__switch-track::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .2s; }
    .fbpick__switch input:checked + .fbpick__switch-track { background: #1877f2; }
    .fbpick__switch input:checked + .fbpick__switch-track::after { transform: translateX(16px); }
    .fbpick__switch input:focus-visible + .fbpick__switch-track { outline: 2px solid #1877f2; outline-offset: 2px; }
    .fbpick__days { width: auto; min-width: 210px; }
    /* Indeterminate progress bar + spinner (connecting overlay, row imports) */
    .fbbar { position: relative; height: 4px; border-radius: 999px; background: #e3ecfa; overflow: hidden; }
    .fbbar span { position: absolute; top: 0; left: -40%; width: 40%; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #4f8ef7, #1877f2); animation: fbbar 1.2s ease-in-out infinite; }
    @keyframes fbbar { to { left: 100%; } }
    .fbimport { margin-top: 8px; max-width: 260px; }
    .fbimport__label { display: flex; align-items: center; gap: 8px; font-size: .78rem; font-weight: 600; color: #1360c6; margin-bottom: 5px; }
    .fbimport__spin { width: 12px; height: 12px; border-radius: 50%; border: 2px solid #c6dafc; border-top-color: #1877f2; animation: fbspin .8s linear infinite; }
    @keyframes fbspin { to { transform: rotate(360deg); } }
    .fbbusy { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; background: rgba(17, 27, 51, .45); backdrop-filter: blur(2px); }
    .fbbusy[hidden] { display: none; }
    .fbbusy__box { width: min(360px, calc(100vw - 32px)); padding: 28px 24px; border-radius: 18px; background: #fff; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,.2); }
    .fbbusy__logo { position: relative; display: inline-grid; place-items: center; width: 64px; height: 64px; border-radius: 50%; background: #1877f2; color: #fff; font-size: 1.6rem; }
    .fbbusy__ring { position: absolute; inset: -6px; border-radius: 50%; border: 3px solid transparent; border-top-color: #1877f2; border-right-color: #8bb6f9; animation: fbspin 1s linear infinite; }
    @media (prefers-reduced-motion: reduce) { .fbbar span, .fbimport__spin, .fbbusy__ring { animation-duration: 3s; } }
    @media (max-width: 575.98px) { .fbpick__head { flex-wrap: wrap; } .fbpick__grid { padding: 14px; } }
    .intg-copy { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8rem; word-break: break-all; }
    .select2-container--default .select2-selection--single { height: 36px; border-color: var(--portal-border, #dee2e6); border-radius: 8px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: .7rem; font-size: .85rem; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 34px; }
    .select2-dropdown { border-color: var(--portal-border, #dee2e6); border-radius: 10px; overflow: hidden; font-size: .85rem; }
</style>
@endpush

@section('crm-content')
<div class="mb-3">
    <div class="portal-section-title mb-0">Integrations</div>
    <p class="text-muted mb-0" style="font-size: 0.85rem;">Bring leads from other channels straight into the CRM.</p>
</div>

<div class="portal-card intg-card mb-3">
    <div class="intg-card__head">
        <span class="intg-logo" aria-hidden="true"><i class="fab fa-facebook-f"></i></span>
        <div class="flex-grow-1 min-w-0">
            <h5 class="intg-card__title">Facebook Lead Ads</h5>
            <p class="intg-card__sub">
                @if($isAdmin)
                Connect Facebook Pages and assign each one to an agency or agent — the Page's lead form leads go straight to their CRM. The ad's name becomes the lead's Source.
                @else
                Every lead from your Facebook / Instagram lead forms arrives in Leads automatically. The ad's name becomes the lead's Source.
                @endif
            </p>
        </div>
        @php $connectedCount = $isAdmin ? $connections->total() : $connections->count(); @endphp
        @if(!$configured)
        <span class="intg-status intg-status--off"><i class="fas fa-circle-pause"></i>Not set up</span>
        @elseif($connectedCount)
        <span class="intg-status intg-status--ok"><i class="fas fa-circle-check"></i>{{ $connectedCount }} page{{ $connectedCount === 1 ? '' : 's' }} connected</span>
        @else
        <span class="intg-status intg-status--off">Not connected</span>
        @endif
    </div>

    <div class="intg-card__body">
        @if(!$configured)
            @if($isAdmin)
            <div class="alert alert-warning small">
                <div class="fw-bold mb-1"><i class="fas fa-triangle-exclamation me-1"></i>Facebook app credentials missing</div>
                Add <code>FACEBOOK_APP_ID</code>, <code>FACEBOOK_APP_SECRET</code> and <code>FACEBOOK_WEBHOOK_VERIFY_TOKEN</code> to the <code>.env</code> file, then in the Meta app (developers.facebook.com):
                <ul class="mb-0 mt-2">
                    <li><strong>Facebook Login › Valid OAuth Redirect URIs:</strong> <span class="intg-copy">{{ $callbackUrl }}</span></li>
                    <li><strong>Webhooks › Page › Callback URL:</strong> <span class="intg-copy">{{ $webhookUrl }}</span> (verify token = <code>FACEBOOK_WEBHOOK_VERIFY_TOKEN</code>), subscribe to <strong>leadgen</strong>.</li>
                    <li><strong>Permissions:</strong> {{ implode(', ', \App\Services\Integrations\FacebookLeadAds::SCOPES) }}.</li>
                </ul>
            </div>
            @else
            <div class="alert alert-light border small mb-0"><i class="fas fa-circle-info me-1"></i>The Facebook integration isn't available yet — MW Realty is setting it up.</div>
            @endif
        @elseif(!$canManage)
            <div class="alert alert-light border small mb-0"><i class="fas fa-circle-info me-1"></i>Your agency manages integrations. Facebook leads it receives are shared with you like any other agency lead.</div>
        @else
            {{-- Back from Facebook Login: Super Admin assigns each Page to an account; an agency / agent ticks their own. --}}
            @if($pendingPages)
            <form method="POST" action="{{ route('portal.crm.integrations.facebook.pages.store') }}" class="fbpick mb-4" id="fbPickForm">
                @csrf
                <div class="fbpick__head">
                    <span class="fbpick__head-icon"><i class="fab fa-facebook-f"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fbpick__title">{{ $isAdmin ? 'Assign your Facebook Pages' : 'Choose the Pages to connect' }}</div>
                        <div class="fbpick__sub">
                            {{ $isAdmin ? 'Pick the agency or agent whose CRM receives each Page\'s leads. Pages left unassigned are skipped.' : 'Leads from the lead forms on the selected Pages will come into your CRM.' }}
                            Each Page can belong to one account only.
                        </div>
                    </div>
                    @unless($isAdmin)
                    <label class="fbpick__all"><input type="checkbox" class="form-check-input mt-0" id="fbPickAll" checked> Select all</label>
                    @endunless
                </div>

                <div class="fbpick__grid">
                    @foreach($pendingPages as $page)
                    @php
                        $existing = $existingConnections->get($page['id']);
                        $mine = $existing && !$isAdmin && $existing->portal_user_id === $ownerId;
                        $blocked = $existing && !$mine;
                    @endphp
                    <div class="fbpick__card {{ $blocked ? 'is-blocked' : (!$isAdmin ? 'is-selected' : '') }} js-pick-card">
                        <button type="button" class="fbpick__remove js-pick-remove" title="Remove from this list" aria-label="Remove {{ $page['name'] }} from this list"><i class="fas fa-xmark"></i></button>
                        <label class="fbpick__main" @unless($isAdmin || $blocked) for="fbPage{{ $loop->index }}" @endunless>
                            @unless($isAdmin)
                            <input type="checkbox" class="fbpick__check js-pick-check" name="page_ids[]" value="{{ $page['id'] }}" id="fbPage{{ $loop->index }}" @checked(!$blocked) @disabled($blocked)>
                            <span class="fbpick__tick" aria-hidden="true"><i class="fas fa-check"></i></span>
                            @endunless
                            @if($page['picture'])
                            <img src="{{ $page['picture'] }}" alt="" class="fbpick__pic">
                            @else
                            <span class="fbpick__pic fbpick__pic--letter">{{ mb_strtoupper(mb_substr($page['name'], 0, 1)) }}</span>
                            @endif
                            <span class="fbpick__text">
                                <span class="fbpick__name" title="{{ $page['name'] }}">{{ $page['name'] }}</span>
                                <span class="fbpick__id">ID {{ $page['id'] }}</span>
                            </span>
                        </label>
                        <div class="fbpick__foot">
                            @if($blocked)
                            <span class="fbpick__chip fbpick__chip--blocked"><i class="fas fa-ban"></i>{{ $isAdmin ? 'Connected to ' . ($existing->owner?->displayName() ?? 'another account') : 'Connected to another account' }}</span>
                            @elseif($existing)
                            <span class="fbpick__chip fbpick__chip--ok"><i class="fas fa-rotate"></i>Connected — access will be refreshed</span>
                            @else
                            <span class="fbpick__chip"><i class="fas fa-plus"></i>New Page</span>
                            @endif
                        </div>
                        @if($isAdmin && !$blocked)
                        <select name="owners[{{ $page['id'] }}]" class="js-account-picker" data-placeholder="Assign to agency / agent…">
                            <option value=""></option>
                        </select>
                        @endif
                    </div>
                    @endforeach
                </div>

                {{-- Bring in the leads these Pages already have (runs in the background, duplicates skipped). --}}
                <div class="fbpick__import">
                    <label class="fbpick__switch">
                        <input type="checkbox" name="import_existing" value="1" id="fbImportToggle">
                        <span class="fbpick__switch-track" aria-hidden="true"></span>
                        <span>
                            <span class="fw-semibold d-block">Also import the leads these Pages already have</span>
                            <span class="small text-muted">Runs in the background. People already in the CRM aren't added twice — a lead from a new ad is added to their history.</span>
                        </span>
                    </label>
                    <select name="import_days" class="form-select form-select-sm fbpick__days" id="fbImportDays" disabled aria-label="How far back to import">
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90" selected>Last 90 days (all Facebook keeps)</option>
                    </select>
                </div>

                <div class="fbpick__actions">
                    @unless($isAdmin)<span class="fbpick__count" id="fbPickCount"></span>@endunless
                    <button type="submit" form="cancelPagesForm" class="btn btn-sm portal-btn-ghost ms-auto">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-portal-primary" id="fbPickSubmit"><i class="fas fa-link me-1"></i>Connect Pages</button>
                </div>
            </form>
            <form method="POST" action="{{ route('portal.crm.integrations.facebook.pages.cancel') }}" id="cancelPagesForm" class="d-none">@csrf</form>

            {{-- Shown while the chosen Pages are being connected. --}}
            <div class="fbbusy" id="fbBusy" hidden>
                <div class="fbbusy__box" role="status" aria-live="polite">
                    <div class="fbbusy__logo"><i class="fab fa-facebook-f"></i><span class="fbbusy__ring"></span></div>
                    <div class="fw-bold mt-3" id="fbBusyTitle">Connecting your Pages…</div>
                    <div class="small text-muted">Subscribing them to Facebook lead notifications. This takes a few seconds.</div>
                    <div class="fbbar mt-3"><span></span></div>
                </div>
            </div>
            @endif

            @php $brokenPages = $connections->filter(fn ($c) => $c->needs_reconnect_at); @endphp
            @if($brokenPages->isNotEmpty())
            <div class="alert alert-warning small d-flex gap-2 align-items-start">
                <i class="fas fa-plug-circle-xmark mt-1"></i>
                <div>
                    <strong>{{ $brokenPages->pluck('page_name')->join(', ', ' and ') }}</strong> {{ $brokenPages->count() === 1 ? 'has' : 'have' }} stopped sending leads — Facebook needs a fresh login (the access expired or a permission is missing).
                    Click <strong>Reconnect</strong>, log in with a Facebook account that is an admin of the Page, tick it and click <strong>Connect Pages</strong>. Then use <strong>Sync now</strong> to fetch the leads missed meanwhile.
                </div>
            </div>
            @endif

            @if(!$connectedCount && $search === '')
            {{-- Nothing connected yet: one clear starting point, with how it works alongside. --}}
            <div class="fbempty">
                <div class="fbempty__main">
                    <div class="fbempty__art" aria-hidden="true">
                        <span class="fbempty__bubble fbempty__bubble--fb"><i class="fab fa-facebook-f"></i></span>
                        <span class="fbempty__line"></span>
                        <span class="fbempty__bubble fbempty__bubble--crm"><i class="fas fa-address-card"></i></span>
                    </div>
                    <h6 class="fbempty__title">{{ $pendingPages ? 'Pick your Pages above to finish' : ($isAdmin ? 'Connect Facebook Pages for your agencies & agents' : 'Connect your Facebook Page') }}</h6>
                    <p class="fbempty__text">{{ $isAdmin ? 'Log in with a Facebook account that manages the Pages, then assign each Page to the agency or agent who should get its leads.' : 'Log in with a Facebook account that is an admin of your Page. Leads from your lead ads then land in your CRM automatically.' }}</p>
                    <a href="{{ route('portal.crm.integrations.facebook.connect') }}" data-popup-url="{{ route('portal.crm.integrations.facebook.connect', ['popup' => 1]) }}" class="btn fbempty__btn js-fb-connect">
                        <i class="fab fa-facebook me-2"></i>Continue with Facebook
                    </a>
                    <div class="fbempty__note"><i class="fas fa-lock me-1"></i>Opens a secure Facebook window. MW Realty only reads your Pages' lead forms.</div>
                </div>
                <ol class="fbempty__steps">
                    <li><span class="fbempty__num">1</span><div><div class="fw-semibold">Connect the Page</div><div class="text-muted">Log in with Facebook and pick the Pages that run the lead ads — each Page goes to one account.</div></div></li>
                    <li><span class="fbempty__num">2</span><div><div class="fw-semibold">Leads arrive instantly</div><div class="text-muted">Each form submission becomes a lead — name, email, phone and answers included.</div></div></li>
                    <li><span class="fbempty__num">3</span><div><div class="fw-semibold">Sorted by ad</div><div class="text-muted">The ad's name is the lead's Source, so you can filter and report per campaign.</div></div></li>
                </ol>
            </div>
            @else
            <div class="fbtoolbar">
                <div>
                    <div class="fbtoolbar__title">Connected Pages <span class="fbtoolbar__count">{{ $connectedCount }}</span></div>
                    <div class="small text-muted">{{ $isAdmin ? 'Each Page sends its leads to the account shown.' : 'New leads from these Pages arrive in Leads automatically.' }}</div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @if($isAdmin)
                    <form method="GET" class="fbtoolbar__search">
                        <i class="fas fa-search"></i>
                        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search Page or account…" aria-label="Search Pages">
                    </form>
                    @endif
                    <a href="{{ route('portal.crm.integrations.facebook.connect') }}" data-popup-url="{{ route('portal.crm.integrations.facebook.connect', ['popup' => 1]) }}" class="btn btn-sm text-white js-fb-connect" style="background: #1877f2;" title="Opens a Facebook window">
                        <i class="fab fa-facebook me-1"></i>Connect another Page
                    </a>
                </div>
            </div>
            @endif

            @if($connections->isNotEmpty())
            {{-- Bulk disconnect: the row checkboxes belong to this form (form="fbBulkForm"). --}}
            <form method="POST" action="{{ route('portal.crm.integrations.facebook.bulk-destroy') }}" id="fbBulkForm" class="fbbulk" hidden>
                @csrf @method('DELETE')
                <span class="fbbulk__count"><i class="fas fa-check-square me-1"></i><span id="fbBulkCount">0</span> selected</span>
                <button type="button" class="btn btn-sm portal-btn-ghost" id="fbBulkClear">Clear</button>
                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-link-slash me-1"></i>Disconnect selected</button>
            </form>
            <div class="table-responsive mb-3">
                <table class="table portal-table align-middle mb-0">
                    <thead><tr>
                        <th class="fbcheck-col"><input type="checkbox" class="form-check-input" id="fbBulkAll" aria-label="Select all Pages"></th>
                        <th>Page</th>@if($isAdmin)<th>Leads go to</th>@endif<th>Status</th><th class="text-center">Leads</th><th>Last lead</th><th class="text-end">Actions</th>
                    </tr></thead>
                    <tbody>
                        @foreach($connections as $connection)
                        <tr class="{{ $connection->needs_reconnect_at ? 'intg-reconnect' : '' }}">
                            <td class="fbcheck-col"><input type="checkbox" class="form-check-input js-bulk-check" name="ids[]" value="{{ $connection->id }}" form="fbBulkForm" data-page="{{ $connection->page_name }}" aria-label="Select {{ $connection->page_name }}"></td>
                            <td>
                                <div class="fw-semibold">{{ $connection->page_name }}</div>
                                <div class="small text-muted">Connected {{ $connection->created_at->format('d M Y') }}@if($connection->connected_by) by {{ $connection->connected_by }}@endif</div>
                            </td>
                            @if($isAdmin)
                            <td>
                                <div class="fw-semibold">{{ $connection->owner?->displayName() ?? '—' }}</div>
                                @if($connection->owner)<div class="small text-muted">{{ $connection->owner->isAgency() ? 'Agency' : 'Agent' }}</div>@endif
                            </td>
                            @endif
                            @include('portal.crm.integrations._connection_cells', ['connection' => $connection])
                            <td class="text-end text-nowrap">
                                @if($connection->needs_reconnect_at)
                                <a href="{{ route('portal.crm.integrations.facebook.connect') }}" data-popup-url="{{ route('portal.crm.integrations.facebook.connect', ['popup' => 1]) }}" class="btn btn-sm text-white js-fb-connect" style="background: #1877f2;"><i class="fab fa-facebook me-1"></i>Reconnect</a>
                                @endif
                                <form method="POST" action="{{ route('portal.crm.integrations.facebook.sync', $connection->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm portal-btn-ghost" title="Fetch leads from the last 30 days (or since the last sync)"><i class="fas fa-rotate me-1"></i>Sync now</button>
                                </form>
                                <form method="POST" action="{{ route('portal.crm.integrations.facebook.destroy', $connection->id) }}" class="d-inline js-disconnect" data-page="{{ $connection->page_name }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Disconnect</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($isAdmin)<div>{{ $connections->links('pagination::bootstrap-5') }}</div>@endif
            @elseif($isAdmin && $search !== '')
            <p class="text-muted small mb-0">No connected Pages match “{{ $search }}”.</p>
            @endif

            {{-- How to connect + common problems. --}}
            <details class="fbguide">
                <summary><i class="fas fa-book-open me-2"></i>How to connect a Facebook Page <span class="fbguide__hint">— steps &amp; troubleshooting</span></summary>
                <div class="fbguide__body">
                    <div>
                        <div class="fbguide__heading">Before you start</div>
                        <ul>
                            <li>Use a Facebook account with <strong>full control (admin)</strong> of the Page — in Meta Business Suite › Settings › Pages › People.</li>
                            <li>Lead ads need a <strong>lead form</strong> (Instant Form) on the Page — create one in Meta Ads Manager or Business Suite.</li>
                            <li>Allow pop-ups for this site — Facebook Login opens in a small window.</li>
                        </ul>
                    </div>
                    <div>
                        <div class="fbguide__heading">Connecting</div>
                        <ol>
                            <li>Click <strong>{{ $connectedCount ? 'Connect another Page' : 'Continue with Facebook' }}</strong> and log in.</li>
                            <li>If Facebook asks <em>"Continue with previous settings?"</em>, click <strong>Edit settings</strong>.</li>
                            <li>Select <strong>all the Pages</strong> you want and keep <strong>every permission switched on</strong> (Pages, lead access, manage ads).</li>
                            <li>Back here, {{ $isAdmin ? 'assign each Page to its agency / agent' : 'tick the Pages' }}, choose whether to import existing leads, and click <strong>Connect Pages</strong>.</li>
                            <li>New leads now arrive in <strong>Leads</strong> within seconds. Use <strong>Sync now</strong> any time to fetch recent ones.</li>
                        </ol>
                    </div>
                    <div>
                        <div class="fbguide__heading">If something goes wrong</div>
                        <ul>
                            <li><strong>Reconnect needed</strong> — Facebook stopped accepting access (password changed, admin removed, or a permission missing). Click <strong>Reconnect</strong> and repeat the steps; missed leads are fetched automatically.</li>
                            <li><strong>"Requires … permission"</strong> — a permission was switched off during login. Remove the app in Facebook › Settings › Business integrations, then connect again with all switches on.</li>
                            <li><strong>Already connected to another account</strong> — a Page can feed one account only; it must be disconnected there first.</li>
                            <li><strong>No lead forms yet</strong> — the Page is connected; leads arrive once a lead form gets submissions.</li>
                        </ul>
                    </div>
                </div>
            </details>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if($isAdmin && $pendingPages)
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
<script>
    // Agency / agent pickers: searched on the server, 20 per request, more on scroll.
    if (window.jQuery) {
        jQuery('.js-account-picker').each(function () {
            jQuery(this).select2({
                placeholder: this.dataset.placeholder,
                allowClear: true,
                width: '100%',
                ajax: {
                    url: "{{ route('portal.crm.integrations.accounts') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term || '', page: params.page || 1 }; },
                    processResults: function (data) { return data; },
                },
            });
        });
    }
</script>
@endif
<script>
    // Pages picker: selected-card highlight, select all, count, and removing a Page from the list.
    (function () {
        var form = document.getElementById('fbPickForm');
        if (!form) return;
        var all = document.getElementById('fbPickAll'), count = document.getElementById('fbPickCount'), submit = document.getElementById('fbPickSubmit');
        function checks() { return Array.prototype.slice.call(form.querySelectorAll('.js-pick-check:not(:disabled)')); }
        function refresh() {
            var list = checks(), picked = list.filter(function (c) { return c.checked; }).length;
            list.forEach(function (c) { c.closest('.js-pick-card').classList.toggle('is-selected', c.checked); });
            if (count) count.textContent = picked + ' of ' + list.length + ' Page' + (list.length === 1 ? '' : 's') + ' selected';
            if (all) { all.checked = list.length > 0 && picked === list.length; all.indeterminate = picked > 0 && picked < list.length; }
            if (count && submit) submit.disabled = picked === 0;
        }
        form.addEventListener('change', function (e) { if (e.target.classList.contains('js-pick-check')) refresh(); });
        if (all) all.addEventListener('change', function () { checks().forEach(function (c) { c.checked = all.checked; }); refresh(); });
        var importToggle = document.getElementById('fbImportToggle'), importDays = document.getElementById('fbImportDays');
        if (importToggle) importToggle.addEventListener('change', function () { importDays.disabled = !importToggle.checked; });
        form.addEventListener('submit', function () {
            if (importToggle && importToggle.checked) document.getElementById('fbBusyTitle').textContent = 'Connecting your Pages and starting the lead import…';
            document.getElementById('fbBusy').hidden = false;
            if (submit) submit.disabled = true;
        });
        form.querySelectorAll('.js-pick-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.closest('.js-pick-card').remove();
                if (!form.querySelector('.js-pick-card')) { document.getElementById('cancelPagesForm').submit(); return; }
                refresh();
            });
        });
        refresh();
    })();

    // Background lead imports: poll their progress; reload once they've all finished to show the totals.
    (function () {
        if (!document.querySelector('.js-import')) return;
        var url = @json(route('portal.crm.integrations.facebook.import-status'));
        function poll() {
            fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (data) {
                    if (!data.importing.length) { window.location.reload(); return; }
                    data.importing.forEach(function (row) {
                        var el = document.querySelector('.js-import[data-id="' + row.id + '"] .js-import-text');
                        if (el) el.textContent = row.status === 'queued' ? 'Starting lead import…' : 'Importing leads… ' + row.added + ' added' + (row.skipped ? ', ' + row.skipped + ' already in CRM' : '');
                    });
                    setTimeout(poll, 3000);
                })
                .catch(function () { setTimeout(poll, 10000); });
        }
        setTimeout(poll, 3000);
    })();

    // Facebook Login in a centred popup over this page (it reloads this page when done); popup blocked → this tab.
    document.querySelectorAll('.js-fb-connect').forEach(function (link) {
        link.addEventListener('click', function (e) {
            var w = 600, h = 720;
            var left = window.screenX + Math.max(0, (window.outerWidth - w) / 2), top = window.screenY + Math.max(0, (window.outerHeight - h) / 2);
            var popup = window.open(link.dataset.popupUrl, 'fbConnect', 'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',scrollbars=yes');
            if (popup) {
                e.preventDefault();
                popup.focus();
            }
        });
    });
    try {
        new BroadcastChannel('fb-connect').onmessage = function () { window.location.reload(); };
    } catch (e) {}

    // Bulk disconnect: select all, live count, confirm.
    (function () {
        var form = document.getElementById('fbBulkForm');
        if (!form) return;
        var all = document.getElementById('fbBulkAll'), checks = Array.prototype.slice.call(document.querySelectorAll('.js-bulk-check'));
        function picked() { return checks.filter(function (c) { return c.checked; }); }
        function refresh() {
            var n = picked().length;
            form.hidden = n === 0;
            document.getElementById('fbBulkCount').textContent = n;
            all.checked = n > 0 && n === checks.length;
            all.indeterminate = n > 0 && n < checks.length;
        }
        all.addEventListener('change', function () { checks.forEach(function (c) { c.checked = all.checked; }); refresh(); });
        checks.forEach(function (c) { c.addEventListener('change', refresh); });
        document.getElementById('fbBulkClear').addEventListener('click', function () { checks.forEach(function (c) { c.checked = false; }); refresh(); });
        form.addEventListener('submit', async function (e) {
            if (form.dataset.confirmed) return;
            e.preventDefault();
            var names = picked().map(function (c) { return c.dataset.page; });
            if (await window.portalConfirm({ title: 'Disconnect ' + names.length + ' Page' + (names.length === 1 ? '' : 's') + '?', message: names.join(', ') + ' will stop sending new Facebook leads. Leads already in the CRM stay.', confirmText: 'Disconnect', tone: 'danger' })) {
                form.dataset.confirmed = '1';
                form.requestSubmit();
            }
        });
    })();

    // Confirm before disconnecting a Page.
    document.querySelectorAll('.js-disconnect').forEach(function (form) {
        form.addEventListener('submit', async function (e) {
            if (form.dataset.confirmed) return;
            e.preventDefault();
            if (await window.portalConfirm({ title: 'Disconnect ' + form.dataset.page + '?', message: 'New Facebook leads from this Page will stop coming in. Leads already in the CRM stay.', confirmText: 'Disconnect', tone: 'danger' })) {
                form.dataset.confirmed = '1';
                form.requestSubmit();
            }
        });
    });
</script>
@endpush
