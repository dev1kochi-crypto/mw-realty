<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\Testimonial;
use App\Models\CmsKit\SectionLabel;
use App\Models\CmsKit\Language;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use App\Support\ManagesOrderIndex;
use App\Support\ValidatesImageDimensions;

class TestimonialController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $data = Testimonial::orderBy('order_index', 'asc');
            return DataTables::eloquent($data)
                ->addIndexColumn()
                ->addColumn('checkbox', function($row){
                    return '<input type="checkbox" name="ids[]" value="'.$row->id.'" class="form-check-input row-checkbox">';
                })
                ->addColumn('image', function($row){
                    if (!$row->image) return '<span class="text-muted">No Image</span>';
                    $img = '<img src="'.media_url($row->image).'" class="rounded border" style="height: 40px; width: 40px; object-fit: cover;">';
                    if ($row->type === 'video') {
                        $img = '<span class="position-relative d-inline-block">'.$img.'<i class="fas fa-play-circle position-absolute top-50 start-50 translate-middle text-white" style="text-shadow: 0 0 3px rgba(0,0,0,.7);"></i></span>';
                    }
                    return $img;
                })
                ->addColumn('name_info', function($row){
                    return '<strong>'.e($row->getTranslation('name') ?: 'No Name').'</strong><br><small class="text-muted">'.e($row->getTranslation('designation') ?: '').'</small>';
                })
                ->addColumn('content_preview', function($row){
                    return '<div class="text-truncate" style="max-width: 200px;">'.e(strip_tags($row->getTranslation('content') ?: '')).'</div>';
                })
                ->addColumn('rating', function($row){
                    if(!config('cms-kit.database.testimonials.items.rating')) return '';
                    return '<span class="text-warning">'.str_repeat('<i class="fas fa-star"></i>', $row->rating).'</span>';
                })
                ->addColumn('order', function($row){
                    return '<input type="number" min="1" value="'.$row->order_index.'" class="form-control form-control-sm text-center reorder-input" style="width: 60px;" data-id="'.$row->id.'">';
                })
                ->addColumn('status_toggle', function($row){
                    $checked = $row->status ? 'checked' : '';
                    return '<div class="form-check form-switch p-0 m-0 d-flex justify-content-center">
                                <input class="form-check-input status-toggle" type="checkbox" data-id="'.$row->id.'" '.$checked.' style="cursor: pointer; margin-left: 0;">
                            </div>';
                })
                ->addColumn('actions', function($row){
                    $editBtn = '<a href="'.route('cms.testimonials.edit', $row->id).'" class="btn btn-link text-primary p-0 me-2"><i class="fas fa-edit"></i></a>';
                    $deleteBtn = '<form action="'.route('cms.testimonials.destroy', $row->id).'" method="POST" style="display:inline;">'.csrf_field().method_field('DELETE').'<button type="submit" class="btn btn-link text-danger p-0" onclick="return confirm(\'Delete this and reorder others?\')"><i class="fas fa-trash"></i></button></form>';
                    return '<div class="text-end pe-3">'.$editBtn.$deleteBtn.'</div>';
                })
                ->rawColumns(['checkbox', 'image', 'name_info', 'content_preview', 'rating', 'order', 'status_toggle', 'actions'])
                ->make(true);
        }

        $languages = Language::active()->get();
        $section = SectionLabel::firstOrCreate(['section_key' => 'testimonials']);
        return view('cms-kit::testimonials.index', compact('section', 'languages'));
    }

    public function create()
    {
        $languages = Language::active()->get();
        $nextOrder = (Testimonial::max('order_index') ?? 0) + 1;

        return view('cms-kit::testimonials.create', compact('languages', 'nextOrder'));
    }

    public function edit($id)
    {
        $testimonial = Testimonial::findOrFail($id);
        $languages = Language::active()->get();
        $nextOrder = $testimonial->order_index;

        return view('cms-kit::testimonials.edit', compact('testimonial', 'languages', 'nextOrder'));
    }

    public function updateSection(Request $request)
    {
        $languages = Language::active()->get();
        $section = SectionLabel::firstOrCreate(['section_key' => 'testimonials']);
        $sectionConfig = config('cms-kit.database.testimonials.section', []);
        $requiredFields = $sectionConfig['required'] ?? [];
        
        $rules = [];
        foreach ($languages as $lang) {
            if (in_array('title', $requiredFields)) {
                $rules["translations.{$lang->code}.section_title"] = 'required';
            }
            if (in_array('sub_heading_1', $requiredFields)) {
                $rules["translations.{$lang->code}.section_sub_heading_1"] = 'required';
            }
            if (in_array('sub_heading_2', $requiredFields)) {
                $rules["translations.{$lang->code}.section_sub_heading_2"] = 'required';
            }
            if (in_array('description', $requiredFields)) {
                $rules["description.{$lang->code}"] = 'required';
            }
        }

        if (in_array('section_image', $requiredFields) && !$request->hasFile('section_image') && (!$section->section_image || $request->boolean('remove_section_image'))) {
             $rules['section_image'] = 'required';
        }

        if (in_array('banner', $requiredFields) && !$request->hasFile('banner') && (!$section->banner || $request->boolean('remove_banner'))) {
             $rules['banner'] = 'required';
        }

        $rules['remove_section_image'] = 'nullable|boolean';
        $rules['remove_banner'] = 'nullable|boolean';

        $request->validate($rules);

        $translations = [];
        $description = [];
        foreach ($languages as $lang) {
            $transData = [];
            if ($sectionConfig['title'] ?? false) {
                $transData['section_title'] = $request->input("translations.{$lang->code}.section_title");
            }
            if ($sectionConfig['sub_heading_1'] ?? false) {
                $transData['section_sub_heading_1'] = $request->input("translations.{$lang->code}.section_sub_heading_1");
            }
            if ($sectionConfig['sub_heading_2'] ?? false) {
                $transData['section_sub_heading_2'] = $request->input("translations.{$lang->code}.section_sub_heading_2");
            }
            if ($sectionConfig['description'] ?? false) {
                $description[$lang->code] = $request->input("description.{$lang->code}");
            }

            $translations[$lang->code] = $transData;
        }

        $data = [
            'translations' => $translations,
            'description' => $description,
            'status' => $request->has('status'),
        ];

        if (($sectionConfig['section_image'] ?? false) && $request->hasFile('section_image')) {
            if ($section->section_image) {
                app(\App\Services\ManagedFiles::class)->delete($section->section_image);
            }
            $data['section_image'] = app(\App\Services\ManagedFiles::class)->store($request->file('section_image'), 'testimonials');
        } elseif ($request->boolean('remove_section_image') && $section->section_image) {
            app(\App\Services\ManagedFiles::class)->delete($section->section_image);
            $data['section_image'] = null;
        }
        $data['section_image_alt'] = $request->boolean('remove_section_image') ? null : $request->input('section_image_alt');

        if (($sectionConfig['banner'] ?? false) && $request->hasFile('banner')) {
            if ($section->banner) {
                app(\App\Services\ManagedFiles::class)->delete($section->banner);
            }
            $data['banner'] = app(\App\Services\ManagedFiles::class)->store($request->file('banner'), 'testimonials');
        } elseif ($request->boolean('remove_banner') && $section->banner) {
            app(\App\Services\ManagedFiles::class)->delete($section->banner);
            $data['banner'] = null;
        }
        $data['banner_alt'] = $request->boolean('remove_banner') ? null : $request->input('banner_alt');

        $extra_fields = [];

        $extra_fields = [];
        foreach ($sectionConfig['extra_fields'] ?? [] as $key => $field) {
            $extra_fields[$key] = $request->input("extra_fields.{$key}");
        }
        if ($sectionConfig['display_home'] ?? false) {
            $extra_fields['display_home'] = $request->boolean('display_home');
        }
        $data['extra_fields'] = $extra_fields;

        $section->update($data);

        return redirect()->back()->with('success', 'Section settings updated.');
    }

    protected function validateItem(Request $request, array $itemConfig, $languages, ?Testimonial $testimonial = null)
    {
        $requiredFields = $itemConfig['required'] ?? [];
        $rules = [];

        foreach ($languages as $lang) {
            if (in_array('name', $requiredFields)) {
                $rules["translations.{$lang->code}.name"] = 'required';
            }
            if (in_array('designation', $requiredFields)) {
                $rules["translations.{$lang->code}.designation"] = 'required';
            }
            // Video testimonials show the photo + play button, not the text card, so
            // content isn't required for them even when it's required for text ones.
            if (in_array('content', $requiredFields) && $request->input('type', 'text') !== 'video') {
                $rules["translations.{$lang->code}.content"] = 'required';
            }
        }

        if (in_array('rating', $requiredFields)) {
            $rules['rating'] = 'required';
        }

        if (
            in_array('image', $requiredFields)
            && !$request->hasFile('image')
            && (
                $request->isMethod('post')
                || !$testimonial?->image
                || $request->boolean('remove_image')
            )
        ) {
            $rules['image'] = 'required';
        }

        $rules['remove_image'] = 'nullable|boolean';
        $rules['order_index'] = 'nullable|integer|min:1';

        $rules['type'] = 'nullable|in:text,video';
        $isVideo = $request->input('type') === 'video';
        $rules['video_source'] = $isVideo ? 'required|in:url,file' : 'nullable|in:url,file';
        $rules['video_url'] = [
            $isVideo && $request->input('video_source') === 'url' ? 'required' : 'nullable',
            'nullable', 'url',
        ];
        $requiresVideoFile = $isVideo && $request->input('video_source') === 'file'
            && !$request->hasFile('video_file')
            && ($request->isMethod('post') || !$testimonial?->video_file);
        $rules['video_file'] = [
            $requiresVideoFile ? 'required' : 'nullable',
            'nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo', 'max:10240',
        ];

        $request->validate($rules);
    }

    public function store(Request $request)
    {
        $languages = Language::active()->get();
        $itemConfig = config('cms-kit.database.testimonials.items', []);
        
        $this->validateItem($request, $itemConfig, $languages);
        $this->validateImageWithinLimits($request, 'image', config('cms-kit.images.testimonials.item_image', []), 'Testimonial image');

        $order = $this->resolveOrderForCreate(Testimonial::class, $request->order_index ? (int) $request->order_index : null);
        
        // Shift existing items
        Testimonial::where('order_index', '>=', $order)->increment('order_index');

        $translations = [];
        foreach ($languages as $lang) {
            $transData = [];
            if ($itemConfig['name'] ?? false) {
                $transData['name'] = $request->input("translations.{$lang->code}.name");
            }
            if ($itemConfig['designation'] ?? false) {
                $transData['designation'] = $request->input("translations.{$lang->code}.designation");
            }
            if ($itemConfig['content'] ?? false) {
                $transData['content'] = $request->input("translations.{$lang->code}.content");
            }
            
            $translations[$lang->code] = $transData;
        }

        $extra_fields = [];
        foreach ($itemConfig['extra_fields'] ?? [] as $key => $field) {
            $extra_fields[$key] = $request->input("extra_fields.{$key}");
        }

        $data = [
            'translations' => $translations,
            'extra_fields' => $extra_fields,
            'order_index' => $order,
            'status' => $request->has('status'),
        ];

        if ($itemConfig['rating'] ?? false) {
            $data['rating'] = $request->rating;
        }

        if (($itemConfig['image'] ?? false) && $request->hasFile('image')) {
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'testimonials');
        }
        $data['image_alt'] = $request->input('image_alt');

        $data['type'] = $request->input('type', 'text');
        if ($data['type'] === 'video') {
            $data['video_source'] = $request->input('video_source');
            if ($request->hasFile('video_file')) {
                $data['video_file'] = app(\App\Services\ManagedFiles::class)->store($request->file('video_file'), 'testimonials/videos');
                $data['video_url'] = null;
            } else {
                $data['video_url'] = $request->input('video_url');
                $data['video_file'] = null;
            }
        } else {
            $data['video_source'] = null;
            $data['video_url'] = null;
            $data['video_file'] = null;
        }

        Testimonial::create($data);
        $this->normalizeOrderIndex(Testimonial::class);

        return redirect()->route('cms.testimonials.index')->with('success', 'Testimonial added.');
    }

    public function update(Request $request, $id)
    {
        $testimonial = Testimonial::findOrFail($id);
        $languages = Language::active()->get();
        $itemConfig = config('cms-kit.database.testimonials.items', []);
        
        $this->validateItem($request, $itemConfig, $languages, $testimonial);
        $this->validateImageWithinLimits($request, 'image', config('cms-kit.images.testimonials.item_image', []), 'Testimonial image');

        $newOrder = $request->order_index ? (int) $request->order_index : $testimonial->order_index;
        $newOrder = $this->resolveOrderForReorder(Testimonial::class, $newOrder);
        if ($newOrder != $testimonial->order_index) {
            $this->reorderItem($testimonial, $newOrder);
        }

        $translations = [];
        foreach ($languages as $lang) {
            $transData = [];
            if ($itemConfig['name'] ?? false) {
                $transData['name'] = $request->input("translations.{$lang->code}.name");
            }
            if ($itemConfig['designation'] ?? false) {
                $transData['designation'] = $request->input("translations.{$lang->code}.designation");
            }
            if ($itemConfig['content'] ?? false) {
                $transData['content'] = $request->input("translations.{$lang->code}.content");
            }
            $translations[$lang->code] = $transData;
        }

        $extra_fields = [];
        foreach ($itemConfig['extra_fields'] ?? [] as $key => $field) {
            $extra_fields[$key] = $request->input("extra_fields.{$key}");
        }

        $data = [
            'translations' => $translations,
            'extra_fields' => $extra_fields,
            'order_index' => $newOrder,
            'status' => $request->has('status')
        ];

        if ($itemConfig['rating'] ?? false) {
            $data['rating'] = $request->rating;
        }

        if (($itemConfig['image'] ?? false) && $request->hasFile('image')) {
            if ($testimonial->image) {
                app(\App\Services\ManagedFiles::class)->delete($testimonial->image);
            }
            $data['image'] = app(\App\Services\ManagedFiles::class)->store($request->file('image'), 'testimonials');
        } elseif ($request->boolean('remove_image') && $testimonial->image) {
            app(\App\Services\ManagedFiles::class)->delete($testimonial->image);
            $data['image'] = null;
        }
        $data['image_alt'] = $request->boolean('remove_image') ? null : $request->input('image_alt');

        $data['type'] = $request->input('type', 'text');
        if ($data['type'] === 'video') {
            $data['video_source'] = $request->input('video_source');
            if ($request->hasFile('video_file')) {
                if ($testimonial->video_file) {
                    app(\App\Services\ManagedFiles::class)->delete($testimonial->video_file);
                }
                $data['video_file'] = app(\App\Services\ManagedFiles::class)->store($request->file('video_file'), 'testimonials/videos');
                $data['video_url'] = null;
            } elseif ($data['video_source'] === 'url') {
                if ($testimonial->video_file) {
                    app(\App\Services\ManagedFiles::class)->delete($testimonial->video_file);
                }
                $data['video_file'] = null;
                $data['video_url'] = $request->input('video_url');
            } else {
                $data['video_url'] = null;
            }
        } else {
            if ($testimonial->video_file) {
                app(\App\Services\ManagedFiles::class)->delete($testimonial->video_file);
            }
            $data['video_source'] = null;
            $data['video_url'] = null;
            $data['video_file'] = null;
        }

        $testimonial->update($data);
        $this->normalizeOrderIndex(Testimonial::class);

        return redirect()->route('cms.testimonials.index')->with('success', 'Testimonial updated.');
    }

    protected function reorderItem($item, $newOrder)
    {
        $oldOrder = $item->order_index;
        if ($newOrder > $oldOrder) {
            Testimonial::where('order_index', '>', $oldOrder)
                ->where('order_index', '<=', $newOrder)
                ->decrement('order_index');
        } elseif ($newOrder < $oldOrder) {
            Testimonial::where('order_index', '>=', $newOrder)
                ->where('order_index', '<', $oldOrder)
                ->increment('order_index');
        }
    }

    public function destroy($id)
    {
        $testimonial = Testimonial::findOrFail($id);
        $order = $testimonial->order_index;
        if ($testimonial->image) {
            app(\App\Services\ManagedFiles::class)->delete($testimonial->image);
        }
        $testimonial->delete();
        
        // Fill the gap
        Testimonial::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(Testimonial::class);

        return redirect()->back()->with('success', 'Testimonial deleted.');
    }

    public function toggleStatus($id)
    {
        $testimonial = Testimonial::findOrFail($id);
        $testimonial->update(['status' => !$testimonial->status]);
        return response()->json(['success' => true, 'status' => $testimonial->status]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:testimonials,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $id = $request->id;
        $newOrder = $this->resolveOrderForReorder(Testimonial::class, (int) $request->order_index);
        $testimonial = Testimonial::findOrFail($id);
        $this->reorderItem($testimonial, $newOrder);
        $testimonial->update(['order_index' => $newOrder]);
        $this->normalizeOrderIndex(Testimonial::class);
        return redirect()->back()->with('success', 'Order updated.');
    }

    public function bulkAction(Request $request)
    {
        $ids = $request->ids;
        $action = $request->action;

        if (!$ids || !is_array($ids)) {
            return redirect()->back()->with('error', 'No items selected.');
        }

        if ($action == 'delete') {
            foreach ($ids as $id) {
                $testimonial = Testimonial::find($id);
                if ($testimonial) {
                    $order = $testimonial->order_index;
                    if ($testimonial->image) {
                        app(\App\Services\ManagedFiles::class)->delete($testimonial->image);
                    }
                    $testimonial->delete();
                    Testimonial::where('order_index', '>', $order)->decrement('order_index');
                }
            }
            $this->normalizeOrderIndex(Testimonial::class);
            return redirect()->back()->with('success', 'Selected items deleted.');
        }

        if ($action == 'active') {
            Testimonial::whereIn('id', $ids)->update(['status' => true]);
            return redirect()->back()->with('success', 'Selected items activated.');
        }

        if ($action == 'inactive') {
            Testimonial::whereIn('id', $ids)->update(['status' => false]);
            return redirect()->back()->with('success', 'Selected items inactivated.');
        }

        return redirect()->back()->with('error', 'Invalid action.');
    }
}


