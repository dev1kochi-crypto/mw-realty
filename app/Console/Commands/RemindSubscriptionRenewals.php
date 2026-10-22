<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\PortalUser;
use App\Notifications\SubscriptionRenewalReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Runs every morning (UAE time) and reminds each Agent/Company whose Stripe subscription
 * auto-renews tomorrow — one email plus one bell notification. Accounts that turned auto-renew
 * off are skipped (nothing will be charged). renewal_reminder_sent_for records which renewal date
 * was reminded, so re-runs the same day never double-send, while the next period's new
 * subscription_renews_at (synced from Stripe) re-arms it automatically.
 */
class RemindSubscriptionRenewals extends Command
{
    /** Business timezone: "tomorrow" and the scheduled morning run are both UAE time. */
    public const TIMEZONE = 'Asia/Dubai';

    protected $signature = 'portal:remind-subscription-renewals';

    protected $description = 'Email + notify agents/companies whose subscription auto-renews tomorrow';

    public function handle(): int
    {
        $tomorrow = now(self::TIMEZONE)->addDay();
        // subscription_renews_at is stored in the app timezone (UTC) — compare against the UTC
        // bounds of tomorrow's UAE calendar day.
        $from = $tomorrow->copy()->startOfDay()->timezone(config('app.timezone'));
        $to = $tomorrow->copy()->endOfDay()->timezone(config('app.timezone'));

        $total = 0;

        PortalUser::with(['plan', 'scheduledPlan'])
            ->whereNotNull('stripe_subscription_id')
            ->whereIn('subscription_status', ['active', 'trialing'])
            ->where('subscription_cancel_at_period_end', false)
            ->whereBetween('subscription_renews_at', [$from, $to])
            ->where(fn ($q) => $q->whereNull('renewal_reminder_sent_for')
                ->orWhereColumn('renewal_reminder_sent_for', '<>', 'subscription_renews_at'))
            ->chunkById(100, function ($portalUsers) use (&$total) {
                foreach ($portalUsers as $portalUser) {
                    if ($this->remind($portalUser)) {
                        $total++;
                    }
                }
            });

        $this->info("Sent renewal reminders to {$total} account(s).");

        return self::SUCCESS;
    }

    private function remind(PortalUser $portalUser): bool
    {
        // A downgrade scheduled for this renewal is what actually gets billed tomorrow.
        $plan = $portalUser->scheduledPlan ?? $portalUser->plan;
        if (!$plan) {
            return false;
        }

        $interval = ($portalUser->scheduled_plan_id ? $portalUser->scheduled_interval : $portalUser->billing_interval) ?: 'monthly';
        $amount = $plan->priceFor($interval);
        $renewsAt = $portalUser->subscription_renews_at->copy()->timezone(self::TIMEZONE);

        try {
            Mail::to($portalUser->email)->queue(
                new SubscriptionRenewalReminderMail($portalUser, $plan, $interval, $amount, $renewsAt)
            );
            $portalUser->notify(new SubscriptionRenewalReminderNotification(
                $plan->getTranslation('name'),
                $amount,
                $renewsAt->format('d M Y'),
            ));
        } catch (\Throwable $e) {
            Log::error("Failed to send renewal reminder to portal user #{$portalUser->id}: " . $e->getMessage());

            return false;
        }

        $portalUser->forceFill(['renewal_reminder_sent_for' => $portalUser->subscription_renews_at])->save();

        return true;
    }
}
