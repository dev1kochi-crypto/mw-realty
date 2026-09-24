<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\FindPropertyItem;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use App\Models\Filter;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use App\Support\ManagesOrderIndex;
use App\Support\ValidatesImageDimensions;

class FindPropertyController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    /**
     * Property types are picked from the same "property_type" filter used by the
     * property search filter bar, so a card here always points at a real, filterable type.
     */
    protected function propertyTypeFilter()
    {
        return Filter::where('key', 'property_type')->where('type', 'select')->with('activeValues')->first();
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $counts = \App\Models\Property::active()->selectRaw('property_type, COUNT(*) as total')->groupBy('property_type')->pluck('total', 'property_type');
            $data = FindPropertyItem::orderBy('order_index', 'asc');
            return \App\Support\TranslatedTable::column(DataTables::of($data), 'title', 'title')
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
                })
                ->addColumn('title', function ($row) {
                    return $row->getTranslation('title');
                })
                ->addColumn('image', function ($row) {
                    if ($row->image) {
                        return '<img src="' . media_url($row->image) . '" class="img-thumbnail" style="height: 40px;">';
                    }
                    return '-';
                })
                ->addColumn('property_type', function ($row) {
                    return $row->property_type ?: '-';
                })
                ->addColumn('property_count', function ($row) use ($counts) {
                    return ($counts[$row->property_type] ?? 0) . ' properties';
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
                    if (auth('cms')->user()->can('find-properties.edit')) {
                        $btns .= '<a href="' . route('cms.find-properties.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('find-properties.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'image', 'status', 'order', 'action'])
                ->make(true);
        }

        $section = SectionLabel::where('section_key', 'find-properties')->first();
        $languages = Language::where('status', true)->get();
        return view('cms-kit::find-properties.index', compact('section', 'languages'));
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $iconConfig = config('cms-kit.images.find-properties.item_image');
        $propertyTypeFilter = $this->propertyTypeFilter();
        $nextOrder = FindPropertyItem::count() + 1;
        return view('cms-kit::find-properties.create', compact('languages', 'iconConfig', 'propertyTypeFilter', 'nextOrder'));
    }

    protected function itemRules(bool $isUpdate = false, ?FindPropertyItem $item = null): array
    {
        $iconConfig = config('cms-kit.images.find-properties.item_image', []);
        $languages = Language::where('status', true)->get();
        $rules = ['order_index' => 'nullable|integer|min:1'];

        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.title"] = 'required';
        }

        $requiresImage = !$isUpdate || !$item?->image || request()->boolean('remove_image');
        $rules['image'] = ($requiresImage ? 'required' : 'nullable') . '|image|max:' . ($iconConfig['max_size'] ?? 512);
        $rules['remove_image'] = 'nullable|boolean';

        return $rules;
    }

    public function store(Request $request)
    {
        $request->validate($this->itemRules());
        $iconConfig = config('cms-kit.images.find-properties.item_image', []);
        $this->validateImageWithinLimits($request, 'image', $iconConfig, 'Image');

        $data = $request->only(['order_index', 'translations', 'property_type']);
        $data['status'] = $request->has('status');

        if ($request->hasFile('image')) {
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'find-properties');
        }
        $data['image_alt'] = $request->input('image_alt');

        $order = $this->resolveOrderForCreate(FindPropertyItem::class, $request->order_index ? (int) $request->order_index : null);
        FindPropertyItem::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        FindPropertyItem::create($data);

        return redirect()->route('cms.find-properties.index')->with('success', 'Item added successfully.');
    }

    public function edit($id)
    {
        $item = FindPropertyItem::findOrFail($id);
        $languages = Language::where('status', true)->get();
        $iconConfig = config('cms-kit.images.find-properties.item_image');
        $propertyTypeFilter = $this->propertyTypeFilter();
        return view('cms-kit::find-properties.edit', compact('item', 'languages', 'iconConfig', 'propertyTypeFilter'));
    }

    public function update(Request $request, $id)
    {
        $item = FindPropertyItem::findOrFail($id);
        $request->validate($this->itemRules(true, $item));
        $iconConfig = config('cms-kit.images.find-properties.item_image', []);
        $this->validateImageWithinLimits($request, 'image', $iconConfig, 'Image');

        $data = $request->only(['order_index', 'translations', 'property_type']);
        $data['status'] = $request->has('status');

        if ($request->hasFile('image')) {
            if ($item->image) {
                app(\App\Services\ManagedFiles::class)->delete($item->image);
            }
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'find-properties');
        } elseif ($request->boolean('remove_image') && $item->image) {
            app(\App\Services\ManagedFiles::class)->delete($item->image);
            $data['image'] = null;
        }

        $data['image_alt'] = $request->boolean('remove_image') ? null : $request->input('image_alt');

        $item->update($data);

        return redirect()->route('cms.find-properties.index')->with('success', 'Item updated successfully.');
    }

    public function destroy($id)
    {
        $item = FindPropertyItem::findOrFail($id);
        $order = $item->order_index;
        if ($item->image) {
            app(\App\Services\ManagedFiles::class)->delete($item->image);
        }
        $item->delete();

        FindPropertyItem::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(FindPropertyItem::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $item = FindPropertyItem::findOrFail($id);
        $item->status = !$item->status;
        $item->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:find_property_items,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $item = FindPropertyItem::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(FindPropertyItem::class, (int) $request->order_index);
        $oldOrder = $item->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                FindPropertyItem::where('order_index', '>', $oldOrder)->where('order_index', '<=', $newOrder)->decrement('order_index');
            } else {
                FindPropertyItem::where('order_index', '>=', $newOrder)->where('order_index', '<', $oldOrder)->increment('order_index');
            }
            $item->order_index = $newOrder;
            $item->save();
        }
        $this->normalizeOrderIndex(FindPropertyItem::class);

        return response()->json(['success' => true]);
    }

    public function updateSection(Request $request)
    {
        $languages = Language::where('status', true)->get();
        $rules = [];
        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.title"] = 'required';
        }
        $request->validate($rules);

        SectionLabel::updateOrCreate(
            ['section_key' => 'find-properties'],
            [
                'translations' => $request->input('translations', []),
                'status' => $request->has('status'),
            ]
        );

        return redirect()->route('cms.find-properties.index')->with('success', 'Section settings updated.');
    }

    public function bulkAction(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));
        $action = $request->input('action');

        if (empty($ids) || !$action) {
            return response()->json(['success' => false, 'message' => 'No action or items selected.'], 422);
        }

        if ($action === 'delete') {
            $items = FindPropertyItem::whereIn('id', $ids)->get();
            foreach ($items as $item) {
                if ($item->image) {
                    app(\App\Services\ManagedFiles::class)->delete($item->image);
                }
                $item->delete();
            }
            $this->normalizeOrderIndex(FindPropertyItem::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            FindPropertyItem::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            FindPropertyItem::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}
