<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\PortalUser;
use App\Models\PlanPayment;
use App\Models\CmsKit\Language;
use Illuminate\Routing\Controller;
use App\Support\ManagesOrderIndex;

class PlanController extends Controller
{
    use ManagesOrderIndex;

    public function index(Request $request)
    {
        // Plans are a small, hand-curated list of pricing tiers (not customer data), so the
        // full set renders on one page — that's also what the drag-to-reorder card grid needs.
        $plans = Plan::withCount('subscribers')->orderBy('order_index', 'asc')->get();
        $languages = Language::where('status', true)->get();

        // Estimated monthly revenue from currently assigned paid plans (no invoicing/billing yet —
        // this is a live snapshot of assigned plans × price, not a transaction ledger). Summed at
        // the database level so it stays cheap regardless of how many subscribers exist.
        $monthlyRevenue = (float) PortalUser::query()
            ->join('plans', 'plans.id', '=', 'portal_users.plan_id')
            ->where('portal_users.status', 'approved')
            ->where('plans.billing_cycle', 'monthly')
            ->where('plans.price', '>', 0)
            ->sum('plans.price');

        $planStats = [
            'total_plans' => Plan::count(),
            'active_subscribers' => PortalUser::approved()->whereNotNull('plan_id')->count(),
            'monthly_revenue' => $monthlyRevenue,
        ];

        return view('cms-kit::plans.index', compact('plans', 'languages', 'planStats'));
    }

    public function show($id)
    {
        $plan = Plan::withCount('subscribers')->findOrFail($id);

        // Paginated — a single plan (e.g. the default Free tier) can end up with
        // tens of thousands of subscribers, so this must never load them all at once.
        $subscribers = PortalUser::where('plan_id', $id)
            ->withCount(['properties', 'leads'])
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'subscribers_page')
            ->withQueryString();

        $recentPayments = PlanPayment::where('plan_id', $id)
            ->with('portalUser')
            ->orderByDesc('paid_at')
            ->paginate(15, ['*'], 'payments_page')
            ->withQueryString();

        $totalCollected = (float) PlanPayment::where('plan_id', $id)->sum('amount');
        $activeSubscribers = PortalUser::where('plan_id', $id)->where('status', 'approved')->count();

        return view('cms-kit::plans.show', compact('plan', 'subscribers', 'recentPayments', 'totalCollected', 'activeSubscribers'));
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
        $plan = Plan::withCount('subscribers')->findOrFail($id);

        if ($plan->subscribers_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'This plan can\'t be deleted — ' . $plan->subscribers_count . ' agent/company account' . ($plan->subscribers_count === 1 ? ' is' : 's are') . ' still on it. Move them to another plan first.',
            ], 422);
        }

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

    /**
     * Drag-and-drop reorder from the card grid — the client sends the full
     * plan id sequence in its new order, we just re-number 1..n to match.
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:plans,id',
        ]);

        foreach ($request->input('order') as $index => $id) {
            Plan::where('id', $id)->update(['order_index' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }
}
