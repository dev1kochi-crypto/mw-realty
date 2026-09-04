@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.brands.index') }}">Brands</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add Brand</li>
@endsection

@section('content')
@php
    $brandConfig = config('cms-kit.database.brands.items', []);
    $brandRequired = $brandConfig['required'] ?? [];
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $brandExtraFields = config('cms-kit.database.brands.items.extra_fields', []);
    $hasTranslatableExtraFields = collect($brandExtraFields)->contains(fn($field) => $field['translatable'] ?? false);
@endphp
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Add Brand</h5>
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
        <form action="{{ route('cms.brands.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                <i class="fas fa-info-circle text-primary me-2"></i> 
                <strong>Note:</strong> Please upload a high-quality logo. Required fields are marked with <span class="text-danger">*</span>.
            </div>

            @if($hasTranslatableExtraFields && $showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="brandLanguageTabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="brand-tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#brand-panel-{{ $lang->code }}" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>

            <div class="tab-content mb-4 language-switcher-content">
                @foreach($languages as $lang)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="brand-panel-{{ $lang->code }}" role="tabpanel">
                    @include('cms-kit::partials.extra-fields-translatable', [
                        'configKey' => 'brands.items',
                        'lang' => $lang,
                        'existingTranslations' => [],
                    ])
                </div>
                @endforeach
            </div>
            @endif

            <div class="row g-4">
                @if($brandConfig['image'] ?? true)
                <div class="col-12">
                    <label class="form-label fw-bold">Brand Logo {!! in_array('image', $brandRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" {{ in_array('image', $brandRequired) ? 'required' : '' }}>
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block mt-1">Recommended size: {{ $imageConfig['width'] }}x{{ $imageConfig['height'] }} px. Max: {{ $imageConfig['max_size'] }} KB.</small>
                </div>
                @endif

                @if($brandConfig['image_alt'] ?? true)
                <div class="col-12">
                    <label class="form-label fw-bold">Image ALT Text {!! in_array('image_alt', $brandRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <input type="text" name="image_alt" class="form-control @error('image_alt') is-invalid @enderror" value="{{ old('image_alt') }}" placeholder="Describe image for accessibility" {{ in_array('image_alt', $brandRequired) ? 'required' : '' }}>
                    @error('image_alt')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                @endif

                @if($brandConfig['order'] ?? true)
                <div class="col-md-6">
                    <label class="form-label fw-bold">Sort Order</label>
                    <input type="number" name="order_index" class="form-control @error('order_index') is-invalid @enderror" value="{{ old('order_index', $nextOrder) }}" min="1">
                    @error('order_index')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                @endif

                @if($brandConfig['status'] ?? true)
                <div class="col-md-6 d-flex align-items-end pb-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="status" id="brandStatus" checked>
                        <label class="form-check-label fw-bold" for="brandStatus">Active Status</label>
                    </div>
                </div>
                @endif

                @include('cms-kit::partials.extra-fields-global', [
                    'configKey' => 'brands.items',
                    'existingValues' => [],
                ])

            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Save</button>
                <a href="{{ route('cms.brands.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
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
