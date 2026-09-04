<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\CareerDepartment;
use App\Models\CmsKit\Language;
use CMS\SiteManager\Support\ManagesOrderIndex;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CareerDepartmentController extends Controller
{
    use ManagesOrderIndex;

    protected function activeLanguages()
    {
        return Language::where('status', true)->get();
    }

    protected function hasMeaningfulText($value): bool
    {
        return is_string($value) && trim(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) !== '';
    }

    protected function textRule(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            function ($attribute, $value, $fail) use ($required) {
                if (($required || ($value !== null && $value !== '')) && !$this->hasMeaningfulText($value)) {
                    $fail('Invalid input');
                }
            },
        ];
    }

    protected function validationRules(): array
    {
        $departmentConfig = config('cms-kit.database.careers.departments', []);
        $rules = [
            'order_index' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'boolean'],
        ];

        if ($departmentConfig['stats'] ?? false) {
            $rules['stats_text'] = ['nullable', 'string'];
        }

        foreach ($this->activeLanguages() as $lang) {
            if ($departmentConfig['title'] ?? true) {
                $rules["translations.{$lang->code}.title"] = $this->textRule(true);
            }
            if ($departmentConfig['description'] ?? true) {
                $rules["translations.{$lang->code}.description"] = $this->textRule(false);
            }
        }

        return $rules;
    }

    protected function validationMessages(): array
    {
        return [
            'translations.*.title.required' => 'Title is required',
            'order_index.integer' => 'Invalid input',
            'order_index.min' => 'Invalid input',
        ];
    }

    protected function normalizeTranslations(array $translations): array
    {
        $normalized = [];

        foreach ($translations as $lang => $values) {
            $normalized[$lang] = [
                'title' => (config('cms-kit.database.careers.departments.title', true) && isset($values['title'])) ? trim((string) $values['title']) : null,
                'description' => (config('cms-kit.database.careers.departments.description', true) && isset($values['description'])) ? trim((string) $values['description']) : null,
                'extra_fields' => (array) data_get($values, 'extra_fields', []),
            ];
        }

        return $normalized;
    }

    protected function mergeTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.careers.departments.extra_fields', []);
        $translatableFields = collect($fieldConfig)->filter(fn ($field) => $field['translatable'] ?? false)->keys();

        foreach ($translations as $lang => $values) {
            $translations[$lang]['extra_fields'] = [];
            foreach ($translatableFields as $fieldName) {
                $translations[$lang]['extra_fields'][$fieldName] = data_get($values, "extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    protected function normalizeStats(?string $stats): array
    {
        if (!(config('cms-kit.database.careers.departments.stats', false))) {
            return [];
        }

        if (!is_string($stats) || trim($stats) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item) => trim($item),
            preg_split('/\r\n|\r|\n/', $stats) ?: []
        )));
    }

    public function index(Request $request)
    {
        $columns = config('cms-kit.database.careers.departments.columns', []);

        if ($request->ajax()) {
            $data = CareerDepartment::query()->ordered();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('select_all', fn ($row) => '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">')
                ->addColumn('title', fn ($row) => e($row->getTranslation('title')))
                ->addColumn('description', function ($row) {
                    return e(Str::limit(strip_tags((string) $row->getTranslation('description')), 90));
                })
                ->addColumn('status', function ($row) {
                    $checked = $row->status ? 'checked' : '';

                    return '<div class="form-check form-switch"><input class="form-check-input toggle-status" type="checkbox" data-id="' . $row->id . '" ' . $checked . '></div>';
                })
                ->addColumn('order', fn ($row) => '<input type="number" min="1" class="form-control form-control-sm reorder-input" data-id="' . $row->id . '" value="' . $row->order_index . '" style="width: 80px;">')
                ->addColumn('action', function ($row) {
                    $buttons = '<div class="btn-group">';

                    if (auth('cms')->user()?->can('careers.edit')) {
                        $buttons .= '<a href="' . route('cms.careers.departments.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }

                    if (auth('cms')->user()?->can('careers.delete')) {
                        $buttons .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }

                    return $buttons . '</div>';
                })
                ->rawColumns(['select_all', 'status', 'order', 'action'])
                ->make(true);
        }

        return view('cms-kit::careers.departments.index', compact('columns'));
    }

    public function create()
    {
        $languages = $this->activeLanguages();
        $nextOrder = CareerDepartment::count() + 1;

        return view('cms-kit::careers.departments.create', compact('languages', 'nextOrder'));
    }

    public function store(Request $request)
    {
        $request->validate($this->validationRules(), $this->validationMessages());

        $order = $this->resolveOrderForCreate(CareerDepartment::class, $request->filled('order_index') ? (int) $request->order_index : null);
        CareerDepartment::where('order_index', '>=', $order)->increment('order_index');

        $extraFields = [];
        foreach (config('cms-kit.database.careers.departments.extra_fields', []) as $key => $field) {
            if ($field['translatable'] ?? false) {
                continue;
            }

            $extraFields[$key] = $request->input("extra_fields.{$key}");
        }

        CareerDepartment::create([
            'translations' => $this->mergeTranslatableExtraFields($this->normalizeTranslations($request->input('translations', []))),
            'stats' => $this->normalizeStats($request->input('stats_text')),
            'extra_fields' => $extraFields,
            'order_index' => $order,
            'status' => $request->boolean('status', true),
        ]);

        return redirect()->route('cms.careers.departments.index')->with('success', 'Department created successfully.');
    }

    public function edit($id)
    {
        $department = CareerDepartment::findOrFail($id);
        $languages = $this->activeLanguages();

        return view('cms-kit::careers.departments.edit', compact('department', 'languages'));
    }

    public function update(Request $request, $id)
    {
        $department = CareerDepartment::findOrFail($id);
        $request->validate($this->validationRules(), $this->validationMessages());

        $newOrder = $request->filled('order_index') ? (int) $request->order_index : $department->order_index;
        $newOrder = $this->resolveOrderForReorder(CareerDepartment::class, $newOrder);
        $oldOrder = $department->order_index;

        if ($newOrder !== $oldOrder) {
            if ($newOrder > $oldOrder) {
                CareerDepartment::where('order_index', '>', $oldOrder)
                    ->where('order_index', '<=', $newOrder)
                    ->decrement('order_index');
            } else {
                CareerDepartment::where('order_index', '>=', $newOrder)
                    ->where('order_index', '<', $oldOrder)
                    ->increment('order_index');
            }
        }

        $extraFields = [];
        foreach (config('cms-kit.database.careers.departments.extra_fields', []) as $key => $field) {
            if ($field['translatable'] ?? false) {
                continue;
            }

            $extraFields[$key] = $request->input("extra_fields.{$key}");
        }

        $department->update([
            'translations' => $this->mergeTranslatableExtraFields($this->normalizeTranslations($request->input('translations', []))),
            'stats' => $this->normalizeStats($request->input('stats_text')),
            'extra_fields' => $extraFields,
            'order_index' => $newOrder,
            'status' => $request->has('status') ? $request->boolean('status') : $department->status,
        ]);

        $this->normalizeOrderIndex(CareerDepartment::class);

        return redirect()->route('cms.careers.departments.index')->with('success', 'Department updated successfully.');
    }

    public function destroy($id)
    {
        $department = CareerDepartment::findOrFail($id);
        $order = $department->order_index;
        $department->delete();

        CareerDepartment::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(CareerDepartment::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $department = CareerDepartment::findOrFail($id);
        $department->update(['status' => !$department->status]);

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer', Rule::exists('career_departments', 'id')],
            'order_index' => ['required', 'integer', 'min:1'],
        ]);

        $department = CareerDepartment::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(CareerDepartment::class, (int) $request->order_index);
        $oldOrder = $department->order_index;

        if ($newOrder !== $oldOrder) {
            if ($newOrder > $oldOrder) {
                CareerDepartment::where('order_index', '>', $oldOrder)
                    ->where('order_index', '<=', $newOrder)
                    ->decrement('order_index');
            } else {
                CareerDepartment::where('order_index', '>=', $newOrder)
                    ->where('order_index', '<', $oldOrder)
                    ->increment('order_index');
            }

            $department->update(['order_index' => $newOrder]);
        }

        $this->normalizeOrderIndex(CareerDepartment::class);

        return response()->json(['success' => true]);
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
            CareerDepartment::whereIn('id', $ids)->delete();
            $this->normalizeOrderIndex(CareerDepartment::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            CareerDepartment::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            CareerDepartment::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}
