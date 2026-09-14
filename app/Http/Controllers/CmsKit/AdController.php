<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Ad;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Routing\Controller;
use App\Support\ManagesOrderIndex;
use App\Support\ValidatesImageDimensions;

class AdController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Ad::orderBy('order_index', 'asc');
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
                })
                ->addColumn('image', function ($row) {
                    return '<img src="' . asset('storage/' . $row->image) . '" class="img-thumbnail" style="height: 40px;">';
                })
                ->addColumn('placement', function ($row) {
                    return '<span class="badge bg-light text-dark border">' . e($row->placement) . '</span>';
                })
                ->addColumn('schedule', function ($row) {
                    if (!$row->starts_at && !$row->ends_at) {
                        return '<span class="text-muted">Always on</span>';
                    }
                    return ($row->starts_at?->format('d M Y') ?? '—') . ' &rarr; ' . ($row->ends_at?->format('d M Y') ?? '—');
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
                    if (auth('cms')->user()->can('ads.edit')) {
                        $btns .= '<a href="' . route('cms.ads.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('ads.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'image', 'placement', 'schedule', 'status', 'order', 'action'])
                ->make(true);
        }

        $placements = Ad::query()->distinct()->orderBy('placement')->pluck('placement');
        return view('cms-kit::ads.index', compact('placements'));
    }

    public function create()
    {
        $imageConfig = config('cms-kit.images.ads.image', []);
        $placements = Ad::query()->distinct()->orderBy('placement')->pluck('placement');
        $nextOrder = Ad::count() + 1;
        return view('cms-kit::ads.create', compact('imageConfig', 'placements', 'nextOrder'));
    }

    protected function rules(bool $isUpdate = false, ?Ad $ad = null): array
    {
        $requiresImage = !$isUpdate || !$ad?->image || request()->boolean('remove_image');

        return [
            'name' => 'required|string|max:255',
            'placement' => 'required|string|max:100',
            'link_url' => 'nullable|url|max:500',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'order_index' => 'nullable|integer|min:1',
            'image' => ($requiresImage ? 'required' : 'nullable') . '|image|max:' . (config('cms-kit.images.ads.image.max_size') ?? 4096),
            'image_alt' => 'nullable|string|max:255',
            'remove_image' => 'nullable|boolean',
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());
        $this->validateImageWithinLimits($request, 'image', config('cms-kit.images.ads.image', []), 'Image');

        $data = $request->only(['name', 'placement', 'link_url', 'starts_at', 'ends_at', 'order_index', 'image_alt']);
        $data['status'] = $request->boolean('status', true);
        $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'ads');

        $order = $this->resolveOrderForCreate(Ad::class, $request->order_index ? (int) $request->order_index : null);
        Ad::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        Ad::create($data);

        return redirect()->route('cms.ads.index')->with('success', 'Ad added successfully.');
    }

    public function edit($id)
    {
        $ad = Ad::findOrFail($id);
        $imageConfig = config('cms-kit.images.ads.image', []);
        $placements = Ad::query()->distinct()->orderBy('placement')->pluck('placement');
        return view('cms-kit::ads.edit', compact('ad', 'imageConfig', 'placements'));
    }

    public function update(Request $request, $id)
    {
        $ad = Ad::findOrFail($id);
        $request->validate($this->rules(true, $ad));
        $this->validateImageWithinLimits($request, 'image', config('cms-kit.images.ads.image', []), 'Image');

        $data = $request->only(['name', 'placement', 'link_url', 'starts_at', 'ends_at', 'order_index', 'image_alt']);
        $data['status'] = $request->boolean('status');

        if ($request->hasFile('image')) {
            if ($ad->image) {
                app(\App\Services\ManagedFiles::class)->delete($ad->image);
            }
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'ads');
        }

        $ad->update($data);

        return redirect()->route('cms.ads.index')->with('success', 'Ad updated successfully.');
    }

    public function destroy($id)
    {
        $ad = Ad::findOrFail($id);
        $order = $ad->order_index;
        if ($ad->image) {
            app(\App\Services\ManagedFiles::class)->delete($ad->image);
        }
        $ad->delete();

        Ad::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(Ad::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $ad = Ad::findOrFail($id);
        $ad->status = !$ad->status;
        $ad->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:ads,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $ad = Ad::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(Ad::class, (int) $request->order_index);
        $oldOrder = $ad->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                Ad::where('order_index', '>', $oldOrder)->where('order_index', '<=', $newOrder)->decrement('order_index');
            } else {
                Ad::where('order_index', '>=', $newOrder)->where('order_index', '<', $oldOrder)->increment('order_index');
            }
            $ad->order_index = $newOrder;
            $ad->save();
        }
        $this->normalizeOrderIndex(Ad::class);

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
            $ads = Ad::whereIn('id', $ids)->get();
            foreach ($ads as $ad) {
                if ($ad->image) {
                    app(\App\Services\ManagedFiles::class)->delete($ad->image);
                }
                $ad->delete();
            }
            $this->normalizeOrderIndex(Ad::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            Ad::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            Ad::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}
