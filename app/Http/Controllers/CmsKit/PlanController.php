<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\PortalUser;
use App\Models\CmsKit\Language;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Routing\Controller;
use CMS\SiteManager\Support\ManagesOrderIndex;

class PlanController extends Controller
{
    use ManagesOrderIndex;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Plan::withCount('subscribers')->orderBy('order_index', 'asc');
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('name', function ($row) {
                    return $row->getTranslation('name');
                })
                ->addColumn('price', function ($row) {
                    if ($row->billing_cycle === 'free') {
                        return 'Free';
                    }
                    $suffix = ['monthly' => '/mo', 'yearly' => '/yr', 'one_time' => ' one-time'][$row->billing_cycle] ?? '';
                    return 'AED ' . number_format($row->price) . $suffix;
                })
                ->addColumn('limit', function ($row) {
                    return $row->isUnlimited() ? 'Unlimited' : $row->property_limit . ' properties';
                })
                ->addColumn('subscribers_count', function ($row) {
                    return $row->subscribers_count;
                })
                ->addColumn('popular', function ($row) {
                    return $row->is_popular ? '<span class="badge bg-warning text-dark">Popular</span>' : '';
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
                ->addColumn('action', function ($row) {
                    $btns = '<div class="btn-group">';
                    if (auth('cms')->user()->can('plans.edit')) {
                        $btns .= '<a href="' . route('cms.plans.edit', $row->id) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
                    }
                    if (auth('cms')->user()->can('plans.delete')) {
                        $btns .= '<button type="button" class="btn btn-sm btn-outline-danger delete-item" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                    }
                    $btns .= '</div>';
                    return $btns;
                })
                ->rawColumns(['popular', 'status', 'order', 'action'])
                ->make(true);
        }

        $languages = Language::where('status', true)->get();

        // Estimated monthly revenue from currently assigned paid plans (no invoicing/billing yet —
        // this is a live snapshot of assigned plans × price, not a transaction ledger).
        $monthlyRevenue = PortalUser::approved()
            ->whereHas('plan', fn ($q) => $q->where('billing_cycle', 'monthly')->where('price', '>', 0))
            ->with('plan')
            ->get()
            ->sum(fn ($u) => (float) $u->plan->price);

        $planStats = [
            'total_plans' => Plan::count(),
            'active_subscribers' => PortalUser::approved()->whereNotNull('plan_id')->count(),
            'monthly_revenue' => $monthlyRevenue,
        ];

        return view('cms-kit::plans.index', compact('languages', 'planStats'));
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $nextOrder = Plan::count() + 1;
        return view('cms-kit::plans.create', compact('languages', 'nextOrder'));
    }

    protected function rules(): array
    {
        $languages = Language::where('status', true)->get();
        $rules = [
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:free,monthly,yearly,one_time',
            'property_limit' => 'nullable|integer|min:0',
            'order_index' => 'nullable|integer|min:1',
        ];
        foreach ($languages as $lang) {
            $rules["translations.{$lang->code}.name"] = 'required';
        }
        return $rules;
    }

    protected function buildTranslations(Request $request, array $languages): array
    {
        $translations = $request->input('translations', []);
        foreach ($languages as $lang) {
            $features = $request->input("features.{$lang}", '');
            $translations[$lang]['features'] = array_values(array_filter(array_map('trim', explode("\n", $features))));
        }
        return $translations;
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());
        $languages = Language::where('status', true)->pluck('code')->all();

        $data = $request->only(['price', 'billing_cycle', 'property_limit']);
        $data['translations'] = $this->buildTranslations($request, $languages);
        $data['is_popular'] = $request->has('is_popular');
        $data['status'] = $request->has('status');

        $order = $this->resolveOrderForCreate(Plan::class, $request->order_index ? (int) $request->order_index : null);
        Plan::where('order_index', '>=', $order)->increment('order_index');
        $data['order_index'] = $order;

        Plan::create($data);

        return redirect()->route('cms.plans.index')->with('success', 'Plan created successfully.');
    }

    public function edit($id)
    {
        $plan = Plan::findOrFail($id);
        $languages = Language::where('status', true)->get();
        return view('cms-kit::plans.edit', compact('plan', 'languages'));
    }

    public function update(Request $request, $id)
    {
        $plan = Plan::findOrFail($id);
        $request->validate($this->rules());
        $languages = Language::where('status', true)->pluck('code')->all();

        $data = $request->only(['price', 'billing_cycle', 'property_limit']);
        $data['translations'] = $this->buildTranslations($request, $languages);
        $data['is_popular'] = $request->has('is_popular');
        $data['status'] = $request->has('status');

        $plan->update($data);

        return redirect()->route('cms.plans.index')->with('success', 'Plan updated successfully.');
    }

    public function destroy($id)
    {
        $plan = Plan::findOrFail($id);
        $order = $plan->order_index;
        $plan->delete();

        Plan::where('order_index', '>', $order)->decrement('order_index');
        $this->normalizeOrderIndex(Plan::class);

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $plan = Plan::findOrFail($id);
        $plan->status = !$plan->status;
        $plan->save();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:plans,id',
            'order_index' => 'required|integer|min:1',
        ]);

        $plan = Plan::findOrFail($request->id);
        $newOrder = $this->resolveOrderForReorder(Plan::class, (int) $request->order_index);
        $oldOrder = $plan->order_index;

        if ($newOrder != $oldOrder) {
            if ($newOrder > $oldOrder) {
                Plan::where('order_index', '>', $oldOrder)->where('order_index', '<=', $newOrder)->decrement('order_index');
            } else {
                Plan::where('order_index', '>=', $newOrder)->where('order_index', '<', $oldOrder)->increment('order_index');
            }
            $plan->order_index = $newOrder;
            $plan->save();
        }
        $this->normalizeOrderIndex(Plan::class);

        return response()->json(['success' => true]);
    }
}
