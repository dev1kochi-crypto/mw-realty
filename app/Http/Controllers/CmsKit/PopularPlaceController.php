<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\PopularPlace;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use App\Support\ManagesOrderIndex;
use App\Support\ValidatesImageDimensions;

class PopularPlaceController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = PopularPlace::orderBy('order_index', 'asc');
            return \App\Support\TranslatedTable::column(DataTables::of($data), 'name', 'name')
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
                })
                ->addColumn('name', function ($row) {
                    return $row->getTranslation('name');
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
                ->orderColumn('order', fn ($query, $direction) => $query->reorder()->orderBy('order_index', $direction))
                ->addColumn('order', function ($row) {
                    return '<input type="number" min="1" class="form-control form-control-sm reorder-input" data-id="' . $row->id . '" value="' . $row->order_index . '" style="width: 80px;">';
                })
                ->addColumn('action', function ($row) {
                    $btns = '<div class="btn-group">';
                    if (auth('cms')->user()->can('popular-places.edit')) {
                        $btns .= '<a href="' . route('cms.popular-places.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('popular-places.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'image', 'status', 'order', 'action'])
                ->make(true);
        }

        $section = SectionLabel::where('section_key', 'popular-places')->first();
        $languages = Language::where('status', true)->get();
        return view('cms-kit::popular-places.index', compact('section', 'languages'));
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $imageConfig = config('cms-kit.images.popular-places.item_image');
        $nextOrder = PopularPlace::count() + 1;
        return view('cms-kit::popular-places.create', compact('languages', 'imageConfig', 'nextOrder'));
    }

    protected function getItemValidationRules(bool $isUpdate = false, ?PopularPlace $place = null): array
    {
        $imageConfig = config('cms-kit.images.popular-places.item_image');
        $itemConfig = config('cms-kit.database.popular-places.items', []);
        $requiredFields = $itemConfig['required'] ?? [];
        $languages = Language::where('status', true)->get();
        $rules = [
            'order_index' => 'nullable|integer|min:1',
        ];

        foreach ($languages as $lang) {
            if (($itemConfig['name'] ?? true) && in_array('name', $requiredFields)) {
                $rules["translations.{$lang->code}.name"] = 'required';
            }
        }

        if ($itemConfig['image'] ?? true) {
            $requiresImage = in_array('image', $requiredFields) && (!$isUpdate || !$place?->image || request()->boolean('remove_image'));
            $rules['image'] = ($requiresImage ? 'required' : 'nullable') . '|image|max:' . ($imageConfig['max_size'] ?? 2048);
            $rules['remove_image'] = 'nullable|boolean';
        }

        return $rules;
    }

    public function store(Request $request)
    {
        $request->validate($this->getItemValidationRules());
        $imageConfig = config('cms-kit.images.popular-places.item_image', []);
        $this->validateImageWithinLimits($request, 'image', $imageConfig, 'Place image');

        $data = $request->except(['image', 'status']);
        $data['status'] = $request->has('status');
        $data['translations'] = $request->input('translations', []);

        if ($request->hasFile('image')) {
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'popular-places');
        }

        $order = $this->resolveOrderForCreate(PopularPlace::class, $request->order_index ? (int) $request->order_index : null);
        PopularPlace::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        PopularPlace::create($data);

        return redirect()->route('cms.popular-places.index')->with('success', 'Place added successfully.');
    }

    public function edit($id)
    {
        $place = PopularPlace::findOrFail($id);
        $languages = Language::where('status', true)->get();
        $imageConfig = config('cms-kit.images.popular-places.item_image');
        return view('cms-kit::popular-places.edit', compact('place', 'languages', 'imageConfig'));
    }

    public function update(Request $request, $id)
    {
        $place = PopularPlace::findOrFail($id);
        $request->validate($this->getItemValidationRules(true, $place));
        $imageConfig = config('cms-kit.images.popular-places.item_image', []);
        $this->validateImageWithinLimits($request, 'image', $imageConfig, 'Place image');

        $data = $request->except(['image', 'status']);
        $data['status'] = $request->has('status');
        $data['translations'] = $request->input('translations', []);

        if ($request->hasFile('image')) {
            if ($place->image) {
                app(\App\Services\ManagedFiles::class)->delete($place->image);
            }
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'popular-places');
        } elseif ($request->boolean('remove_image') && $place->image) {
            app(\App\Services\ManagedFiles::class)->delete($place->image);
            $data['image'] = null;
        }

        $data['image_alt'] = $request->boolean('remove_image') ? null : $request->input('image_alt');

        $place->update($data);

        return redirect()->route('cms.popular-places.index')->with('success', 'Place updated successfully.');
    }

    public function destroy($id)
    {
        $place = PopularPlace::findOrFail($id);
        $order = $place->order_index;
        if ($place->image) {
            app(\App\Services\ManagedFiles::class)->delete($place->image);
        }
        $place->delete();

        PopularPlace::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(PopularPlace::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $place = PopularPlace::findOrFail($id);
        $place->status = !$place->status;
        $place->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:popular_places,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $place = PopularPlace::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(PopularPlace::class, (int) $request->order_index);
        $oldOrder = $place->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                PopularPlace::where('order_index', '>', $oldOrder)
                    ->where('order_index', '<=', $newOrder)
                    ->decrement('order_index');
            } else {
                PopularPlace::where('order_index', '>=', $newOrder)
                    ->where('order_index', '<', $oldOrder)
                    ->increment('order_index');
            }
            $place->order_index = $newOrder;
            $place->save();
        }
        $this->normalizeOrderIndex(PopularPlace::class);

        return response()->json(['success' => true]);
    }

    public function updateSection(Request $request)
    {
        $languages = Language::where('status', true)->get();
        $sectionConfig = config('cms-kit.database.popular-places.section', []);
        $requiredFields = $sectionConfig['required'] ?? [];

        $rules = [];
        foreach ($languages as $lang) {
            if (($sectionConfig['title'] ?? true) && in_array('title', $requiredFields)) {
                $rules["translations.{$lang->code}.title"] = 'required';
            }
        }
        $request->validate($rules);

        SectionLabel::updateOrCreate(
            ['section_key' => 'popular-places'],
            [
                'translations' => $request->input('translations', []),
                'status' => $request->has('status'),
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
            $places = PopularPlace::whereIn('id', $ids)->get();
            foreach ($places as $place) {
                if ($place->image) {
                    app(\App\Services\ManagedFiles::class)->delete($place->image);
                }
                $place->delete();
            }
            $this->normalizeOrderIndex(PopularPlace::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            PopularPlace::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            PopularPlace::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}
