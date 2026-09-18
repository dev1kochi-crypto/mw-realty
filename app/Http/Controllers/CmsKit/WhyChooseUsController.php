<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\WhyChooseUsItem;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use App\Support\ManagesOrderIndex;
use App\Support\ValidatesImageDimensions;

class WhyChooseUsController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = WhyChooseUsItem::orderBy('order_index', 'asc');
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
                    if (auth('cms')->user()->can('why-choose-us.edit')) {
                        $btns .= '<a href="' . route('cms.why-choose-us.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('why-choose-us.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'image', 'status', 'order', 'action'])
                ->make(true);
        }

        $section = SectionLabel::where('section_key', 'why-choose-us')->first();
        $languages = Language::where('status', true)->get();
        $sectionImageConfig = config('cms-kit.images.why-choose-us.section_image', []);
        return view('cms-kit::why-choose-us.index', compact('section', 'languages', 'sectionImageConfig'));
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $iconConfig = config('cms-kit.images.why-choose-us.item_icon');
        $nextOrder = WhyChooseUsItem::count() + 1;
        return view('cms-kit::why-choose-us.create', compact('languages', 'iconConfig', 'nextOrder'));
    }

    protected function itemRules(bool $isUpdate = false, ?WhyChooseUsItem $item = null): array
    {
        $iconConfig = config('cms-kit.images.why-choose-us.item_icon', []);
        $languages = Language::where('status', true)->get();
        $rules = ['order_index' => 'nullable|integer|min:1'];

        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.title"] = 'required';
        }

        $requiresImage = !$isUpdate || !$item?->image || request()->boolean('remove_image');
        $rules['image'] = ($requiresImage ? 'required' : 'nullable') . '|image|max:' . ($iconConfig['max_size'] ?? 256);
        $rules['remove_image'] = 'nullable|boolean';

        return $rules;
    }

    public function store(Request $request)
    {
        $request->validate($this->itemRules());
        $iconConfig = config('cms-kit.images.why-choose-us.item_icon', []);
        $this->validateImageWithinLimits($request, 'image', $iconConfig, 'Icon');

        $data = $request->only(['order_index', 'translations']);
        $data['status'] = $request->has('status');

        if ($request->hasFile('image')) {
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'why-choose-us');
        }

        $order = $this->resolveOrderForCreate(WhyChooseUsItem::class, $request->order_index ? (int) $request->order_index : null);
        WhyChooseUsItem::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        WhyChooseUsItem::create($data);

        return redirect()->route('cms.why-choose-us.index')->with('success', 'Item added successfully.');
    }

    public function edit($id)
    {
        $item = WhyChooseUsItem::findOrFail($id);
        $languages = Language::where('status', true)->get();
        $iconConfig = config('cms-kit.images.why-choose-us.item_icon');
        return view('cms-kit::why-choose-us.edit', compact('item', 'languages', 'iconConfig'));
    }

    public function update(Request $request, $id)
    {
        $item = WhyChooseUsItem::findOrFail($id);
        $request->validate($this->itemRules(true, $item));
        $iconConfig = config('cms-kit.images.why-choose-us.item_icon', []);
        $this->validateImageWithinLimits($request, 'image', $iconConfig, 'Icon');

        $data = $request->only(['order_index', 'translations']);
        $data['status'] = $request->has('status');

        if ($request->hasFile('image')) {
            if ($item->image) {
                app(\App\Services\ManagedFiles::class)->delete($item->image);
            }
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'why-choose-us');
        } elseif ($request->boolean('remove_image') && $item->image) {
            app(\App\Services\ManagedFiles::class)->delete($item->image);
            $data['image'] = null;
        }

        $data['image_alt'] = $request->boolean('remove_image') ? null : $request->input('image_alt');

        $item->update($data);

        return redirect()->route('cms.why-choose-us.index')->with('success', 'Item updated successfully.');
    }

    public function destroy($id)
    {
        $item = WhyChooseUsItem::findOrFail($id);
        $order = $item->order_index;
        if ($item->image) {
            app(\App\Services\ManagedFiles::class)->delete($item->image);
        }
        $item->delete();

        WhyChooseUsItem::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(WhyChooseUsItem::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $item = WhyChooseUsItem::findOrFail($id);
        $item->status = !$item->status;
        $item->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:why_choose_us_items,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $item = WhyChooseUsItem::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(WhyChooseUsItem::class, (int) $request->order_index);
        $oldOrder = $item->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                WhyChooseUsItem::where('order_index', '>', $oldOrder)->where('order_index', '<=', $newOrder)->decrement('order_index');
            } else {
                WhyChooseUsItem::where('order_index', '>=', $newOrder)->where('order_index', '<', $oldOrder)->increment('order_index');
            }
            $item->order_index = $newOrder;
            $item->save();
        }
        $this->normalizeOrderIndex(WhyChooseUsItem::class);

        return response()->json(['success' => true]);
    }

    public function updateSection(Request $request)
    {
        $languages = Language::where('status', true)->get();
        $rules = ['remove_image' => 'nullable|boolean'];
        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.title"] = 'required';
        }
        $request->validate($rules);

        $imageConfig = config('cms-kit.images.why-choose-us.section_image', []);
        $this->validateImageWithinLimits($request, 'section_image', $imageConfig, 'Section image');

        $section = SectionLabel::where('section_key', 'why-choose-us')->first();

        $data = [
            'translations' => $request->input('translations', []),
            'status' => $request->has('status'),
        ];

        if ($request->hasFile('section_image')) {
            if ($section?->section_image) {
                app(\App\Services\ManagedFiles::class)->delete($section->section_image);
            }
            $data['section_image'] = app(\App\Services\ManagedFiles::class)->store($request->file('section_image'), 'why-choose-us');
            $data['section_image_alt'] = $request->input('section_image_alt');
        } elseif ($request->boolean('remove_section_image') && $section?->section_image) {
            app(\App\Services\ManagedFiles::class)->delete($section->section_image);
            $data['section_image'] = null;
            $data['section_image_alt'] = null;
        } elseif ($request->filled('section_image_alt')) {
            $data['section_image_alt'] = $request->input('section_image_alt');
        }

        SectionLabel::updateOrCreate(['section_key' => 'why-choose-us'], $data);

        return redirect()->route('cms.why-choose-us.index')->with('success', 'Section settings updated.');
    }

    public function bulkAction(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));
        $action = $request->input('action');

        if (empty($ids) || !$action) {
            return response()->json(['success' => false, 'message' => 'No action or items selected.'], 422);
        }

        if ($action === 'delete') {
            $items = WhyChooseUsItem::whereIn('id', $ids)->get();
            foreach ($items as $item) {
                if ($item->image) {
                    app(\App\Services\ManagedFiles::class)->delete($item->image);
                }
                $item->delete();
            }
            $this->normalizeOrderIndex(WhyChooseUsItem::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            WhyChooseUsItem::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            WhyChooseUsItem::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}
