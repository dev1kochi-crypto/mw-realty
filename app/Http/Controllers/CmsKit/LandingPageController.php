<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\CmsKit\LandingPage;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\Enquiry;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Support\ManagesOrderIndex;
use App\Support\ValidatesImageDimensions;

class LandingPageController extends Controller
{
    use ValidatesImageDimensions, ManagesOrderIndex;

    protected function rules(bool $isUpdate = false, ?LandingPage $page = null): array
    {
        $languages = Language::where('status', true)->get();
        $imagesConfig = config('cms-kit.images.landing-pages');

        $rules = [
            'page_type' => ['required', \Illuminate\Validation\Rule::in([LandingPage::TYPE_TEMPLATE, LandingPage::TYPE_CUSTOM])],
            'slug' => ['nullable', 'string', 'max:255', \Illuminate\Validation\Rule::unique('landing_pages', 'slug')->ignore($page?->id)],
            'published_at' => 'nullable|date',
            'order_index' => 'nullable|integer|min:1',
            'use_site_header_footer' => 'nullable|boolean',
            // Accepts a full URL or a site-relative path (e.g. /thank-you or another landing page's slug) —
            // not validated as a strict URL since a relative path wouldn't pass that rule.
            'thank_you_url' => 'nullable|string|max:2048',
            // One shared HTML/CSS — text differences per language are handled separately below,
            // not by duplicating the markup.
            'custom_html' => 'required_if:page_type,' . LandingPage::TYPE_CUSTOM . '|nullable|string',
            'custom_css' => 'nullable|string',
            'custom_js' => 'nullable|string',
            'text_translations' => 'nullable|array',
            'metadata' => 'nullable|array',
            'metadata.meta_title' => 'nullable|string|max:255',
            'metadata.meta_description' => 'nullable|string|max:500',
            'metadata.meta_keywords' => 'nullable|string|max:500',
            'metadata.og_title' => 'nullable|string|max:255',
            'metadata.og_description' => 'nullable|string|max:500',
            'metadata.canonical_url' => 'nullable|url|max:2048',
            'metadata.other_meta_tags' => 'nullable|string',
            'metadata.og_image' => 'nullable|image|max:' . ($imagesConfig['metadata_og_image']['max_size'] ?? 4096),
            'remove_metadata_og_image' => 'nullable|boolean',
        ];

        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.title"] = 'required|string|max:255';
            $rules["translations.{$lang->code}.content"] = 'required_if:page_type,' . LandingPage::TYPE_TEMPLATE . '|nullable|string';
        }

