<?php

namespace App\Http\Controllers;

use App\Models\CmsKit\Language;
use App\Models\SearchFilter;
use App\Services\SearchFilterService;
use App\Support\SearchFilterFields;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin › Filters › Search Filters — the website search filters of each listing page
 * (Properties, Commercial, Premium, Off-plan). Each page has its own set; "Copy from" clones one
 * page's set onto another to edit from there. The Home banner search has fixed filters
 * (SearchFilter::HOME_FIELDS): only their labels and values are edited here.
 */
class SearchFilterController extends Controller
{
    public function __construct(private readonly SearchFilterService $service)
    {
    }

    public function index(Request $request)
    {
        $page = $this->page($request->input('page'));
        $filters = SearchFilter::forPage($page)->withCount('options')->get();
        $counts = SearchFilter::selectRaw('page, count(*) as total, sum(status) as active')->groupBy('page')->get()->keyBy('page');

        return view('search-filters.index', [
            'page' => $page,
            'filters' => $filters,
            'counts' => $counts,
            'fields' => SearchFilterFields::all(),
        ]);
    }

    public function create(Request $request)
    {
        $page = $this->page($request->input('page'));
        if ($page === 'home') {
            return redirect()->route('cms.filters.index', ['page' => 'home'])->with('success', 'The Home search filters are fixed — open one to edit its labels and values.');
        }
        $used = SearchFilter::where('page', $page)->pluck('field')->all();

        return view('search-filters.form', $this->formData(new SearchFilter([
            'page' => $page, 'type' => 'select', 'options_source' => 'auto', 'status' => true,
        ]), $used));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data, $request) {
            $data['order_index'] = (int) SearchFilter::where('page', $data['page'])->max('order_index') + 1;
            $filter = SearchFilter::create($data);
            $this->syncOptions($filter, $request);
        });
        SearchFilterService::flush();

        return redirect()->route('cms.filters.index', ['page' => $data['page']])->with('success', 'Filter added to the ' . SearchFilter::PAGES[$data['page']] . ' page.');
    }

    public function edit(SearchFilter $filter)
    {
        $filter->load('options');
        $used = SearchFilter::where('page', $filter->page)->where('id', '!=', $filter->id)->pluck('field')->all();

        return view('search-filters.form', $this->formData($filter, $used));
    }

    public function update(Request $request, SearchFilter $filter)
    {
        $data = $this->validated($request, $filter);
        DB::transaction(function () use ($filter, $data, $request) {
            if ($data['page'] !== $filter->page) {
                $data['order_index'] = (int) SearchFilter::where('page', $data['page'])->max('order_index') + 1;
            }
            $filter->update($data);
            $this->syncOptions($filter, $request);
        });
        SearchFilterService::flush();

        return redirect()->route('cms.filters.index', ['page' => $filter->page])->with('success', 'Filter "' . $filter->label() . '" saved.');
    }

    public function destroy(SearchFilter $filter)
    {
        abort_if($filter->isFixed(), 403, 'Home search filters are fixed.');
        $page = $filter->page;
        $filter->delete();
        $this->renumber($page);
        SearchFilterService::flush();

        return response()->json(['success' => true]);
    }

    public function toggleStatus(SearchFilter $filter)
    {
        abort_if($filter->isFixed(), 403, 'Home search filters are fixed.');
        $filter->update(['status' => !$filter->status]);
        SearchFilterService::flush();

        return response()->json(['success' => true, 'status' => $filter->status]);
    }

    /** Drag-and-drop order: `ids` = the page's filter ids, top to bottom. */
    public function reorder(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:search_filters,id']);
        DB::transaction(function () use ($request) {
            foreach (array_values($request->input('ids')) as $i => $id) {
                SearchFilter::whereKey($id)->where('page', '!=', 'home')->update(['order_index' => $i + 1]);
            }
        });
        SearchFilterService::flush();

        return response()->json(['success' => true]);
    }

    /**
     * Copy one page's filters onto another. "replace" makes the target an exact copy;
     * "merge" only adds the filters the target doesn't have yet.
     */
    public function copy(Request $request)
    {
        $pages = array_keys(SearchFilter::listingPages());
        $data = $request->validate([
            'from' => ['required', Rule::in($pages)],
            'to' => ['required', Rule::in($pages), 'different:from'],
            'mode' => 'required|in:replace,merge',
        ]);

        $copied = DB::transaction(function () use ($data) {
            if ($data['mode'] === 'replace') {
                SearchFilter::where('page', $data['to'])->get()->each->delete();
            }
            $existing = SearchFilter::where('page', $data['to'])->pluck('field')->all();
            $order = (int) SearchFilter::where('page', $data['to'])->max('order_index');
            $copied = 0;

            foreach (SearchFilter::forPage($data['from'])->with('options')->get() as $source) {
                if (in_array($source->field, $existing, true)) continue;
                $copy = $source->replicate(['options_count']);
                $copy->page = $data['to'];
                $copy->order_index = ++$order;
                $copy->save();
                foreach ($source->options as $option) {
                    $copy->options()->create($option->only(['value', 'translations', 'order_index', 'status']));
                }
                $copied++;
            }

            return $copied;
        });
        SearchFilterService::flush();

        return redirect()->route('cms.filters.index', ['page' => $data['to']])
            ->with('success', $copied . ' filter' . ($copied === 1 ? '' : 's') . ' copied from ' . SearchFilter::PAGES[$data['from']] . '. Edit them here — the ' . SearchFilter::PAGES[$data['from']] . ' page is not affected.');
    }

    /** The database values a field's "auto" options would show on a page (form preview). */
    public function fieldOptions(Request $request)
    {
        $request->validate([
            'field' => ['required', Rule::in(array_keys(SearchFilterFields::all()))],
            'page' => ['nullable', Rule::in(array_keys(SearchFilter::PAGES))],
            'type' => ['nullable', Rule::in(array_keys(SearchFilter::TYPES))],
        ]);
        $options = $this->service->previewOptions($request->input('field'), $request->input('page', 'properties'), app()->getLocale(), $request->input('type', 'select'));

        return response()->json(['options' => $options->take(60)->values(), 'total' => $options->count()]);
    }

    // ------------------------------------------------------------------------------------------

    private function page(?string $page): string
    {
        return array_key_exists((string) $page, SearchFilter::PAGES) ? $page : 'properties';
    }

    private function formData(SearchFilter $filter, array $usedFields): array
    {
        return [
            'filter' => $filter,
            'languages' => Language::where('status', true)->get(),
            'groups' => SearchFilterFields::grouped(),
            'fields' => SearchFilterFields::all(),
            'usedFields' => $usedFields,
            // page => fields that already have a filter there (other than this one), for the page switch.
            'usedByPage' => SearchFilter::when($filter->exists, fn ($q) => $q->where('id', '!=', $filter->id))
                ->get(['page', 'field'])->groupBy('page')->map(fn ($rows) => $rows->pluck('field')->values()),
        ];
    }

    private function validated(Request $request, ?SearchFilter $filter = null): array
    {
        // A Home filter keeps its page, field and style; new filters go on listing pages only.
        if ($filter?->isFixed()) {
            $request->merge(['page' => $filter->page, 'field' => $filter->field, 'type' => $filter->type, 'status' => '1']);
        }
        $pages = $filter?->isFixed() ? ['home'] : array_keys(SearchFilter::listingPages());
        $request->validate([
            'page' => ['required', Rule::in($pages)],
            'field' => [
                'required', Rule::in(array_keys(SearchFilterFields::all())),
                Rule::unique('search_filters', 'field')->where('page', $request->input('page'))->ignore($filter?->id),
            ],
            'type' => ['required', Rule::in(array_keys(SearchFilter::TYPES))],
            'translations' => 'required|array',
            'translations.*.label' => 'nullable|string|max:120',
            'translations.*.placeholder' => 'nullable|string|max:120',
            'options_source' => 'nullable|in:auto,custom',
            'settings.min' => 'nullable|numeric|min:0',
            'settings.max' => 'nullable|numeric|min:0',
            'settings.step' => 'nullable|numeric|min:0',
            'settings.compare' => 'nullable|in:min,max,exact',
            'options' => 'nullable|array|max:200',
            'options.*.value' => 'nullable|string|max:100',
            'options.*.translations.*.label' => 'nullable|string|max:120',
        ], [
            'field.unique' => 'This page already has a filter for that field — edit the existing one instead.',
        ]);

        $field = $request->input('field');
        $type = $request->input('type');
        if (!SearchFilterFields::allows($field, $type)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'type' => '"' . (SearchFilter::TYPES[$type][0] ?? $type) . '" can\'t be used for ' . SearchFilterFields::get($field)['label'] . '.',
            ]);
        }

        $fallback = config('app.fallback_locale');
        $translations = collect($request->input('translations', []))
            ->map(fn ($t) => array_filter(['label' => trim((string) ($t['label'] ?? '')), 'placeholder' => trim((string) ($t['placeholder'] ?? ''))], 'strlen'))
            ->all();
        if (empty($translations[$fallback]['label'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(["translations.{$fallback}.label" => 'Enter the label visitors will see.']);
        }

        $isOptions = (new SearchFilter(['field' => $field, 'type' => $type]))->usesOptions();
        $source = $isOptions ? $request->input('options_source', 'auto') : 'auto';
        $rows = collect($request->input('options', []))->map(fn ($o) => trim((string) ($o['value'] ?? '')))->filter(fn ($v) => $v !== '');
        if ($isOptions && $source === 'custom') {
            if ($rows->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['options' => 'Add at least one option, or choose the built-in / database values.']);
            }
            // Built-in value sets (sort, features, amenity shortcuts) only take their own values.
            $fixed = array_keys(SearchFilterFields::get($field)['fixed'] ?? []);
            if ($fixed && ($bad = $rows->reject(fn ($v) => in_array($v, $fixed, true)))->isNotEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['options' => 'These values aren\'t supported here: ' . $bad->join(', ') . '. Use: ' . implode(', ', $fixed) . '.']);
            }
            // Price quick-select: "min-max" in AED, empty max = "and up" (e.g. 10000000-).
            if ($type === 'range' && ($bad = $rows->reject(fn ($v) => preg_match('/^\d+-\d*$/', $v)))->isNotEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['options' => 'Quick-select values are "min-max" in AED, e.g. 500000-1000000, or 10000000- for "10M and up". Not valid: ' . $bad->join(', ') . '.']);
            }
        }

        $settings = null;
        if (in_array($type, ['number', 'range'], true)) {
            $settings = array_filter([
                'min' => $request->input('settings.min'),
                'max' => $request->input('settings.max'),
                'step' => $request->input('settings.step'),
                'compare' => $type === 'number' ? $request->input('settings.compare', 'min') : null,
            ], fn ($v) => $v !== null && $v !== '');
            $settings = array_map(fn ($v) => is_numeric($v) ? $v + 0 : $v, $settings) ?: null;
        }

        return [
            'page' => $request->input('page'),
            'field' => $field,
            'type' => $type,
            'translations' => $translations,
            'options_source' => $source,
            'settings' => $settings,
            'status' => $request->boolean('status'),
        ];
    }

    /** Custom option rows from the form, in the order shown (blank rows are skipped). */
    private function syncOptions(SearchFilter $filter, Request $request): void
    {
        if ($filter->options_source !== 'custom' || !$filter->usesOptions()) {
            $filter->options()->delete();
            return;
        }

        $keep = [];
        $order = 0;
        foreach ((array) $request->input('options', []) as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            if ($value === '' || in_array($value, $keep, true)) continue;
            $keep[] = $value;
            $filter->options()->updateOrCreate(['value' => $value], [
                'translations' => collect($row['translations'] ?? [])->map(fn ($t) => ['label' => trim((string) ($t['label'] ?? ''))])->filter(fn ($t) => $t['label'] !== '')->all() ?: null,
                'order_index' => ++$order,
                'status' => !empty($row['status']),
            ]);
        }
        $filter->options()->whereNotIn('value', $keep)->delete();
    }

    private function renumber(string $page): void
    {
        SearchFilter::forPage($page)->get()->each(fn ($f, $i) => $f->order_index === $i + 1 ?: $f->update(['order_index' => $i + 1]));
    }
}
