<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Location;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use CMS\SiteManager\Support\ManagesOrderIndex;
use CMS\SiteManager\Support\ValidatesImageDimensions;

class LocationController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    protected function normalizeMultiValueInput(?string $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item) => trim($item),
            explode("\n", str_replace(["\r", ",", ";"], "\n", $value))
        )));
    }

    protected function getLocationItemValidationRules(bool $isUpdate = false, ?Location $location = null): array
    {
        $imageConfig = config('cms-kit.images.locations.main_image');
        $flagConfig = config('cms-kit.images.locations.flag');
        $itemConfig = config('cms-kit.database.locations.items', []);
        $requiredFields = $itemConfig['required'] ?? [];
        $languages = Language::where('status', true)->get();
        $rules = [
            'order_index' => 'nullable|integer|min:1',
        ];

        foreach ($languages as $lang) {
            foreach (['title', 'address', 'country'] as $field) {
                if (($itemConfig[$field] ?? true) && in_array($field, $requiredFields)) {
                    $rules["translations.{$lang->code}.{$field}"] = 'required';
                }
            }
        }

        foreach (['phone', 'whatsapp', 'fax', 'emails', 'map_link'] as $field) {
            if ($itemConfig[$field] ?? true) {
                $rules[$field] = in_array($field, $requiredFields) ? 'required' : 'nullable';
            }
        }

        if ($itemConfig['image'] ?? true) {
            $requiresImage = in_array('image', $requiredFields) && (!$isUpdate || !$location?->image || request()->boolean('remove_image'));
            $rules['image'] = ($requiresImage ? 'required' : 'nullable') . '|image|max:' . ($imageConfig['max_size'] ?? 2048);
            $rules['remove_image'] = 'nullable|boolean';
        }

        if ($itemConfig['flag'] ?? true) {
            $requiresFlag = in_array('flag', $requiredFields) && (!$isUpdate || !$location?->flag || request()->boolean('remove_flag'));
            $rules['flag'] = ($requiresFlag ? 'required' : 'nullable') . '|image|max:' . ($flagConfig['max_size'] ?? 1024);
            $rules['remove_flag'] = 'nullable|boolean';
        }

        return $rules;
    }

    protected function getLocationSectionValidationRules(): array
    {
        $languages = Language::where('status', true)->get();
        $sectionConfig = config('cms-kit.database.locations.section', []);
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

    protected function mergeLocationTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.locations.items.extra_fields', []);
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
        $fieldConfig = config('cms-kit.database.locations.section.extra_fields', []);
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
            $data = Location::orderBy('order_index', 'asc');
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
                ->addColumn('action', function ($row) use ($request) {
                    $btns = '<div class="btn-group">';
                    if (auth('cms')->user()->can('locations.edit')) {
                        $btns .= '<a href="' . route('cms.locations.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('locations.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'image', 'status', 'order', 'action'])
                ->make(true);
        }

        $section = SectionLabel::where('section_key', 'locations')->first();
        $languages = Language::where('status', true)->get();
        return view('cms-kit::locations.index', compact('section', 'languages'));
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $imageConfig = config('cms-kit.images.locations.main_image');
        $flagConfig = config('cms-kit.images.locations.flag');
        $nextOrder = Location::count() + 1;
        return view('cms-kit::locations.create', compact('languages', 'imageConfig', 'flagConfig', 'nextOrder'));
    }

    public function store(Request $request)
    {
        $request->validate($this->getLocationItemValidationRules());
        $this->validateImageWithinLimits($request, 'image', config('cms-kit.images.locations.main_image', []), 'Location image');
        $this->validateImageWithinLimits($request, 'flag', config('cms-kit.images.locations.flag', []), 'Flag image');

        $data = $request->except(['image', 'flag', 'status', 'emails', 'extra_fields']);
        $data['status'] = $request->has('status');
        $data['translations'] = $this->mergeLocationTranslatableExtraFields($request->input('translations', []));

        $data['phone'] = $this->normalizeMultiValueInput($request->input('phone'));
        
        // Handle Emails
        $data['emails'] = $this->normalizeMultiValueInput($request->input('emails'));

        // Handle Extra Fields
        $extra_fields = [];
        $locConfig = config('cms-kit.database.locations.items', []);
        foreach ($locConfig['extra_fields'] ?? [] as $key => $field) {
            $extra_fields[$key] = $request->input("extra_fields.{$key}");
        }
        $data['extra_fields'] = $extra_fields;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('locations', 'public');
        }


        if ($request->hasFile('flag')) {
            $data['flag'] = $request->file('flag')->store('locations/flags', 'public');
        }

        $order = $this->resolveOrderForCreate(Location::class, $request->order_index ? (int) $request->order_index : null);
        Location::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        Location::create($data);

        return redirect()->route('cms.locations.index')->with('success', 'Location added successfully.');
    }

    public function edit($id)
    {
        $location = Location::findOrFail($id);
        $languages = Language::where('status', true)->get();
        $imageConfig = config('cms-kit.images.locations.main_image');
        $flagConfig = config('cms-kit.images.locations.flag');
        return view('cms-kit::locations.edit', compact('location', 'languages', 'imageConfig', 'flagConfig'));
    }

    public function update(Request $request, $id)
    {
        $location = Location::findOrFail($id);
        $request->validate($this->getLocationItemValidationRules(true, $location));
        $this->validateImageWithinLimits($request, 'image', config('cms-kit.images.locations.main_image', []), 'Location image');
        $this->validateImageWithinLimits($request, 'flag', config('cms-kit.images.locations.flag', []), 'Flag image');

        $data = $request->except(['image', 'flag', 'status', 'emails', 'extra_fields']);
        $data['status'] = $request->has('status');
        $data['translations'] = $this->mergeLocationTranslatableExtraFields($request->input('translations', []));

        $data['phone'] = $this->normalizeMultiValueInput($request->input('phone'));

        $data['emails'] = $this->normalizeMultiValueInput($request->input('emails'));

        // Handle Extra Fields
        $extra_fields = [];
        $locConfig = config('cms-kit.database.locations.items', []);
        foreach ($locConfig['extra_fields'] ?? [] as $key => $field) {
            $extra_fields[$key] = $request->input("extra_fields.{$key}");
        }
        $data['extra_fields'] = $extra_fields;


        if ($request->hasFile('image')) {
            if ($location->image) Storage::disk('public')->delete($location->image);
            $data['image'] = $request->file('image')->store('locations', 'public');
        } elseif ($request->boolean('remove_image') && $location->image) {
            Storage::disk('public')->delete($location->image);
            $data['image'] = null;
        }

        if ($request->hasFile('flag')) {
            if ($location->flag) Storage::disk('public')->delete($location->flag);
            $data['flag'] = $request->file('flag')->store('locations/flags', 'public');
        } elseif ($request->boolean('remove_flag') && $location->flag) {
            Storage::disk('public')->delete($location->flag);
            $data['flag'] = null;
        }

        $data['image_alt'] = $request->boolean('remove_image') ? null : $request->input('image_alt');
        $data['flag_alt'] = $request->boolean('remove_flag') ? null : $request->input('flag_alt');

        $location->update($data);

        return redirect()->route('cms.locations.index')->with('success', 'Location updated successfully.');
    }

    public function destroy($id)
    {
        $location = Location::findOrFail($id);
        $order = $location->order_index;
        if ($location->image) Storage::disk('public')->delete($location->image);
        if ($location->flag) Storage::disk('public')->delete($location->flag);
        $location->delete();

        Location::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(Location::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $location = Location::findOrFail($id);
        $location->status = !$location->status;
        $location->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:locations,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $location = Location::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(Location::class, (int) $request->order_index);
        $oldOrder = $location->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                Location::where('order_index', '>', $oldOrder)
                    ->where('order_index', '<=', $newOrder)
                    ->decrement('order_index');
            } else {
                Location::where('order_index', '>=', $newOrder)
                    ->where('order_index', '<', $oldOrder)
                    ->increment('order_index');
            }
            $location->order_index = $newOrder;
            $location->save();
        }
        $this->normalizeOrderIndex(Location::class);

        return response()->json(['success' => true]);
    }

    public function updateSection(Request $request)
    {
        $sectionConfig = config('cms-kit.database.locations.section', []);
        $request->validate($this->getLocationSectionValidationRules());

        $translations = $this->mergeSectionTranslatableExtraFields($request->input('translations', []));
        
        $extra_fields = [];
        foreach ($sectionConfig['extra_fields'] ?? [] as $key => $field) {
            $extra_fields[$key] = $request->input("extra_fields.{$key}");
        }
        // Preserve status if not in extra_fields but in request
        if ($request->has('status')) {
            $extra_fields['status'] = $request->has('status');
        }

        SectionLabel::updateOrCreate(
            ['section_key' => 'locations'],
            [
                'translations' => $translations,
                'extra_fields' => $extra_fields,
            ]
        );

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
            $locations = Location::whereIn('id', $ids)->get();
            foreach ($locations as $loc) {
                if ($loc->image) Storage::disk('public')->delete($loc->image);
                if ($loc->flag) Storage::disk('public')->delete($loc->flag);
                $loc->delete();
            }
            $this->normalizeOrderIndex(Location::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            Location::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            Location::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}


