<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\CommunityHighlight;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use App\Models\Filter;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use CMS\SiteManager\Support\ManagesOrderIndex;
use CMS\SiteManager\Support\ValidatesImageDimensions;

class CommunityController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    /**
     * Communities are picked from the same "location" filter used by the property
     * search filter bar, so a card here always points at a real, filterable community.
     */
    protected function locationFilter()
    {
        return Filter::where('key', 'location')->where('type', 'select')->with('activeValues')->first();
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = CommunityHighlight::orderBy('order_index', 'asc');
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
                    if (auth('cms')->user()->can('communities.edit')) {
                        $btns .= '<a href="' . route('cms.communities.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('communities.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'image', 'status', 'order', 'action'])
                ->make(true);
        }

        $section = SectionLabel::where('section_key', 'communities')->first();
        $languages = Language::where('status', true)->get();
        return view('cms-kit::communities.index', compact('section', 'languages'));
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $iconConfig = config('cms-kit.images.communities.item_icon');
        $locationFilter = $this->locationFilter();
        $nextOrder = CommunityHighlight::count() + 1;
        return view('cms-kit::communities.create', compact('languages', 'iconConfig', 'locationFilter', 'nextOrder'));
    }

    protected function itemRules(bool $isUpdate = false, ?CommunityHighlight $item = null): array
    {
        $iconConfig = config('cms-kit.images.communities.item_icon', []);
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
        $iconConfig = config('cms-kit.images.communities.item_icon', []);
        $this->validateImageWithinLimits($request, 'image', $iconConfig, 'Icon');

        $data = $request->only(['order_index', 'translations', 'community']);
        $data['status'] = $request->has('status');

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('communities', 'public');
        }
        $data['image_alt'] = $request->input('image_alt');

        $order = $this->resolveOrderForCreate(CommunityHighlight::class, $request->order_index ? (int) $request->order_index : null);
        CommunityHighlight::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        CommunityHighlight::create($data);

        return redirect()->route('cms.communities.index')->with('success', 'Community added successfully.');
    }

    public function edit($id)
    {
        $item = CommunityHighlight::findOrFail($id);
        $languages = Language::where('status', true)->get();
        $iconConfig = config('cms-kit.images.communities.item_icon');
        $locationFilter = $this->locationFilter();
        return view('cms-kit::communities.edit', compact('item', 'languages', 'iconConfig', 'locationFilter'));
    }

    public function update(Request $request, $id)
    {
        $item = CommunityHighlight::findOrFail($id);
        $request->validate($this->itemRules(true, $item));
        $iconConfig = config('cms-kit.images.communities.item_icon', []);
        $this->validateImageWithinLimits($request, 'image', $iconConfig, 'Icon');

        $data = $request->only(['order_index', 'translations', 'community']);
        $data['status'] = $request->has('status');

        if ($request->hasFile('image')) {
            if ($item->image) {
                Storage::disk('public')->delete($item->image);
            }
            $data['image'] = $request->file('image')->store('communities', 'public');
        } elseif ($request->boolean('remove_image') && $item->image) {
            Storage::disk('public')->delete($item->image);
            $data['image'] = null;
        }

        $data['image_alt'] = $request->boolean('remove_image') ? null : $request->input('image_alt');

        $item->update($data);

        return redirect()->route('cms.communities.index')->with('success', 'Community updated successfully.');
    }

    public function destroy($id)
    {
        $item = CommunityHighlight::findOrFail($id);
        $order = $item->order_index;
        if ($item->image) {
            Storage::disk('public')->delete($item->image);
        }
        $item->delete();

        CommunityHighlight::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(CommunityHighlight::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $item = CommunityHighlight::findOrFail($id);
        $item->status = !$item->status;
        $item->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:community_highlights,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $item = CommunityHighlight::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(CommunityHighlight::class, (int) $request->order_index);
        $oldOrder = $item->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                CommunityHighlight::where('order_index', '>', $oldOrder)->where('order_index', '<=', $newOrder)->decrement('order_index');
            } else {
                CommunityHighlight::where('order_index', '>=', $newOrder)->where('order_index', '<', $oldOrder)->increment('order_index');
            }
            $item->order_index = $newOrder;
            $item->save();
        }
        $this->normalizeOrderIndex(CommunityHighlight::class);

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
            ['section_key' => 'communities'],
            [
                'translations' => $request->input('translations', []),
                'extra_fields' => [
                    'listing_status_filter_enabled' => $request->has('listing_status_filter_enabled'),
                    'display_home' => $request->has('display_home'),
                ],
                'status' => $request->has('status'),
            ]
        );

        return redirect()->route('cms.communities.index')->with('success', 'Section settings updated.');
    }

    public function bulkAction(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));
        $action = $request->input('action');

        if (empty($ids) || !$action) {
            return response()->json(['success' => false, 'message' => 'No action or items selected.'], 422);
        }

        if ($action === 'delete') {
            $items = CommunityHighlight::whereIn('id', $ids)->get();
            foreach ($items as $item) {
                if ($item->image) {
                    Storage::disk('public')->delete($item->image);
                }
                $item->delete();
            }
            $this->normalizeOrderIndex(CommunityHighlight::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            CommunityHighlight::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            CommunityHighlight::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}
