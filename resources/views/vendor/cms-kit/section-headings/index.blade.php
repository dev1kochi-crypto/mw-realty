@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Common Titles</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $erroredSection = null;
    foreach ($errors->keys() as $errorKey) {
        if (preg_match('/^sections\.([^.]+)\.translations\.([^.]+)\./', $errorKey, $m)) {
            $erroredSection = $erroredSection ?? $m[1];
        }
    }
@endphp
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-primary">Common Titles</h6>
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

        <ul class="nav nav-tabs mb-4" id="sectionHeadingTabs" role="tablist">
            @foreach($sections as $key => $section)
                @php $isActive = $erroredSection ? $erroredSection === $key : $loop->first; @endphp
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $isActive ? 'active' : '' }}" id="section-tab-{{ $key }}" data-bs-toggle="tab" data-bs-target="#section-panel-{{ $key }}" type="button" role="tab">
                        {{ $section['label'] }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            @foreach($sections as $key => $section)
                @php
                    $isActive = $erroredSection ? $erroredSection === $key : $loop->first;
                    $record = $section['record'];
                    $fields = $section['fields'];
                @endphp
                <div class="tab-pane fade {{ $isActive ? 'show active' : '' }}" id="section-panel-{{ $key }}" role="tabpanel">
                    <form action="{{ route('cms.section-headings.update', $key) }}" method="POST">
                        @csrf
                        @method('PUT')

                        @if($showLanguageUi)
                        <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" role="tablist">
                            @foreach($languages as $lang)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" data-bs-toggle="tab" data-bs-target="#lang-panel-{{ $key }}-{{ $lang->code }}" type="button" role="tab">
                                    <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                                </button>
                            </li>
                            @endforeach
                        </ul>
                        @endif

                        <div class="tab-content mb-4">
                            @foreach($languages as $lang)
                                @php $t = $record->translations[$lang->code] ?? []; @endphp
                                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="lang-panel-{{ $key }}-{{ $lang->code }}" role="tabpanel">
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Title 1 <span class="text-danger">*</span></label>
                                            <input type="text" name="sections[{{ $key }}][translations][{{ $lang->code }}][title_1]" class="form-control @error("sections.{$key}.translations.{$lang->code}.title_1") is-invalid @enderror" value="{{ old("sections.{$key}.translations.{$lang->code}.title_1", $t['title_1'] ?? '') }}" required>
                                            @error("sections.{$key}.translations.{$lang->code}.title_1")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        @if(in_array('title_2', $fields))
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Title 2</label>
                                            <input type="text" name="sections[{{ $key }}][translations][{{ $lang->code }}][title_2]" class="form-control" value="{{ old("sections.{$key}.translations.{$lang->code}.title_2", $t['title_2'] ?? '') }}">
                                        </div>
                                        @endif
                                        @if(in_array('description', $fields))
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Description</label>
                                            <textarea name="sections[{{ $key }}][translations][{{ $lang->code }}][description]" class="form-control" rows="3">{{ old("sections.{$key}.translations.{$lang->code}.description", $t['description'] ?? '') }}</textarea>
                                        </div>
                                        @endif
                                        @if(in_array('button_name', $fields))
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Button Name</label>
                                            <input type="text" name="sections[{{ $key }}][translations][{{ $lang->code }}][button_name]" class="form-control" value="{{ old("sections.{$key}.translations.{$lang->code}.button_name", $t['button_name'] ?? '') }}">
                                        </div>
                                        @endif
                                        @if(in_array('button_url', $fields))
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Button URL</label>
                                            <input type="url" name="sections[{{ $key }}][translations][{{ $lang->code }}][button_url]" class="form-control @error("sections.{$key}.translations.{$lang->code}.button_url") is-invalid @enderror" value="{{ old("sections.{$key}.translations.{$lang->code}.button_url", $t['button_url'] ?? '') }}" placeholder="https://...">
                                            @error("sections.{$key}.translations.{$lang->code}.button_url")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        @endif
                                        @if(in_array('cities', $fields))
                                        @php $cities = old("sections.{$key}.translations.{$lang->code}.cities", $t['cities'] ?? []); @endphp
                                        <div class="col-12">
                                            <label class="form-label fw-bold d-block border-top pt-3">City Names <span class="text-muted fw-normal">(up to {{ \App\Http\Controllers\CmsKit\SectionHeadingController::MAX_CITIES }})</span></label>
                                            <div class="cities-container-{{ $key }}-{{ $lang->code }}">
                                                @forelse($cities as $city)
                                                <div class="city-row row g-2 mb-2">
                                                    <div class="col-md-6">
                                                        <input type="text" name="sections[{{ $key }}][translations][{{ $lang->code }}][cities][]" class="form-control" placeholder="e.g. Dubai" value="{{ $city }}">
                                                    </div>
                                                    <div class="col-auto">
                                                        <button type="button" class="btn btn-outline-danger remove-city"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </div>
                                                @empty
                                                <div class="city-row row g-2 mb-2">
                                                    <div class="col-md-6">
                                                        <input type="text" name="sections[{{ $key }}][translations][{{ $lang->code }}][cities][]" class="form-control" placeholder="e.g. Dubai">
                                                    </div>
                                                    <div class="col-auto">
                                                        <button type="button" class="btn btn-outline-danger remove-city"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </div>
                                                @endforelse
                                            </div>
                                            <button type="button" class="btn btn-sm btn-primary mt-1 add-city rounded-pill px-3 shadow-sm" data-container=".cities-container-{{ $key }}-{{ $lang->code }}" data-max="{{ \App\Http\Controllers\CmsKit\SectionHeadingController::MAX_CITIES }}" data-name="sections[{{ $key }}][translations][{{ $lang->code }}][cities][]" {{ count($cities) >= \App\Http\Controllers\CmsKit\SectionHeadingController::MAX_CITIES ? 'disabled' : '' }}>
                                                <i class="fas fa-plus me-1"></i> Add City
                                            </button>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="status" id="status-{{ $key }}" {{ old('status', $record->status ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="status-{{ $key }}">Status</label>
                        </div>

                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-save me-2"></i>Update {{ $section['label'] }}
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('invalid', function (e) {
    let pane = e.target.closest('.tab-pane');
    while (pane) {
        const btn = document.querySelector(`[data-bs-target="#${pane.id}"]`);
        if (btn && !btn.classList.contains('active')) {
            bootstrap.Tab.getOrCreateInstance(btn).show();
        }
        pane = pane.parentElement ? pane.parentElement.closest('.tab-pane') : null;
    }
    setTimeout(() => { e.target.focus(); }, 150);
}, true);
</script>
<script>
$(function () {
    $(document).on('click', '.add-city', function () {
        const $btn = $(this);
        const container = $($btn.data('container'));
        const max = parseInt($btn.data('max'), 10);
        if (container.find('.city-row').length >= max) return;

        const name = $btn.data('name');
        container.append(`
            <div class="city-row row g-2 mb-2">
                <div class="col-md-6">
                    <input type="text" name="${name}" class="form-control" placeholder="e.g. Dubai">
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-outline-danger remove-city"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        `);
        if (container.find('.city-row').length >= max) $btn.prop('disabled', true);
    });

    $(document).on('click', '.remove-city', function () {
        const $row = $(this).closest('.city-row');
        const container = $row.parent();
        if (container.find('.city-row').length > 1) {
            $row.remove();
        } else {
            $row.find('input').val('');
        }
        container.closest('.col-12').find('.add-city').prop('disabled', false);
    });
});
</script>
@endpush
