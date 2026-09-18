<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\BlogCategory;
use App\Models\CmsKit\Language;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use App\Support\ManagesOrderIndex;

class BlogCategoryController extends Controller
{
    use ManagesOrderIndex;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = BlogCategory::orderBy('order_index', 'asc');
            return \App\Support\TranslatedTable::column(DataTables::of($data), 'title', 'title')
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
                })
                ->addColumn('title', function ($row) {
                    return $row->getTranslation('title');
                })
                ->addColumn('slug', function ($row) {
                    return '<code>' . $row->slug . '</code>';
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
                    if (auth('cms')->user()->can('blog-categories.edit')) {
                        $btns .= '<a href="' . route('cms.blog-categories.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('blog-categories.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'slug', 'status', 'order', 'action'])
                ->make(true);
        }

        $languages = Language::where('status', true)->get();
        return view('cms-kit::blog-categories.index', compact('languages'));
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $nextOrder = BlogCategory::count() + 1;
        return view('cms-kit::blog-categories.create', compact('languages', 'nextOrder'));
    }

    protected function categoryRules(bool $isUpdate = false, ?BlogCategory $category = null): array
    {
        $languages = Language::where('status', true)->get();
        $rules = [
            'order_index' => 'nullable|integer|min:1',
            'slug' => ['nullable', 'string', 'max:255', \Illuminate\Validation\Rule::unique('blog_categories', 'slug')->ignore($category?->id)],
        ];

        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.title"] = 'required|string|max:255';
        }

        return $rules;
    }

    public function store(Request $request)
    {
        $request->validate($this->categoryRules());

        $fallback = config('app.fallback_locale');
        $data = $request->only(['order_index', 'translations']);
        $data['status'] = $request->has('status');
        $data['slug'] = $request->filled('slug')
            ? Str::slug($request->slug)
            : Str::slug($request->translations[$fallback]['title'] ?? $request->translations[array_key_first($request->translations)]['title']);

        $order = $this->resolveOrderForCreate(BlogCategory::class, $request->order_index ? (int) $request->order_index : null);
        BlogCategory::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        BlogCategory::create($data);

        return redirect()->route('cms.blog-categories.index')->with('success', 'Category added successfully.');
    }

    public function edit($id)
    {
        $category = BlogCategory::findOrFail($id);
        $languages = Language::where('status', true)->get();
        return view('cms-kit::blog-categories.edit', compact('category', 'languages'));
    }

    public function update(Request $request, $id)
    {
        $category = BlogCategory::findOrFail($id);
        $request->validate($this->categoryRules(true, $category));

        $data = $request->only(['order_index', 'translations']);
        $data['status'] = $request->has('status');
        if ($request->filled('slug')) {
            $data['slug'] = Str::slug($request->slug);
        }

        $category->update($data);

        return redirect()->route('cms.blog-categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy($id)
    {
        $category = BlogCategory::findOrFail($id);

        if (\App\Models\CmsKit\Blog::whereJsonContains('extra_fields->category', $category->slug)->exists()) {
            return response()->json(['success' => false, 'message' => 'This category is used by one or more blog posts and cannot be deleted.'], 422);
        }

        $order = $category->order_index;
        $category->delete();

        BlogCategory::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(BlogCategory::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $category = BlogCategory::findOrFail($id);
        $category->status = !$category->status;
        $category->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:blog_categories,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $category = BlogCategory::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(BlogCategory::class, (int) $request->order_index);
        $oldOrder = $category->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                BlogCategory::where('order_index', '>', $oldOrder)->where('order_index', '<=', $newOrder)->decrement('order_index');
            } else {
                BlogCategory::where('order_index', '>=', $newOrder)->where('order_index', '<', $oldOrder)->increment('order_index');
            }
            $category->order_index = $newOrder;
            $category->save();
        }
        $this->normalizeOrderIndex(BlogCategory::class);

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
            $usedSlugs = BlogCategory::whereIn('id', $ids)->get()->filter(
                fn ($category) => \App\Models\CmsKit\Blog::whereJsonContains('extra_fields->category', $category->slug)->exists()
            );

            if ($usedSlugs->isNotEmpty()) {
                return response()->json(['success' => false, 'message' => 'Some selected categories are used by blog posts and cannot be deleted.'], 422);
            }

            BlogCategory::whereIn('id', $ids)->delete();
            $this->normalizeOrderIndex(BlogCategory::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            BlogCategory::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            BlogCategory::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}