        return $rules;
    }

    /** Whichever fields the chosen page_type doesn't use get nulled out, so stale content never lingers. */
    protected function cleanForType(array $data): array
    {
        if ($data['page_type'] === LandingPage::TYPE_CUSTOM) {
            foreach ($data['translations'] ?? [] as $lang => $values) {
                $data['translations'][$lang]['content'] = null;
            }
        } else {
            $data['custom_html'] = null;
            $data['custom_css'] = null;
            $data['custom_js'] = null;
            $data['text_translations'] = null;
        }

        return $data;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = LandingPage::orderBy('order_index', 'asc');
            return \App\Support\TranslatedTable::column(DataTables::of($data), 'title', 'title')
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
                })
                ->addColumn('title', function ($row) {
                    return $row->getTranslation('title');
                })
                ->addColumn('slug', function ($row) {
                    return '<code>/' . e($row->slug) . '</code>';
                })
                ->addColumn('page_type', function ($row) {
                    return $row->isCustom()
                        ? '<span class="badge bg-info-subtle text-info-emphasis">Custom HTML</span>'
                        : '<span class="badge bg-primary-subtle text-primary-emphasis">Template</span>';
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
                    if (auth('cms')->user()->can('landing-pages.edit')) {
                        $btns .= '<a href="' . route('cms.landing-pages.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('landing-pages.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'slug', 'page_type', 'status', 'order', 'action'])
                ->make(true);
        }

        return view('cms-kit::landing-pages.index');
    }

    /**
     * Form submissions captured from any landing page (see LandingPageEnquiryController) — same
     * Enquiry records the site-wide Enquiries page shows, just pre-filtered to landing-page sources
     * and with a "which page" filter, so they're easy to find without digging through everything else.
     */
    public function enquiries(Request $request)
    {
        $landingPageSource = 'Landing Page:%';

        if ($request->ajax()) {
            $query = Enquiry::where('page_source', 'like', $landingPageSource);

            if ($request->filled('page_source') && $request->page_source !== 'All') {
                $query->where('page_source', $request->page_source);
            }
            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date));
            }
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date));
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('select_all', function ($row) {
                    return '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">';
                })
                ->addColumn('source_page', function ($row) {
                    return e(Str::after($row->page_source, 'Landing Page: '));
                })
                ->addColumn('date', function ($row) {
                    return $row->created_at->format('d M Y H:i');
                })
                ->addColumn('action', function ($row) {
                    $btns = '<div class="btn-group">';
                    if (auth('cms')->user()->can('landing-pages.view')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-primary view-enquiry" data-id="' . $row->id . '"><i class="fas fa-eye"></i></button>';
                    }
                    if (auth('cms')->user()->can('landing-pages.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['select_all', 'source_page', 'action'])
                ->make(true);
        }

        $sources = Enquiry::where('page_source', 'like', $landingPageSource)
            ->select('page_source')->distinct()->pluck('page_source')->filter()->values();
        $hasData = Enquiry::where('page_source', 'like', $landingPageSource)->exists();

        return view('cms-kit::landing-pages.enquiries', compact('sources', 'hasData'));
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $imagesConfig = config('cms-kit.images.landing-pages');
        $nextOrder = LandingPage::count() + 1;
        return view('cms-kit::landing-pages.create', compact('languages', 'imagesConfig', 'nextOrder'));
    }

    public function store(Request $request)
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('translations.' . config('app.fallback_locale') . '.title', ''))]);
        $request->validate($this->rules());

        $data = $request->only(['page_type', 'translations', 'order_index', 'custom_html', 'custom_css', 'custom_js', 'text_translations', 'thank_you_url']);
        $data['status'] = $request->boolean('status', true);
        $data['use_site_header_footer'] = $request->boolean('use_site_header_footer', true);
        $data['published_at'] = $data['page_type'] === LandingPage::TYPE_TEMPLATE ? $request->input('published_at') : null;
        $data['slug'] = $request->filled('slug')
            ? Str::slug($request->slug)
            : Str::slug($request->input('translations.' . config('app.fallback_locale') . '.title'));

        $data = $this->cleanForType($data);

        $order = $this->resolveOrderForCreate(LandingPage::class, $request->order_index ? (int) $request->order_index : null);
        LandingPage::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        $metadata = $request->input('metadata', []);
        if ($request->hasFile('metadata.og_image')) {
            $metadata['og_image'] = app(\App\Services\ManagedFiles::class)->store($request->file('metadata.og_image'), 'landing-pages/metadata');
        }
        $data['metadata'] = $metadata;

        $page = LandingPage::create($data);

        // The page needs an id before images can move into its own folder (see promoteContentImages()),
        // so this only happens once we actually have one — a cheap follow-up update, only when there's
        // a temp image to promote in the first place.
        if ($page->custom_html) {
            $promotedHtml = $this->promoteContentImages($page->custom_html, $page->id);
            if ($promotedHtml !== $page->custom_html) {
                $page->update(['custom_html' => $promotedHtml]);
            }
        }

        return redirect()->route('cms.landing-pages.index')->with('success', 'Landing page created successfully.');
    }

    public function edit($id)
    {
        $page = LandingPage::findOrFail($id);
        $languages = Language::where('status', true)->get();
        $imagesConfig = config('cms-kit.images.landing-pages');

        return view('cms-kit::landing-pages.edit', compact('page', 'languages', 'imagesConfig'));
    }

    public function update(Request $request, $id)
    {
        $page = LandingPage::findOrFail($id);

        if ($request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->input('slug'))]);
        }
        $request->validate($this->rules(true, $page));

        $data = $request->only(['page_type', 'translations', 'order_index', 'custom_html', 'custom_css', 'custom_js', 'text_translations', 'thank_you_url']);
        $data['status'] = $request->boolean('status');
        $data['use_site_header_footer'] = $request->boolean('use_site_header_footer');
        $data['published_at'] = $data['page_type'] === LandingPage::TYPE_TEMPLATE ? $request->input('published_at') : null;
        if ($request->filled('slug')) {
            $data['slug'] = Str::slug($request->slug);
        }

        $data = $this->cleanForType($data);

        if (!empty($data['custom_html'])) {
            $data['custom_html'] = $this->promoteContentImages($data['custom_html'], $page->id);
        }

        $metadata = $request->input('metadata', []);
        $existingMetadata = $page->metadata ?? [];
        if ($request->hasFile('metadata.og_image')) {
            if (!empty($existingMetadata['og_image'])) {
                app(\App\Services\ManagedFiles::class)->delete($existingMetadata['og_image']);
            }
            $metadata['og_image'] = app(\App\Services\ManagedFiles::class)->store($request->file('metadata.og_image'), 'landing-pages/metadata');
        } elseif ($request->boolean('remove_metadata_og_image') && !empty($existingMetadata['og_image'])) {
            app(\App\Services\ManagedFiles::class)->delete($existingMetadata['og_image']);
            $metadata['og_image'] = null;
        } else {
            $metadata['og_image'] = $existingMetadata['og_image'] ?? null;
        }
        $data['metadata'] = $metadata;

        $page->update($data);

        return redirect()->route('cms.landing-pages.index')->with('success', 'Landing page updated successfully.');
    }

    public function destroy($id)
    {
        $page = LandingPage::findOrFail($id);
        $order = $page->order_index;

        if (!empty($page->metadata['og_image'])) {
            app(\App\Services\ManagedFiles::class)->delete($page->metadata['og_image']);
        }
        Storage::disk('public')->deleteDirectory('landing-pages/content/' . $page->id);

        $page->delete();

        LandingPage::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(LandingPage::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $page = LandingPage::findOrFail($id);
        $page->status = !$page->status;
        $page->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:landing_pages,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $page = LandingPage::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(LandingPage::class, (int) $request->order_index);
        $oldOrder = $page->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                LandingPage::where('order_index', '>', $oldOrder)->where('order_index', '<=', $newOrder)->decrement('order_index');
            } else {
                LandingPage::where('order_index', '>=', $newOrder)->where('order_index', '<', $oldOrder)->increment('order_index');
            }
            $page->order_index = $newOrder;
            $page->save();
        }
        $this->normalizeOrderIndex(LandingPage::class);

        return response()->json(['success' => true]);
    }

    /** Feeds the "Detect Text" button — reads the HTML currently in the textarea and lists every distinct string found. */
    public function extractText(Request $request)
    {
        $strings = LandingPage::extractTranslatableStrings($request->input('custom_html', ''));

        return response()->json(['success' => true, 'strings' => $strings]);
    }

    /** Feeds the "Detect Images" table — reads the HTML currently in the textarea and lists every image src found. */
    public function extractImages(Request $request)
    {
        $images = LandingPage::extractImageSources($request->input('custom_html', ''));

        return response()->json(['success' => true, 'images' => $images]);
    }

    /** Feeds the "Preview" button — renders the current HTML/CSS with the given language's translations swapped in, for an iframe. */
    public function preview(Request $request)
    {
        $temp = new LandingPage([
            'custom_html' => $request->input('custom_html', ''),
            'custom_css' => $request->input('custom_css', ''),
            'custom_js' => $request->input('custom_js', ''),
            'text_translations' => ['preview' => $request->input('rows', [])],
        ]);

        $lang = $request->input('lang', config('app.fallback_locale'));
        $document = $temp->renderDocument('preview', $lang);

        return response($document)->header('Content-Type', 'text/html');
    }

    /**
     * Feeds the "Insert Image"/"Replace" helpers in the custom-HTML editor — uploads and returns the public URL.
     * Stored under a temp subfolder first (with the original filename kept, so the folder stays readable in a
     * file browser): nothing is promoted to permanent storage until the page is actually saved (see
     * promoteContentImages()), so images from abandoned edits/testing don't pile up on disk. A daily cleanup
     * command (landing-pages:cleanup-temp-images) removes anything left here unsaved.
     */
    public function uploadContentImage(Request $request)
    {
        $imagesConfig = config('cms-kit.images.landing-pages.content_image', []);
        $request->validate([
            'image' => 'required|image|max:' . ($imagesConfig['max_size'] ?? 4096),
        ]);
        $this->validateImageWithinLimits($request, 'image', $imagesConfig, 'Content image');

        $file = $request->file('image');
        $disk = Storage::disk('public');
        $directory = 'landing-pages/content/tmp';
        $filename = $this->uniqueFilename($disk, $directory, $this->sanitizeFilename($file->getClientOriginalName()));
        $path = $file->storeAs($directory, $filename, 'public');

        return response()->json(['success' => true, 'url' => asset('storage/' . $path)]);
    }

    /**
     * Called via sendBeacon when the admin clicks "Cancel" while editing (see the Code tab's JS) — deletes
     * temp images uploaded during this edit right away, instead of waiting for the daily sweep to catch them.
     */
    public function discardTempImages(Request $request)
    {
        $disk = Storage::disk('public');

        foreach ((array) $request->input('files', []) as $filename) {
            if (!is_string($filename) || $filename === '') {
                continue;
            }
            // basename() strips any directory traversal — every discard target is forced back under tmp/.
            $path = 'landing-pages/content/tmp/' . basename($filename);
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Moves any temp-uploaded content images that made it into the final HTML into this page's own
     * permanent folder (landing-pages/content/{id}/), and rewrites their URLs in place. Keyed by the page's
     * numeric id rather than its title/slug, since those are editable — an id never changes, so paths already
     * saved in the HTML never go stale from a rename. Anything uploaded but never actually used in the saved
     * HTML is simply left in the temp folder for the cleanup command to sweep up later.
     */
    private function promoteContentImages(string $html, int $pageId): string
    {
        $disk = Storage::disk('public');
        $marker = 'landing-pages/content/tmp/';
        $destDir = 'landing-pages/content/' . $pageId;

        foreach (LandingPage::extractImageSources($html) as $src) {
            $pos = strpos($src, $marker);
            if ($pos === false) {
                continue;
            }

            $tmpPath = substr($src, $pos);
            if (!$disk->exists($tmpPath)) {
                continue;
            }

            $filename = $this->uniqueFilename($disk, $destDir, basename($tmpPath));
            $permPath = $destDir . '/' . $filename;
            $disk->move($tmpPath, $permPath);

            $html = str_replace($src, asset('storage/' . $permPath), $html);
        }

        return $html;
    }

    /** Strips any path, keeps the original name recognizable, and drops characters that aren't safe on disk. */
    private function sanitizeFilename(string $name): string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'image';

        return $ext ? "{$base}.{$ext}" : $base;
    }

    /** Appends -1, -2, ... until the name no longer collides with a file already in that folder. */
    private function uniqueFilename(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $directory, string $filename): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $candidate = $filename;

        for ($i = 1; $disk->exists($directory . '/' . $candidate); $i++) {
            $candidate = $ext ? "{$base}-{$i}.{$ext}" : "{$base}-{$i}";
        }

        return $candidate;
    }

    public function bulkAction(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));
        $action = $request->input('action');

        if (empty($ids) || !$action) {
            return response()->json(['success' => false, 'message' => 'No action or items selected.'], 422);
        }

        if ($action === 'delete') {
            $pages = LandingPage::whereIn('id', $ids)->get();
            $disk = Storage::disk('public');
            foreach ($pages as $page) {
                if (!empty($page->metadata['og_image'])) {
                    app(\App\Services\ManagedFiles::class)->delete($page->metadata['og_image']);
                }
                $disk->deleteDirectory('landing-pages/content/' . $page->id);
                $page->delete();
            }
            $this->normalizeOrderIndex(LandingPage::class);
        }

        if (in_array($action, ['active', 'activate'], true)) {
            LandingPage::whereIn('id', $ids)->update(['status' => true]);
        }

        if (in_array($action, ['inactive', 'deactivate'], true)) {
            LandingPage::whereIn('id', $ids)->update(['status' => false]);
        }

        return response()->json(['success' => true]);
    }
}
