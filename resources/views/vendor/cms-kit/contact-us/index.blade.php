@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Contact</li>
@endsection

@section('content')
@php $showLanguageUi = config('cms-kit.common.modules.languages', true); @endphp
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-primary">Contact Section Settings</h6>
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

        <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
            <i class="fas fa-info-circle text-primary me-2"></i>
            The office address, phone, and email shown alongside this section come from
            <a href="{{ route('cms.site-information.index') }}">Site Information</a>. The enquiry form fields are managed separately.
        </div>

        <form action="{{ route('cms.contact-us.update') }}" method="POST">
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
                    <h6 class="fw-bold text-secondary mb-3">Home Page</h6>
                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <label class="form-label fw-bold">Home Title <span class="text-danger">*</span></label>
                            <input type="text" name="translations[{{ $lang->code }}][home_title]" class="form-control @error("translations.{$lang->code}.home_title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.home_title", $t['home_title'] ?? '') }}" placeholder="Connect with the experts who make property management simple." required>
                            @error("translations.{$lang->code}.home_title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Home Description</label>
                            <textarea name="translations[{{ $lang->code }}][home_description]" class="form-control" rows="3">{{ old("translations.{$lang->code}.home_description", $t['home_description'] ?? '') }}</textarea>
                        </div>
                    </div>

                    <h6 class="fw-bold text-secondary mb-3">Contact Page</h6>
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $t['title'] ?? '') }}" required>
                            @error("translations.{$lang->code}.title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="translations[{{ $lang->code }}][description]" class="form-control" rows="3">{{ old("translations.{$lang->code}.description", $t['description'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="status" id="sectionStatus" {{ old('status', $section->status ?? true) ? 'checked' : '' }}>
                <label class="form-check-label fw-bold" for="sectionStatus">Status</label>
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
