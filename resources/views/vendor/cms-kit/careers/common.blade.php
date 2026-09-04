@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Career Common Section</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $careerSectionConfig = config('cms-kit.database.careers.section', []);
    $sectionTranslations = $section->translations ?? [];
    $existingFilters = old('section_filters', collect(data_get($section->extra_fields, 'filters', []))
        ->map(fn ($filter) => [
            'column' => $filter['column'] ?? $filter['key'] ?? '',
        ])->values()->all());
    $filterEnabled = old('filter_enabled', data_get($section->extra_fields, 'filter_enabled', false) ? '1' : '0');
    $filterableColumns = collect($filterableColumns ?? [])->mapWithKeys(fn ($column) => [$column => \Illuminate\Support\Str::headline($column)])->all();
@endphp

<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-primary">Career Common Section</h6>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('cms.careers.update-section') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                <i class="fas fa-info-circle text-primary me-2"></i>
                <strong>Note:</strong> This single record controls the page intro, banner, and frontend vacancy filters.
            </div>

            @if($showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="careerSectionTabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="career-section-tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#career-section-panel-{{ $lang->code }}" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="tab-content mb-4 language-switcher-content">
                @foreach($languages as $lang)
                    @php $translation = $sectionTranslations[$lang->code] ?? []; @endphp
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="career-section-panel-{{ $lang->code }}" role="tabpanel">
                        <div class="row g-4">
                            @if($careerSectionConfig['title'] ?? true)
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Title {!! in_array('title', $careerSectionConfig['required'] ?? [], true) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $translation['title'] ?? '') }}" {{ in_array('title', $careerSectionConfig['required'] ?? [], true) ? 'required' : '' }}>
                                @error("translations.{$lang->code}.title")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif
                            @if($careerSectionConfig['description'] ?? true)
                            <div class="col-12">
                                <label class="form-label fw-bold">Description</label>
                                <textarea name="translations[{{ $lang->code }}][description]" class="form-control @error("translations.{$lang->code}.description") is-invalid @enderror" rows="3">{{ old("translations.{$lang->code}.description", $translation['description'] ?? '') }}</textarea>
                                @error("translations.{$lang->code}.description")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif

                            @include('cms-kit::partials.extra-fields-translatable', [
                                'configKey' => 'careers.section',
                                'lang' => $lang,
                                'existingTranslations' => $section->translations ?? [],
                            ])
                        </div>
                    </div>
                @endforeach
            </div>

            @if($careerSectionConfig['banner'] ?? true)
            <div class="card bg-light border-0 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Banner</h6>
                    <div class="row g-4">
                        @if($careerSectionConfig['banner'] ?? true)
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Banner Image</label>
                            <input type="file" name="banner" class="form-control @error('banner') is-invalid @enderror" accept="image/*">
                            @error('banner')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if($section->banner)
                            <div class="mt-3">
                                <img src="{{ asset('storage/' . $section->banner) }}" alt="{{ $section->banner_alt }}" class="img-fluid rounded border" style="max-height: 160px;">
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_banner" id="removeCareerBanner" value="1" {{ old('remove_banner') ? 'checked' : '' }}>
                                <label class="form-check-label" for="removeCareerBanner">Remove current banner</label>
                            </div>
                            @endif
                        </div>
                        @endif
                        @if($careerSectionConfig['banner_alt'] ?? true)
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Banner Alt Text</label>
                            <input type="text" name="banner_alt" class="form-control @error('banner_alt') is-invalid @enderror" value="{{ old('banner_alt', $section->banner_alt ?? '') }}" placeholder="Describe the banner image">
                            @error('banner_alt')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            @include('cms-kit::partials.extra-fields-global', [
                'configKey' => 'careers.section',
                'existingValues' => $section->extra_fields ?? [],
            ])

            @if($careerSectionConfig['filters'] ?? true)
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Filter Settings</h6>
                        <small class="text-muted">Choose which vacancy columns should appear as frontend filters.</small>
                    </div>
                    @if($careerSectionConfig['filter_enabled'] ?? true)
                    <div class="d-flex align-items-center gap-3">
                        <span class="small text-muted">Enable filters</span>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="radio" name="filter_enabled" id="filterEnabledYes" value="1" {{ (string) $filterEnabled === '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="filterEnabledYes">Yes</label>
                        </div>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="radio" name="filter_enabled" id="filterEnabledNo" value="0" {{ (string) $filterEnabled !== '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="filterEnabledNo">No</label>
                        </div>
                    </div>
                    @endif
                </div>

                <div id="careerFiltersPanel" class="border rounded-3 p-3" style="{{ ($careerSectionConfig['filter_enabled'] ?? true) && (string) $filterEnabled !== '1' ? 'display:none;' : '' }}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <small class="text-muted">Values are picked automatically from saved vacancies.</small>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addCareerFilter">
                            <i class="fas fa-plus me-1"></i>Add Filter
                        </button>
                    </div>

                    <div id="careerFiltersList" class="d-flex flex-column gap-2">
                        @forelse($existingFilters as $index => $filter)
                            <div class="career-filter-item border rounded-3 p-3">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-10">
                                        <label class="form-label fw-bold mb-1">Vacancy Column</label>
                                        <select name="section_filters[{{ $index }}][column]" class="form-select">
                                            <option value="">Select column</option>
                                            @foreach($filterableColumns as $column => $label)
                                                <option value="{{ $column }}" {{ ($filter['column'] ?? '') === $column ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-career-filter w-100">Remove</button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted small" id="careerFiltersEmpty">No filters added yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            @endif

            <div class="border-top pt-4">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-2"></i>Update Common Section
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function() {
        const filtersPanel = document.getElementById('careerFiltersPanel');
        const filtersList = document.getElementById('careerFiltersList');
        const addCareerFilterButton = document.getElementById('addCareerFilter');
        const emptyStateHtml = '<div class="text-muted small" id="careerFiltersEmpty">No filters added yet.</div>';

        function toggleFiltersPanel() {
            if (!filtersPanel) {
                return;
            }

            if (!document.querySelector('input[name="filter_enabled"]')) {
                filtersPanel.style.display = '';
                return;
            }

            const enabled = document.querySelector('input[name="filter_enabled"]:checked')?.value === '1';
            filtersPanel.style.display = enabled ? '' : 'none';
        }

        function refreshEmptyState() {
            if (!filtersList) {
                return;
            }

            const hasItems = filtersList.querySelector('.career-filter-item');
            const emptyState = document.getElementById('careerFiltersEmpty');

            if (!hasItems && !emptyState) {
                filtersList.insertAdjacentHTML('beforeend', emptyStateHtml);
            }

            if (hasItems && emptyState) {
                emptyState.remove();
            }
        }

        function filterIndex() {
            if (!filtersList) {
                return 0;
            }

            return filtersList.querySelectorAll('.career-filter-item').length;
        }

        document.querySelectorAll('input[name="filter_enabled"]').forEach((input) => {
            input.addEventListener('change', toggleFiltersPanel);
        });

        if (addCareerFilterButton && filtersList) {
            addCareerFilterButton.addEventListener('click', function () {
                const index = filterIndex();
                const emptyState = document.getElementById('careerFiltersEmpty');
                if (emptyState) {
                    emptyState.remove();
                }

                filtersList.insertAdjacentHTML('beforeend', `
                    <div class="career-filter-item border rounded-3 p-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-10">
                                <label class="form-label fw-bold mb-1">Vacancy Column</label>
                                <select name="section_filters[${index}][column]" class="form-select">
                                    <option value="">Select column</option>
                                    @foreach($filterableColumns as $column => $label)
                                    <option value="{{ $column }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-career-filter w-100">Remove</button>
                            </div>
                        </div>
                    </div>
                `);
            });
        }

        $(document).on('click', '.remove-career-filter', function() {
            $(this).closest('.career-filter-item').remove();
            refreshEmptyState();
        });

        document.addEventListener('invalid', function(e) {
            const invalidTabPane = e.target.closest('.tab-pane');
            if (invalidTabPane) {
                const tabId = invalidTabPane.id;
                const tabBtn = document.querySelector(`[data-bs-target="#${tabId}"]`);
                if (tabBtn && !tabBtn.classList.contains('active')) {
                    bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                    setTimeout(() => { e.target.focus(); }, 150);
                }
            }
        }, true);

        toggleFiltersPanel();
        refreshEmptyState();
    });
</script>
@endpush
