@php
    $isEdit = isset($guide);
    $features = array_values(old('features', $guide->features ?? []));
    $steps = array_values(old('steps', $guide->steps ?? []));
    $tips = array_values(old('tips', $guide->tips ?? []));
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<h6 class="fw-bold mb-1">Welcome</h6>
<p class="text-muted small mb-3">The first slide: the icon, "Welcome to <em>Title</em>" and a short intro.</p>
<div class="row g-4">
    <div class="col-md-4">
        <label class="form-label fw-bold">Topic <span class="text-danger">*</span></label>
        @if($isEdit)
            <input type="text" class="form-control bg-light" value="{{ $guide->topic }}" readonly>
            <small class="text-muted">The screen's key — fixed once created.</small>
        @else
            <input type="text" name="topic" class="form-control @error('topic') is-invalid @enderror" value="{{ old('topic') }}" placeholder="e.g. lead-insights" required>
            <small class="text-muted">Must match the key the CRM screen asks for. Lowercase letters, numbers and dashes.</small>
            @error('topic')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @endif
    </div>
    <div class="col-md-4">
        <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $guide->title ?? '') }}" placeholder="e.g. Leads" required>
        <small class="text-muted">Shown as "Welcome to <em>Title</em>".</small>
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-bold">Icon</label>
        <div class="input-group">
            <span class="input-group-text text-primary" style="width: 44px;"><i class="fas {{ old('icon', $guide->icon ?? '') ?: 'fa-circle-info' }}" data-icon-preview></i></span>
            <input type="text" name="icon" class="form-control @error('icon') is-invalid @enderror" value="{{ old('icon', $guide->icon ?? '') }}" placeholder="e.g. fa-address-book" data-icon-input>
        </div>
        <small class="text-muted">A Font Awesome icon name. Blank = <code>fa-circle-info</code>.</small>
        @error('icon')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-bold">Intro</label>
        <textarea name="intro" class="form-control @error('intro') is-invalid @enderror" rows="3" placeholder="One or two sentences on what this screen is for.">{{ old('intro', $guide->intro ?? '') }}</textarea>
        @error('intro')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<hr class="my-4">

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h6 class="fw-bold mb-1">What you can do here</h6>
        <p class="text-muted small mb-0">Features of the screen, each with an icon. Leave empty to skip this slide.</p>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" data-add="features"><i class="fas fa-plus me-1"></i> Add feature</button>
</div>
<div class="d-flex flex-column gap-2" data-list="features">
    @foreach($features as $i => $feature)
        <div class="help-row border rounded-3 p-3 bg-light" data-row>
            <div class="row g-2 align-items-start">
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text text-primary" style="width: 36px;"><i class="fas {{ ($feature['icon'] ?? '') ?: 'fa-check' }}" data-icon-preview></i></span>
                        <input type="text" name="features[{{ $i }}][icon]" class="form-control" value="{{ $feature['icon'] ?? '' }}" placeholder="fa-filter" data-icon-input>
                    </div>
                </div>
                <div class="col-md-8">
                    <input type="text" name="features[{{ $i }}][title]" class="form-control form-control-sm mb-2" value="{{ $feature['title'] ?? '' }}" placeholder="Title">
                    <textarea name="features[{{ $i }}][text]" class="form-control form-control-sm" rows="2" placeholder="Description">{{ $feature['text'] ?? '' }}</textarea>
                </div>
                <div class="col-md-1">@include('cms-kit::help-desk._row-actions')</div>
            </div>
        </div>
    @endforeach
</div>

<hr class="my-4">

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h6 class="fw-bold mb-1">How it works</h6>
        <p class="text-muted small mb-0">Numbered steps, in order. Leave empty to skip this slide.</p>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" data-add="steps"><i class="fas fa-plus me-1"></i> Add step</button>
</div>
<div class="d-flex flex-column gap-2" data-list="steps">
    @foreach($steps as $i => $step)
        <div class="help-row border rounded-3 p-3 bg-light" data-row>
            <div class="row g-2 align-items-start">
                <div class="col-md-11">
                    <input type="text" name="steps[{{ $i }}][title]" class="form-control form-control-sm mb-2" value="{{ $step['title'] ?? '' }}" placeholder="Title">
                    <textarea name="steps[{{ $i }}][text]" class="form-control form-control-sm" rows="2" placeholder="Description">{{ $step['text'] ?? '' }}</textarea>
                </div>
                <div class="col-md-1">@include('cms-kit::help-desk._row-actions')</div>
            </div>
        </div>
    @endforeach
