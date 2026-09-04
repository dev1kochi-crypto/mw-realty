<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Blog;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SectionLabel;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use CMS\SiteManager\Support\ManagesOrderIndex;
use CMS\SiteManager\Support\ValidatesImageDimensions;

class BlogController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    protected function getBlogValidationRules(bool $isUpdate = false, ?Blog $blog = null): array
    {
        $imagesConfig = config('cms-kit.images.blogs');
        $blogConfig = config('cms-kit.database.blogs.items', []);
        $requiredFields = $blogConfig['required'] ?? [];
        $languages = Language::where('status', true)->get();
        $rules = [
            'order_index' => 'nullable|integer|min:1',
        ];

        foreach ($languages as $lang) {
            if (($blogConfig['title'] ?? true) && in_array('title', $requiredFields)) {
                $rules["translations.{$lang->code}.title"] = 'required';
            }
            if (($blogConfig['content'] ?? true) && in_array('content', $requiredFields)) {
                $rules["translations.{$lang->code}.content"] = 'required';
            }
        }

        if ($blogConfig['published_at'] ?? true) {
            $rules['published_at'] = in_array('published_at', $requiredFields) ? 'required|date' : 'nullable|date';
        }

        foreach (['feature_image', 'detail_image', 'banner_image', 'image_3', 'image_4'] as $field) {
            if (!($blogConfig[$field] ?? true)) {
                continue;
            }

            $requiresImage = in_array($field, $requiredFields)
                && (!$isUpdate || !$blog?->{$field} || request()->boolean("remove_{$field}"));

            $rules[$field] = ($requiresImage ? 'required' : 'nullable') . '|image|max:' . ($imagesConfig[$field]['max_size'] ?? 1024);
            $rules["remove_{$field}"] = 'nullable|boolean';
        }

        $rules['remove_metadata_og_image'] = 'nullable|boolean';

        return $rules;
    }

    protected function getBlogSectionValidationRules(): array
    {
        $languages = Language::where('status', true)->get();
        $sectionConfig = config('cms-kit.database.blogs.section', []);
        $requiredFields = $sectionConfig['required'] ?? [];
        $rules = [];

        foreach ($languages as $lang) {
            if (($sectionConfig['title'] ?? true) && in_array('title', $requiredFields)) {
                $rules["translations.{$lang->code}.title"] = 'required';
            }
            if (($sectionConfig['listing_title'] ?? true) && in_array('listing_title', $requiredFields)) {
                $rules["translations.{$lang->code}.listing_title"] = 'required';
            }
            if (($sectionConfig['description'] ?? true) && in_array('description', $requiredFields)) {
                $rules["translations.{$lang->code}.description"] = 'required';
            }
        }

        return $rules;
    }

    protected function mergeBlogTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.blogs.items.extra_fields', []);
        $translatableFields = collect($fieldConfig)->filter(fn ($field) => $field['translatable'] ?? false)->keys();

        foreach ($translations as $lang => $values) {
            $translations[$lang]['extra_fields'] = [];
            foreach ($translatableFields as $fieldName) {
                $translations[$lang]['extra_fields'][$fieldName] = data_get($values, "extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    protected function mergeBlogSectionTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.blogs.section.extra_fields', []);
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
            $cmsUser = auth('cms')->user();
            $data = Blog::orderBy('order_index', 'asc');
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
                })
                ->addColumn('image', function ($row) {
                    if ($row->feature_image) {
                        return '<img src="' . asset('storage/' . $row->feature_image) . '" class="img-thumbnail" style="height: 40px;">';
                    }
                    return '-';
                })
                ->addColumn('title', function ($row) {
                    return $row->getTranslation('title');
                })
                ->editColumn('published_at', function ($row) {
                    return $row->published_at->format('d M Y');
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
                    if ($cmsUser && $cmsUser->can('blogs.edit')) {
                        $btns .= '<a href="' . route('cms.blogs.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if ($cmsUser && $cmsUser->can('blogs.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'image', 'status', 'order', 'action'])
                ->make(true);
        }

        $section = SectionLabel::where('section_key', 'blogs')->first();
        $languages = Language::where('status', true)->get();
        return view('cms-kit::blogs.index', compact('section', 'languages'));
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $imagesConfig = config('cms-kit.images.blogs');
        return view('cms-kit::blogs.create', compact('languages', 'imagesConfig'));
    }

    public function store(Request $request)
    {
        $imagesConfig = config('cms-kit.images.blogs');
        $request->validate($this->getBlogValidationRules());
        foreach (['feature_image', 'detail_image', 'banner_image', 'image_3', 'image_4'] as $field) {
            $this->validateImageWithinLimits($request, $field, $imagesConfig[$field] ?? [], str_replace('_', ' ', ucfirst($field)));
        }

        $data = $request->except(['feature_image', 'detail_image', 'banner_image', 'image_3', 'image_4', 'status', 'slug']);
        // Blog create defaults to active unless explicitly turned off.
        $data['status'] = $request->boolean('status', true);
        $data['translations'] = $this->mergeBlogTranslatableExtraFields($request->input('translations', []));
        $data['slug'] = $request->filled('slug') ? Str::slug($request->slug) : Str::slug($request->translations[config('app.fallback_locale')]['title'] ?? $request->translations[array_key_first($request->translations)]['title']);
        $data['extra_fields'] = $request->input('extra_fields', []);

        $imageFields = ['feature_image', 'detail_image', 'banner_image', 'image_3', 'image_4'];
        foreach ($imageFields as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store('blogs', 'public');
            }
        }

        $order = $this->resolveOrderForCreate(Blog::class, $request->order_index ? (int) $request->order_index : null);
        Blog::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        // Handle Metadata
        if ($request->has('metadata')) {
            $metadata = $request->metadata;
            if ($request->hasFile('metadata.og_image')) {
                $metadata['og_image'] = $request->file('metadata.og_image')->store('blogs/metadata', 'public');
            }
            $data['metadata'] = $metadata;
        }

        Blog::create($data);

        return redirect()->route('cms.blogs.index')->with('success', 'Blog post created successfully.');
    }

    public function edit($id)
    {
        $blog = Blog::findOrFail($id);
        $languages = Language::where('status', true)->get();
        $imagesConfig = config('cms-kit.images.blogs');
        return view('cms-kit::blogs.edit', compact('blog', 'languages', 'imagesConfig'));
    }

    public function update(Request $request, $id)
    {
        $blog = Blog::findOrFail($id);
        $imagesConfig = config('cms-kit.images.blogs');

        $request->validate($this->getBlogValidationRules(true, $blog));
        foreach (['feature_image', 'detail_image', 'banner_image', 'image_3', 'image_4'] as $field) {
            $this->validateImageWithinLimits($request, $field, $imagesConfig[$field] ?? [], str_replace('_', ' ', ucfirst($field)));
        }

        $data = $request->except(['feature_image', 'detail_image', 'banner_image', 'image_3', 'image_4', 'status', 'slug']);
        // Keep existing status when status input is absent in edit form.
        $data['status'] = $request->has('status') ? $request->boolean('status') : $blog->status;
        $data['translations'] = $this->mergeBlogTranslatableExtraFields($request->input('translations', []));
        $data['extra_fields'] = $request->input('extra_fields', []);
        if ($request->filled('slug')) {
            $data['slug'] = Str::slug($request->slug);
        }

        $newSlug = $data['slug'] ?? $blog->slug;
        app(\CMS\SiteManager\Services\UrlRedirectService::class)->recordSlugChange('blog', $blog->slug, $newSlug, auth('cms')->id());

        $imageFields = ['feature_image', 'detail_image', 'banner_image', 'image_3', 'image_4'];
        foreach ($imageFields as $field) {
            if ($request->hasFile($field)) {
                if ($blog->$field) Storage::disk('public')->delete($blog->$field);
                $data[$field] = $request->file($field)->store('blogs', 'public');
            } elseif ($request->boolean("remove_{$field}") && $blog->$field) {
                Storage::disk('public')->delete($blog->$field);
                $data[$field] = null;
            }

            if ($request->boolean("remove_{$field}")) {
                $altField = $field === 'banner_image' ? 'banner_alt' : $field . '_alt';
                $data[$altField] = null;
            }
        }

        // Handle Metadata
        if ($request->has('metadata')) {
            $metadata = $request->metadata;
            $existingMetadata = $blog->metadata ?? [];
            
            if ($request->hasFile('metadata.og_image')) {
                if (!empty($existingMetadata['og_image'])) {
                    Storage::disk('public')->delete($existingMetadata['og_image']);
                }
                $metadata['og_image'] = $request->file('metadata.og_image')->store('blogs/metadata', 'public');
            } elseif ($request->boolean('remove_metadata_og_image') && !empty($existingMetadata['og_image'])) {
                Storage::disk('public')->delete($existingMetadata['og_image']);
                $metadata['og_image'] = null;
            } else {
                // Preserve existing og_image if no new file is uploaded
                $metadata['og_image'] = $existingMetadata['og_image'] ?? null;
            }
            $data['metadata'] = $metadata;
        }

        $blog->update($data);

        return redirect()->route('cms.blogs.index')->with('success', 'Blog post updated successfully.');
    }

    public function destroy($id)
    {
        $blog = Blog::findOrFail($id);
        app(\CMS\SiteManager\Services\UrlRedirectService::class)->recordDeletion('blog', $blog->slug, auth('cms')->id());
        $order = $blog->order_index;
        $imageFields = ['feature_image', 'detail_image', 'banner_image', 'image_3', 'image_4'];
        foreach ($imageFields as $field) {
            if ($blog->$field) Storage::disk('public')->delete($blog->$field);
        }
        
        // Delete Metadata OG Image
        if (!empty($blog->metadata['og_image'])) {
            Storage::disk('public')->delete($blog->metadata['og_image']);
        }

        $blog->delete();

        Blog::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(Blog::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $blog = Blog::findOrFail($id);
        $blog->status = !$blog->status;
        $blog->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:blogs,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $blog = Blog::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(Blog::class, (int) $request->order_index);
        $oldOrder = $blog->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                Blog::where('order_index', '>', $oldOrder)
                    ->where('order_index', '<=', $newOrder)
                    ->decrement('order_index');
            } else {
                Blog::where('order_index', '>=', $newOrder)
                    ->where('order_index', '<', $oldOrder)
                    ->increment('order_index');
            }
            $blog->order_index = $newOrder;
            $blog->save();
        }
        $this->normalizeOrderIndex(Blog::class);

        return response()->json(['success' => true]);
    }

    public function updateSection(Request $request)
    {
        $request->validate($this->getBlogSectionValidationRules());

        SectionLabel::updateOrCreate(
            ['section_key' => 'blogs'],
            [
                'translations' => $this->mergeBlogSectionTranslatableExtraFields($request->input('translations', [])),
                'status' => $request->has('status'),
                'extra_fields' => array_merge(
                    $request->input('extra_fields', []),
                    [
                        'status' => $request->has('status'),
                        'display_home' => ($sectionConfig['display_home'] ?? false) ? $request->boolean('display_home') : false,
                    ]
                )
            ]
        );

        return redirect()->back()->with('success', 'Blog section settings updated.');
    }

    public function bulkAction(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));
        $action = $request->input('action');

        if (empty($ids) || !$action) {
            return response()->json(['success' => false, 'message' => 'No action or items selected.'], 422);
        }

        if ($action === 'delete') {
            $blogs = Blog::whereIn('id', $ids)->get();
            $redirectSvc = app(\CMS\SiteManager\Services\UrlRedirectService::class);
            foreach ($blogs as $blog) {
                $redirectSvc->recordDeletion('blog', $blog->slug, auth('cms')->id());
            }
            foreach ($blogs as $blog) {
                $imageFields = ['feature_image', 'detail_image', 'banner_image', 'image_3', 'image_4'];
                foreach ($imageFields as $field) {
                    if ($blog->$field) {
                        Storage::disk('public')->delete($blog->$field);
                    }
                }

                if (!empty($blog->metadata['og_image'])) {
                    Storage::disk('public')->delete($blog->metadata['og_image']);
                }

                $blog->delete();
            }
            $this->normalizeOrderIndex(Blog::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            Blog::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            Blog::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}


