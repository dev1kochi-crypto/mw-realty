@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.popular-places.index') }}">Popular Places</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add Place</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
@endphp
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Add Place</h5>
    </div>
    <div class="card-body p-4">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form action="{{ route('cms.popular-places.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            @if($showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="langTabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="{{ $lang->code }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $lang->code }}-content" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="tab-content mb-4 language-switcher-content">
                @foreach($languages as $lang)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $lang->code }}-content" role="tabpanel">
                    <label class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                    <input type="text" name="translations[{{ $lang->code }}][name]" class="form-control @error("translations.{$lang->code}.name") is-invalid @enderror" value="{{ old("translations.{$lang->code}.name") }}" placeholder="e.g. Dubai" required>
                    @error("translations.{$lang->code}.name")
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                @endforeach
            </div>

            <hr>

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label d-block">Image <span class="text-danger">*</span></label>
                    <small class="text-muted d-block mb-1">Recommended size: {{ $imageConfig['width'] }}x{{ $imageConfig['height'] }}px, Max: {{ $imageConfig['max_size'] }}KB</small>
                    <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" required>
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <input type="text" name="image_alt" class="form-control mt-2" placeholder="Image ALT text">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $nextOrder) }}" min="1">
                </div>
                <div class="col-md-3 d-flex align-items-end pb-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="status" id="placeStatus" checked>
                        <label class="form-check-label" for="placeStatus">Active</label>
                    </div>
                </div>
            </div>

            <div class="mt-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Save</button>
                <a href="{{ route('cms.popular-places.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // A required field on a hidden language tab (e.g. Arabic Name) fails validation
        // invisibly since the tab-pane is display:none — jump to it and focus the field.
        const firstInvalidField = document.querySelector('.language-switcher-content .is-invalid');
        if (firstInvalidField) {
            const invalidTabPane = firstInvalidField.closest('.tab-pane');
            if (invalidTabPane) {
                const tabBtn = document.querySelector(`[data-bs-target="#${invalidTabPane.id}"]`);
                if (tabBtn) {
                    bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                    setTimeout(() => firstInvalidField.focus(), 150);
                }
            }
        }
    });

    // The Name field on every language tab is browser-required — if the Arabic tab's copy
    // is empty, Chrome silently blocks the whole submit (it can't show its native tooltip on
    // a display:none field), which looks exactly like "Save does nothing". Catch that native
    // constraint-validation failure (capture: true, since 'invalid' doesn't bubble) and jump
    // to whichever tab actually holds the empty required field.
    //
    // One click fires an 'invalid' event for EVERY empty required field at once (English's
    // AND Arabic's), not just the first — without the guard below we'd process both and end
    // up parked on the last one (Arabic) even when English, shown first, is also empty.
    let placeInvalidHandled = false;
    document.addEventListener('invalid', function (e) {
        if (placeInvalidHandled) return;
        placeInvalidHandled = true;
        setTimeout(() => { placeInvalidHandled = false; }, 0);

        const invalidTabPane = e.target.closest('.tab-pane');
        if (invalidTabPane) {
            const tabBtn = document.querySelector(`[data-bs-target="#${invalidTabPane.id}"]`);
            if (tabBtn && !tabBtn.classList.contains('active')) {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            }
        }
        setTimeout(() => { e.target.focus(); }, 150);
    }, true);
</script>
@endpush
