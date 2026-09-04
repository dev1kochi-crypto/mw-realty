@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.locations.index') }}">Locations</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add Location</li>
@endsection

@section('content')
@php
    $locationConfig = config('cms-kit.database.locations.items', []);
    $locationRequired = $locationConfig['required'] ?? [];
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $sectionConfig = config('cms-kit.database.locations.section', []);
    $sectionRequired = $sectionConfig['required'] ?? [];
@endphp
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Add Location</h5>
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
        <form action="{{ route('cms.locations.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
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
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $lang->code }}-content" role="tabpanel">
                            <div class="row g-4">
                        @if($locationConfig['country'] ?? true)
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Country {!! in_array('country', $locationRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <input type="text" name="translations[{{ $lang->code }}][country]" class="form-control @error("translations.{$lang->code}.country") is-invalid @enderror" value="{{ old("translations.{$lang->code}.country") }}" placeholder="e.g. United Arab Emirates" {{ in_array('country', $locationRequired) ? 'required' : '' }}>
                            @error("translations.{$lang->code}.country")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif
                        @if($locationConfig['title'] ?? true)
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Title/City {!! in_array('title', $locationRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title") }}" placeholder="e.g. Deira, Dubai, UAE." {{ in_array('title', $locationRequired) ? 'required' : '' }}>
                            @error("translations.{$lang->code}.title")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif
                        @if($locationConfig['address'] ?? true)
                        <div class="col-12">
                            <label class="form-label fw-bold">Address {!! in_array('address', $locationRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <textarea name="translations[{{ $lang->code }}][address]" class="form-control @error("translations.{$lang->code}.address") is-invalid @enderror" rows="3" {{ in_array('address', $locationRequired) ? 'required' : '' }}>{{ old("translations.{$lang->code}.address") }}</textarea>
                            @error("translations.{$lang->code}.address")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        @include('cms-kit::partials.extra-fields-translatable', [
                            'configKey' => 'locations.items',
                            'lang' => $lang,
                            'existingTranslations' => [],
                        ])
                    </div>
                </div>
                @endforeach
            </div>

            <hr>

            <div class="row g-4">
                <!-- Images -->
                @if($locationConfig['image'] ?? true)
                <div class="col-md-6">
                    <label class="form-label d-block">Location Image {!! in_array('image', $locationRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <small class="text-muted d-block mb-1">Recommended size: {{ $imageConfig['width'] }}x{{ $imageConfig['height'] }}px, Max: {{ $imageConfig['max_size'] }}KB</small>
                    <input type="file" name="image" class="form-control" {{ in_array('image', $locationRequired) ? 'required' : '' }}>
                    <input type="text" name="image_alt" class="form-control mt-2" placeholder="Image ALT text">
                </div>
                @endif
                @if($locationConfig['flag'] ?? true)
                <div class="col-md-6">
                    <label class="form-label d-block">Flag Image {!! in_array('flag', $locationRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <small class="text-muted d-block mb-1">Recommended size: {{ $flagConfig['width'] }}x{{ $flagConfig['height'] }}px, Max: {{ $flagConfig['max_size'] }}KB</small>
                    <input type="file" name="flag" class="form-control" {{ in_array('flag', $locationRequired) ? 'required' : '' }}>
                    <input type="text" name="flag_alt" class="form-control mt-2" placeholder="Flag ALT text">
                </div>
                @endif

                <!-- Contact info -->
                @if($locationConfig['phone'] ?? true)
                <div class="col-md-4">
                    <label class="form-label">Phone Numbers {!! in_array('phone', $locationRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <textarea name="phone" class="form-control" rows="3" placeholder="One phone per line" {{ in_array('phone', $locationRequired) ? 'required' : '' }}>{{ old('phone') }}</textarea>
                    <small class="text-muted">Enter one phone number per line. Comma or semicolon also accepted.</small>
                </div>
                @endif
                @if($locationConfig['whatsapp'] ?? true)
                <div class="col-md-4">
                    <label class="form-label">WhatsApp {!! in_array('whatsapp', $locationRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <input type="text" name="whatsapp" class="form-control" value="{{ old('whatsapp') }}" placeholder="+971 50 123 4567" {{ in_array('whatsapp', $locationRequired) ? 'required' : '' }}>
                </div>
                @endif
                @if($locationConfig['fax'] ?? true)
                <div class="col-md-4">
                    <label class="form-label">Fax {!! in_array('fax', $locationRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <input type="text" name="fax" class="form-control" value="{{ old('fax') }}" {{ in_array('fax', $locationRequired) ? 'required' : '' }}>
                </div>
                @endif

                @if($locationConfig['emails'] ?? true)
                <div class="col-12">
                    <label class="form-label">Emails (multiple) {!! in_array('emails', $locationRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <textarea name="emails" class="form-control" rows="3" placeholder="One email per line" {{ in_array('emails', $locationRequired) ? 'required' : '' }}>{{ old('emails') }}</textarea>
                    <small class="text-muted">Enter one email per line. Comma or semicolon also accepted.</small>
                </div>
                @endif

                <!-- Map -->
                @if($locationConfig['map_link'] ?? true)
                <div class="col-12">
                    <label class="form-label">Google Map Link / Embed URL {!! in_array('map_link', $locationRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <input type="text" name="map_link" class="form-control" value="{{ old('map_link') }}" placeholder="https://maps.google.com/..." {{ in_array('map_link', $locationRequired) ? 'required' : '' }}>
                </div>
                @endif

                <!-- Settings -->
                @if($locationConfig['order'] ?? true)
                <div class="col-md-4">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $nextOrder) }}" min="1">
                </div>
                @endif
                @if($locationConfig['status'] ?? true)
                <div class="col-md-4 d-flex align-items-end pb-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="status" id="locationStatus" checked>
                        <label class="form-check-label" for="locationStatus">Active</label>
                    </div>
                </div>
                @endif

                @include('cms-kit::partials.extra-fields-global', [
                    'configKey' => 'locations.items',
                    'existingValues' => [],
                ])
            </div>


            <div class="mt-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Save</button>
                <a href="{{ route('cms.locations.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
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
