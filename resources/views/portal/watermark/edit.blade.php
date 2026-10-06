@extends('portal.layouts.app')

@section('title', 'Listing Settings')

{{--
    Listings Settings → Watermark (PortalWatermarkController).
    Agency / independent agent: their own watermark (an agency's agents see the agency's, read-only).
    Super Admin: "Default watermark" (its listings + every account without one) and
    "Agency & agent watermarks" (who has set their own, 20 per page).
--}}
@php
    $s = [
        'enabled' => (bool) old('enabled', $settings['enabled']),
        'type' => old('type', $settings['type']),
        'text' => old('text', $settings['text']),
        'color' => old('color', $settings['color']),
        'opacity' => (int) old('opacity', $settings['opacity']),
        'size' => (int) old('size', $settings['size']),
        'position' => old('position', $settings['position']),
    ];
    $imageUrl = $settings['image'] ? route('portal.watermark.image') . '?v=' . md5($settings['image']) : null;
    $positionLabels = ['tl' => 'Top left', 'tc' => 'Top centre', 'tr' => 'Top right', 'ml' => 'Middle left', 'mc' => 'Centre', 'mr' => 'Middle right', 'bl' => 'Bottom left', 'bc' => 'Bottom centre', 'br' => 'Bottom right'];
    $brand = $owner ? ($owner->company_name ?: $owner->name) : 'MW Realty';
@endphp

