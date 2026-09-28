<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\Language;
use App\Models\CmsKit\MarketInsight;
use App\Models\CmsKit\MarketInsightTerm;
use App\Models\CmsKit\SectionLabel;
use App\Services\ManagedFiles;
use App\Support\ManagesOrderIndex;
use CMS\SiteManager\Services\UrlRedirectService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

/**
 * Admin > Market Insights — research-style posts with a topic, region, headline figures,
 * key takeaways and an optional downloadable report, plus the /market-insights page header.
 */
class MarketInsightController extends Controller
{
    use ManagesOrderIndex;

    private const TRENDS = ['up', 'down', 'flat'];

    /** Uploaded files => Cloudinary folder. Card + detail images are required; the rest optional. */
    private const FILE_FIELDS = [
        'card_image' => 'market-insights/cards',
        'detail_image' => 'market-insights/details',
        'featured_image' => 'market-insights/featured',
        'report_file' => 'market-insights/reports',
    ];

    /** Optional files an admin can remove without replacing. */
    private const REMOVABLE_FILES = ['featured_image', 'report_file'];

    public function __construct(private readonly ManagedFiles $files)
    {
    }

    private function config(string $key, $default = null)
    {
        return config("cms-kit.database.market_insights.{$key}", $default);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $cmsUser = auth('cms')->user();

            return \App\Support\TranslatedTable::column(DataTables::of(MarketInsight::orderBy('order_index')), 'title', 'title')
                ->addIndexColumn()
                ->addColumn('select_all', fn ($row) => '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">')
                ->addColumn('image', fn ($row) => $row->card_image
                    ? '<img src="' . e(media_url($row->card_image)) . '" class="img-thumbnail" style="height: 40px;">'
                    : '-')
                ->addColumn('title', fn ($row) => $row->getTranslation('title')) // DataTables escapes non-raw columns itself
                ->editColumn('topic', fn ($row) => '<span class="badge bg-light text-dark border">' . e(MarketInsightTerm::label('topic', $row->topic, 'en')) . '</span>'
                    . ($row->region ? '<div class="small text-muted mt-1">' . e(MarketInsightTerm::label('region', $row->region, 'en')) . '</div>' : ''))
                ->editColumn('published_at', fn ($row) => '<span class="text-nowrap">' . $row->published_at?->format('d M Y') . '</span>')
                ->addColumn('featured', fn ($row) => '<div class="form-check form-switch d-inline-block"><input class="form-check-input toggle-featured" type="checkbox" data-id="' . $row->id . '" ' . ($row->is_featured ? 'checked' : '') . '></div>')
                ->addColumn('status', fn ($row) => '<div class="form-check form-switch d-inline-block"><input class="form-check-input toggle-status" type="checkbox" data-id="' . $row->id . '" ' . ($row->status ? 'checked' : '') . '></div>')
                ->orderColumn('order', fn ($query, $direction) => $query->reorder()->orderBy('order_index', $direction))
                ->addColumn('order', fn ($row) => '<input type="number" min="1" class="form-control form-control-sm reorder-input" data-id="' . $row->id . '" value="' . $row->order_index . '" style="width: 64px;">')
                ->addColumn('action', function ($row) use ($cmsUser) {
                    $buttons = '<div class="btn-group">';
                    if ($cmsUser?->can('market-insights.edit')) {
                        $buttons .= '<a href="' . route('cms.market-insights.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if ($cmsUser?->can('market-insights.delete')) {
                        $buttons .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }

                    return $buttons . '</div>';
                })
                ->rawColumns(['select_all', 'image', 'topic', 'published_at', 'featured', 'status', 'order', 'action'])
                ->make(true);
        }

        $section = SectionLabel::where('section_key', 'market-insights')->first();
        $languages = Language::where('status', true)->get();

        return view('cms-kit::market-insights.index', compact('section', 'languages'));
    }

    public function create()
    {
        return view('cms-kit::market-insights.create', $this->formData(new MarketInsight([
            'published_at' => now(),
            'status' => true,
            'region' => 'dubai',
        ])));
    }

    public function edit($id)
    {
        return view('cms-kit::market-insights.edit', $this->formData(MarketInsight::findOrFail($id)));
    }

    /** slug => English title for the form's select: active terms, plus the post's current one even if inactive. */
    private function termOptions(string $type, ?string $current): array
    {
        return MarketInsightTerm::ofType($type)
            ->where(fn ($q) => $q->where('status', true)->when($current, fn ($q) => $q->orWhere('slug', $current)))
            ->ordered()->get()
            ->mapWithKeys(fn ($term) => [$term->slug => $term->getTranslation('title', config('app.fallback_locale', 'en'))])
            ->all();
    }

