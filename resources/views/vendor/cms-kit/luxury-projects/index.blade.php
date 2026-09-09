@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Luxury Projects</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $sectionConfig = config('cms-kit.database.luxury-projects.section', []);
    $sectionRequired = $sectionConfig['required'] ?? [];
@endphp
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-primary">"Luxury Project" Section Settings</h6>
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
                    <strong>Note:</strong> This controls the "Luxury Project" section on the home page — the property cards
                    themselves come from your Properties listings, not from here. Required fields are marked with <span class="text-danger">*</span>.
                </div>

                <form action="{{ route('cms.luxury-projects.update') }}" method="POST">
                    @csrf

                    @if($showLanguageUi)
                    <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="sectionLanguageTabs" role="tablist">
                        @foreach($languages as $lang)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="section-tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#section-panel-{{ $lang->code }}" type="button" role="tab">
                                <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                            </button>
                        </li>
                        @endforeach
                    </ul>
                    @endif

                    <div class="tab-content mb-4 language-switcher-content">
                        @foreach($languages as $lang)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="section-panel-{{ $lang->code }}" role="tabpanel">
                            <div class="row g-4">
                                @if($sectionConfig['title'] ?? true)
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Title {!! in_array('title', $sectionRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                    <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $section->translations[$lang->code]['title'] ?? '') }}" placeholder="Luxury Project" {{ in_array('title', $sectionRequired) ? 'required' : '' }}>
                                    @error("translations.{$lang->code}.title")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif
                                @if($sectionConfig['description'] ?? true)
                                <div class="col-12">
                                    <label class="form-label fw-bold">Description</label>
                                    <textarea name="translations[{{ $lang->code }}][description]" class="form-control @error("translations.{$lang->code}.description") is-invalid @enderror" rows="3">{{ old("translations.{$lang->code}.description", $section->translations[$lang->code]['description'] ?? '') }}</textarea>
                                    @error("translations.{$lang->code}.description")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif

                                @include('cms-kit::partials.extra-fields-translatable', [
                                    'configKey' => 'luxury-projects.section',
                                    'lang' => $lang,
                                    'existingTranslations' => $section->translations ?? [],
                                ])
                            </div>
                        </div>
                        @endforeach
                    </div>

                    @if($sectionConfig['status'] ?? true)
                    <div class="row g-4">
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status" id="sectionStatus" {{ old('status', $section->status ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="sectionStatus">Status</label>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="col-12 border-top pt-4 mt-4">
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-save me-2"></i>Update Section Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
