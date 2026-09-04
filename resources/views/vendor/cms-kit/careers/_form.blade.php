@php
    $careerConfig = config('cms-kit.database.careers.items', []);
    $careerRequired = $careerConfig['required'] ?? [];
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $item = $career ?? null;
    $isEdit = (bool) $item;
    $fallbackLocale = config('app.fallback_locale', 'en');
    $jobTypeOptionsByLanguage = $jobTypeOptions ?? [];
    $departmentOptionsByLanguage = $departmentOptions ?? [];
    $baseOptionsByLanguage = $baseOptions ?? [];
    $classificationValues = [
        'job_type' => old('job_type', $item->job_type ?? ''),
        'department' => old('department', $item->department ?? ''),
        'location' => old('location', $item->location ?? ''),
        'country' => old('country', $item->country ?? ''),
        'base' => old('base', $item->base ?? ''),
    ];

    foreach ($languages as $lang) {
        $code = $lang->code;

        $jobTypeOptionsByLanguage[$code] = $jobTypeOptionsByLanguage[$code] ?? ($jobTypeOptionsByLanguage[$fallbackLocale] ?? []);
        $departmentOptionsByLanguage[$code] = $departmentOptionsByLanguage[$code] ?? ($departmentOptionsByLanguage[$fallbackLocale] ?? []);
        $baseOptionsByLanguage[$code] = $baseOptionsByLanguage[$code] ?? ($baseOptionsByLanguage[$fallbackLocale] ?? []);

        if ($classificationValues['job_type'] !== '' && !array_key_exists($classificationValues['job_type'], $jobTypeOptionsByLanguage[$code])) {
            $fallbackLabel = \Illuminate\Support\Str::headline(str_replace(['-', '_'], ' ', $classificationValues['job_type']));
            $jobTypeOptionsByLanguage[$code][$classificationValues['job_type']] = [
                'label' => $fallbackLabel,
                'english' => $fallbackLabel,
            ];
        }

        if ($classificationValues['department'] !== '' && !array_key_exists($classificationValues['department'], $departmentOptionsByLanguage[$code])) {
            $departmentOptionsByLanguage[$code][$classificationValues['department']] = [
                'label' => $classificationValues['department'],
                'english' => $classificationValues['department'],
            ];
        }

        if ($classificationValues['base'] !== '' && !array_key_exists($classificationValues['base'], $baseOptionsByLanguage[$code])) {
            $fallbackLabel = \Illuminate\Support\Str::headline(str_replace(['-', '_'], ' ', $classificationValues['base']));
            $baseOptionsByLanguage[$code][$classificationValues['base']] = [
                'label' => $fallbackLabel,
                'english' => $fallbackLabel,
            ];
        }
    }
@endphp

