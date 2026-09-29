{{--
    Add / Edit popup for Master › Stage, Tag, Source.
    Params: $kind = stage|tag|source, $mode = add|edit.
    Keeps the ids / names each page's script already uses: add → {kind}_form=create, #{kind}Name,
    #{kind}Color, #stageIsClosed; edit → #edit{Kind}Form, #edit{Kind}Name, #edit{Kind}Color, #editStageClosed.
--}}
@php
    $Kind = ucfirst($kind);
    $isAdd = $mode === 'add';
    $prefix = $isAdd ? $kind : "edit{$Kind}";
    $modalId = $isAdd ? "add{$Kind}Modal" : "edit{$Kind}Modal";
    $hasColor = $kind !== 'source';
    $defaultColor = ['stage' => '#4f46e5', 'tag' => '#14b8a6', 'source' => null][$kind];
    $meta = [
        'stage' => ['fa-layer-group', 'A step in your lead pipeline, e.g. “Site Visit Scheduled”.', 'e.g. Site Visit Scheduled'],
        'tag' => ['fa-tag', 'A label to group leads, e.g. “Hot Lead” or “VIP”.', 'e.g. Hot Lead'],
        'source' => ['fa-share-nodes', 'Where a lead came from, e.g. “Instagram” or “Referral”.', 'e.g. Instagram'],
    ][$kind];
    $swatches = ['#4f46e5', '#0ea5e9', '#14b8a6', '#22c55e', '#f59e0b', '#f97316', '#ef4444', '#ec4899', '#8b5cf6', '#64748b'];
@endphp

