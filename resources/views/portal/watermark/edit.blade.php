@extends('portal.layouts.app')

@section('title', 'Listing Settings')

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
@endphp

@push('styles')
<style>
    .wm-head { display: flex; align-items: center; gap: .6rem; margin-bottom: .35rem; }
    .wm-head h1 { font-size: 1.35rem; font-weight: 800; margin: 0; padding-left: .7rem; border-left: 4px solid var(--portal-accent); }
    .wm-tabs { border-bottom: 1px solid var(--portal-border); margin-bottom: 1.25rem; }
    .wm-tabs span { display: inline-block; padding: .6rem 1.1rem; font-weight: 700; font-size: .85rem; color: var(--portal-primary); border-bottom: 2px solid var(--portal-primary); }

    .wm-card { display: grid; grid-template-columns: 340px minmax(0, 1fr); background: var(--portal-surface); border: 1px solid var(--portal-border); border-radius: var(--portal-radius); box-shadow: var(--portal-shadow); overflow: hidden; }
    .wm-controls { padding: 1.4rem; border-right: 1px solid var(--portal-border); display: flex; flex-direction: column; gap: 1.1rem; }
    .wm-stage { padding: 1.4rem; display: flex; flex-direction: column; gap: .6rem; }

    .wm-enable { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem .9rem; border-radius: 12px; background: #f6f8fd; }
    .wm-enable .form-check-input { width: 2.8em; height: 1.5em; cursor: pointer; margin: 0; }
    .wm-enable .form-check-input:checked { background-color: var(--portal-primary); border-color: var(--portal-primary); }

    .wm-types { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
    .wm-type { position: relative; cursor: pointer; margin: 0; }
    .wm-type input { position: absolute; opacity: 0; }
    .wm-type span { display: flex; flex-direction: column; align-items: center; gap: .35rem; padding: .8rem .5rem; border: 1.5px solid var(--portal-border); border-radius: 12px; font-weight: 700; font-size: .82rem; color: var(--portal-muted); transition: all .15s; }
    .wm-type span i { font-size: 1.25rem; }
    .wm-type input:checked + span { border-color: var(--portal-primary); color: var(--portal-primary); background: #f3f6fc; box-shadow: 0 0 0 3px rgba(36, 67, 115, .1); }
    .wm-type input:focus-visible + span { outline: 2px solid var(--portal-primary); outline-offset: 2px; }

    .wm-drop { position: relative; display: flex; align-items: center; justify-content: center; min-height: 130px; border: 1.5px dashed #c4ccdd; border-radius: 12px; background: repeating-conic-gradient(#eef0f5 0% 25%, #fff 0% 50%) 50% / 16px 16px; cursor: pointer; overflow: hidden; }
    .wm-drop img { max-width: 85%; max-height: 110px; }
    .wm-drop__empty { text-align: center; color: var(--portal-muted); font-size: .8rem; background: rgba(255, 255, 255, .9); padding: .6rem .9rem; border-radius: 10px; }
    .wm-drop__empty i { display: block; font-size: 1.4rem; color: var(--portal-primary); margin-bottom: .3rem; }
    .wm-drop__remove { position: absolute; top: 8px; right: 8px; width: 26px; height: 26px; border-radius: 50%; border: 0; background: var(--portal-primary); color: #fff; font-size: .75rem; }

    .wm-label { display: flex; justify-content: space-between; font-size: .8rem; font-weight: 700; color: var(--portal-text); margin-bottom: .35rem; }
    .wm-label output { color: var(--portal-muted); font-weight: 600; }
    .wm-range { width: 100%; accent-color: var(--portal-primary); }
    .wm-swatches { display: flex; gap: .45rem; align-items: center; }
    .wm-swatch { width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--portal-border); cursor: pointer; padding: 0; }
    .wm-swatch.is-active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--portal-primary); }
    .wm-color { width: 32px; height: 32px; padding: 0; border: 0; background: none; cursor: pointer; }

    .wm-actions { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; margin-top: auto; }

    .wm-preview { position: relative; aspect-ratio: 16 / 9; border-radius: 12px; overflow: hidden; background: #ddd; }
    .wm-preview > img.wm-photo { width: 100%; height: 100%; object-fit: cover; display: block; }
    .wm-mark { position: absolute; pointer-events: none; transition: opacity .15s; }
    .wm-mark img { display: block; width: 100%; }
    .wm-mark span { display: block; white-space: nowrap; font-family: Verdana, 'DejaVu Sans', sans-serif; font-weight: 700; line-height: 1.15; }
    .wm-preview.is-off .wm-mark { display: none; }
    .wm-handle { position: absolute; width: 22px; height: 22px; margin: -11px 0 0 -11px; border-radius: 50%; border: 2px solid #1f2340; background: #fff; cursor: pointer; padding: 0; box-shadow: 0 2px 6px rgba(0, 0, 0, .25); transition: transform .12s; z-index: 2; }
    .wm-handle:hover { transform: scale(1.15); }
    .wm-handle.is-active { background: var(--portal-primary); border-color: #fff; box-shadow: 0 0 0 3px var(--portal-primary); }
    .wm-hint { font-size: .8rem; color: var(--portal-muted); }

    @media (max-width: 991.98px) {
        .wm-card { grid-template-columns: 1fr; }
        .wm-controls { border-right: 0; border-bottom: 1px solid var(--portal-border); }
        .wm-stage { order: -1; }
    }
</style>
@endpush

@section('content')
<div class="wm-head"><h1>Listing Settings</h1></div>
<div class="wm-tabs"><span>Watermark</span></div>

@if($readOnly)
<div class="alert alert-info d-flex gap-2 align-items-center"><i class="fas fa-building"></i>
    Your listings belong to {{ $owner->company_name ?: $owner->name }}, so their watermark is used on your photos. Only the agency can change it.
</div>
@endif

<form method="POST" action="{{ route('portal.watermark.update') }}" enctype="multipart/form-data" id="wmForm">
    @csrf
    <input type="hidden" name="position" id="wmPosition" value="{{ $s['position'] }}">
    <input type="hidden" name="remove_image" id="wmRemoveImage" value="0">

    <div class="wm-card">
        <fieldset class="wm-controls" @disabled($readOnly)>
            <div class="wm-enable">
                <div>
                    <div class="fw-bold" style="font-size: .9rem;">Add watermark to photos</div>
                    <div class="wm-hint">Applied to every listing photo uploaded from now on.</div>
                </div>
                <div class="form-check form-switch m-0">
                    <input type="hidden" name="enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="wmEnabled" @checked($s['enabled']) aria-label="Add watermark to photos">
                </div>
            </div>

            <div class="wm-types">
                <label class="wm-type"><input type="radio" name="type" value="image" @checked($s['type'] === 'image')><span><i class="fas fa-image"></i>Image</span></label>
                <label class="wm-type"><input type="radio" name="type" value="text" @checked($s['type'] === 'text')><span><i class="fas fa-font"></i>Text</span></label>
            </div>

            <div data-wm-for="image">
                <input type="file" name="image" id="wmFile" accept="image/png,image/jpeg,image/webp" hidden>
                <div class="wm-drop" id="wmDrop" role="button" tabindex="0" aria-label="Upload watermark image">
                    <img id="wmImageThumb" src="{{ $imageUrl }}" alt="Watermark image" @if(!$imageUrl) hidden @endif>
                    <div class="wm-drop__empty" id="wmDropEmpty" @if($imageUrl) hidden @endif><i class="fas fa-cloud-arrow-up"></i>Upload your logo<br><small>PNG with transparent background works best</small></div>
                    <button type="button" class="wm-drop__remove" id="wmRemove" title="Remove image" aria-label="Remove image" @if(!$imageUrl) hidden @endif><i class="fas fa-xmark"></i></button>
                </div>
                @error('image')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div data-wm-for="text">
                <label class="wm-label" for="wmText">Watermark text</label>
                <input type="text" name="text" id="wmText" class="form-control" maxlength="60" value="{{ $s['text'] }}" placeholder="e.g. {{ $owner->company_name ?: $owner->name }}">
                @error('text')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <div class="wm-label mt-3"><span>Colour</span></div>
                <div class="wm-swatches">
                    @foreach(['#ffffff', '#000000', '#244373', '#ca2844'] as $swatch)
                    <button type="button" class="wm-swatch" data-color="{{ $swatch }}" style="background: {{ $swatch }};" aria-label="Colour {{ $swatch }}"></button>
                    @endforeach
                    <input type="color" name="color" id="wmColor" class="wm-color" value="{{ $s['color'] }}" aria-label="Custom colour">
                </div>
            </div>

            <div>
                <div class="wm-label"><label for="wmOpacity">Opacity</label><output id="wmOpacityOut">{{ $s['opacity'] }}%</output></div>
                <input type="range" class="wm-range" name="opacity" id="wmOpacity" min="5" max="100" value="{{ $s['opacity'] }}">
            </div>
            <div>
                <div class="wm-label"><label for="wmSize">Size</label><output id="wmSizeOut">{{ $s['size'] }}%</output></div>
                <input type="range" class="wm-range" name="size" id="wmSize" min="5" max="80" value="{{ $s['size'] }}">
            </div>

            @unless($readOnly)
            <div class="wm-actions">
                <a href="{{ route('portal.watermark.edit') }}" class="btn btn-portal-light">Cancel</a>
                <button type="submit" class="btn btn-portal-primary">Apply changes</button>
            </div>
            @endunless
        </fieldset>

        <div class="wm-stage">
            <div class="wm-preview {{ $s['enabled'] ? '' : 'is-off' }}" id="wmPreview">
                <img class="wm-photo" src="{{ asset('frontend/assets/images/home/hero-bg.jpg') }}" alt="Sample listing photo">
                <div class="wm-mark" id="wmMark">
                    <img id="wmMarkImg" src="{{ $imageUrl }}" alt="" @if(!$imageUrl) hidden @endif>
                    <span id="wmMarkText"></span>
                </div>
                @foreach($positionLabels as $key => $label)
                <button type="button" class="wm-handle {{ $s['position'] === $key ? 'is-active' : '' }}" data-position="{{ $key }}" title="{{ $label }}" aria-label="{{ $label }}" @disabled($readOnly)></button>
                @endforeach
            </div>
            <div class="wm-hint" id="wmStatus"></div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('wmForm');
    const preview = document.getElementById('wmPreview');
    const mark = document.getElementById('wmMark');
    const markImg = document.getElementById('wmMarkImg');
    const markText = document.getElementById('wmMarkText');
    const handles = [...preview.querySelectorAll('.wm-handle')];
    const el = id => document.getElementById(id);
    const type = () => form.querySelector('input[name="type"]:checked').value;

    // Same geometry as App\Services\Watermark::apply(): size = % of photo width, margin = 3% of the short side.
    function render() {
        const W = preview.clientWidth, H = preview.clientHeight;
        const margin = Math.round(Math.min(W, H) * 0.03);
        const targetW = W * el('wmSize').value / 100;
        const pos = el('wmPosition').value;
        const isText = type() === 'text';

        document.querySelectorAll('[data-wm-for]').forEach(b => { b.hidden = b.dataset.wmFor !== type(); });
        preview.classList.toggle('is-off', !el('wmEnabled').checked);
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

        // Handles sit on the 3×3 grid of anchor points.
        handles.forEach(h => {
            const p = h.dataset.position;
            h.style.left = (p[1] === 'l' ? 6 : p[1] === 'r' ? 94 : 50) + '%';
            h.style.top = (p[0] === 't' ? 9 : p[0] === 'b' ? 91 : 50) + '%';
            h.classList.toggle('is-active', p === pos);
        });

        el('wmOpacityOut').textContent = el('wmOpacity').value + '%';
        el('wmSizeOut').textContent = el('wmSize').value + '%';
        document.querySelectorAll('.wm-swatch').forEach(b => b.classList.toggle('is-active', b.dataset.color === el('wmColor').value.toLowerCase()));
        el('wmStatus').innerHTML = el('wmEnabled').checked
            ? '<i class="fas fa-circle-check text-success me-1"></i>Preview of how your listing photos will look. Click a dot to move the watermark.'
            : '<i class="fas fa-circle-pause me-1"></i>Watermark is off. Turn on "Add watermark to photos" to use it.';
    }

    function setImage(src) {
        el('wmImageThumb').src = markImg.src = src || '';
        if (!src) { el('wmImageThumb').removeAttribute('src'); markImg.removeAttribute('src'); }
        el('wmImageThumb').hidden = el('wmRemove').hidden = !src;
        el('wmDropEmpty').hidden = !!src;
        render();
    }

    handles.forEach(h => h.addEventListener('click', () => { el('wmPosition').value = h.dataset.position; render(); }));
    form.addEventListener('input', render);
    form.addEventListener('change', render);
    document.querySelectorAll('.wm-swatch').forEach(b => b.addEventListener('click', () => { el('wmColor').value = b.dataset.color; render(); }));

    const openPicker = () => { if (!form.querySelector('fieldset').disabled) el('wmFile').click(); };
    el('wmDrop').addEventListener('click', e => { if (!e.target.closest('#wmRemove')) openPicker(); });
    el('wmDrop').addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPicker(); } });
    el('wmFile').addEventListener('change', () => {
        const file = el('wmFile').files[0];
        if (!file) return;
        el('wmRemoveImage').value = 0;
        const reader = new FileReader();
        reader.onload = e => setImage(e.target.result);
        reader.readAsDataURL(file);
    });
    el('wmRemove').addEventListener('click', () => { el('wmFile').value = ''; el('wmRemoveImage').value = 1; setImage(null); });

    markImg.addEventListener('load', render);
    window.addEventListener('resize', render);
    document.querySelector('.wm-photo').addEventListener('load', render);
    render();
})();
</script>
@endpush
