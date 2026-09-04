<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\Career;
use App\Models\CmsKit\CareerDepartment;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use CMS\SiteManager\Support\ManagesOrderIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class CareerController extends Controller
{
    use ManagesOrderIndex;

    protected array $translatableFields = [
        'title',
        'short_description',
        'about',
        'responsibilities',
        'requirements',
        'join_the_team',
    ];

    protected function activeLanguages()
    {
        return Language::where('status', true)->get();
    }

    protected function hasMeaningfulText($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $plainText = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plainText = str_replace("\xc2\xa0", ' ', $plainText);

        return trim($plainText) !== '';
    }

    protected function requiredTextRule(string $message = 'Invalid input'): array
    {
        return [
            'required',
            'string',
            function ($attribute, $value, $fail) use ($message) {
                if (!$this->hasMeaningfulText($value)) {
                    $fail($message);
                }
            },
        ];
    }

    protected function optionalTextRule(string $message = 'Invalid input'): array
    {
        return [
            'nullable',
            'string',
            function ($attribute, $value, $fail) use ($message) {
                if ($value !== null && $value !== '' && !$this->hasMeaningfulText($value)) {
                    $fail($message);
                }
            },
        ];
    }

    protected function getCareerValidationRules(?int $careerId = null): array
    {
        $careerConfig = config('cms-kit.database.careers.items', []);
        $requiredFields = $careerConfig['required'] ?? [];
        $rules = [
            'translations' => ['nullable', 'array'],
            'translations.*.extra_fields' => ['nullable', 'array'],
            'extra_fields' => ['nullable', 'array'],
            'order_index' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'boolean'],
            'metadata.meta_title' => $this->optionalTextRule(),
            'metadata.meta_description' => $this->optionalTextRule(),
            'metadata.meta_keywords' => $this->optionalTextRule(),
            'metadata.canonical_url' => ['nullable', 'url'],
            'metadata.og_title' => $this->optionalTextRule(),
            'metadata.og_description' => $this->optionalTextRule(),
            'metadata.og_image' => ['nullable', 'image', 'max:512'],
            'remove_metadata_og_image' => ['nullable', 'boolean'],
            'metadata.other_meta_tags' => ['nullable', 'string'],
        ];

        if ($careerConfig['slug'] ?? true) {
            $rules['slug'] = ['nullable', 'string', 'max:255'];
        }

        foreach (['job_type', 'department', 'location', 'country', 'base'] as $field) {
            if (!($careerConfig[$field] ?? true)) {
                continue;
            }

            $fieldRules = in_array($field, $requiredFields, true)
                ? $this->requiredTextRule()
                : $this->optionalTextRule();

            if ($field === 'job_type') {
                $fieldRules[] = Rule::in(array_keys($this->getStaticOptionDefinitions('job_type_options')));
            }

            if ($field === 'base') {
                $fieldRules[] = Rule::in(array_keys($this->getStaticOptionDefinitions('base_options')));
            }

            if ($field === 'department') {
                $fieldRules[] = Rule::in(array_keys($this->getDepartmentOptions()[config('app.fallback_locale', 'en')] ?? []));
            }

            $rules[$field] = $fieldRules;
        }

        if ($careerConfig['published_date'] ?? true) {
            $rules['published_date'] = in_array('published_date', $requiredFields, true)
                ? ['required', 'date']
                : ['nullable', 'date'];
        }

        foreach ($this->activeLanguages() as $lang) {
            foreach ($this->translatableFields as $field) {
                if (!($careerConfig[$field] ?? true)) {
                    continue;
                }

                $isRequired = in_array($field, $requiredFields, true);
                $rules["translations.{$lang->code}.{$field}"] = $isRequired
                    ? $this->requiredTextRule()
                    : $this->optionalTextRule();
            }
        }

        return $rules;
    }

    protected function getCareerSectionValidationRules(): array
    {
        $sectionConfig = config('cms-kit.database.careers.section', []);
        $section = SectionLabel::firstOrCreate(['section_key' => 'careers']);
        $rules = [
            'translations' => ['nullable', 'array'],
            'translations.*.extra_fields' => ['nullable', 'array'],
            'extra_fields' => ['nullable', 'array'],
            'section_filters' => ['nullable', 'array'],
        ];

        $requiredFields = $sectionConfig['required'] ?? [];

        if ($sectionConfig['filter_enabled'] ?? true) {
            $rules['filter_enabled'] = ['required', 'boolean'];
        }

        if ($sectionConfig['banner'] ?? true) {
            $bannerRequired = in_array('banner', $requiredFields, true)
                && (!$section?->banner || request()->boolean('remove_banner'));
            $rules['banner'] = [$bannerRequired ? 'required' : 'nullable', 'image', 'max:2048'];
            $rules['remove_banner'] = ['nullable', 'boolean'];
        }

        if ($sectionConfig['banner_alt'] ?? true) {
            $rules['banner_alt'] = ['nullable', 'string', 'max:255'];
        }

        if ($sectionConfig['filters'] ?? true) {
            $rules['section_filters.*.column'] = ['nullable', 'string', Rule::in($this->getFilterableColumns())];
        }

        foreach ($this->activeLanguages() as $lang) {
            if ($sectionConfig['title'] ?? true) {
                $rules["translations.{$lang->code}.title"] = in_array('title', $requiredFields, true)
                    ? $this->requiredTextRule('Title is required')
                    : $this->optionalTextRule();
            }

            if ($sectionConfig['description'] ?? true) {
                $rules["translations.{$lang->code}.description"] = $this->optionalTextRule();
            }
        }

        return $rules;
    }

    protected function normalizeTranslations(array $translations): array
    {
        $normalized = [];
        $fieldConfig = config('cms-kit.database.careers.items.extra_fields', []);
        $translatableExtraFields = collect($fieldConfig)
            ->filter(fn ($field) => $field['translatable'] ?? false)
            ->keys();

        foreach ($translations as $lang => $values) {
            foreach ($this->translatableFields as $field) {
                $value = $values[$field] ?? null;
                $normalized[$lang][$field] = is_string($value) ? trim($value) : $value;
            }

            $normalized[$lang]['extra_fields'] = [];
            foreach ($translatableExtraFields as $fieldName) {
                $value = data_get($values, "extra_fields.{$fieldName}");
                $normalized[$lang]['extra_fields'][$fieldName] = is_string($value) ? trim($value) : $value;
            }
        }

        return $normalized;
    }

    protected function normalizeSectionTranslations(array $translations): array
    {
        $sectionConfig = config('cms-kit.database.careers.section', []);
        $normalized = [];

        foreach ($translations as $lang => $values) {
            $normalized[$lang]['title'] = ($sectionConfig['title'] ?? true) && isset($values['title'])
                ? trim((string) $values['title'])
                : null;
            $normalized[$lang]['description'] = ($sectionConfig['description'] ?? true) && isset($values['description'])
                ? trim((string) $values['description'])
                : null;
            $normalized[$lang]['extra_fields'] = (array) data_get($values, 'extra_fields', []);
        }

        return $normalized;
    }

    protected function mergeCareerSectionTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.careers.section.extra_fields', []);
        $translatableFields = collect($fieldConfig)->filter(fn ($field) => $field['translatable'] ?? false)->keys();

        foreach ($translations as $lang => $values) {
            $translations[$lang]['extra_fields'] = [];
            foreach ($translatableFields as $fieldName) {
                $translations[$lang]['extra_fields'][$fieldName] = data_get($values, "extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    protected function mergeCareerItemTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.careers.items.extra_fields', []);
        $translatableFields = collect($fieldConfig)->filter(fn ($field) => $field['translatable'] ?? false)->keys();

        foreach ($translations as $lang => $values) {
            $translations[$lang]['extra_fields'] = [];
            foreach ($translatableFields as $fieldName) {
                $translations[$lang]['extra_fields'][$fieldName] = data_get($values, "extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    protected function resolveConfiguredExtraFields(Request $request, string $configKey, array $existingValues = [], string $storagePath = ''): array
    {
        $resolved = [];

        foreach (config("cms-kit.database.{$configKey}.extra_fields", []) as $key => $field) {
            if ($field['translatable'] ?? false) {
                continue;
            }

            $existingValue = $existingValues[$key] ?? null;

            if (($field['type'] ?? 'text') === 'file') {
                if ($request->hasFile("extra_fields.{$key}")) {
                    if ($storagePath !== '' && is_string($existingValue) && $existingValue !== '') {
                        Storage::disk('public')->delete($existingValue);
                    }

                    $resolved[$key] = $request->file("extra_fields.{$key}")->store($storagePath, 'public');
                    continue;
                }

                $resolved[$key] = $existingValue;
                continue;
            }

            $resolved[$key] = $request->input("extra_fields.{$key}");
        }

        return $resolved;
    }

    protected function resolveConfiguredTranslatableExtraFields(Request $request, string $configKey, array $existingTranslations = [], string $storagePath = ''): array
    {
        $translations = $request->input('translations', []);
        $fieldConfig = config("cms-kit.database.{$configKey}.extra_fields", []);

        foreach ($translations as $lang => $values) {
            $translations[$lang]['extra_fields'] = $translations[$lang]['extra_fields'] ?? [];

            foreach ($fieldConfig as $fieldName => $field) {
                if (!($field['translatable'] ?? false)) {
                    continue;
                }

                $existingValue = data_get($existingTranslations, "{$lang}.extra_fields.{$fieldName}");

                if (($field['type'] ?? 'text') === 'file') {
                    if ($request->hasFile("translations.{$lang}.extra_fields.{$fieldName}")) {
                        if ($storagePath !== '' && is_string($existingValue) && $existingValue !== '') {
                            Storage::disk('public')->delete($existingValue);
                        }

                        $translations[$lang]['extra_fields'][$fieldName] = $request->file("translations.{$lang}.extra_fields.{$fieldName}")->store($storagePath, 'public');
                        continue;
                    }

                    $translations[$lang]['extra_fields'][$fieldName] = $existingValue;
                    continue;
                }

                $translations[$lang]['extra_fields'][$fieldName] = data_get($values, "extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    protected function normalizeSectionFilters(array $filters): array
    {
        $normalized = [];
        $allowedColumns = $this->getFilterableColumns();

        foreach ($filters as $filter) {
            $column = trim((string) ($filter['column'] ?? $filter['key'] ?? ''));

            if ($column === '' || !in_array($column, $allowedColumns, true)) {
                continue;
            }

            $normalized[] = ['key' => $column];
        }

        return collect($normalized)->unique('key')->values()->all();
    }

    protected function getFilterableColumns(): array
    {
        $itemConfig = config('cms-kit.database.careers.items', []);
        $configured = $itemConfig['columns'] ?? [];
        $defaultColumns = config('cms-kit.database.careers.section.filterable_columns', ['job_type', 'department', 'location', 'country', 'base']);

        return collect($defaultColumns)
            ->filter(fn ($column) => ($itemConfig[$column] ?? true) && ($configured[$column] ?? true))
            ->values()
            ->all();
    }

    protected function getDistinctCareerColumnOptions(string $column): array
    {
        if ($column === 'job_type') {
            $configured = array_keys($this->getStaticOptionDefinitions('job_type_options'));

            $stored = Career::query()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->all();

            return array_values(array_unique(array_filter(array_merge($configured, $stored))));
        }

        if ($column === 'base') {
            $configured = array_keys($this->getStaticOptionDefinitions('base_options'));

            $stored = Career::query()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->all();

            return array_values(array_unique(array_filter(array_merge($configured, $stored))));
        }

        return Career::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    protected function getJobTypeOptions(): array
    {
        return $this->getLocalizedStaticOptions('job_type_options');
    }

    protected function getBaseOptions(): array
    {
        return $this->getLocalizedStaticOptions('base_options');
    }

    protected function getStaticOptionDefinitions(string $configKey): array
    {
        $fallbackLocale = config('app.fallback_locale', 'en');

        return collect(config("cms-kit.database.careers.items.{$configKey}", []))
            ->map(function ($labels) use ($fallbackLocale) {
                if (is_array($labels)) {
                    return $labels;
                }

                return [$fallbackLocale => (string) $labels];
            })
            ->all();
    }

    protected function formatLocalizedOptionLabel(array $labels, string $langCode): string
    {
        $fallbackLocale = config('app.fallback_locale', 'en');
        $fallbackLabel = trim((string) ($labels[$fallbackLocale] ?? reset($labels) ?: ''));
        $currentLabel = trim((string) ($labels[$langCode] ?? $fallbackLabel));

        if ($langCode !== $fallbackLocale && $fallbackLabel !== '' && $currentLabel !== $fallbackLabel) {
            return "{$currentLabel} ({$fallbackLabel})";
        }

        return $currentLabel !== '' ? $currentLabel : $fallbackLabel;
    }

    protected function getLocalizedStaticOptions(string $configKey): array
    {
        $definitions = $this->getStaticOptionDefinitions($configKey);
        $options = [];
        $fallbackLocale = config('app.fallback_locale', 'en');

        foreach ($this->activeLanguages() as $language) {
            $options[$language->code] = collect($definitions)
                ->mapWithKeys(function ($labels, $value) use ($language, $fallbackLocale) {
                    $label = trim((string) ($labels[$language->code] ?? $labels[$fallbackLocale] ?? reset($labels) ?: ''));
                    $english = trim((string) ($labels[$fallbackLocale] ?? reset($labels) ?: $label));

                    return [$value => [
                        'label' => $label !== '' ? $label : $english,
                        'english' => $english,
                    ]];
                })
                ->all();
        }

        return $options;
    }

    protected function getDepartmentOptions(): array
    {
        $departments = CareerDepartment::query()
            ->where('status', true)
            ->ordered()
            ->get();
        $options = [];
        $fallbackLocale = config('app.fallback_locale', 'en');

        foreach ($this->activeLanguages() as $language) {
            $options[$language->code] = $departments
                ->mapWithKeys(function ($department) use ($language, $fallbackLocale) {
                    $fallbackTitle = trim((string) ($department->translations[$fallbackLocale]['title'] ?? ''));
                    $localizedTitle = trim((string) ($department->translations[$language->code]['title'] ?? $fallbackTitle));

                    if ($fallbackTitle === '' && $localizedTitle === '') {
                        return [];
                    }

                    $value = $fallbackTitle !== '' ? $fallbackTitle : $localizedTitle;

                    return [$value => [
                        'label' => $localizedTitle !== '' ? $localizedTitle : $fallbackTitle,
                        'english' => $fallbackTitle !== '' ? $fallbackTitle : $localizedTitle,
                    ]];
                })
                ->all();
        }

        return $options;
    }

    protected function formatJobType(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $fallbackLocale = config('app.fallback_locale', 'en');

        return data_get($this->getJobTypeOptions(), "{$fallbackLocale}.{$value}.english")
            ?? data_get($this->getJobTypeOptions(), "{$fallbackLocale}.{$value}.label")
            ?? Str::headline(str_replace(['-', '_'], ' ', $value));
    }

    protected function resolveUniqueSlug(Request $request, array $translations, ?int $ignoreId = null): string
    {
        $fallbackLocale = config('app.fallback_locale');
        $providedSlug = trim((string) $request->input('slug'));
        $titleSource = $translations[$fallbackLocale]['title']
            ?? data_get($translations, array_key_first($translations) . '.title');

        $baseSlug = Str::slug($providedSlug !== '' ? $providedSlug : (string) $titleSource);

        if ($baseSlug === '') {
            throw ValidationException::withMessages([
                'slug' => 'Invalid input',
            ]);
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (
            Career::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function getSectionFilterOptions(): array
    {
        $section = SectionLabel::where('section_key', 'careers')->first();
        $filters = data_get($section?->extra_fields, 'filters', []);

        return collect($filters)
            ->filter(fn ($filter) => !empty($filter['key']))
            ->mapWithKeys(fn ($filter) => [
                $filter['key'] => [
                    'label' => Str::headline($filter['key']),
                    'options' => $this->getDistinctCareerColumnOptions($filter['key']),
                ],
            ])->all();
    }

    protected function validationMessages(): array
    {
        return [
            'translations.*.title.required' => 'Title is required',
            'banner.image' => 'Banner must be an image.',
            'banner.max' => 'Banner image is too large.',
            'metadata.canonical_url.url' => 'Canonical URL must be a valid URL.',
            'metadata.og_image.image' => 'OG image must be an image.',
            'metadata.og_image.max' => 'OG image is too large.',
            'job_type.required' => 'Job type is required',
            'job_type.in' => 'Invalid job type selected.',
            'department.required' => 'Department is required',
            'department.in' => 'Invalid department selected.',
            'location.required' => 'Location is required',
            'base.in' => 'Invalid base selected.',
            'published_date.required' => 'Published date is required',
            'published_date.date' => 'Invalid input',
            'order_index.integer' => 'Invalid input',
            'order_index.min' => 'Invalid input',
            'filter_enabled.required' => 'Invalid input',
            'filter_enabled.boolean' => 'Invalid input',
        ];
    }

    public function index(Request $request)
    {
        return redirect()->route('cms.careers.common');
    }

    public function common()
    {
        $section = SectionLabel::firstOrCreate(['section_key' => 'careers']);
        $languages = $this->activeLanguages();
        $filterableColumns = $this->getFilterableColumns();

        return view('cms-kit::careers.common', compact('section', 'languages', 'filterableColumns'));
    }

    public function vacancies(Request $request)
    {
        if ($request->ajax()) {
            $data = Career::query()->ordered();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('select_all', fn ($row) => '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">')
                ->addColumn('title', fn ($row) => e($row->getTranslation('title')))
                ->editColumn('job_type', fn ($row) => e($this->formatJobType($row->job_type)))
                ->editColumn('published_date', fn ($row) => optional($row->published_date)->format('d M Y'))
                ->addColumn('status', function ($row) {
                    $checked = $row->status ? 'checked' : '';

                    return '<div class="form-check form-switch">
                                <input class="form-check-input toggle-status" type="checkbox" data-id="' . $row->id . '" ' . $checked . '>
                            </div>';
                })
                ->addColumn('order', fn ($row) => '<input type="number" min="1" class="form-control form-control-sm reorder-input" data-id="' . $row->id . '" value="' . $row->order_index . '" style="width: 80px;">')
                ->addColumn('action', function ($row) {
                    $buttons = '<div class="btn-group">';

                    if (auth('cms')->user()?->can('careers.edit')) {
                        $buttons .= '<a href="' . route('cms.careers.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }

                    if (auth('cms')->user()?->can('careers.delete')) {
                        $buttons .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }

                    $buttons .= '</div>';

                    return $buttons;
                })
                ->rawColumns(['select_all', 'status', 'order', 'action'])
                ->make(true);
        }

        $section = SectionLabel::firstOrCreate(['section_key' => 'careers']);
        $languages = $this->activeLanguages();

        return view('cms-kit::careers.index', compact('section', 'languages'));
    }

    public function create()
    {
        $languages = $this->activeLanguages();
        $nextOrder = Career::count() + 1;
        $filterOptions = $this->getSectionFilterOptions();
        $jobTypeOptions = $this->getJobTypeOptions();
        $baseOptions = $this->getBaseOptions();
        $departmentOptions = $this->getDepartmentOptions();

        return view('cms-kit::careers.create', compact('languages', 'nextOrder', 'filterOptions', 'departmentOptions', 'jobTypeOptions', 'baseOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->getCareerValidationRules(),
            $this->validationMessages(),
            ['translations.*.title' => 'title']
        );

        $translations = $this->mergeCareerItemTranslatableExtraFields(
            $this->normalizeTranslations(
                $this->resolveConfiguredTranslatableExtraFields($request, 'careers.items', [], 'careers/items/extra-fields')
            )
        );
        $order = $this->resolveOrderForCreate(Career::class, $request->filled('order_index') ? (int) $request->order_index : null);
        $extraFields = $this->resolveConfiguredExtraFields($request, 'careers.items', [], 'careers/items/extra-fields');

        Career::where('order_index', '>=', $order)->increment('order_index');

        $metadata = $request->input('metadata', []);
        if ($request->hasFile('metadata.og_image')) {
            $metadata['og_image'] = $request->file('metadata.og_image')->store('careers/metadata', 'public');
        }

        Career::create([
            'slug' => $this->resolveUniqueSlug($request, $translations),
            'job_type' => trim((string) ($validated['job_type'] ?? '')),
            'department' => trim((string) ($validated['department'] ?? '')),
            'location' => trim((string) ($validated['location'] ?? '')),
            'country' => trim((string) ($validated['country'] ?? '')),
            'base' => trim((string) ($validated['base'] ?? '')),
            'published_date' => $validated['published_date'] ?? now()->toDateString(),
            'order_index' => $order,
            'status' => $request->boolean('status', true),
            'translations' => $translations,
            'metadata' => $metadata,
            'extra_fields' => $extraFields,
        ]);

        return redirect()->route('cms.careers.vacancies.index')->with('success', 'Career created successfully.');
    }

    public function edit($id)
    {
        $career = Career::findOrFail($id);
        $languages = $this->activeLanguages();
        $filterOptions = $this->getSectionFilterOptions();
        $jobTypeOptions = $this->getJobTypeOptions();
        $baseOptions = $this->getBaseOptions();
        $departmentOptions = $this->getDepartmentOptions();

        return view('cms-kit::careers.edit', compact('career', 'languages', 'filterOptions', 'departmentOptions', 'jobTypeOptions', 'baseOptions'));
    }

    public function update(Request $request, $id)
    {
        $career = Career::findOrFail($id);
        $validated = $request->validate(
            $this->getCareerValidationRules($career->id),
            $this->validationMessages(),
            ['translations.*.title' => 'title']
        );

        $metadata = $request->input('metadata', []);
        $existingMetadata = $career->metadata ?? [];
        $extraFields = $this->resolveConfiguredExtraFields($request, 'careers.items', $career->extra_fields ?? [], 'careers/items/extra-fields');
        $translations = $this->mergeCareerItemTranslatableExtraFields(
            $this->normalizeTranslations(
                $this->resolveConfiguredTranslatableExtraFields($request, 'careers.items', $career->translations ?? [], 'careers/items/extra-fields')
            )
        );
        if ($request->hasFile('metadata.og_image')) {
            if (!empty($existingMetadata['og_image'])) {
                Storage::disk('public')->delete($existingMetadata['og_image']);
            }
            $metadata['og_image'] = $request->file('metadata.og_image')->store('careers/metadata', 'public');
        } elseif ($request->boolean('remove_metadata_og_image') && !empty($existingMetadata['og_image'])) {
            Storage::disk('public')->delete($existingMetadata['og_image']);
            $metadata['og_image'] = null;
        } else {
            $metadata['og_image'] = $existingMetadata['og_image'] ?? null;
        }

        $newSlug = $this->resolveUniqueSlug($request, $translations, $career->id);
        app(\CMS\SiteManager\Services\UrlRedirectService::class)->recordSlugChange('career', $career->slug, $newSlug, auth('cms')->id());

        $career->update([
            'slug' => $newSlug,
            'job_type' => trim((string) ($validated['job_type'] ?? $career->job_type)),
            'department' => trim((string) ($validated['department'] ?? $career->department)),
            'location' => trim((string) ($validated['location'] ?? $career->location)),
            'country' => trim((string) ($validated['country'] ?? $career->country)),
            'base' => trim((string) ($validated['base'] ?? $career->base)),
            'published_date' => $validated['published_date'] ?? optional($career->published_date)->toDateString(),
            'status' => $request->has('status') ? $request->boolean('status') : $career->status,
            'translations' => $translations,
            'metadata' => $metadata,
            'extra_fields' => $extraFields,
        ]);

        return redirect()->route('cms.careers.vacancies.index')->with('success', 'Career updated successfully.');
    }

    public function destroy($id)
    {
        $career = Career::findOrFail($id);
        app(\CMS\SiteManager\Services\UrlRedirectService::class)->recordDeletion('career', $career->slug, auth('cms')->id());
        $order = $career->order_index;

        $career->delete();

        Career::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(Career::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $career = Career::findOrFail($id);
        $career->status = !$career->status;
        $career->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer', Rule::exists('careers', 'id')],
            'order_index' => ['required', 'integer', 'min:1'],
        ], [
            'order_index.required' => 'Invalid input',
            'order_index.integer' => 'Invalid input',
            'order_index.min' => 'Invalid input',
        ]);

        $career = Career::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(Career::class, (int) $request->order_index);
        $oldOrder = $career->order_index;

        if ($newOrder !== $oldOrder) {
            if ($newOrder > $oldOrder) {
                Career::where('order_index', '>', $oldOrder)
                    ->where('order_index', '<=', $newOrder)
                    ->decrement('order_index');
            } else {
                Career::where('order_index', '>=', $newOrder)
                    ->where('order_index', '<', $oldOrder)
                    ->increment('order_index');
            }

            $career->order_index = $newOrder;
            $career->save();
        }

        $this->normalizeOrderIndex(Career::class);

        return response()->json(['success' => true]);
    }

    public function updateSection(Request $request)
    {
        $sectionConfig = config('cms-kit.database.careers.section', []);
        $request->validate($this->getCareerSectionValidationRules(), $this->validationMessages());

        $section = SectionLabel::firstOrCreate(['section_key' => 'careers']);
        $data = [
            'translations' => $this->mergeCareerSectionTranslatableExtraFields(
                $this->normalizeSectionTranslations(
                    $this->resolveConfiguredTranslatableExtraFields($request, 'careers.section', $section->translations ?? [], 'careers/section/extra-fields')
                )
            ),
            'extra_fields' => array_merge(
                $this->resolveConfiguredExtraFields($request, 'careers.section', $section->extra_fields ?? [], 'careers/section/extra-fields'),
                [
                'filter_enabled' => ($sectionConfig['filter_enabled'] ?? true) ? $request->boolean('filter_enabled') : false,
                'filters' => ($sectionConfig['filters'] ?? true) ? $this->normalizeSectionFilters($request->input('section_filters', [])) : [],
                ]
            ),
            'banner_alt' => ($sectionConfig['banner_alt'] ?? true) ? trim((string) $request->input('banner_alt', '')) : '',
        ];

        if (($sectionConfig['banner'] ?? true) && $request->hasFile('banner')) {
            if ($section->banner) {
                Storage::disk('public')->delete($section->banner);
            }

            $data['banner'] = $request->file('banner')->store('careers/section', 'public');
        } elseif ($request->boolean('remove_banner') && $section->banner) {
            Storage::disk('public')->delete($section->banner);
            $data['banner'] = null;
        }

        $data['banner_alt'] = ($sectionConfig['banner_alt'] ?? true) && !$request->boolean('remove_banner')
            ? trim((string) $request->input('banner_alt', ''))
            : '';

        $section->update($data);

        return redirect()->route('cms.careers.common')->with('success', 'Career section updated successfully.');
    }

    public function bulkAction(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));
        $action = $request->input('action');

        if (empty($ids) || !$action) {
            return response()->json(['success' => false, 'message' => 'No action or items selected.'], 422);
        }

        if ($action === 'delete') {
            abort_unless(auth('cms')->user()?->can('careers.delete'), 403);
            $careers = Career::whereIn('id', $ids)->get();
            $redirectSvc = app(\CMS\SiteManager\Services\UrlRedirectService::class);
            foreach ($careers as $career) {
                $redirectSvc->recordDeletion('career', $career->slug, auth('cms')->id());
            }
            Career::whereIn('id', $ids)->delete();
            $this->normalizeOrderIndex(Career::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            Career::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            Career::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}
