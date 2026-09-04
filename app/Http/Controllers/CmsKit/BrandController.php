<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Brand;
use App\Models\CmsKit\Language;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use CMS\SiteManager\Support\ManagesOrderIndex;
use CMS\SiteManager\Support\ValidatesImageDimensions;

class BrandController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    protected function getValidationRules(bool $isUpdate = false): array
    {
        $imageConfig = config('cms-kit.images.brands.logo');
        $brandConfig = config('cms-kit.database.brands.items', []);
        $requiredFields = $brandConfig['required'] ?? [];
        $brand = $isUpdate ? Brand::find(request()->route('id')) : null;
        $requiresImage = in_array('image', $requiredFields)
            && (!$isUpdate || !$brand?->image || request()->boolean('remove_image'));

        return [
            'image' => ($requiresImage ? 'required' : 'nullable') . '|image|max:' . ($imageConfig['max_size'] ?? 512),
            'image_alt' => in_array('image_alt', $requiredFields) ? 'required|string|max:255' : 'nullable|string|max:255',
            'remove_image' => 'nullable|boolean',
            'order_index' => 'nullable|integer|min:1',
        ];
    }

    protected function buildBrandTranslations(Request $request): array
    {
        $languages = Language::active()->get();
        $fieldConfig = config('cms-kit.database.brands.items.extra_fields', []);
        $translatableFields = collect($fieldConfig)->filter(fn ($field) => $field['translatable'] ?? false)->keys();
        $translations = [];

        foreach ($languages as $lang) {
            $translations[$lang->code]['extra_fields'] = [];
            foreach ($translatableFields as $fieldName) {
                $translations[$lang->code]['extra_fields'][$fieldName] = $request->input("translations.{$lang->code}.extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $cmsUser = auth('cms')->user();
            $data = Brand::orderBy('order_index', 'asc');
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
                })
                ->addColumn('image', function ($row) {
                    if (!$row->image) {
                        return '<span class="text-muted">No Image</span>';
                    }
                    return '<img src="' . asset('storage/' . $row->image) . '" class="img-thumbnail" style="height: 40px;">';
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
                ->addColumn('action', function ($row) use ($cmsUser) {
                    $btns = '<div class="btn-group">';
                    if ($cmsUser && $cmsUser->can('brands.edit')) {
                        $btns .= '<a href="' . route('cms.brands.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if ($cmsUser && $cmsUser->can('brands.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'image', 'status', 'order', 'action'])
                ->make(true);
        }

        return view('cms-kit::brands.index');
    }

    public function create()
    {
        $imageConfig = config('cms-kit.images.brands.logo');
        $languages = Language::active()->get();
        $nextOrder = Brand::count() + 1;
        return view('cms-kit::brands.create', compact('imageConfig', 'languages', 'nextOrder'));
    }

    public function store(Request $request)
    {
        $request->validate($this->getValidationRules());
        $this->validateImageWithinLimits($request, 'image', config('cms-kit.images.brands.logo', []), 'Brand logo');

        $data = $request->only(['image_alt', 'order_index', 'extra_fields']);
        $data['status'] = $request->has('status');
        $data['translations'] = $this->buildBrandTranslations($request);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('brands', 'public');
        }

        $order = $this->resolveOrderForCreate(Brand::class, $request->order_index ? (int) $request->order_index : null);
        $data['order_index'] = $order;
        Brand::where('order_index', '>=', $order)->increment('order_index');

        Brand::create($data);

        return redirect()->route('cms.brands.index')->with('success', 'Brand added successfully.');
    }

    public function edit($id)
    {
        $brand = Brand::findOrFail($id);
        $imageConfig = config('cms-kit.images.brands.logo');
        $languages = Language::active()->get();
        return view('cms-kit::brands.edit', compact('brand', 'imageConfig', 'languages'));
    }

    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);
        $request->validate($this->getValidationRules(true));
        $this->validateImageWithinLimits($request, 'image', config('cms-kit.images.brands.logo', []), 'Brand logo');

        $data = $request->only(['image_alt', 'order_index', 'extra_fields']);
        $data['status'] = $request->has('status');
        $data['translations'] = $this->buildBrandTranslations($request);

        if ($request->hasFile('image')) {
            if ($brand->image) Storage::disk('public')->delete($brand->image);
            $data['image'] = $request->file('image')->store('brands', 'public');
        } elseif ($request->boolean('remove_image') && $brand->image) {
            Storage::disk('public')->delete($brand->image);
            $data['image'] = null;
            $data['image_alt'] = null;
        }

        $brand->update($data);

        return redirect()->route('cms.brands.index')->with('success', 'Brand updated successfully.');
    }

    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);
        $order = $brand->order_index;
        if ($brand->image) Storage::disk('public')->delete($brand->image);
        $brand->delete();

        Brand::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(Brand::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $brand = Brand::findOrFail($id);
        $brand->status = !$brand->status;
        $brand->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:brands,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $brand = Brand::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(Brand::class, (int) $request->order_index);
        $oldOrder = $brand->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                Brand::where('order_index', '>', $oldOrder)
                    ->where('order_index', '<=', $newOrder)
                    ->decrement('order_index');
            } else {
                Brand::where('order_index', '>=', $newOrder)
                    ->where('order_index', '<', $oldOrder)
                    ->increment('order_index');
            }
            $brand->order_index = $newOrder;
            $brand->save();
        }
        $this->normalizeOrderIndex(Brand::class);

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
            $brands = Brand::whereIn('id', $ids)->get();
            foreach ($brands as $brand) {
                if ($brand->image) {
                    Storage::disk('public')->delete($brand->image);
                }
                $brand->delete();
            }
            $this->normalizeOrderIndex(Brand::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            Brand::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            Brand::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}


