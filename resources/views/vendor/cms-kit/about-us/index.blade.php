@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">About Us</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
@endphp
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-primary">"Why Choose Us / About" Section Settings</h6>
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

        <form action="{{ route('cms.about-us.update') }}" method="POST" enctype="multipart/form-data">
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
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Title 1 <span class="text-danger">*</span></label>
                            <input type="text" name="translations[{{ $lang->code }}][title_1]" class="form-control @error("translations.{$lang->code}.title_1") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title_1", $t['title_1'] ?? '') }}" required>
                            @error("translations.{$lang->code}.title_1")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Title 2</label>
                            <input type="text" name="translations[{{ $lang->code }}][title_2]" class="form-control" value="{{ old("translations.{$lang->code}.title_2", $t['title_2'] ?? '') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="translations[{{ $lang->code }}][description]" class="form-control tinymce-extra-field" rows="4">{{ old("translations.{$lang->code}.description", $t['description'] ?? '') }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">CEO Name</label>
                            <input type="text" name="translations[{{ $lang->code }}][ceo_name]" class="form-control" value="{{ old("translations.{$lang->code}.ceo_name", $t['ceo_name'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">CEO Designation</label>
                            <input type="text" name="translations[{{ $lang->code }}][ceo_designation]" class="form-control" value="{{ old("translations.{$lang->code}.ceo_designation", $t['ceo_designation'] ?? '') }}" placeholder="e.g. Founder & CEO">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">CEO Quote</label>
                            <textarea name="translations[{{ $lang->code }}][ceo_quote]" class="form-control" rows="3" placeholder="A short quote shown on the director/CEO card.">{{ old("translations.{$lang->code}.ceo_quote", $t['ceo_quote'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <hr>

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label d-block">Image</label>
                    <small class="text-muted d-block mb-1">Recommended size: {{ $imageConfig['width'] ?? '' }}x{{ $imageConfig['height'] ?? '' }}px, Max: {{ $imageConfig['max_size'] ?? '' }}KB</small>
                    <input type="file" name="image" class="form-control">
                    <input type="text" name="image_alt" class="form-control mt-2" placeholder="Image ALT text" value="{{ old('image_alt', $section->section_image_alt ?? '') }}">
                    @if($section?->section_image)
                        <div class="mt-2">
                            <img src="{{ asset('storage/' . $section->section_image) }}" class="img-thumbnail" style="height: 70px;">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage" value="1">
                                <label class="form-check-label" for="removeImage">Remove current image</label>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label d-block">CEO Image</label>
                    <small class="text-muted d-block mb-1">Recommended size: {{ $ceoImageConfig['width'] ?? '' }}x{{ $ceoImageConfig['height'] ?? '' }}px, Max: {{ $ceoImageConfig['max_size'] ?? '' }}KB</small>
                    <input type="file" name="ceo_image" class="form-control">
                    <input type="text" name="ceo_image_alt" class="form-control mt-2" placeholder="CEO image ALT text" value="{{ old('ceo_image_alt', $section->banner_alt ?? '') }}">
                    @if($section?->banner)
                        <div class="mt-2">
                            <img src="{{ asset('storage/' . $section->banner) }}" class="img-thumbnail" style="height: 70px;">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_ceo_image" id="removeCeoImage" value="1">
                                <label class="form-check-label" for="removeCeoImage">Remove current CEO image</label>
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