@push('styles')
<style>
    .wm-page-head { margin-bottom: .6rem; }
    .wm-page-head h1 { font-size: 1.3rem; font-weight: 800; margin: 0 0 .15rem; }
    .wm-page-head p { margin: 0; font-size: .85rem; color: var(--portal-muted); }
    .wm-tabs { display: flex; gap: 1.5rem; border-bottom: 1px solid var(--portal-border); margin-bottom: .9rem; overflow-x: auto; }
    .wm-tabs a { padding: .65rem 0; font-weight: 600; font-size: .86rem; color: var(--portal-muted); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
    .wm-tabs a:hover { color: var(--portal-text); }
    .wm-tabs a.is-active { color: var(--portal-primary); border-bottom-color: var(--portal-primary); }
    .wm-tabs .wm-tab-count { margin-left: .35rem; padding: .05rem .45rem; border-radius: 999px; background: #eef1f7; font-size: .7rem; }

    .wm-notice { display: flex; gap: .65rem; align-items: flex-start; padding: .75rem 1rem; border-radius: 10px; font-size: .84rem; margin-bottom: 1rem; background: #eef3fb; color: #244373; }
    .wm-notice i { margin-top: .15rem; }

    .wm-layout { display: grid; grid-template-columns: minmax(0, 420px) minmax(0, 1fr); gap: 1.25rem; align-items: start; }
    .wm-panel { background: var(--portal-surface); border: 1px solid var(--portal-border); border-radius: var(--portal-radius); box-shadow: var(--portal-shadow); }
    .wm-settings { display: flex; flex-direction: column; margin: 0; padding: 0; border: 0; min-width: 0; }
    .wm-row { padding: .7rem 1.1rem; border-bottom: 1px solid var(--portal-border); }
    .wm-row-title { font-size: .78rem; font-weight: 700; color: var(--portal-text); margin-bottom: .4rem; display: flex; justify-content: space-between; align-items: center; }
    .wm-row-title output { font-weight: 600; color: var(--portal-muted); }
    .wm-help { font-size: .76rem; color: var(--portal-muted); }

    .wm-switch-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .wm-switch-row .form-check-input { width: 2.6em; height: 1.4em; cursor: pointer; margin: 0; }
    .wm-switch-row .form-check-input:checked { background-color: var(--portal-primary); border-color: var(--portal-primary); }
    .wm-state { display: inline-flex; align-items: center; gap: .3rem; font-size: .7rem; font-weight: 700; padding: .15rem .55rem; border-radius: 999px; margin-left: .4rem; vertical-align: middle; }
    .wm-state.is-on { background: #e8f7ef; color: #15803d; }
    .wm-state.is-off { background: #f1f2f7; color: var(--portal-muted); }

    .wm-segment { display: grid; grid-template-columns: 1fr 1fr; padding: 3px; border-radius: 10px; background: #f1f2f7; }
    .wm-segment label { margin: 0; cursor: pointer; }
    .wm-segment input { position: absolute; opacity: 0; pointer-events: none; }
    .wm-segment span { display: flex; align-items: center; justify-content: center; gap: .4rem; padding: .45rem; border-radius: 8px; font-size: .82rem; font-weight: 600; color: var(--portal-muted); transition: background .15s, color .15s; }
    .wm-segment input:checked + span { background: #fff; color: var(--portal-text); box-shadow: 0 1px 3px rgba(20, 24, 50, .12); }
    .wm-segment input:focus-visible + span { outline: 2px solid var(--portal-primary); }

    .wm-logo { display: flex; align-items: center; gap: .85rem; }
    .wm-logo-box { width: 56px; height: 56px; flex: 0 0 56px; border-radius: 10px; border: 1px solid var(--portal-border); background: repeating-conic-gradient(#eef0f5 0% 25%, #fff 0% 50%) 50% / 12px 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; color: #9aa0b8; }
    .wm-logo-box img { max-width: 88%; max-height: 88%; }
    .wm-logo-actions { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .3rem; }
    .wm-logo-actions .btn { font-size: .78rem; padding: .3rem .7rem; }

    .wm-swatches { display: flex; gap: .45rem; align-items: center; }
    .wm-swatch { width: 22px; height: 22px; flex: 0 0 22px; border-radius: 50%; border: 2px solid var(--portal-border); cursor: pointer; padding: 0; }
    .wm-swatch.is-active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--portal-primary); }
    .wm-color { width: 26px; height: 26px; padding: 0; border: 0; background: none; cursor: pointer; }

    .wm-grid { display: grid; grid-template-columns: repeat(3, 34px); grid-template-rows: repeat(3, 26px); gap: 4px; padding: 6px; border-radius: 10px; background: #f1f2f7; width: max-content; }
    .wm-grid button { border: 0; border-radius: 6px; background: #fff; padding: 0; position: relative; }
    .wm-grid button::after { content: ""; position: absolute; inset: 50% auto auto 50%; width: 6px; height: 6px; margin: -3px 0 0 -3px; border-radius: 50%; background: #c4c9da; }
    .wm-grid button:hover::after { background: var(--portal-primary); }
    .wm-grid button.is-active { background: var(--portal-primary); }
    .wm-grid button.is-active::after { background: #fff; }
    .wm-position-row { display: flex; align-items: center; gap: 1rem; }

    .wm-range { width: 100%; accent-color: var(--portal-primary); }
    .wm-sliders { display: flex; flex-direction: column; justify-content: center; gap: .8rem; }
    .wm-place { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 1.25rem; align-items: start; }
    .wm-text-row { display: flex; align-items: center; gap: .6rem; }
    .wm-text-row .form-control { flex: 1; min-width: 0; }

    .wm-footer { position: sticky; bottom: 0; display: flex; justify-content: flex-end; gap: .5rem; padding: .7rem 1.1rem; background: var(--portal-surface); border-radius: 0 0 var(--portal-radius) var(--portal-radius); }
    .wm-footer .btn { min-width: 110px; }

    .wm-preview-panel { position: sticky; top: 1rem; padding: .85rem 1rem; }
    .wm-preview-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: .75rem; font-size: .85rem; font-weight: 700; }
    /* Sized to the screen: as wide as fits, but never taller than the space under the header. */
    .wm-preview { position: relative; aspect-ratio: 4 / 3; width: 100%; max-width: calc((100vh - 330px) * 4 / 3); min-width: 280px; margin: 0 auto; border-radius: 10px; overflow: hidden; background: #ddd; }
    .wm-preview > img.wm-photo { width: 100%; height: 100%; object-fit: cover; display: block; }
    .wm-mark { position: absolute; pointer-events: none; transition: opacity .15s; }
    .wm-mark img { display: block; width: 100%; }
    .wm-mark span { display: block; white-space: nowrap; font-family: Verdana, 'DejaVu Sans', sans-serif; font-weight: 700; line-height: 1.15; }
    .wm-preview.is-off .wm-mark { display: none; }
    .wm-preview-off { position: absolute; inset: auto 0 0 0; padding: .5rem .8rem; background: rgba(20, 24, 50, .65); color: #fff; font-size: .76rem; display: none; }
    .wm-preview.is-off .wm-preview-off { display: block; }

    /* Agency & agent watermarks tab */
    .wm-toolbar { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--portal-border); }
    .wm-toolbar form { display: flex; gap: .5rem; flex: 1; max-width: 420px; }
    .wm-filter { display: inline-flex; padding: 3px; border-radius: 999px; background: #f1f2f7; }
    .wm-filter a { font-size: .76rem; font-weight: 600; padding: .3rem .8rem; border-radius: 999px; color: var(--portal-muted); text-decoration: none; }
    .wm-filter a.is-active { background: #fff; color: var(--portal-text); box-shadow: 0 1px 3px rgba(20, 24, 50, .12); }
    .wm-accounts th:first-child, .wm-accounts td:first-child { padding-left: 1.25rem; }
    .wm-accounts th:last-child, .wm-accounts td:last-child { padding-right: 1.25rem; }
    .wm-accounts td { padding-top: .7rem; padding-bottom: .7rem; }
    .wm-accounts thead th { padding-top: .75rem; }
    .wm-accounts .wm-sn { width: 3.5rem; color: var(--portal-muted); font-size: .8rem; font-weight: 600; }
    .wm-thumb { width: 64px; height: 40px; border-radius: 6px; border: 1px solid var(--portal-border); background: repeating-conic-gradient(#eef0f5 0% 25%, #fff 0% 50%) 50% / 10px 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .wm-thumb img { max-width: 90%; max-height: 85%; }
    .wm-thumb span { font-size: .62rem; font-weight: 700; padding: 0 .25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; text-shadow: 0 0 2px rgba(0, 0, 0, .5); }

    @media (max-width: 991.98px) {
        .wm-layout { grid-template-columns: 1fr; }
        .wm-preview-panel { position: static; order: -1; }
    }
</style>
@endpush

@section('content')
<div class="wm-page-head">
    <h1>Listing Settings</h1>
    <p>{{ $isAdmin ? 'The default watermark for MW Realty listings and for every agency or agent that hasn\'t set their own.' : 'How your listing photos are branded when you upload them.' }}</p>
</div>

<nav class="wm-tabs">
    @foreach($tabs as $key => $label)
    <a href="{{ route('portal.watermark.edit', $key === 'default' ? [] : ['tab' => $key]) }}" class="{{ $tab === $key ? 'is-active' : '' }}">{{ $label }}@if($key === 'accounts')<span class="wm-tab-count">{{ number_format($ownCount) }}</span>@endif</a>
    @endforeach
</nav>

@if($tab === 'accounts')
    {{-- ============ Super Admin: every agency / agent watermark ============ --}}
    <div class="wm-notice"><i class="fas fa-circle-info"></i><span>An account's own watermark always wins. Accounts with none, or with theirs turned off, get your <a href="{{ route('portal.watermark.edit') }}">default watermark</a>. Agents in an agency use the agency's.</span></div>
    <div class="wm-panel">
        <div class="wm-toolbar">
            <form method="GET">
                <input type="hidden" name="tab" value="accounts">
                @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search agency, agent or email" maxlength="100">
                <button class="btn btn-sm btn-portal-light" type="submit"><i class="fas fa-magnifying-glass"></i></button>
            </form>
            <div class="wm-filter">
                @foreach(['' => 'All', 'on' => 'On', 'off' => 'Off'] as $key => $label)
                <a href="{{ route('portal.watermark.edit', array_filter(['tab' => 'accounts', 'q' => $search, 'status' => $key])) }}" class="{{ (string) $status === $key ? 'is-active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="table-responsive">
            <table class="table portal-table align-middle mb-0 wm-accounts">
                <thead><tr><th class="wm-sn">#</th><th>Account</th><th>Watermark</th><th>Position</th><th>Opacity · Size</th><th>Status</th><th>Updated</th></tr></thead>
                <tbody>
                    @forelse($accounts as $account)
                    @php $w = array_merge(\App\Services\Watermark::DEFAULTS, $account->watermark ?? []); @endphp
                    <tr>
                        <td class="wm-sn">{{ $accounts->firstItem() + $loop->index }}</td>
                        <td>
                            <div class="fw-semibold">{{ $account->displayName() }}</div>
                            <div class="small text-muted">{{ $account->type === 'company' ? 'Agency' : ($account->company_id ? 'Agency agent' : 'Independent agent') }} · {{ $account->email }}</div>
                        </td>
                        <td>
                            <div class="wm-thumb">
                                @if($w['type'] === 'text')
                                <span style="color: {{ $w['color'] }};">{{ $w['text'] ?: '—' }}</span>
                                @elseif($w['image'])
                                <img src="{{ route('portal.watermark.image', ['account' => $account->id, 'v' => md5($w['image'])]) }}" alt="" loading="lazy">
                                @else
                                <i class="fas fa-image text-muted"></i>
                                @endif
                            </div>
                        </td>
                        <td class="small">{{ $positionLabels[$w['position']] ?? '—' }}</td>
                        <td class="small">{{ $w['opacity'] }}% · {{ $w['size'] }}%</td>
                        <td>@if($w['enabled'])<span class="wm-state is-on m-0"><i class="fas fa-circle" style="font-size: 5px;"></i>On</span>@else<span class="wm-state is-off m-0" title="Uses the default watermark">Off · default</span>@endif</td>
                        <td class="small text-muted">{{ $account->updated_at?->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">{{ $search !== '' || $status ? 'No accounts match.' : 'No agency or agent has set up their own watermark yet — all use the default.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($accounts->hasPages())
        <div class="p-3 border-top">{{ $accounts->links() }}</div>
        @endif
    </div>
@else
    @if($readOnly)
    <div class="wm-notice"><i class="fas fa-building"></i><span>Your listings belong to <strong>{{ $brand }}</strong>, so their watermark is used on your photos. Only the agency can change it.</span></div>
    @elseif($usingDefault)
    <div class="wm-notice"><i class="fas fa-circle-info"></i><span>You haven't turned on a watermark of your own, so the <strong>MW Realty default watermark</strong> is added to your listing photos. Set up yours below to use it instead.</span></div>
    @endif

    <form method="POST" action="{{ route('portal.watermark.update') }}" enctype="multipart/form-data" id="wmForm" class="wm-layout">
        @csrf
        <input type="hidden" name="position" id="wmPosition" value="{{ $s['position'] }}">
        <input type="hidden" name="remove_image" id="wmRemoveImage" value="0">

        <div class="wm-panel">
            <fieldset class="wm-settings" @disabled($readOnly)>
                <div class="wm-row">
                    <div class="wm-switch-row">
                        <div>
                            <div class="fw-bold" style="font-size: .9rem;">{{ $isAdmin ? 'Default watermark' : 'Watermark' }} <span class="wm-state" id="wmState"></span></div>
                            <div class="wm-help">{{ $isAdmin ? 'Added to photos uploaded from now on — your listings and accounts without their own.' : 'Added to every listing photo uploaded from now on.' }}</div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input type="hidden" name="enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="wmEnabled" @checked($s['enabled']) aria-label="Add watermark to photos">
                        </div>
                    </div>
                </div>

                <div class="wm-row">
                    <div class="wm-row-title">Type</div>
                    <div class="wm-segment">
                        <label><input type="radio" name="type" value="image" @checked($s['type'] === 'image')><span><i class="fas fa-image"></i>Logo</span></label>
                        <label><input type="radio" name="type" value="text" @checked($s['type'] === 'text')><span><i class="fas fa-font"></i>Text</span></label>
                    </div>
                </div>

                <div class="wm-row" data-wm-for="image">
                    <div class="wm-row-title">Logo</div>
                    <input type="file" name="image" id="wmFile" accept="image/png,image/jpeg,image/webp" hidden>
                    <div class="wm-logo">
                        <div class="wm-logo-box">
                            <img id="wmImageThumb" src="{{ $imageUrl }}" alt="Watermark logo" @if(!$imageUrl) hidden @endif>
                            <i class="fas fa-image fa-lg" id="wmImageEmpty" @if($imageUrl) hidden @endif></i>
                        </div>
                        <div>
                            @unless($readOnly)
                            <div class="wm-logo-actions">
                                <button type="button" class="btn btn-portal-light" id="wmPick"><i class="fas fa-arrow-up-from-bracket me-1"></i><span id="wmPickLabel">{{ $imageUrl ? 'Replace' : 'Upload' }}</span></button>
                                <button type="button" class="btn btn-link text-danger p-0" id="wmRemove" @if(!$imageUrl) hidden @endif>Remove</button>
                            </div>
                            @endunless
                            <div class="wm-help">PNG with a transparent background works best. Max 4 MB.</div>
                        </div>
                    </div>
                    @error('image')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </div>

                <div class="wm-row" data-wm-for="text">
                    <label class="wm-row-title" for="wmText">Text &amp; colour</label>
                    <div class="wm-text-row">
                        <input type="text" name="text" id="wmText" class="form-control form-control-sm" maxlength="60" value="{{ $s['text'] }}" placeholder="e.g. {{ $brand }}">
                        <div class="wm-swatches">
                            @foreach(['#ffffff', '#000000', '#244373', '#ca2844'] as $swatch)
                            <button type="button" class="wm-swatch" data-color="{{ $swatch }}" style="background: {{ $swatch }};" aria-label="Colour {{ $swatch }}"></button>
                            @endforeach
                            <input type="color" name="color" id="wmColor" class="wm-color" value="{{ $s['color'] }}" aria-label="Custom colour">
                        </div>
                    </div>
                    @error('text')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                {{-- Position and opacity / size side by side, so the panel fits on one screen. --}}
                <div class="wm-row wm-place">
                    <div>
                        <div class="wm-row-title">Position</div>
                        <div class="wm-grid" role="radiogroup" aria-label="Position">
                            @foreach($positionLabels as $key => $label)
                            <button type="button" role="radio" data-position="{{ $key }}" title="{{ $label }}" aria-label="{{ $label }}" aria-checked="{{ $s['position'] === $key ? 'true' : 'false' }}" class="{{ $s['position'] === $key ? 'is-active' : '' }}"></button>
                            @endforeach
                        </div>
                        <div class="wm-help mt-1" id="wmPositionLabel">{{ $positionLabels[$s['position']] ?? '' }}</div>
                    </div>
                    <div class="wm-sliders">
                        <div>
                            <div class="wm-row-title"><label for="wmOpacity" class="m-0">Opacity</label><output id="wmOpacityOut">{{ $s['opacity'] }}%</output></div>
                            <input type="range" class="wm-range" name="opacity" id="wmOpacity" min="5" max="100" value="{{ $s['opacity'] }}">
                        </div>
                        <div>
                            <div class="wm-row-title"><label for="wmSize" class="m-0">Size</label><output id="wmSizeOut">{{ $s['size'] }}%</output></div>
                            <input type="range" class="wm-range" name="size" id="wmSize" min="5" max="80" value="{{ $s['size'] }}">
                        </div>
                    </div>
                </div>

                @unless($readOnly)
                <div class="wm-footer">
                    <a href="{{ route('portal.watermark.edit') }}" class="btn btn-portal-light">Cancel</a>
                    <button type="submit" class="btn btn-portal-primary">Save changes</button>
                </div>
                @endunless
            </fieldset>
        </div>

        <div class="wm-panel wm-preview-panel">
            <div class="wm-preview-head"><span>Preview</span><span class="wm-help fw-normal">Sample listing photo</span></div>
            <div class="wm-preview {{ $s['enabled'] ? '' : 'is-off' }}" id="wmPreview">
                <img class="wm-photo" src="{{ asset('frontend/assets/images/home/hero-bg.jpg') }}" alt="Sample listing photo">
                <div class="wm-mark" id="wmMark">
                    <img id="wmMarkImg" src="{{ $imageUrl }}" alt="" @if(!$imageUrl) hidden @endif>
                    <span id="wmMarkText"></span>
                </div>
                <div class="wm-preview-off"><i class="fas fa-circle-pause me-1"></i>Watermark is off — photos are uploaded without it{{ $isAdmin ? '' : ($usingDefault ? ' (the MW Realty default is used instead)' : '') }}.</div>
            </div>
        </div>
    </form>
@endif
@endsection

@if($tab !== 'accounts')
@push('scripts')
<script>
(function () {
    const form = document.getElementById('wmForm');
    const preview = document.getElementById('wmPreview');
    const mark = document.getElementById('wmMark');
    const markImg = document.getElementById('wmMarkImg');
    const markText = document.getElementById('wmMarkText');
    const cells = [...form.querySelectorAll('.wm-grid [data-position]')];
    const el = id => document.getElementById(id);
    const type = () => form.querySelector('input[name="type"]:checked').value;

    // Same geometry as App\Services\Watermark::apply(): size = % of photo width, margin = 3% of the short side.
    function render() {
        const W = preview.clientWidth, H = preview.clientHeight;
        const margin = Math.round(Math.min(W, H) * 0.03);
        const targetW = W * el('wmSize').value / 100;
        const pos = el('wmPosition').value;
        const isText = type() === 'text';
        const on = el('wmEnabled').checked;

        document.querySelectorAll('[data-wm-for]').forEach(b => { b.hidden = b.dataset.wmFor !== type(); });
        preview.classList.toggle('is-off', !on);
        el('wmState').className = 'wm-state ' + (on ? 'is-on' : 'is-off');
        el('wmState').textContent = on ? 'On' : 'Off';
        markImg.hidden = isText || !markImg.getAttribute('src');
        markText.hidden = !isText;

        if (isText) {
            markText.textContent = el('wmText').value.trim() || el('wmText').placeholder.replace(/^e\.g\.\s*/, '');
            markText.style.color = el('wmColor').value;
            markText.style.fontSize = '100px';
            markText.style.fontSize = (100 * targetW / Math.max(1, markText.scrollWidth)) + 'px';
            mark.style.width = 'auto';
        } else {
            mark.style.width = targetW + 'px';
        }
        mark.style.opacity = el('wmOpacity').value / 100;

        const mw = mark.offsetWidth, mh = mark.offsetHeight;
        mark.style.left = (pos[1] === 'l' ? margin : pos[1] === 'r' ? W - mw - margin : (W - mw) / 2) + 'px';
        mark.style.top = (pos[0] === 't' ? margin : pos[0] === 'b' ? H - mh - margin : (H - mh) / 2) + 'px';

        cells.forEach(c => {
            c.classList.toggle('is-active', c.dataset.position === pos);
            c.setAttribute('aria-checked', c.dataset.position === pos ? 'true' : 'false');
            if (c.dataset.position === pos) el('wmPositionLabel').textContent = c.title;
        });
        el('wmOpacityOut').textContent = el('wmOpacity').value + '%';
        el('wmSizeOut').textContent = el('wmSize').value + '%';
        document.querySelectorAll('.wm-swatch').forEach(b => b.classList.toggle('is-active', b.dataset.color === el('wmColor').value.toLowerCase()));
    }

    function setImage(src) {
        el('wmImageThumb').src = markImg.src = src || '';
        if (!src) { el('wmImageThumb').removeAttribute('src'); markImg.removeAttribute('src'); }
        el('wmImageThumb').hidden = !src;
        el('wmImageEmpty').hidden = !!src;
        if (el('wmRemove')) el('wmRemove').hidden = !src;
        if (el('wmPickLabel')) el('wmPickLabel').textContent = src ? 'Replace' : 'Upload';
        render();
    }

    cells.forEach(c => c.addEventListener('click', () => { if (!form.querySelector('fieldset').disabled) { el('wmPosition').value = c.dataset.position; render(); } }));
    form.addEventListener('input', render);
    form.addEventListener('change', render);
    document.querySelectorAll('.wm-swatch').forEach(b => b.addEventListener('click', () => { el('wmColor').value = b.dataset.color; render(); }));

    el('wmPick')?.addEventListener('click', () => el('wmFile').click());
    el('wmFile').addEventListener('change', () => {
        const file = el('wmFile').files[0];
        if (!file) return;
        el('wmRemoveImage').value = 0;
        const reader = new FileReader();
        reader.onload = e => setImage(e.target.result);
        reader.readAsDataURL(file);
    });
    el('wmRemove')?.addEventListener('click', () => { el('wmFile').value = ''; el('wmRemoveImage').value = 1; setImage(null); });

    markImg.addEventListener('load', render);
    window.addEventListener('resize', render);
    document.querySelector('.wm-photo').addEventListener('load', render);
    render();
})();
</script>
@endpush
@endif