<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">{{ $isEdit ? 'Edit Vacancy' : 'Add Vacancy' }}</h5>
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

        <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <input type="hidden" name="job_type" id="careerJobTypeMaster" value="{{ $classificationValues['job_type'] }}">
            <input type="hidden" name="department" id="careerDepartmentMaster" value="{{ $classificationValues['department'] }}">
            <input type="hidden" name="location" id="careerLocationMaster" value="{{ $classificationValues['location'] }}">
            <input type="hidden" name="country" id="careerCountryMaster" value="{{ $classificationValues['country'] }}">
            <input type="hidden" name="base" id="careerBaseMaster" value="{{ $classificationValues['base'] }}">

            <div class="row g-4 mb-4">
                @if($careerConfig['slug'] ?? true)
                <div class="col-md-6">
                    <label class="form-label fw-bold">Slug</label>
                    <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $item->slug ?? '') }}" placeholder="Auto-generated from title if left empty">
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                @endif
                @if($careerConfig['published_date'] ?? true)
                <div class="col-md-6">
                    <label class="form-label fw-bold">Published Date {!! in_array('published_date', $careerRequired, true) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <input type="date" name="published_date" class="form-control @error('published_date') is-invalid @enderror" value="{{ old('published_date', optional($item?->published_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" {{ in_array('published_date', $careerRequired, true) ? 'required' : '' }}>
                    @error('published_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                @endif
            </div>

            <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                <i class="fas fa-info-circle text-primary me-2"></i>
                <strong>Note:</strong> Classification dropdowns follow the selected language tab and keep the English value visible on the right.
            </div>

            @if($showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="careerLanguageTabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="career-tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#career-panel-{{ $lang->code }}" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="tab-content mb-4 language-switcher-content">
                @foreach($languages as $lang)
                    @php $translation = data_get($item, "translations.{$lang->code}", []); @endphp
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="career-panel-{{ $lang->code }}" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card bg-light border-0 career-classification-card">
                                    <div class="card-body p-4">
                                        <h6 class="fw-bold mb-3">Classification</h6>
                                        <div class="row g-4 career-classification-row">
                                            @if($careerConfig['job_type'] ?? true)
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Job Type {!! in_array('job_type', $careerRequired, true) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                                <div class="career-dual-dropdown career-classification-proxy @error('job_type') is-invalid @enderror" data-master-field="job_type" data-placeholder="Search job type">
                                                    <input type="text" class="form-control career-dual-dropdown-input" value="{{ data_get($jobTypeOptionsByLanguage, $lang->code . '.' . $classificationValues['job_type'] . '.label', '') }}" placeholder="Search job type" autocomplete="off">
                                                    <div class="career-dual-dropdown-menu">
                                                        @foreach($jobTypeOptionsByLanguage[$lang->code] ?? [] as $option => $meta)
                                                            <button type="button" class="career-dual-dropdown-option {{ $classificationValues['job_type'] === $option ? 'active' : '' }}" data-value="{{ $option }}" data-label="{{ $meta['label'] ?? '' }}">
                                                                <span class="career-dual-dropdown-primary">{{ $meta['label'] ?? '' }}</span>
                                                                <span class="career-dual-dropdown-secondary">{{ $meta['english'] ?? '' }}</span>
                                                            </button>
                                                        @endforeach
                                                        <div class="career-dual-dropdown-empty d-none">No options found.</div>
                                                    </div>
                                                </div>
                                                @error('job_type')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            @endif
                                            @if($careerConfig['department'] ?? true)
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Department {!! in_array('department', $careerRequired, true) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                                <div class="career-dual-dropdown career-classification-proxy @error('department') is-invalid @enderror" data-master-field="department" data-placeholder="Search department">
                                                    <input type="text" class="form-control career-dual-dropdown-input" value="{{ data_get($departmentOptionsByLanguage, $lang->code . '.' . $classificationValues['department'] . '.label', '') }}" placeholder="Search department" autocomplete="off">
                                                    <div class="career-dual-dropdown-menu">
                                                        @foreach($departmentOptionsByLanguage[$lang->code] ?? [] as $option => $meta)
                                                            <button type="button" class="career-dual-dropdown-option {{ $classificationValues['department'] === $option ? 'active' : '' }}" data-value="{{ $option }}" data-label="{{ $meta['label'] ?? '' }}">
                                                                <span class="career-dual-dropdown-primary">{{ $meta['label'] ?? '' }}</span>
                                                                <span class="career-dual-dropdown-secondary">{{ $meta['english'] ?? '' }}</span>
                                                            </button>
                                                        @endforeach
                                                        <div class="career-dual-dropdown-empty d-none">No options found.</div>
                                                    </div>
                                                </div>
                                                @error('department')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                                @if(empty($departmentOptionsByLanguage[$lang->code] ?? []))
                                                    <small class="text-muted d-block mt-1">Add active departments first to populate this dropdown.</small>
                                                @endif
                                            </div>
                                            @endif
                                            @if($careerConfig['location'] ?? true)
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold">Location {!! in_array('location', $careerRequired, true) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                                <input type="text" class="form-control career-classification-proxy @error('location') is-invalid @enderror" data-master-field="location" value="{{ $classificationValues['location'] }}">
                                                @error('location')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            @endif
                                            @if($careerConfig['country'] ?? true)
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold">Country</label>
                                                <input type="text" class="form-control career-classification-proxy @error('country') is-invalid @enderror" data-master-field="country" value="{{ $classificationValues['country'] }}">
                                                @error('country')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            @endif
                                            @if($careerConfig['base'] ?? true)
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold">Base</label>
                                                <div class="career-dual-dropdown career-classification-proxy @error('base') is-invalid @enderror" data-master-field="base" data-placeholder="Search base">
                                                    <input type="text" class="form-control career-dual-dropdown-input" value="{{ data_get($baseOptionsByLanguage, $lang->code . '.' . $classificationValues['base'] . '.label', '') }}" placeholder="Search base" autocomplete="off">
                                                    <div class="career-dual-dropdown-menu">
                                                        @foreach($baseOptionsByLanguage[$lang->code] ?? [] as $option => $meta)
                                                            <button type="button" class="career-dual-dropdown-option {{ $classificationValues['base'] === $option ? 'active' : '' }}" data-value="{{ $option }}" data-label="{{ $meta['label'] ?? '' }}">
                                                                <span class="career-dual-dropdown-primary">{{ $meta['label'] ?? '' }}</span>
                                                                <span class="career-dual-dropdown-secondary">{{ $meta['english'] ?? '' }}</span>
                                                            </button>
                                                        @endforeach
                                                        <div class="career-dual-dropdown-empty d-none">No options found.</div>
                                                    </div>
                                                </div>
                                                @error('base')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if($careerConfig['title'] ?? true)
                            <div class="col-12">
                                <label class="form-label fw-bold">Title {!! in_array('title', $careerRequired, true) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $translation['title'] ?? '') }}" {{ in_array('title', $careerRequired, true) ? 'required' : '' }}>
                                @error("translations.{$lang->code}.title")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif
                            @if($careerConfig['short_description'] ?? true)
                            <div class="col-12">
                                <label class="form-label fw-bold">Short Description</label>
                                <textarea name="translations[{{ $lang->code }}][short_description]" class="form-control @error("translations.{$lang->code}.short_description") is-invalid @enderror" rows="3">{{ old("translations.{$lang->code}.short_description", $translation['short_description'] ?? '') }}</textarea>
                                @error("translations.{$lang->code}.short_description")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif
                            @foreach(['about' => 'About', 'responsibilities' => 'Responsibilities', 'requirements' => 'Requirements', 'join_the_team' => 'Join the Team'] as $field => $label)
                            @if($careerConfig[$field] ?? true)
                            <div class="col-12">
                                <label class="form-label fw-bold">{{ $label }}</label>
                                <textarea name="translations[{{ $lang->code }}][{{ $field }}]" class="form-control tinymce-editor @error("translations.{$lang->code}.{$field}") is-invalid @enderror" rows="8">{{ old("translations.{$lang->code}.{$field}", $translation[$field] ?? '') }}</textarea>
                                @error("translations.{$lang->code}.{$field}")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif
                            @endforeach

                            @include('cms-kit::partials.extra-fields-translatable', [
                                'configKey' => 'careers.items',
                                'lang' => $lang,
                                'existingTranslations' => $item->translations ?? [],
                            ])
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card bg-light border-0 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-search me-2"></i>SEO Metadata</h6>
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label fw-bold">Meta Title</label>
                            <input type="text" name="metadata[meta_title]" class="form-control @error('metadata.meta_title') is-invalid @enderror" value="{{ old('metadata.meta_title', $item->metadata['meta_title'] ?? '') }}">
                            @error('metadata.meta_title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Meta Description</label>
                            <textarea name="metadata[meta_description]" class="form-control @error('metadata.meta_description') is-invalid @enderror" rows="3">{{ old('metadata.meta_description', $item->metadata['meta_description'] ?? '') }}</textarea>
                            @error('metadata.meta_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Meta Keywords</label>
                            <input type="text" name="metadata[meta_keywords]" class="form-control @error('metadata.meta_keywords') is-invalid @enderror" value="{{ old('metadata.meta_keywords', $item->metadata['meta_keywords'] ?? '') }}" placeholder="keyword1, keyword2">
                            @error('metadata.meta_keywords')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Canonical URL</label>
                            <input type="url" name="metadata[canonical_url]" class="form-control @error('metadata.canonical_url') is-invalid @enderror" value="{{ old('metadata.canonical_url', $item->metadata['canonical_url'] ?? '') }}" placeholder="https://example.com/careers/job-slug">
                            @error('metadata.canonical_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">OG Title</label>
                            <input type="text" name="metadata[og_title]" class="form-control @error('metadata.og_title') is-invalid @enderror" value="{{ old('metadata.og_title', $item->metadata['og_title'] ?? '') }}">
                            @error('metadata.og_title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">OG Description</label>
                            <textarea name="metadata[og_description]" class="form-control @error('metadata.og_description') is-invalid @enderror" rows="2">{{ old('metadata.og_description', $item->metadata['og_description'] ?? '') }}</textarea>
                            @error('metadata.og_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">OG Image</label>
                            @if(!empty($item->metadata['og_image'] ?? null))
                                <div class="mb-2">
                                    <img src="{{ asset('storage/' . $item->metadata['og_image']) }}" class="rounded border" style="height: 100px;">
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="remove_metadata_og_image" id="removeCareerOgImage" value="1" {{ old('remove_metadata_og_image') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="removeCareerOgImage">Remove current OG image</label>
                                </div>
                            @endif
                            <input type="file" name="metadata[og_image]" class="form-control @error('metadata.og_image') is-invalid @enderror" accept="image/*">
                            @error('metadata.og_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Other Meta Tags</label>
                            <textarea name="metadata[other_meta_tags]" class="form-control @error('metadata.other_meta_tags') is-invalid @enderror" rows="3">{{ old('metadata.other_meta_tags', $item->metadata['other_meta_tags'] ?? '') }}</textarea>
                            @error('metadata.other_meta_tags')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-light border-0 mb-4">
                <div class="card-body p-4">
                    <div class="row g-4 align-items-end">
                        @if($careerConfig['order'] ?? true)
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Order</label>
                            <input type="number" name="order_index" class="form-control @error('order_index') is-invalid @enderror" value="{{ old('order_index', $item->order_index ?? $nextOrder) }}" min="1">
                            @error('order_index')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif
                        @if($careerConfig['status'] ?? true)
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="status" id="careerStatus" value="1" {{ old('status', $item->status ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="careerStatus">Status (Active)</label>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            @include('cms-kit::partials.extra-fields-global', [
                'configKey' => 'careers.items',
                'existingValues' => $item->extra_fields ?? [],
            ])

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">{{ $submitLabel }}</button>
                <a href="{{ route('cms.careers.vacancies.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const careerClassificationMasterIds = {
        job_type: 'careerJobTypeMaster',
        department: 'careerDepartmentMaster',
        location: 'careerLocationMaster',
        country: 'careerCountryMaster',
        base: 'careerBaseMaster'
    };

    const careerDualDropdownStyles = `
        <style>
            .career-classification-card,
            .career-classification-card .card-body,
            .career-classification-row,
            .career-classification-row > div {
                overflow: visible !important;
            }
            .career-dual-dropdown { position: relative; }
            .career-dual-dropdown-input {
                cursor: pointer;
                background-image: linear-gradient(45deg, transparent 50%, #6b7280 50%), linear-gradient(135deg, #6b7280 50%, transparent 50%);
                background-position: calc(100% - 18px) calc(50% - 3px), calc(100% - 12px) calc(50% - 3px);
                background-size: 6px 6px, 6px 6px;
                background-repeat: no-repeat;
                padding-right: 2.5rem;
            }
            .career-dual-dropdown-menu {
                position: absolute;
                top: calc(100% + 6px);
                left: 0;
                right: 0;
                z-index: 1055;
                display: none;
                max-height: 260px;
                overflow-y: auto;
                background: #fff;
                border: 1px solid #dfe5ec;
                border-radius: 12px;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
            }
            .career-dual-dropdown.open .career-dual-dropdown-menu { display: block; }
            .career-dual-dropdown-option {
                width: 100%;
                padding: 0.85rem 1rem;
                display: flex;
                justify-content: space-between;
                gap: 1rem;
                border: 0;
                background: transparent;
                text-align: left;
            }
            .career-dual-dropdown-option + .career-dual-dropdown-option { border-top: 1px solid #edf1f5; }
            .career-dual-dropdown-option:hover,
            .career-dual-dropdown-option.active {
                background: #f8fafc;
            }
            .career-dual-dropdown-primary {
                color: #1f2937;
                font-weight: 500;
            }
            .career-dual-dropdown-secondary {
                color: #6b7280;
                font-size: 0.875rem;
                white-space: nowrap;
            }
            .career-dual-dropdown-option.is-hidden { display: none; }
            .career-dual-dropdown-empty {
                padding: 0.9rem 1rem;
                color: #6b7280;
                font-size: 0.9rem;
            }
        </style>
    `;

    if (!document.getElementById('careerDualDropdownStyles')) {
        document.head.insertAdjacentHTML('beforeend', careerDualDropdownStyles.replace('<style>', '<style id="careerDualDropdownStyles">'));
    }

    tinymce.init({
        selector: '.tinymce-editor',
        height: 360,
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
        toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help'
    });

    function updateCareerDualDropdownState(dropdown, value) {
        const input = dropdown.querySelector('.career-dual-dropdown-input');
        const selectedOption = dropdown.querySelector(`.career-dual-dropdown-option[data-value="${CSS.escape(value)}"]`);
        const placeholder = dropdown.dataset.placeholder || '';

        dropdown.querySelectorAll('.career-dual-dropdown-option').forEach((option) => {
            option.classList.toggle('active', option.dataset.value === value);
            option.classList.remove('is-hidden');
        });

        input.value = selectedOption ? (selectedOption.dataset.label || '') : '';
        input.placeholder = placeholder;
        toggleCareerDualDropdownEmpty(dropdown);
    }

    function toggleCareerDualDropdownEmpty(dropdown) {
        const emptyState = dropdown.querySelector('.career-dual-dropdown-empty');
        if (!emptyState) {
            return;
        }

        const hasVisibleOptions = Array.from(dropdown.querySelectorAll('.career-dual-dropdown-option'))
            .some((option) => !option.classList.contains('is-hidden'));

        emptyState.classList.toggle('d-none', hasVisibleOptions);
    }

    function resetCareerDualDropdownOptions(dropdown) {
        dropdown.querySelectorAll('.career-dual-dropdown-option').forEach((option) => {
            option.classList.remove('is-hidden');
        });

        toggleCareerDualDropdownEmpty(dropdown);
    }

    function syncCareerClassification(field, value, source) {
        const masterInput = document.getElementById(careerClassificationMasterIds[field] || '');
        if (masterInput) {
            masterInput.value = value;
        }

        document.querySelectorAll(`.career-classification-proxy[data-master-field="${field}"]`).forEach((element) => {
            if (element !== source) {
                if (element.classList.contains('career-dual-dropdown')) {
                    updateCareerDualDropdownState(element, value);
                } else {
                    element.value = value;
                }
            }
        });
    }

    document.querySelectorAll('.career-classification-proxy').forEach((element) => {
        const field = element.dataset.masterField;

        if (element.classList.contains('career-dual-dropdown')) {
            const input = element.querySelector('.career-dual-dropdown-input');
            input.setAttribute('readonly', 'readonly');

            input.addEventListener('click', function() {
                const willOpen = !element.classList.contains('open');
                document.querySelectorAll('.career-dual-dropdown').forEach((dropdown) => dropdown.classList.remove('open'));
                if (willOpen) {
                    resetCareerDualDropdownOptions(element);
                    element.classList.add('open');
                }
            });

            element.querySelectorAll('.career-dual-dropdown-option').forEach((option) => {
                option.addEventListener('click', function() {
                    syncCareerClassification(field, this.dataset.value, element);
                    updateCareerDualDropdownState(element, this.dataset.value);
                    element.classList.remove('open');
                });
            });
        } else {
            element.addEventListener('input', function() {
                syncCareerClassification(field, this.value, this);
            });
        }
    });

    ['job_type', 'department', 'location', 'country', 'base'].forEach((field) => {
        const masterInput = document.getElementById(careerClassificationMasterIds[field] || '');
        if (masterInput) {
            syncCareerClassification(field, masterInput.value, null);
        }
    });

    document.addEventListener('click', function(e) {
        document.querySelectorAll('.career-dual-dropdown').forEach((dropdown) => {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
                const field = dropdown.dataset.masterField;
                const masterInput = document.getElementById(careerClassificationMasterIds[field] || '');
                updateCareerDualDropdownState(dropdown, masterInput ? masterInput.value : '');
            }
        });
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
</script>
@endpush
