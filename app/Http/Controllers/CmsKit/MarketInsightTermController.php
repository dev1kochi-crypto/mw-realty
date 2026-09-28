<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\Language;
use App\Models\CmsKit\MarketInsight;
use App\Models\CmsKit\MarketInsightTerm;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

/**
 * Admin > Market Insights > Topics / Regions — one controller for both lists; the route's `type`
 * default (topic | region) says which one. Order is kept per list, and a term a post still uses
 * can't be deleted.
 */
class MarketInsightTermController extends Controller
{
    private const META = [
        'topic' => ['route' => 'cms.market-insight-topics', 'label' => 'Topic', 'plural' => 'Topics', 'example' => 'e.g. Price Trends'],
        'region' => ['route' => 'cms.market-insight-regions', 'label' => 'Region', 'plural' => 'Regions', 'example' => 'e.g. Dubai'],
    ];

    private function type(Request $request): string
    {
        $type = $request->route('type');
        abort_unless(in_array($type, MarketInsightTerm::TYPES, true), 404);

        return $type;
    }

    private function meta(string $type): array
    {
        return self::META[$type] + ['type' => $type];
    }

    public function index(Request $request)
    {
        $type = $this->type($request);
        $meta = $this->meta($type);

        if ($request->ajax()) {
            $cmsUser = auth('cms')->user();
            $usage = MarketInsight::whereNotNull($type)->selectRaw("{$type} as slug, count(*) as total")->groupBy($type)->pluck('total', 'slug');

            return \App\Support\TranslatedTable::column(DataTables::of(MarketInsightTerm::ofType($type)->ordered()), 'title', 'title')
                ->addColumn('select_all', fn ($row) => '<input type="checkbox" class="row-checkbox form-check-input" value="' . $row->id . '">')
                ->addColumn('title', fn ($row) => $row->getTranslation('title', config('app.fallback_locale', 'en')))
                ->addColumn('translated', function ($row) {
                    return collect($row->translations ?? [])
                        ->except(config('app.fallback_locale', 'en'))
                        ->map(fn ($values, $code) => '<span class="badge bg-light text-dark border me-1" dir="auto">' . strtoupper(e($code)) . ': ' . e($values['title'] ?? '—') . '</span>')
                        ->implode('');
                })
                ->editColumn('slug', fn ($row) => '<code>' . e($row->slug) . '</code>')
                ->addColumn('posts', fn ($row) => (int) ($usage[$row->slug] ?? 0))
                ->addColumn('status', fn ($row) => '<div class="form-check form-switch d-inline-block"><input class="form-check-input toggle-status" type="checkbox" data-id="' . $row->id . '" ' . ($row->status ? 'checked' : '') . '></div>')
                ->orderColumn('order', fn ($query, $direction) => $query->reorder()->orderBy('order_index', $direction))
                ->addColumn('order', fn ($row) => '<input type="number" min="1" class="form-control form-control-sm reorder-input" data-id="' . $row->id . '" value="' . $row->order_index . '" style="width: 70px;">')
                ->addColumn('action', function ($row) use ($cmsUser, $meta) {
                    $buttons = '<div class="btn-group">';
                    if ($cmsUser?->can('market-insights.edit')) {
                        $buttons .= '<a href="' . route($meta['route'] . '.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if ($cmsUser?->can('market-insights.delete')) {
                        $buttons .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }

                    return $buttons . '</div>';
                })
                ->rawColumns(['select_all', 'translated', 'slug', 'status', 'order', 'action'])
                ->make(true);
        }

        return view('cms-kit::market-insights.terms.index', compact('meta'));
    }

    public function create(Request $request)
    {
        $type = $this->type($request);

        return view('cms-kit::market-insights.terms.form', [
            'meta' => $this->meta($type),
            'term' => new MarketInsightTerm(['status' => true, 'order_index' => MarketInsightTerm::ofType($type)->count() + 1]),
            'languages' => Language::where('status', true)->get(),
        ]);
    }

    public function edit(Request $request, $id)
    {
        $type = $this->type($request);

        return view('cms-kit::market-insights.terms.form', [
            'meta' => $this->meta($type),
            'term' => MarketInsightTerm::ofType($type)->findOrFail($id),
            'languages' => Language::where('status', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $type = $this->type($request);
        $data = $this->validated($request, $type);

        $order = $this->clampOrder($type, $request->filled('order_index') ? (int) $request->order_index : PHP_INT_MAX, 1);
        MarketInsightTerm::ofType($type)->where('order_index', '>=', $order)->increment('order_index');

        MarketInsightTerm::create($data + ['type' => $type, 'order_index' => $order]);

        return redirect()->route($this->meta($type)['route'] . '.index')->with('success', $this->meta($type)['label'] . ' added successfully.');
    }

    public function update(Request $request, $id)
    {
        $type = $this->type($request);
        $term = MarketInsightTerm::ofType($type)->findOrFail($id);
        $data = $this->validated($request, $type, $term);

        // A slug change is carried over to every post using the old slug, so nothing is orphaned.
        if ($data['slug'] !== $term->slug) {
            MarketInsight::where($type, $term->slug)->update([$type => $data['slug']]);
        }

        $term->update($data);
        if ($request->filled('order_index')) {
            $this->move($term, (int) $request->order_index);
        }

        return redirect()->route($this->meta($type)['route'] . '.index')->with('success', $this->meta($type)['label'] . ' updated successfully.');
    }

    private function validated(Request $request, string $type, ?MarketInsightTerm $term = null): array
    {
        $fallback = config('app.fallback_locale', 'en');
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input("translations.{$fallback}.title", ''))]);

        $request->validate([
            'slug' => ['required', 'string', 'max:50', Rule::unique('market_insight_terms', 'slug')->where('type', $type)->ignore($term?->id)],
            "translations.{$fallback}.title" => 'required|string|max:100',
            'translations.*.title' => 'nullable|string|max:100',
            'order_index' => 'nullable|integer|min:1',
        ], [
            "translations.{$fallback}.title.required" => 'The English name is required.',
            'slug.unique' => 'Another ' . strtolower($this->meta($type)['label']) . ' already uses this slug.',
        ]);

        return [
            'slug' => $request->input('slug'),
            'translations' => collect($request->input('translations', []))
                ->map(fn ($values) => ['title' => trim((string) ($values['title'] ?? ''))])
                ->all(),
            'status' => $request->boolean('status'),
        ];
    }

    public function destroy(Request $request, $id)
    {
        $type = $this->type($request);
        $term = MarketInsightTerm::ofType($type)->findOrFail($id);

        if ($message = $this->inUseMessage($type, collect([$term]))) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        $term->delete();
        $this->normalize($type);

        return response()->json(['success' => true]);
    }

    public function toggleStatus(Request $request, $id)
    {
        $term = MarketInsightTerm::ofType($this->type($request))->findOrFail($id);
        $term->update(['status' => !$term->status]);

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $type = $this->type($request);
        $request->validate(['id' => 'required|integer', 'order_index' => 'required|integer|min:1']);

        $this->move(MarketInsightTerm::ofType($type)->findOrFail($request->id), (int) $request->order_index);

        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request)
    {
        $type = $this->type($request);
        $ids = array_filter((array) $request->input('ids', []));
        $action = $request->input('action');

        if (empty($ids) || !$action) {
            return response()->json(['success' => false, 'message' => 'No action or items selected.'], 422);
        }

        $terms = MarketInsightTerm::ofType($type)->whereIn('id', $ids)->get();

        if ($action === 'delete') {
            if ($message = $this->inUseMessage($type, $terms)) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            MarketInsightTerm::whereIn('id', $terms->pluck('id'))->delete();
            $this->normalize($type);
        } elseif (in_array($action, ['active', 'inactive'], true)) {
            MarketInsightTerm::whereIn('id', $terms->pluck('id'))->update(['status' => $action === 'active']);
        }

        return response()->json(['success' => true]);
    }

    /** Null when none of $terms is used by a post; otherwise a message naming the ones that are. */
    private function inUseMessage(string $type, $terms): ?string
    {
        $used = MarketInsight::whereIn($type, $terms->pluck('slug'))->distinct()->pluck($type)->all();
        if (!$used) {
            return null;
        }

        $names = $terms->whereIn('slug', $used)->map(fn ($term) => $term->getTranslation('title', 'en'))->implode(', ');

        return "Still used by one or more insights, so it can't be deleted: {$names}. Move those insights to another "
            . strtolower($this->meta($type)['label']) . ' first, or switch it off instead.';
    }

    private function clampOrder(string $type, int $order, int $extra = 0): int
    {
        return max(1, min($order, MarketInsightTerm::ofType($type)->count() + $extra));
    }

    /** Moves $term to position $order within its own list, shifting the others. */
    private function move(MarketInsightTerm $term, int $order): void
    {
        $new = $this->clampOrder($term->type, $order);
        $old = (int) $term->order_index;
        $siblings = MarketInsightTerm::ofType($term->type)->whereKeyNot($term->id);

        if ($new > $old) {
            (clone $siblings)->whereBetween('order_index', [$old + 1, $new])->decrement('order_index');
        } elseif ($new < $old) {
            (clone $siblings)->whereBetween('order_index', [$new, $old - 1])->increment('order_index');
        }

        $term->update(['order_index' => $new]);
        $this->normalize($term->type);
    }

    /** Renumbers one list 1..n, closing any gaps. */
    private function normalize(string $type): void
    {
        MarketInsightTerm::ofType($type)->ordered()->get()->values()
            ->each(fn ($term, $index) => (int) $term->order_index !== $index + 1 && $term->update(['order_index' => $index + 1]));
    }
}
