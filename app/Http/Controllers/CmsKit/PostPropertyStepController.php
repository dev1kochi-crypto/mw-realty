<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\PostPropertyStep;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use CMS\SiteManager\Support\ManagesOrderIndex;
use CMS\SiteManager\Support\ValidatesImageDimensions;

class PostPropertyStepController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    protected function mergeTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.post-property-steps.items.extra_fields', []);
        $translatableFields = collect($fieldConfig)->filter(fn ($field) => $field['translatable'] ?? false)->keys();

        foreach ($translations as $lang => $values) {
            $translations[$lang]['extra_fields'] = [];
            foreach ($translatableFields as $fieldName) {
                $translations[$lang]['extra_fields'][$fieldName] = data_get($values, "extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    protected function mergeSectionTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.post-property-steps.section.extra_fields', []);
        $translatableFields = collect($fieldConfig)->filter(fn ($field) => $field['translatable'] ?? false)->keys();

        foreach ($translations as $lang => $values) {
            $translations[$lang]['extra_fields'] = [];
            foreach ($translatableFields as $fieldName) {
                $translations[$lang]['extra_fields'][$fieldName] = data_get($values, "extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = PostPropertyStep::orderBy('order_index', 'asc');
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
                })
                ->addColumn('title', function ($row) {
                    return $row->getTranslation('title');
                })
                ->addColumn('image', function ($row) {
                    if ($row->image) {
                        return '<img src="' . asset('storage/' . $row->image) . '" class="img-thumbnail" style="height: 40px;">';
                    }
                    return '-';
                })
                ->addColumn('status', function ($row) {
                    $checked = $row->status ? 'checked' : '';
                    return '<div class="form-check form-switch">
                                <input class="form-check-input toggle-status" type="checkbox" data-id="' . $row->id . '" ' . $checked . '>
                            </div>';
                })
                ->addColumn('order', function ($row) {
                    return '<input type="number" min="1" class="form-control form-control-sm reorder-input" data-id="' . $row->id . '" value="' . $row->order_index . '" style="width: 80px;">';
                })
                ->addColumn('action', function ($row) {
                    $btns = '<div class="btn-group">';
                    if (auth('cms')->user()->can('post-property-steps.edit')) {
                        $btns .= '<a href="' . route('cms.post-property-steps.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('post-property-steps.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'image', 'status', 'order', 'action'])
                ->make(true);
        }

        $section = SectionLabel::where('section_key', 'post-property-steps')->first();
        $languages = Language::where('status', true)->get();
        return view('cms-kit::post-property-steps.index', compact('section', 'languages'));
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $iconConfig = config('cms-kit.images.post-property-steps.item_icon');
        $nextOrder = PostPropertyStep::count() + 1;
        return view('cms-kit::post-property-steps.create', compact('languages', 'iconConfig', 'nextOrder'));
    }

    protected function getItemValidationRules(bool $isUpdate = false, ?PostPropertyStep $step = null): array
    {
        $iconConfig = config('cms-kit.images.post-property-steps.item_icon');
        $itemConfig = config('cms-kit.database.post-property-steps.items', []);
        $requiredFields = $itemConfig['required'] ?? [];
        $languages = Language::where('status', true)->get();
        $rules = [
            'order_index' => 'nullable|integer|min:1',
        ];

        foreach ($languages as $lang) {
            foreach (['title', 'description'] as $field) {
                if (($itemConfig[$field] ?? true) && in_array($field, $requiredFields)) {
                    $rules["translations.{$lang->code}.{$field}"] = 'required';
                }
            }
        }

        if ($itemConfig['image'] ?? true) {
            $requiresImage = in_array('image', $requiredFields) && (!$isUpdate || !$step?->image || request()->boolean('remove_image'));
            $rules['image'] = ($requiresImage ? 'required' : 'nullable') . '|image|max:' . ($iconConfig['max_size'] ?? 512);
            $rules['remove_image'] = 'nullable|boolean';
        }

        return $rules;
    }

    protected function getSectionValidationRules(): array
    {
        $languages = Language::where('status', true)->get();
        $sectionConfig = config('cms-kit.database.post-property-steps.section', []);
        $requiredFields = $sectionConfig['required'] ?? [];
        $rules = [];

        foreach ($languages as $lang) {
            foreach (['title', 'description'] as $field) {
                if (($sectionConfig[$field] ?? true) && in_array($field, $requiredFields)) {
                    $rules["translations.{$lang->code}.{$field}"] = 'required';
                }
            }
        }

        return $rules;
    }

    public function store(Request $request)
    {
        $request->validate($this->getItemValidationRules());
        $iconConfig = config('cms-kit.images.post-property-steps.item_icon', []);
        $this->validateImageWithinLimits($request, 'image', $iconConfig, 'Step icon');

        $data = $request->except(['image', 'status', 'extra_fields']);
        $data['status'] = $request->has('status');
        $data['translations'] = $this->mergeTranslatableExtraFields($request->input('translations', []));

        $extraFields = [];
        $stepConfig = config('cms-kit.database.post-property-steps.items', []);
        foreach ($stepConfig['extra_fields'] ?? [] as $key => $field) {
            $extraFields[$key] = $request->input("extra_fields.{$key}");
        }
        $data['extra_fields'] = $extraFields;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('post-property-steps', 'public');
        }

        $order = $this->resolveOrderForCreate(PostPropertyStep::class, $request->order_index ? (int) $request->order_index : null);
        PostPropertyStep::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        PostPropertyStep::create($data);

        return redirect()->route('cms.post-property-steps.index')->with('success', 'Step added successfully.');
    }

    public function edit($id)
    {
        $step = PostPropertyStep::findOrFail($id);
        $languages = Language::where('status', true)->get();
        $iconConfig = config('cms-kit.images.post-property-steps.item_icon');
        return view('cms-kit::post-property-steps.edit', compact('step', 'languages', 'iconConfig'));
    }

    public function update(Request $request, $id)
    {
        $step = PostPropertyStep::findOrFail($id);
        $request->validate($this->getItemValidationRules(true, $step));
        $iconConfig = config('cms-kit.images.post-property-steps.item_icon', []);
        $this->validateImageWithinLimits($request, 'image', $iconConfig, 'Step icon');

        $data = $request->except(['image', 'status', 'extra_fields']);
        $data['status'] = $request->has('status');
        $data['translations'] = $this->mergeTranslatableExtraFields($request->input('translations', []));

        $extraFields = [];
        $stepConfig = config('cms-kit.database.post-property-steps.items', []);
        foreach ($stepConfig['extra_fields'] ?? [] as $key => $field) {
            $extraFields[$key] = $request->input("extra_fields.{$key}");
        }
        $data['extra_fields'] = $extraFields;

        if ($request->hasFile('image')) {
            if ($step->image) {
                Storage::disk('public')->delete($step->image);
            }
            $data['image'] = $request->file('image')->store('post-property-steps', 'public');
        } elseif ($request->boolean('remove_image') && $step->image) {
            Storage::disk('public')->delete($step->image);
            $data['image'] = null;
        }

        $data['image_alt'] = $request->boolean('remove_image') ? null : $request->input('image_alt');

        $step->update($data);

        return redirect()->route('cms.post-property-steps.index')->with('success', 'Step updated successfully.');
    }

    public function destroy($id)
    {
        $step = PostPropertyStep::findOrFail($id);
        $order = $step->order_index;
        if ($step->image) {
            Storage::disk('public')->delete($step->image);
        }
        $step->delete();

        PostPropertyStep::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(PostPropertyStep::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $step = PostPropertyStep::findOrFail($id);
        $step->status = !$step->status;
        $step->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:post_property_steps,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $step = PostPropertyStep::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(PostPropertyStep::class, (int) $request->order_index);
        $oldOrder = $step->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                PostPropertyStep::where('order_index', '>', $oldOrder)
                    ->where('order_index', '<=', $newOrder)
                    ->decrement('order_index');
            } else {
                PostPropertyStep::where('order_index', '>=', $newOrder)
                    ->where('order_index', '<', $oldOrder)
                    ->increment('order_index');
            }
            $step->order_index = $newOrder;
            $step->save();
        }
        $this->normalizeOrderIndex(PostPropertyStep::class);

        return response()->json(['success' => true]);
    }

    public function updateSection(Request $request)
    {
        $sectionConfig = config('cms-kit.database.post-property-steps.section', []);
        $imageConfig = config('cms-kit.images.post-property-steps.section_image', []);
        $request->validate($this->getSectionValidationRules());
        $this->validateImageWithinLimits($request, 'section_image', $imageConfig, 'Section image');

        $section = SectionLabel::where('section_key', 'post-property-steps')->first();

        $translations = $this->mergeSectionTranslatableExtraFields($request->input('translations', []));

        $extraFields = [];
        foreach ($sectionConfig['extra_fields'] ?? [] as $key => $field) {
            $extraFields[$key] = $request->input("extra_fields.{$key}");
        }

        $data = [
            'translations' => $translations,
            'extra_fields' => $extraFields,
            'status' => $request->has('status'),
        ];

        if ($request->hasFile('section_image')) {
            if ($section?->section_image) {
                Storage::disk('public')->delete($section->section_image);
            }
            $data['section_image'] = $request->file('section_image')->store('post-property-steps', 'public');
        } elseif ($request->boolean('remove_section_image') && $section?->section_image) {
            Storage::disk('public')->delete($section->section_image);
            $data['section_image'] = null;
        }

        if ($request->hasFile('section_image') || $request->boolean('remove_section_image')) {
            $data['section_image_alt'] = $request->boolean('remove_section_image') ? null : $request->input('section_image_alt');
        } elseif ($request->filled('section_image_alt')) {
            $data['section_image_alt'] = $request->input('section_image_alt');
        }

        SectionLabel::updateOrCreate(['section_key' => 'post-property-steps'], $data);

        return redirect()->back()->with('success', 'Section settings updated.');
    }

    public function bulkAction(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));
        $action = $request->input('action');

        if (empty($ids) || !$action) {
            return response()->json(['success' => false, 'message' => 'No action or items selected.'], 422);
        }

        if ($action === 'delete') {
            $steps = PostPropertyStep::whereIn('id', $ids)->get();
            foreach ($steps as $step) {
                if ($step->image) {
                    Storage::disk('public')->delete($step->image);
                }
                $step->delete();
            }
            $this->normalizeOrderIndex(PostPropertyStep::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            PostPropertyStep::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            PostPropertyStep::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}
