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
                $now = now();
                $payment = PlanPayment::firstOrCreate([
                    'idempotency_key' => self::key($user->id, $plan->id, $plan->billing_cycle, $now->year, $now->month),
                ], [
                    'portal_user_id' => $user->id, 'plan_id' => $plan->id,
                    'plan_name' => $plan->getTranslation('name'), 'amount' => $plan->price,
                    'billing_cycle' => $plan->billing_cycle, 'period_year' => $now->year,
                    'period_month' => $plan->billing_cycle === 'monthly' ? $now->month : null,
                    'paid_at' => $now->toDateString(),
                ]);
                $user->last_payment_at = $payment->paid_at;
            }
            $user->payment_status = $status;
            $user->save();
        });
    }
}
