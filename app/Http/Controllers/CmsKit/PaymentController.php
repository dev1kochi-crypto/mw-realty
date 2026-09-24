<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\Plan;
use App\Models\PlanPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin "Payments" — every plan payment (Stripe card payments + manually recorded ones),
 * filterable, with totals for the current filter and a CSV export of the same rows.
 */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filtered($request);

        $totals = (clone $query)->toBase()
            ->selectRaw("COUNT(*) as cnt, COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) as total, COALESCE(SUM(CASE WHEN status = 'paid' THEN discount_amount END), 0) as discounts, COUNT(DISTINCT CASE WHEN status = 'paid' THEN portal_user_id END) as accounts, SUM(status = 'failed') as failed, COALESCE(SUM(CASE WHEN status = 'failed' THEN amount END), 0) as failed_amount")
            ->first();

        $payments = $query->with(['portalUser', 'plan'])
            ->orderByDesc('paid_at')->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('cms-kit::payments.index', [
            'payments' => $payments,
            'totals' => $totals,
            'plans' => Plan::orderBy('order_index')->get(),
            'thisMonth' => (float) PlanPayment::paid()->whereYear('paid_at', now()->year)->whereMonth('paid_at', now()->month)->sum('amount'),
            'filtersActive' => collect($request->only(['search', 'plan_id', 'cycle', 'method', 'coupon', 'from', 'to', 'type', 'status']))->filter(fn ($v) => filled($v))->isNotEmpty(),
        ]);
    }

    /** Invoice page on our own domain (not Stripe's hosted page). */
    public function show($id, \App\Services\InvoiceService $invoices)
    {
        return view('cms-kit::payments.show', $invoices->data(PlanPayment::findOrFail($id)));
    }

    public function pdf($id, \App\Services\InvoiceService $invoices)
    {
        $payment = PlanPayment::findOrFail($id);

        return $invoices->pdf($payment)->download(\App\Services\InvoiceService::filename($payment));
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->with('portalUser')->orderByDesc('paid_at')->orderByDesc('id');

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Status', 'Account', 'Type', 'Email', 'Plan', 'Billing', 'Amount (AED)', 'Original (AED)', 'Discount (AED)', 'Coupon', 'Method', 'Stripe Invoice']);
            $rows->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $p) {
                    fputcsv($out, [
                        $p->paid_at?->format('Y-m-d'),
                        ucfirst($p->status),
                        $p->portalUser?->displayName() ?? 'Deleted account',
                        $p->portalUser ? ucfirst($p->portalUser->type) : '',
                        $p->portalUser?->email,
                        $p->plan_name,
                        $p->billing_cycle,
                        number_format((float) $p->amount, 2, '.', ''),
                        $p->original_amount !== null ? number_format((float) $p->original_amount, 2, '.', '') : '',
                        $p->discount_amount !== null ? number_format((float) $p->discount_amount, 2, '.', '') : '',
                        $p->coupon_code,
                        $p->stripe_invoice_id ? 'Card (Stripe)' : 'Manual',
                        $p->stripe_invoice_id,
                    ]);
                }
            });
            fclose($out);
        }, 'payments-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request): Builder
    {
        return PlanPayment::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($q) => $q->where('coupon_code', 'like', $term)
                    ->orWhere('stripe_invoice_id', 'like', $term)
                    ->orWhereHas('portalUser', fn ($u) => $u->where('name', 'like', $term)
                        ->orWhere('company_name', 'like', $term)
                        ->orWhere('email', 'like', $term)));
            })
            ->when(array_key_exists((string) $request->input('status'), PlanPayment::STATUSES), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('plan_id'), fn ($q) => $q->where('plan_id', $request->integer('plan_id')))
            ->when($request->filled('cycle'), fn ($q) => $q->where('billing_cycle', $request->input('cycle')))
            ->when($request->input('method') === 'stripe', fn ($q) => $q->whereNotNull('stripe_invoice_id'))
            ->when($request->input('method') === 'manual', fn ($q) => $q->whereNull('stripe_invoice_id'))
            ->when($request->input('coupon') === 'with', fn ($q) => $q->whereNotNull('coupon_code'))
            ->when($request->input('coupon') === 'without', fn ($q) => $q->whereNull('coupon_code'))
            ->when(in_array($request->input('type'), ['agent', 'company'], true), fn ($q) => $q->whereHas('portalUser', fn ($u) => $u->where('type', $request->input('type'))))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->input('to')));
    }
}
