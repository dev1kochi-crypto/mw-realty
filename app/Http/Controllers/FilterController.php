<?php

namespace App\Http\Controllers;

use App\Models\Filter;
use App\Models\FilterValue;
use App\Models\CmsKit\Language;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use CMS\SiteManager\Support\ManagesOrderIndex;

class FilterController extends Controller
{
    use ManagesOrderIndex;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Filter::withCount('values')->orderBy('order_index', 'asc');
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('label', function ($row) {
                    return $row->getTranslation('label') ?? $row->key;
                })
                ->addColumn('key', function ($row) {
                    return '<code>' . $row->key . '</code>';
                })
                ->addColumn('type', function ($row) {
                    return ucfirst($row->type);
                })
                ->addColumn('values_count', function ($row) {
                    return $row->type === 'select' ? $row->values_count : '—';
                })
                ->addColumn('status', function ($row) {
                    $checked = $row->status ? 'checked' : '';
                    return '<div class="form-check form-switch">
                                <input class="form-check-input toggle-status" type="checkbox" data-id="' . $row->id . '" ' . $checked . '>
                            </div>';
                })
                ->addColumn('order', function ($row) {
                    return '<input type="number" min="1" class="form-control form-control-sm reorder-input" data-id="' . $row->id . '" value="' . $row->order_index . '" style="width: 70px;">';
                })
                ->addColumn('action', function ($row) {
                    $btns = '<div class="btn-group">';
                    if (auth('cms')->user()->can('filters.edit')) {
                        $btns .= '<a href="' . route('cms.filters.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('filters.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['key', 'status', 'order', 'action'])
                ->make(true);
        }

        return view('filters.index');
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $nextOrder = Filter::count() + 1;
        return view('filters.create', compact('languages', 'nextOrder'));
    }

    protected function rules(bool $isUpdate = false, ?Filter $filter = null): array
    {
        return [
            'key' => [
                $isUpdate ? 'required' : 'required',
                'alpha_dash',
                $isUpdate
                    ? \Illuminate\Validation\Rule::unique('filters', 'key')->ignore($filter?->id)
                    : \Illuminate\Validation\Rule::unique('filters', 'key'),
            ],
            'translations.*.label' => 'required',
            'type' => 'required|in:select,range,number',
            'show_on' => 'nullable|array',
            'order_index' => 'nullable|integer|min:1',
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());

        $data = $request->only(['key', 'type']);
        $data['key'] = Str::slug($request->input('key'), '_');
        $data['translations'] = $request->input('translations', []);
        $data['show_on'] = $request->input('show_on', ['home', 'listing']);
        $data['status'] = $request->has('status');

        $order = $this->resolveOrderForCreate(Filter::class, $request->order_index ? (int) $request->order_index : null);
        Filter::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        Filter::create($data);

        return redirect()->route('cms.filters.index')->with('success', 'Filter created successfully.');
    }

    public function edit($id)
    {
        $filter = Filter::with(['values' => fn ($q) => $q->orderBy('order_index')])->findOrFail($id);
        $languages = Language::where('status', true)->get();
        return view('filters.edit', compact('filter', 'languages'));
    }

    public function update(Request $request, $id)
    {
        $filter = Filter::findOrFail($id);
        $request->validate($this->rules(true, $filter));

        $data = $request->only(['type']);
        $data['key'] = Str::slug($request->input('key'), '_');
        $data['translations'] = $request->input('translations', []);
        $data['show_on'] = $request->input('show_on', ['home', 'listing']);
        $data['status'] = $request->has('status');

        $filter->update($data);

        return redirect()->route('cms.filters.edit', $filter->id)->with('success', 'Filter updated successfully.');
    }

    public function destroy($id)
    {
        $filter = Filter::findOrFail($id);
        $order = $filter->order_index;
        $filter->delete();

        Filter::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(Filter::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $filter = Filter::findOrFail($id);
        $filter->status = !$filter->status;
        $filter->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:filters,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $filter = Filter::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(Filter::class, (int) $request->order_index);
        $oldOrder = $filter->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                Filter::where('order_index', '>', $oldOrder)->where('order_index', '<=', $newOrder)->decrement('order_index');
            } else {
                Filter::where('order_index', '>=', $newOrder)->where('order_index', '<', $oldOrder)->increment('order_index');
            }
            $filter->order_index = $newOrder;
            $filter->save();
        }
        $this->normalizeOrderIndex(Filter::class);

        return response()->json(['success' => true]);
    }

    // --- Filter values (options) ---

    public function storeValue(Request $request, $filterId)
    {
        $filter = Filter::findOrFail($filterId);
        $request->validate([
            'value' => 'required|string',
            'translations.*.label' => 'required',
        ]);

        FilterValue::create([
            'filter_id' => $filter->id,
            'value' => Str::slug($request->input('value'), '_'),
            'translations' => $request->input('translations', []),
            'order_index' => FilterValue::where('filter_id', $filter->id)->count() + 1,
            'status' => $request->has('status'),
        ]);

        return redirect()->route('cms.filters.edit', $filter->id)->with('success', 'Option added.');
    }

    public function updateValue(Request $request, $filterId, $valueId)
    {
        $value = FilterValue::where('filter_id', $filterId)->findOrFail($valueId);
        $request->validate([
            'value' => 'required|string',
            'translations.*.label' => 'required',
        ]);

        $value->update([
            'value' => Str::slug($request->input('value'), '_'),
            'translations' => $request->input('translations', []),
            'status' => $request->boolean('status'),
        ]);

        return redirect()->route('cms.filters.edit', $filterId)->with('success', 'Option updated.');
    }

    public function destroyValue($filterId, $valueId)
    {
        $value = FilterValue::where('filter_id', $filterId)->findOrFail($valueId);
        $value->delete();

        return response()->json(['success' => true]);
    }

    public function toggleValueStatus($filterId, $valueId)
    {
        $value = FilterValue::where('filter_id', $filterId)->findOrFail($valueId);
        $value->status = !$value->status;
        $value->save();

        return response()->json(['success' => true]);
    }
}