    private function formData(MarketInsight $insight): array
    {
        return [
            'insight' => $insight,
            'languages' => Language::where('status', true)->get(),
            'topics' => $this->termOptions('topic', $insight->topic),
            'regions' => $this->termOptions('region', $insight->region),
            'maxStats' => (int) $this->config('max_stats', 4),
            'trends' => self::TRENDS,
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        foreach (self::FILE_FIELDS as $field => $folder) {
            if ($request->hasFile($field)) {
                $data[$field] = $this->files->store($request->file($field), $folder);
            }
        }
        if ($request->hasFile('metadata.og_image')) {
            $data['metadata']['og_image'] = $this->files->store($request->file('metadata.og_image'), 'market-insights/metadata');
        }

        $order = $this->resolveOrderForCreate(MarketInsight::class, $request->filled('order_index') ? (int) $request->order_index : null);
        MarketInsight::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        MarketInsight::create($data);

        return redirect()->route('cms.market-insights.index')->with('success', 'Market insight created successfully.');
    }

    public function update(Request $request, $id)
    {
        $insight = MarketInsight::findOrFail($id);
        $data = $this->validated($request, $insight);

        foreach (self::FILE_FIELDS as $field => $folder) {
            if ($request->hasFile($field)) {
                $this->files->delete($insight->{$field});
                $data[$field] = $this->files->store($request->file($field), $folder);
            } elseif (in_array($field, self::REMOVABLE_FILES, true) && $request->boolean("remove_{$field}")) {
                $this->files->delete($insight->{$field});
                $data[$field] = null;
            }
        }

        $existingOg = $insight->metadata['og_image'] ?? null;
        if ($request->hasFile('metadata.og_image')) {
            $this->files->delete($existingOg);
            $data['metadata']['og_image'] = $this->files->store($request->file('metadata.og_image'), 'market-insights/metadata');
        } elseif ($request->boolean('remove_metadata_og_image')) {
            $this->files->delete($existingOg);
            $data['metadata']['og_image'] = null;
        } else {
            $data['metadata']['og_image'] = $existingOg;
        }

        app(UrlRedirectService::class)->recordSlugChange('market-insight', $insight->slug, $data['slug'], auth('cms')->id());

        if ($request->filled('order_index') && (int) $request->order_index !== (int) $insight->order_index) {
            $this->moveOrder($insight, (int) $request->order_index);
        }

        $insight->update($data);

        return redirect()->route('cms.market-insights.index')->with('success', 'Market insight updated successfully.');
    }

    /** Validates the form and shapes it into model attributes (files are handled by the caller). */
    private function validated(Request $request, ?MarketInsight $insight = null): array
    {
        $fallback = config('app.fallback_locale', 'en');
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input("translations.{$fallback}.title", ''))]);

        $request->validate([
            'slug' => ['required', 'string', 'max:255', Rule::unique('market_insights', 'slug')->ignore($insight?->id)],
            'topic' => ['required', Rule::exists('market_insight_terms', 'slug')->where('type', 'topic')],
            'region' => ['nullable', Rule::exists('market_insight_terms', 'slug')->where('type', 'region')],
            'published_at' => 'required|date',
            "translations.{$fallback}.title" => 'required|string|max:255',
            "translations.{$fallback}.summary" => 'required|string|max:500',
            "translations.{$fallback}.content" => 'required|string',
            'translations.*.title' => 'nullable|string|max:255',
            'translations.*.summary' => 'nullable|string|max:500',
            'translations.*.takeaways' => 'nullable|string|max:3000',
            'translations.*.author_name' => 'nullable|string|max:100',
            'translations.*.author_role' => 'nullable|string|max:100',
            'stats' => 'nullable|array|max:' . (int) $this->config('max_stats', 4),
            'stats.*.value' => 'nullable|string|max:20',
            'stats.*.trend' => ['nullable', Rule::in(self::TRENDS)],
            'stats.*.label.*' => 'nullable|string|max:60',
            'card_image' => [$insight?->card_image ? 'nullable' : 'required', 'image', 'max:' . $this->config('image_max_kb', 4096)],
            'detail_image' => [$insight?->detail_image ? 'nullable' : 'required', 'image', 'max:' . $this->config('image_max_kb', 4096)],
            'featured_image' => ['nullable', 'image', 'max:' . $this->config('image_max_kb', 4096)],
            'image_alt' => 'nullable|string|max:255',
            'report_file' => 'nullable|file|mimes:pdf|max:' . $this->config('report_file_max_kb', 10240),
            'order_index' => 'nullable|integer|min:1',
            'metadata' => 'nullable|array',
            'metadata.og_image' => 'nullable|image|max:4096',
        ], [
            "translations.{$fallback}.title.required" => 'The English title is required.',
            "translations.{$fallback}.summary.required" => 'The English summary is required.',
            "translations.{$fallback}.content.required" => 'The English article content is required.',
            'card_image.required' => 'Please upload a card image.',
            'detail_image.required' => 'Please upload an article (detail) image.',
            'report_file.mimes' => 'The downloadable report must be a PDF.',
        ]);

        $translations = collect($request->input('translations', []))
            ->map(fn ($values) => [
                'title' => trim((string) ($values['title'] ?? '')),
                'summary' => trim((string) ($values['summary'] ?? '')),
                'content' => (string) ($values['content'] ?? ''),
                'takeaways' => trim((string) ($values['takeaways'] ?? '')),
                'author_name' => trim((string) ($values['author_name'] ?? '')),
                'author_role' => trim((string) ($values['author_role'] ?? '')),
            ])
            ->all();

        return [
            'slug' => $request->input('slug'),
            'topic' => $request->input('topic'),
            'region' => $request->input('region') ?: null,
            'published_at' => $request->input('published_at'),
            'image_alt' => $request->input('image_alt'),
            'is_featured' => $request->boolean('is_featured'),
            'status' => $request->boolean('status'),
            'translations' => $translations,
            'stats' => $this->normalizeStats($request->input('stats', [])),
            'metadata' => collect($request->input('metadata', []))->except('og_image')->all(),
        ];
    }

    /** Keeps only figures that have a value; labels are per language, trend defaults to "flat". */
    private function normalizeStats(array $stats): array
    {
        return collect($stats)
            ->filter(fn ($stat) => trim((string) ($stat['value'] ?? '')) !== '')
            ->map(fn ($stat) => [
                'value' => trim((string) $stat['value']),
                'trend' => in_array($stat['trend'] ?? null, self::TRENDS, true) ? $stat['trend'] : 'flat',
                'label' => collect($stat['label'] ?? [])->map(fn ($label) => trim((string) $label))->filter()->all(),
            ])
            ->values()
            ->all();
    }

    public function destroy($id)
    {
        $insight = MarketInsight::findOrFail($id);
        $this->deleteWithFiles($insight);
        $this->normalizeOrderIndex(MarketInsight::class);

        return response()->json(['success' => true]);
    }

    private function deleteWithFiles(MarketInsight $insight): void
    {
        app(UrlRedirectService::class)->recordDeletion('market-insight', $insight->slug, auth('cms')->id());
        foreach (array_keys(self::FILE_FIELDS) as $field) {
            $this->files->delete($insight->{$field});
        }
        $this->files->delete($insight->metadata['og_image'] ?? null);
        $insight->delete();
    }

    public function toggleStatus($id)
    {
        $insight = MarketInsight::findOrFail($id);
        $insight->update(['status' => !$insight->status]);

        return response()->json(['success' => true]);
    }

    public function toggleFeatured($id)
    {
        $insight = MarketInsight::findOrFail($id);
        $insight->update(['is_featured' => !$insight->is_featured]);

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:market_insights,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $this->moveOrder(MarketInsight::findOrFail($request->id), (int) $request->order_index);
        $this->normalizeOrderIndex(MarketInsight::class);

        return response()->json(['success' => true]);
    }

    public function updateSection(Request $request)
    {
        $fallback = config('app.fallback_locale', 'en');
        $request->validate([
            "translations.{$fallback}.listing_title" => 'required|string|max:255',
            'translations.*.listing_title' => 'nullable|string|max:255',
            'translations.*.title' => 'nullable|string|max:255',
            'translations.*.description' => 'nullable|string|max:1000',
        ], ["translations.{$fallback}.listing_title.required" => 'The English page title is required.']);

        SectionLabel::updateOrCreate(['section_key' => 'market-insights'], [
            'translations' => collect($request->input('translations', []))
                ->map(fn ($values) => collect($values)->only(['listing_title', 'title', 'description'])->map(fn ($v) => trim((string) $v))->all())
                ->all(),
            'status' => true,
        ]);

        return redirect()->route('cms.market-insights.index')->with('success', 'Market Insights page header updated.');
    }

    public function bulkAction(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));
        $action = $request->input('action');

        if (empty($ids) || !$action) {
            return response()->json(['success' => false, 'message' => 'No action or items selected.'], 422);
        }

        if ($action === 'delete') {
            abort_unless(auth('cms')->user()?->can('market-insights.delete'), 403);
            MarketInsight::whereIn('id', $ids)->get()->each(fn ($insight) => $this->deleteWithFiles($insight));
            $this->normalizeOrderIndex(MarketInsight::class);
        } elseif (in_array($action, ['active', 'inactive'], true)) {
            MarketInsight::whereIn('id', $ids)->update(['status' => $action === 'active']);
        }

        return response()->json(['success' => true]);
    }
}
