<?php

namespace App\Services;

use App\Models\PlanPayment;
use App\Models\PortalUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentRecorder
{
    public static function key(int $owner, ?int $plan, string $cycle, int $year, ?int $month): string
    {
        $period = $cycle === 'one_time' ? 'once' : ($cycle === 'monthly' ? sprintf('%04d-%02d', $year, $month) : (string) $year);
        return $owner.':'.($plan ?? 'deleted').':'.$cycle.':'.$period;
    }

    public function record(int $id, string $status): void
    {
        DB::transaction(function () use ($id, $status) {
            $user = PortalUser::lockForUpdate()->findOrFail($id);
            if ($status === 'paid') {
                $plan = $user->plan()->lockForUpdate()->first();
                if (!$plan || $plan->billing_cycle === 'free') {
                    throw ValidationException::withMessages(['payment_status' => 'Assign a paid plan before recording a payment.']);
                }
                // Stripe records these automatically from each paid invoice — a manual entry would double-count.
                if ($user->hasStripeSubscription()) {
                    throw ValidationException::withMessages(['payment_status' => 'This account pays by card through Stripe — payments are recorded automatically.']);
                }
                $now = now();
                $cycle = $user->billing_interval === 'yearly' && $plan->hasYearly() ? 'yearly' : $plan->billing_cycle;
                $key = self::key($user->id, $plan->id, $cycle, $now->year, $now->month);
                // Only a genuinely new payment consumes a coupon use — re-marking the same period
                // as paid just returns the existing row.
                $payment = PlanPayment::where('idempotency_key', $key)->first();
                if (!$payment) {
                    $priced = app(CouponService::class)->applyToPayment($user, $plan);
                    $payment = PlanPayment::create([
                        'idempotency_key' => $key,
                        'portal_user_id' => $user->id, 'plan_id' => $plan->id,
                        'plan_name' => $plan->getTranslation('name'), 'amount' => $priced['amount'],
                        'original_amount' => $priced['discount'] > 0 ? $priced['original'] : null,
                        'discount_amount' => $priced['discount'] > 0 ? $priced['discount'] : null,
                        'coupon_code' => $priced['discount'] > 0 ? $priced['code'] : null,
                        'billing_cycle' => $cycle, 'period_year' => $now->year,
                        'period_month' => $cycle === 'monthly' ? $now->month : null,
                        'paid_at' => $now->toDateString(),
                    ]);
                    // Invoice email (PDF attached) once this transaction commits.
                    DB::afterCommit(fn () => app(InvoiceService::class)->sendOnce($payment));
                }
                $user->last_payment_at = $payment->paid_at;
            }
            $user->payment_status = $status;
            $user->save();
        });
    }
}