<div class="modal fade mm-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true" data-mm-kind="{{ $kind }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form @if($isAdd) action="{{ route("portal.crm.master.{$kind}s.store") }}" @else id="edit{{ $Kind }}Form" @endif method="POST">
                @csrf
                @if($isAdd)<input type="hidden" name="{{ $kind }}_form" value="create">@else @method('PUT') @endif

                <div class="mm-head">
                    <span class="mm-head__icon"><i class="fas {{ $isAdd ? 'fa-plus' : 'fa-pen' }}"></i></span>
                    <div class="min-w-0 flex-grow-1">
                        <h5 class="mm-head__title" id="{{ $modalId }}Label">{{ $isAdd ? "Add {$Kind}" : "Edit {$Kind}" }}</h5>
                        <p class="mm-head__sub"><i class="fas {{ $meta[0] }} me-1"></i>{{ $meta[1] }}</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="mm-body">
                    <div class="mm-preview" aria-hidden="true">
                        <span class="mm-preview__label">Preview</span>
                        <span class="mm-preview__item mm-preview__item--{{ $kind }}" data-mm-preview>
                            @if($kind === 'stage')<span class="mm-dot" data-mm-dot></span>@endif
                            @if($kind === 'source')<i class="fas fa-share-nodes"></i>@endif
                            <span data-mm-text>{{ $isAdd ? '' : '' }}</span>
                        </span>
                    </div>

                    <label class="mm-label" for="{{ $prefix }}Name">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="{{ $prefix }}Name" class="form-control mm-input @if($isAdd) @error('name') is-invalid @enderror @endif"
                           value="{{ $isAdd ? old('name') : '' }}" required maxlength="100" placeholder="{{ $meta[2] }}" autocomplete="off" data-mm-name>
                    @if($isAdd)
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @endif

                    @if($hasColor)
                    <label class="mm-label mt-3">Colour</label>
                    <div class="mm-swatches" role="radiogroup" aria-label="Colour">
                        @foreach($swatches as $swatch)
                        <button type="button" class="mm-swatch" style="--sw: {{ $swatch }};" data-mm-swatch="{{ $swatch }}" aria-label="Colour {{ $swatch }}"></button>
                        @endforeach
                        <label class="mm-swatch mm-swatch--custom" title="Custom colour">
                            <i class="fas fa-eye-dropper"></i>
                            <input type="color" name="color" id="{{ $prefix }}Color" value="{{ $isAdd ? old('color', $defaultColor) : $defaultColor }}" data-mm-color>
                        </label>
                    </div>
                    @endif

                    @if($kind === 'stage')
                    <label class="mm-switch mt-3" for="{{ $isAdd ? 'stageIsClosed' : 'editStageClosed' }}">
                        <span class="mm-switch__text">
                            <strong>Counts as closed</strong>
                            <small>Leads here are finished — won (e.g. “Closed Won”) or lost (“Closed Lost”, “Dropped”). Used by the Sales report.</small>
                        </span>
                        <span class="form-check form-switch m-0">
                            <input type="checkbox" name="is_closed" value="1" class="form-check-input" role="switch" id="{{ $isAdd ? 'stageIsClosed' : 'editStageClosed' }}" @if($isAdd) @checked(old('is_closed')) @endif>
                        </span>
                    </label>
                    @endif
                </div>

                <div class="mm-foot">
                    <button type="button" class="btn btn-portal-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary"><i class="fas {{ $isAdd ? 'fa-plus' : 'fa-check' }} me-1"></i>{{ $isAdd ? "Add {$Kind}" : 'Save changes' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
@push('styles')
<style>
    .mm-modal .modal-content { border: 0; border-radius: 20px; overflow: hidden; box-shadow: 0 24px 60px rgba(28, 35, 64, 0.22); }
    .mm-head { display: flex; align-items: flex-start; gap: 0.85rem; padding: 1.25rem 1.4rem 1rem; border-bottom: 1px solid var(--portal-border); background: linear-gradient(180deg, rgba(36, 67, 115, 0.05), transparent); }
    .mm-head__icon { width: 44px; height: 44px; border-radius: 13px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; background: linear-gradient(135deg, var(--portal-primary), var(--portal-accent, #ca2844)); box-shadow: 0 8px 18px rgba(36, 67, 115, 0.25); }
    .mm-head__title { margin: 0; font-weight: 800; font-size: 1.1rem; color: var(--portal-text); }
    .mm-head__sub { margin: 0.15rem 0 0; font-size: 0.8rem; color: var(--portal-muted); }
    .mm-body { padding: 1.2rem 1.4rem 0.4rem; }
    .mm-label { display: block; font-size: 0.74rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: var(--portal-muted); margin-bottom: 0.4rem; }
    .mm-input { border-radius: 12px; border: 1.5px solid var(--portal-border); padding: 0.65rem 0.9rem; font-size: 0.92rem; font-weight: 600; }
    .mm-input:focus { border-color: var(--portal-primary); box-shadow: 0 0 0 4px rgba(36, 67, 115, 0.12); }

    .mm-preview { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.75rem 0.9rem; margin-bottom: 1rem; border-radius: 14px; background: var(--portal-bg); border: 1px dashed var(--portal-border); }
    .mm-preview__label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--portal-muted); }
    .mm-preview__item { display: inline-flex; align-items: center; gap: 0.45rem; max-width: 70%; font-weight: 700; font-size: 0.88rem; }
    .mm-preview__item span[data-mm-text] { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .mm-preview__item--stage { color: var(--portal-text); }
    .mm-preview__item--tag { padding: 0.28rem 0.75rem; border-radius: 999px; background: color-mix(in srgb, var(--mm-color, #14b8a6) 14%, #fff); color: var(--mm-color, #14b8a6); }
    .mm-preview__item--source { padding: 0.28rem 0.75rem; border-radius: 999px; background: #fff; border: 1px solid var(--portal-border); color: var(--portal-primary); }
    .mm-dot { width: 11px; height: 11px; border-radius: 50%; background: var(--mm-color, #4f46e5); box-shadow: 0 0 0 3px color-mix(in srgb, var(--mm-color, #4f46e5) 20%, transparent); }
    .mm-placeholder { color: var(--portal-muted); font-weight: 600; font-style: italic; }

    .mm-swatches { display: flex; flex-wrap: wrap; gap: 0.45rem; }
    .mm-swatch { width: 32px; height: 32px; border-radius: 10px; border: 2px solid #fff; background: var(--sw); box-shadow: 0 0 0 1px var(--portal-border); cursor: pointer; position: relative; padding: 0; transition: transform .12s ease, box-shadow .12s ease; }
    .mm-swatch:hover { transform: translateY(-1px); }
    .mm-swatch.is-selected { box-shadow: 0 0 0 2px var(--sw, var(--portal-primary)); }
    .mm-swatch.is-selected::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.75rem; }
    .mm-swatch--custom { --sw: conic-gradient(#ef4444, #f59e0b, #22c55e, #0ea5e9, #8b5cf6, #ef4444); background: var(--sw); display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 0.72rem; margin: 0; }
    .mm-swatch--custom input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
    .mm-swatch--custom.is-selected { --sw: var(--mm-color); background: var(--mm-color); }
    .mm-swatch--custom.is-selected::after { content: none; }

    .mm-switch { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.8rem 0.95rem; border-radius: 14px; border: 1.5px solid var(--portal-border); cursor: pointer; margin-bottom: 0; }
    .mm-switch:has(input:checked) { border-color: #14b8a6; background: rgba(20, 184, 166, 0.06); }
    .mm-switch__text strong { display: block; font-size: 0.88rem; color: var(--portal-text); }
    .mm-switch__text small { display: block; font-size: 0.76rem; color: var(--portal-muted); line-height: 1.4; }
    .mm-switch .form-check-input { width: 2.6em; height: 1.4em; cursor: pointer; }

    .mm-foot { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.4rem 1.25rem; }
    .mm-foot .btn { border-radius: 11px; font-weight: 700; padding: 0.55rem 1.15rem; }
</style>
@endpush

@push('scripts')
<script>
// Live preview + colour swatches for every Master add/edit popup (values set by a page's
// Edit button are picked up when the popup opens).
(function () {
    document.querySelectorAll('.mm-modal').forEach(function (modal) {
        const name = modal.querySelector('[data-mm-name]');
        const color = modal.querySelector('[data-mm-color]');
        const preview = modal.querySelector('[data-mm-preview]');
        const text = modal.querySelector('[data-mm-text]');
        const kind = modal.dataset.mmKind;

        function refresh() {
            const value = name.value.trim();
            text.textContent = value || 'Your ' + kind + ' name';
            text.classList.toggle('mm-placeholder', !value);
            if (!color) return;
            const c = color.value.toLowerCase();
            preview.style.setProperty('--mm-color', c);
            modal.style.setProperty('--mm-color', c);
            let matched = false;
            modal.querySelectorAll('[data-mm-swatch]').forEach(function (sw) {
                const on = sw.dataset.mmSwatch.toLowerCase() === c;
                sw.classList.toggle('is-selected', on);
                matched = matched || on;
            });
            modal.querySelector('.mm-swatch--custom').classList.toggle('is-selected', !matched);
        }

        name.addEventListener('input', refresh);
        if (color) {
            color.addEventListener('input', refresh);
            modal.querySelectorAll('[data-mm-swatch]').forEach(function (sw) {
                sw.addEventListener('click', function () { color.value = sw.dataset.mmSwatch; refresh(); });
            });
        }
        modal.addEventListener('show.bs.modal', function () { setTimeout(refresh, 0); });
        modal.addEventListener('shown.bs.modal', function () { refresh(); name.focus(); name.select(); });
        refresh();
    });
})();
</script>
@endpush
@endonce
