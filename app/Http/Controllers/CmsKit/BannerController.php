<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\Banner;
use App\Models\CmsKit\Language;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\ManagesOrderIndex;
use App\Support\ValidatesImageDimensions;

class BannerController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Banner::orderBy('order_index', 'asc');
            return \App\Support\TranslatedTable::column(DataTables::of($data), 'localized_title', 'line_1')
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
            })
                ->addColumn('media', function ($row) {
                if ($row->banner_type === 'video') {
                    $videoText = $row->video_file ? basename($row->video_file) : $row->video_url;
                    return '<i class="fas fa-video fa-2x text-muted"></i><br><small>' . e(Str::limit($videoText, 20)) . '</small>';
                }
                $url = $row->image ? asset('storage/' . $row->image) : asset('vendor/cms-kit/img/placeholder.png');
                return '<img src="' . $url . '" class="img-thumbnail" style="width: 100px; height: 50px; object-fit: cover;">';
            })
                ->addColumn('localized_title', function ($row) {
                return $row->getTranslation('line_1');
            })
                ->addColumn('status', function ($row) {
                $checked = $row->status ? 'checked' : '';
                return '<div class="form-check form-switch">
                                <input class="form-check-input toggle-status" type="checkbox" data-id="' . $row->id . '" ' . $checked . '>
                            </div>';
                })
                ->orderColumn('order', fn ($query, $direction) => $query->reorder()->orderBy('order_index', $direction))
                ->addColumn('order', function ($row) {
                return '<input type="number" min="1" class="form-control form-control-sm reorder-input" data-id="' . $row->id . '" value="' . $row->order_index . '" style="width: 70px;">';
            })
                ->addColumn('action', function ($row) {
                return '<div class="btn-group">
                                <a href="' . route('cms.banners.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>
                            </div>';
            })
                ->rawColumns(['select_all', 'media', 'status', 'order', 'action'])
                ->make(true);
        }

        $maxBanners = config('cms-kit.database.banners.max_items', 5);
        $currentCount = Banner::count();
        $canAddBanner = $currentCount < $maxBanners;

        return view('cms-kit::banners.index', compact('canAddBanner', 'maxBanners'));
    }

    public function create()
    {
        $maxBanners = config('cms-kit.database.banners.max_items', 5);
        $currentCount = Banner::count();

        if ($currentCount >= $maxBanners) {
            return redirect()->route('cms.banners.index')->with('error', "Maximum banner limit ($maxBanners) reached.");
        }

        $languages = Language::where('status', true)->get();
        $allowedTypes = config('cms-kit.database.banners.allowed_types', ['image', 'video']);
        $mainImageConfig = config('cms-kit.images.banners.main_image', ['max_size' => 2048, 'width' => 1920, 'height' => 800]);
        $avatarConfig = config('cms-kit.images.banners.client_avatar', ['max_size' => 512, 'width' => 100, 'height' => 100]);
        $nextOrder = Banner::count() + 1;

        return view('cms-kit::banners.create', compact('languages', 'allowedTypes', 'mainImageConfig', 'avatarConfig', 'nextOrder'));
    }

    public function store(Request $request)
    {
        $maxBanners = config('cms-kit.database.banners.max_items', 5);
        if (Banner::count() >= $maxBanners) {
            return redirect()->route('cms.banners.index')->with('error', "Maximum banner limit ($maxBanners) reached.");
        }

        $allowedTypes = config('cms-kit.database.banners.allowed_types', ['image', 'video']);
        $resolvedBannerType = count($allowedTypes) === 1
            ? $allowedTypes[0]
            : $request->input('banner_type');
        $request->merge(['banner_type' => $resolvedBannerType]);
        $videoMaxSize = config('cms-kit.images.banners.banner_video.max_size', 10240);
        $typeRule = 'required|in:' . implode(',', $allowedTypes);
        $mainImageConfig = config('cms-kit.images.banners.main_image', ['max_size' => 2048, 'width' => 1920, 'height' => 800]);
        $avatarConfig = config('cms-kit.images.banners.client_avatar', ['max_size' => 512, 'width' => 100, 'height' => 100]);
        $request->validate([
            'banner_type' => $typeRule,
            'image' => 'nullable|required_if:banner_type,image|image|max:' . $mainImageConfig['max_size'],
            'google_avatars.*' => 'nullable|image|max:' . $avatarConfig['max_size'],
            'video_source' => [
                Rule::requiredIf(fn () => $resolvedBannerType === 'video'),
                'nullable',
                'in:url,file',
            ],
            'video_url' => [
                Rule::requiredIf(fn () => $resolvedBannerType === 'video' && $request->input('video_source') === 'url'),
                'nullable',
                'url',
            ],
            'video_file' => [
                Rule::requiredIf(fn () => $resolvedBannerType === 'video' && $request->input('video_source') === 'file'),
                'nullable',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo',
                'max:' . $videoMaxSize,
            ],
            'translations.*.line_1' => 'required',
            'order_index' => 'nullable|integer|min:1',
        ], [
            'image.required_if' => 'Banner image is required when banner type is image.',
            'image.image' => 'Banner image must be a valid image file.',
            'image.max' => 'Banner image size must not exceed ' . ($mainImageConfig['max_size'] ?? 2048) . ' KB.',
            'google_avatars.*.image' => 'Each client avatar must be a valid image file.',
            'google_avatars.*.max' => 'Each client avatar must not exceed ' . ($avatarConfig['max_size'] ?? 512) . ' KB.',
            'video_source.required' => 'Please choose a video source.',
            'video_url.required_if' => 'Video URL is required when URL source is selected.',
            'video_url.required' => 'Video URL is required when URL source is selected.',
            'video_url.url' => 'Video URL must be a valid URL.',
            'video_file.required_if' => 'Video file is required when file source is selected.',
            'video_file.required' => 'Video file is required when file source is selected.',
            'video_file.mimetypes' => 'Video file must be an MP4, MOV, or AVI file.',
            'video_file.max' => 'Video file size must not exceed ' . $videoMaxSize . ' KB.',
            'translations.*.line_1.required' => 'Banner heading is required in every language.',
            'order_index.min' => 'Display order must be at least 1.',
        ]);
        $this->validateImageWithinLimits($request, 'image', $mainImageConfig, 'Banner image');
        $this->validateImageArrayWithinLimits($request, 'google_avatars', $avatarConfig, 'Client avatar');

        $data = $request->only(['banner_type', 'video_url', 'order_index', 'status']);
        $data['status'] = $request->has('status');

        // Handle translations (including JSON fields like buttons and translatable extra_fields)
        $translations = $request->input('translations');
        foreach ($translations as $lang => $fields) {
            if (isset($fields['buttons'])) {
                $translations[$lang]['buttons'] = array_values(array_filter($fields['buttons'], fn($b) => !empty($b['label'])));
            }
            // Keep translatable extra_fields in translations
            // No need to remove them, they'll be stored as is
        }
        $data['translations'] = $translations;

        // Handle Image
        if ($request->hasFile('image')) {
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'banners');
        }

        $data['image_alt'] = $resolvedBannerType === 'image'
            ? $request->input('image_alt')
            : null;

        // Handle Video File
        if ($resolvedBannerType === 'video' && $request->hasFile('video_file')) {
            $data['video_file'] = app(\App\Services\ManagedFiles::class)->store($request->file('video_file'), 'banners/videos');
            $data['video_url'] = null; // Clear URL if file is uploaded
        } elseif ($resolvedBannerType !== 'video') {
            $data['video_file'] = null;
            $data['video_url'] = null;
        }

        // Handle Extra Fields (Social Proof + Config Extra Fields)
        $extraFields = $request->only(['google_rating', 'google_review_count', 'google_avatars_alt']);

        // Merge any additional fields from config
        if ($request->has('extra_fields')) {
            $extraFields = array_merge($extraFields, $request->input('extra_fields'));
        }

        // Handle Multiple Avatars
        if ($request->hasFile('google_avatars')) {
            $avatars = [];
            foreach ($request->file('google_avatars') as $file) {
                $avatars[] = app(\App\Services\ManagedFiles::class)->store($file, 'banners/avatars');
            }
            $extraFields['google_avatars'] = $avatars;
        }
        $data['extra_fields'] = $extraFields;

        $order = $this->resolveOrderForCreate(Banner::class, $request->order_index ? (int) $request->order_index : null);
        Banner::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        Banner::create($data);

        return redirect()->route('cms.banners.index')->with('success', 'Banner created successfully.');
    }

    public function edit($id)
    {
        $banner = Banner::findOrFail($id);
        $languages = Language::where('status', true)->get();
        $allowedTypes = config('cms-kit.database.banners.allowed_types', ['image', 'video']);
        if (count($allowedTypes) === 1) {
            $banner->banner_type = $allowedTypes[0];
        }
        $mainImageConfig = config('cms-kit.images.banners.main_image', ['max_size' => 2048, 'width' => 1920, 'height' => 800]);
        $avatarConfig = config('cms-kit.images.banners.client_avatar', ['max_size' => 512, 'width' => 100, 'height' => 100]);

        return view('cms-kit::banners.edit', compact('banner', 'languages', 'allowedTypes', 'mainImageConfig', 'avatarConfig'));
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $allowedTypes = config('cms-kit.database.banners.allowed_types', ['image', 'video']);
        $resolvedBannerType = count($allowedTypes) === 1
            ? $allowedTypes[0]
            : $request->input('banner_type');
        $request->merge(['banner_type' => $resolvedBannerType]);
        $videoMaxSize = config('cms-kit.images.banners.banner_video.max_size', 10240);
        $typeRule = 'required|in:' . implode(',', $allowedTypes);
        $mainImageConfig = config('cms-kit.images.banners.main_image', ['max_size' => 2048, 'width' => 1920, 'height' => 800]);
        $avatarConfig = config('cms-kit.images.banners.client_avatar', ['max_size' => 512, 'width' => 100, 'height' => 100]);
        $removeImage = $request->boolean('remove_image');
        $request->validate([
            'banner_type' => $typeRule,
            'image' => [
                Rule::requiredIf(fn () => $resolvedBannerType === 'image' && (!$banner->image || $removeImage)),
                'nullable',
                'image',
                'max:' . $mainImageConfig['max_size'],
            ],
            'remove_image' => 'nullable|boolean',
            'google_avatars.*' => 'nullable|image|max:' . $avatarConfig['max_size'],
            'video_source' => [
                Rule::requiredIf(fn () => $resolvedBannerType === 'video'),
                'nullable',
                'in:url,file',
            ],
            'video_url' => [
                Rule::requiredIf(fn () => $resolvedBannerType === 'video' && $request->input('video_source') === 'url'),
                'nullable',
                'url',
            ],
            'video_file' => [
                // Only demand a fresh upload when this banner doesn't already have a
                // video file on record — editing an existing video banner without
                // touching the upload field must keep the current file, not error out.
                Rule::requiredIf(fn () => $resolvedBannerType === 'video' && $request->input('video_source') === 'file' && !$banner->video_file),
                'nullable',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo',
                'max:' . $videoMaxSize,
            ],
            'translations.*.line_1' => 'required',
            'order_index' => 'nullable|integer|min:1',
        ], [
            'image.image' => 'Banner image must be a valid image file.',
            'image.max' => 'Banner image size must not exceed ' . ($mainImageConfig['max_size'] ?? 2048) . ' KB.',
            'google_avatars.*.image' => 'Each client avatar must be a valid image file.',
            'google_avatars.*.max' => 'Each client avatar must not exceed ' . ($avatarConfig['max_size'] ?? 512) . ' KB.',
            'video_source.required' => 'Please choose a video source.',
            'video_url.required_if' => 'Video URL is required when URL source is selected.',
            'video_url.required' => 'Video URL is required when URL source is selected.',
            'video_url.url' => 'Video URL must be a valid URL.',
            'video_file.required_if' => 'Video file is required when file source is selected.',
            'video_file.required' => 'Video file is required when file source is selected.',
            'video_file.mimetypes' => 'Video file must be an MP4, MOV, or AVI file.',
            'video_file.max' => 'Video file size must not exceed ' . $videoMaxSize . ' KB.',
            'translations.*.line_1.required' => 'Banner heading is required in every language.',
            'order_index.min' => 'Display order must be at least 1.',
        ]);
        $this->validateImageWithinLimits($request, 'image', $mainImageConfig, 'Banner image');
        $this->validateImageArrayWithinLimits($request, 'google_avatars', $avatarConfig, 'Client avatar');

        $data = $request->only(['banner_type', 'video_url', 'order_index', 'status']);
        $data['status'] = $request->has('status');

        $translations = $request->input('translations');
        foreach ($translations as $lang => $fields) {
            if (isset($fields['buttons'])) {
                $translations[$lang]['buttons'] = array_values(array_filter($fields['buttons'], fn($b) => !empty($b['label'])));
            }
            // Keep translatable extra_fields in translations
            // No need to remove them, they'll be stored as is
        }
        $data['translations'] = $translations;

        if ($request->hasFile('image')) {
            if ($banner->image)
                app(\App\Services\ManagedFiles::class)->delete($banner->image);
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'banners');
        } elseif ($removeImage && $banner->image) {
            app(\App\Services\ManagedFiles::class)->delete($banner->image);
            $data['image'] = null;
        }

        // Handle Video File
        if ($resolvedBannerType === 'video' && $request->hasFile('video_file')) {
            if ($banner->video_file)
                app(\App\Services\ManagedFiles::class)->delete($banner->video_file);
            $data['video_file'] = app(\App\Services\ManagedFiles::class)->store($request->file('video_file'), 'banners/videos');
            $data['video_url'] = null; // Clear URL if file is uploaded
        } elseif ($resolvedBannerType === 'video' && $request->input('video_source') === 'file') {
            // File source selected but no new upload — keep the existing video_file
            // untouched (leave it out of $data entirely so fill() doesn't null it).
        } elseif ($resolvedBannerType === 'video' && $request->input('video_url')) {
            if ($banner->video_file) {
                app(\App\Services\ManagedFiles::class)->delete($banner->video_file);
                $data['video_file'] = null;
            }
        } else {
            if ($banner->video_file) {
                app(\App\Services\ManagedFiles::class)->delete($banner->video_file);
            }
            $data['video_file'] = null;
            $data['video_url'] = null;
        }

        $data['image_alt'] = $resolvedBannerType === 'image' && !$removeImage
            ? $request->input('image_alt')
            : null;

        // Social Proof Extra Fields + Config Extra Fields
        $extraFields = $request->only(['google_rating', 'google_review_count', 'google_avatars_alt']);
        
        // Merge any additional fields from config
        if ($request->has('extra_fields')) {
            $extraFields = array_merge($extraFields, $request->input('extra_fields'));
        }
        
        $existingExtra = $banner->extra_fields ?? [];

        if ($request->hasFile('google_avatars')) {
            // Delete old avatars? Optional, but keeping simple for now
            $avatars = [];
            foreach ($request->file('google_avatars') as $file) {
                $avatars[] = app(\App\Services\ManagedFiles::class)->store($file, 'banners/avatars');
            }
            $extraFields['google_avatars'] = $avatars;
        }
        else {
            $extraFields['google_avatars'] = $existingExtra['google_avatars'] ?? [];
        }
        $data['extra_fields'] = $extraFields;

        $banner->update($data);

        return redirect()->route('cms.banners.index')->with('success', 'Banner updated successfully.');
    }

    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);
        $order = $banner->order_index;
        if ($banner->image)
            app(\App\Services\ManagedFiles::class)->delete($banner->image);
        if ($banner->video_file)
            app(\App\Services\ManagedFiles::class)->delete($banner->video_file);
        $banner->delete();

        Banner::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(Banner::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $banner = Banner::findOrFail($id);
        $banner->status = !$banner->status;
        $banner->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:banners,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $banner = Banner::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(Banner::class, (int) $request->order_index);
        $oldOrder = $banner->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                Banner::where('order_index', '>', $oldOrder)
                    ->where('order_index', '<=', $newOrder)
                    ->decrement('order_index');
            } else {
                Banner::where('order_index', '>=', $newOrder)
                    ->where('order_index', '<', $oldOrder)
                    ->increment('order_index');
            }
            $banner->order_index = $newOrder;
            $banner->save();
        }
        $this->normalizeOrderIndex(Banner::class);

        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request)
    {
        $ids = $request->ids;
        $action = $request->action;

        if ($action === 'delete') {
            $banners = Banner::whereIn('id', $ids)->get();
            foreach ($banners as $banner) {
                if ($banner->image)
                    app(\App\Services\ManagedFiles::class)->delete($banner->image);
                if ($banner->video_file)
                    app(\App\Services\ManagedFiles::class)->delete($banner->video_file);
                $banner->delete();
            }
            $this->normalizeOrderIndex(Banner::class);
        } elseif ($action === 'activate') {
            Banner::whereIn('id', $ids)->update(['status' => true]);
        } elseif ($action === 'deactivate') {
            Banner::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}