</div>

<hr class="my-4">

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h6 class="fw-bold mb-1">Good to know</h6>
        <p class="text-muted small mb-0">Short tips and rules. Leave empty to skip this slide.</p>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" data-add="tips"><i class="fas fa-plus me-1"></i> Add tip</button>
</div>
<div class="d-flex flex-column gap-2" data-list="tips">
    @foreach($tips as $i => $tip)
        <div class="help-row border rounded-3 p-3 bg-light" data-row>
            <div class="row g-2 align-items-start">
                <div class="col-md-11">
                    <textarea name="tips[{{ $i }}]" class="form-control form-control-sm" rows="2" placeholder="Tip">{{ $tip }}</textarea>
                </div>
                <div class="col-md-1">@include('cms-kit::help-desk._row-actions')</div>
            </div>
        </div>
    @endforeach
</div>

<hr class="my-4">

<div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" name="status" id="guideStatus" {{ old('status', $guide->status ?? true) ? 'checked' : '' }}>
    <label class="form-check-label fw-bold" for="guideStatus">Active</label>
    <small class="text-muted d-block">When inactive, the screen's <strong>!</strong> icon shows nothing.</small>
</div>

{{-- New-row templates; __i__ becomes a unique index. --}}
<template data-template="features">
    <div class="help-row border rounded-3 p-3 bg-light" data-row>
        <div class="row g-2 align-items-start">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text text-primary" style="width: 36px;"><i class="fas fa-check" data-icon-preview></i></span>
                    <input type="text" name="features[__i__][icon]" class="form-control" placeholder="fa-filter" data-icon-input>
                </div>
            </div>
            <div class="col-md-8">
                <input type="text" name="features[__i__][title]" class="form-control form-control-sm mb-2" placeholder="Title">
                <textarea name="features[__i__][text]" class="form-control form-control-sm" rows="2" placeholder="Description"></textarea>
            </div>
            <div class="col-md-1">@include('cms-kit::help-desk._row-actions')</div>
        </div>
    </div>
</template>
<template data-template="steps">
    <div class="help-row border rounded-3 p-3 bg-light" data-row>
        <div class="row g-2 align-items-start">
            <div class="col-md-11">
                <input type="text" name="steps[__i__][title]" class="form-control form-control-sm mb-2" placeholder="Title">
                <textarea name="steps[__i__][text]" class="form-control form-control-sm" rows="2" placeholder="Description"></textarea>
            </div>
            <div class="col-md-1">@include('cms-kit::help-desk._row-actions')</div>
        </div>
    </div>
</template>
<template data-template="tips">
    <div class="help-row border rounded-3 p-3 bg-light" data-row>
        <div class="row g-2 align-items-start">
            <div class="col-md-11">
                <textarea name="tips[__i__]" class="form-control form-control-sm" rows="2" placeholder="Tip"></textarea>
            </div>
            <div class="col-md-1">@include('cms-kit::help-desk._row-actions')</div>
        </div>
    </div>
</template>

@push('scripts')
<script>
(function () {
    // Rows are submitted in page order (the server re-indexes them), so moving a row reorders it.
    let next = 10000;

    document.querySelectorAll('[data-add]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const type = btn.dataset.add;
            const html = document.querySelector('[data-template="' + type + '"]').innerHTML.replaceAll('__i__', next++);
            const list = document.querySelector('[data-list="' + type + '"]');
            list.insertAdjacentHTML('beforeend', html);
            list.lastElementChild.querySelector('input, textarea').focus();
        });
    });

    document.addEventListener('click', function (e) {
        const action = e.target.closest('[data-row-action]');
        if (!action) return;
        const row = action.closest('[data-row]');
        if (action.dataset.rowAction === 'remove') row.remove();
        if (action.dataset.rowAction === 'up' && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
        if (action.dataset.rowAction === 'down' && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
    });

    // Live icon preview next to every icon field.
    document.addEventListener('input', function (e) {
        if (!e.target.matches('[data-icon-input]')) return;
        const preview = e.target.closest('.input-group').querySelector('[data-icon-preview]');
        const fallback = e.target.name === 'icon' ? 'fa-circle-info' : 'fa-check';
        preview.className = 'fas ' + (/^fa-[a-z0-9-]+$/.test(e.target.value.trim()) ? e.target.value.trim() : fallback);
    });
})();
</script>
@endpush
