@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.post-property-steps.index') }}">Property Posting Steps</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Step</li>
@endsection

@section('content')
@php
    $stepConfig = config('cms-kit.database.post-property-steps.items', []);
    $stepRequired = $stepConfig['required'] ?? [];
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
@endphp
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Edit Step</h5>
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
        <form action="{{ route('cms.post-property-steps.update', $step->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                <i class="fas fa-info-circle text-primary me-2"></i>
                <strong>Note:</strong> Please ensure all required fields <span class="text-danger">(*)</span> are filled{{ $showLanguageUi ? ' across all language tabs' : '' }}.
            </div>

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
                @php $t = $step->translations[$lang->code] ?? []; @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $lang->code }}-content" role="tabpanel">
                    <div class="row g-4">
                        @if($stepConfig['title'] ?? true)
                        <div class="col-12">
                            <label class="form-label fw-bold">Title {!! in_array('title', $stepRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $t['title'] ?? '') }}" {{ in_array('title', $stepRequired) ? 'required' : '' }}>
                            @error("translations.{$lang->code}.title")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif
                        @if($stepConfig['description'] ?? true)
                        <div class="col-12">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="translations[{{ $lang->code }}][description]" class="form-control @error("translations.{$lang->code}.description") is-invalid @enderror" rows="3">{{ old("translations.{$lang->code}.description", $t['description'] ?? '') }}</textarea>
                            @error("translations.{$lang->code}.description")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        @include('cms-kit::partials.extra-fields-translatable', [
                            'configKey' => 'post-property-steps.items',
                            'lang' => $lang,
                            'existingTranslations' => $step->translations ?? [],
                        ])
                    </div>
                </div>
                @endforeach
            </div>

            <hr>

            <div class="row g-4">
                @if($stepConfig['image'] ?? true)
                <div class="col-md-6">
                    <label class="form-label d-block">Step Icon</label>
                    <small class="text-muted d-block mb-1">Recommended size: {{ $iconConfig['width'] }}x{{ $iconConfig['height'] }}px, Max: {{ $iconConfig['max_size'] }}KB</small>
                    <input type="file" name="image" class="form-control @error('image') is-invalid @enderror">
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <input type="text" name="image_alt" class="form-control mt-2" placeholder="Icon ALT text" value="{{ old('image_alt', $step->image_alt) }}">
                    @if($step->image)
                        <div class="mt-2">
                            <img src="{{ asset('storage/' . $step->image) }}" class="img-thumbnail" style="height: 60px;">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage" value="1">
                                <label class="form-check-label" for="removeImage">Remove current icon</label>
                            </div>
                        </div>
                    @endif
                </div>
                @endif

                @if($stepConfig['order'] ?? true)
                <div class="col-md-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $step->order_index) }}" min="1">
                </div>
                @endif
                @if($stepConfig['status'] ?? true)
                <div class="col-md-3 d-flex align-items-end pb-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="status" id="stepStatus" {{ old('status', $step->status) ? 'checked' : '' }}>
                        <label class="form-check-label" for="stepStatus">Active</label>
                    </div>
                </div>
                @endif

                @include('cms-kit::partials.extra-fields-global', [
                    'configKey' => 'post-property-steps.items',
                    'existingValues' => $step->extra_fields ?? [],
                ])
            </div>

            <div class="mt-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Update</button>
                <a href="{{ route('cms.post-property-steps.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('invalid', function(e) {
    let invalidTabPane = e.target.closest('.tab-pane');
    if (invalidTabPane) {
        let tabId = invalidTabPane.id;
        let tabBtn = document.querySelector(`[data-bs-target="#${tabId}"]`);
        if (tabBtn && !tabBtn.classList.contains('active')) {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            setTimeout(() => { e.target.focus(); }, 150);
        }
    }
}, true);
</script>
@endpush
