@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Market Trends</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
@endphp
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-primary">"Market Trends" Section Settings</h6>
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

        <form action="{{ route('cms.market-trends.update') }}" method="POST" enctype="multipart/form-data">
            @csrf

            @if($showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="langTabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#panel-{{ $lang->code }}" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="tab-content mb-4 language-switcher-content">
                @foreach($languages as $lang)
                @php $t = $section->translations[$lang->code] ?? []; @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="panel-{{ $lang->code }}" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $t['title'] ?? '') }}" required>
                            @error("translations.{$lang->code}.title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold text-secondary mb-3">Trend Points</h6>
                    @php
                        $trends = old("translations.{$lang->code}.trends", $t['trends'] ?? []);
                        if (!$trends) { $trends = ['']; }
                    @endphp
                    <div class="trend-points-list" data-lang="{{ $lang->code }}">
                        @foreach ($trends as $trend)
                        <div class="row g-2 mb-2 trend-point-row align-items-center">
                            <div class="col">
                                <input type="text" name="translations[{{ $lang->code }}][trends][]" class="form-control" value="{{ $trend }}" placeholder="e.g. Higher mortgage rates have slowed rapid price growth.">
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-outline-danger remove-trend-point"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm add-trend-point" data-lang="{{ $lang->code }}">
                        <i class="fas fa-plus me-1"></i>Add Trend Point
                    </button>
                </div>
                @endforeach
            </div>

            <hr>

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label d-block">Image 1</label>
                    <small class="text-muted d-block mb-1">Recommended size: {{ $image1Config['width'] ?? '' }}x{{ $image1Config['height'] ?? '' }}px, Max: {{ $image1Config['max_size'] ?? '' }}KB</small>
                    <input type="file" name="image_1" class="form-control @error('image_1') is-invalid @enderror">
                    @error('image_1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <input type="text" name="image_1_alt" class="form-control mt-2" placeholder="Image 1 ALT text" value="{{ old('image_1_alt', $section->section_image_alt ?? '') }}">
                    @if($section?->section_image)
                        <div class="mt-2">
                            <img src="{{ media_url($section->section_image) }}" class="img-thumbnail" style="height: 70px;">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_image_1" id="removeImage1" value="1">
                                <label class="form-check-label" for="removeImage1">Remove current image</label>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label d-block">Image 2</label>
                    <small class="text-muted d-block mb-1">Recommended size: {{ $image2Config['width'] ?? '' }}x{{ $image2Config['height'] ?? '' }}px, Max: {{ $image2Config['max_size'] ?? '' }}KB</small>
                    <input type="file" name="image_2" class="form-control @error('image_2') is-invalid @enderror">
                    @error('image_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <input type="text" name="image_2_alt" class="form-control mt-2" placeholder="Image 2 ALT text" value="{{ old('image_2_alt', $section->banner_alt ?? '') }}">
                    @if($section?->banner)
                        <div class="mt-2">
                            <img src="{{ media_url($section->banner) }}" class="img-thumbnail" style="height: 70px;">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_image_2" id="removeImage2" value="1">
                                <label class="form-check-label" for="removeImage2">Remove current image</label>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-md-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="status" id="sectionStatus" {{ old('status', $section->status ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="sectionStatus">Status</label>
                    </div>
                </div>
            </div>

            <div class="col-12 border-top pt-4 mt-4">
                <button type="submit" class="btn btn-primary px-4 shadow-sm">
                    <i class="fas fa-save me-2"></i>Update Section Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('click', function (e) {
        const addBtn = e.target.closest('.add-trend-point');
        if (addBtn) {
            const lang = addBtn.dataset.lang;
            const list = document.querySelector('.trend-points-list[data-lang="' + lang + '"]');
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 trend-point-row align-items-center';
            row.innerHTML = '<div class="col"><input type="text" name="translations[' + lang + '][trends][]" class="form-control" placeholder="e.g. Higher mortgage rates have slowed rapid price growth."></div>' +
                '<div class="col-auto"><button type="button" class="btn btn-outline-danger remove-trend-point"><i class="fas fa-trash"></i></button></div>';
            list.appendChild(row);
            row.querySelector('input').focus();
            return;
        }

        const removeBtn = e.target.closest('.remove-trend-point');
        if (removeBtn) {
            const list = removeBtn.closest('.trend-points-list');
            const row = removeBtn.closest('.trend-point-row');
            // Keep at least one (empty) row so the field is never entirely removed from the form.
            if (list.querySelectorAll('.trend-point-row').length <= 1) {
                row.querySelector('input').value = '';
            } else {
                row.remove();
            }
        }
    });
</script>
@endpush
