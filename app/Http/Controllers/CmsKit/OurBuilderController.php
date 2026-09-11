<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\OurBuilderItem;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use App\Support\ManagesOrderIndex;
use App\Support\ValidatesImageDimensions;

class OurBuilderController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = OurBuilderItem::orderBy('order_index', 'asc');
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
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
                    if (auth('cms')->user()->can('our-builders.edit')) {
                        $btns .= '<a href="' . route('cms.our-builders.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('our-builders.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'image', 'status', 'order', 'action'])
                ->make(true);
        }

        $section = SectionLabel::where('section_key', 'our-builders')->first();
        $languages = Language::where('status', true)->get();
        return view('cms-kit::our-builders.index', compact('section', 'languages'));
    }

    public function create()
    {
        $imageConfig = config('cms-kit.images.our-builders.item_logo');
        $nextOrder = OurBuilderItem::count() + 1;
        return view('cms-kit::our-builders.create', compact('imageConfig', 'nextOrder'));
    }

    protected function itemRules(bool $isUpdate = false, ?OurBuilderItem $item = null): array
    {
        $imageConfig = config('cms-kit.images.our-builders.item_logo', []);
        $requiresImage = !$isUpdate || !$item?->image || request()->boolean('remove_image');

        return [
            'order_index' => 'nullable|integer|min:1',
            'image' => ($requiresImage ? 'required' : 'nullable') . '|image|max:' . ($imageConfig['max_size'] ?? 1024),
            'remove_image' => 'nullable|boolean',
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->itemRules());
        $imageConfig = config('cms-kit.images.our-builders.item_logo', []);
        $this->validateImageWithinLimits($request, 'image', $imageConfig, 'Builder logo');

        $data = $request->only(['order_index']);
        $data['status'] = $request->has('status');
        $data['image_alt'] = $request->input('image_alt');

        if ($request->hasFile('image')) {
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'our-builders');
        }

        $order = $this->resolveOrderForCreate(OurBuilderItem::class, $request->order_index ? (int) $request->order_index : null);
        OurBuilderItem::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        OurBuilderItem::create($data);

        return redirect()->route('cms.our-builders.index')->with('success', 'Builder added successfully.');
    }

    public function edit($id)
    {
        $item = OurBuilderItem::findOrFail($id);
        $imageConfig = config('cms-kit.images.our-builders.item_logo');
        return view('cms-kit::our-builders.edit', compact('item', 'imageConfig'));
    }

    public function update(Request $request, $id)
    {
        $item = OurBuilderItem::findOrFail($id);
        $request->validate($this->itemRules(true, $item));
        $imageConfig = config('cms-kit.images.our-builders.item_logo', []);
        $this->validateImageWithinLimits($request, 'image', $imageConfig, 'Builder logo');

        $data = $request->only(['order_index']);
        $data['status'] = $request->has('status');

        if ($request->hasFile('image')) {
            if ($item->image) {
                app(\App\Services\ManagedFiles::class)->delete($item->image);
            }
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'our-builders');
        } elseif ($request->boolean('remove_image') && $item->image) {
            app(\App\Services\ManagedFiles::class)->delete($item->image);
            $data['image'] = null;
        }

        $data['image_alt'] = $request->boolean('remove_image') ? null : $request->input('image_alt');

        $item->update($data);

        return redirect()->route('cms.our-builders.index')->with('success', 'Builder updated successfully.');
    }

    public function destroy($id)
    {
        $item = OurBuilderItem::findOrFail($id);
        $order = $item->order_index;
        if ($item->image) {
            app(\App\Services\ManagedFiles::class)->delete($item->image);
        }
        $item->delete();

        OurBuilderItem::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(OurBuilderItem::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $item = OurBuilderItem::findOrFail($id);
        $item->status = !$item->status;
        $item->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:our_builder_items,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $item = OurBuilderItem::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(OurBuilderItem::class, (int) $request->order_index);
        $oldOrder = $item->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                OurBuilderItem::where('order_index', '>', $oldOrder)->where('order_index', '<=', $newOrder)->decrement('order_index');
            } else {
                OurBuilderItem::where('order_index', '>=', $newOrder)->where('order_index', '<', $oldOrder)->increment('order_index');
            }
            $item->order_index = $newOrder;
            $item->save();
        }
        $this->normalizeOrderIndex(OurBuilderItem::class);

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
            ['section_key' => 'our-builders'],
            [
                'translations' => $request->input('translations', []),
                'status' => $request->has('status'),
            ]
        );

        return redirect()->route('cms.our-builders.index')->with('success', 'Section settings updated.');
    }

    public function bulkAction(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));
        $action = $request->input('action');

        if (empty($ids) || !$action) {
            return response()->json(['success' => false, 'message' => 'No action or items selected.'], 422);
        }

        if ($action === 'delete') {
            $items = OurBuilderItem::whereIn('id', $ids)->get();
            foreach ($items as $item) {
                if ($item->image) {
                    app(\App\Services\ManagedFiles::class)->delete($item->image);
                }
                $item->delete();
            }
            $this->normalizeOrderIndex(OurBuilderItem::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            OurBuilderItem::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            OurBuilderItem::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}
