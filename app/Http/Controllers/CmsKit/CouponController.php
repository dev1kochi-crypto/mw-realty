<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\Coupon;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/** Admin CRUD for plan discount coupons — see App\Services\CouponService for how they apply. */
class CouponController extends Controller
{
    public function index(Request $request)
    {
        $coupons = Coupon::withCount('redemptions')
            ->when($request->filled('search'), fn ($q) => $q->where('code', 'like', '%' . strtoupper($request->input('search')) . '%'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $plans = Plan::orderBy('order_index')->get()->keyBy('id');

        $stats = [
            'total' => Coupon::count(),
            'active' => Coupon::where('status', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'redemptions' => \App\Models\CouponRedemption::count(),
            'saved' => (float) \App\Models\PlanPayment::paid()->sum('discount_amount'),
        ];

        return view('cms-kit::coupons.index', compact('coupons', 'plans', 'stats'));
    }

    public function create()
    {
        return view('cms-kit::coupons.create', ['plans' => $this->paidPlans()]);
    }

    public function store(Request $request)
    {
        Coupon::create($this->validated($request));

        return redirect()->route('cms.coupons.index')->with('success', 'Coupon created successfully.');
    }

    public function show($id)
    {
        $coupon = Coupon::withCount('redemptions')->findOrFail($id);
        $redemptions = $coupon->redemptions()->with(['portalUser', 'plan'])->latest('redeemed_at')->paginate(15);

        return view('cms-kit::coupons.show', compact('coupon', 'redemptions'));
    }

    public function edit($id)
    {
        return view('cms-kit::coupons.edit', ['coupon' => Coupon::withCount('redemptions')->findOrFail($id), 'plans' => $this->paidPlans()]);
    }

    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update($this->validated($request, $coupon));

        return redirect()->route('cms.coupons.index')->with('success', 'Coupon updated successfully.');
    }

    public function toggleStatus($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['status' => !$coupon->status]);

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        // Redemptions cascade — payments already recorded keep their own coupon_code/discount copy.
        Coupon::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    protected function paidPlans()
    {
        return Plan::where('price', '>', 0)->orderBy('order_index')->get();
    }

    protected function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'description' => 'nullable|string|max:255',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => ['required', 'numeric', 'min:0.01', $request->input('discount_type') === 'percent' ? 'max:100' : 'max:1000000'],
            'plan_ids' => 'nullable|array',
            'plan_ids.*' => 'integer|exists:plans,id',
            'duration' => 'required|in:once,repeating,forever',
            'duration_months' => 'nullable|required_if:duration,repeating|integer|min:1|max:120',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
        ], [
            'code.regex' => 'Use letters, numbers, dashes or underscores only (e.g. WELCOME20).',
        ]);

        $data['plan_ids'] = !empty($data['plan_ids']) ? array_map('intval', $data['plan_ids']) : null;
        $data['duration_months'] = $data['duration'] === 'repeating' ? $data['duration_months'] : null;
        $data['status'] = $request->has('status');

        return $data;
    }
}
