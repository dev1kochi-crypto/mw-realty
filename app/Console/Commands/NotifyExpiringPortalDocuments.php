<?php

namespace App\Console\Commands;

use App\Mail\PortalDocumentExpiredMail;
use App\Models\PortalUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Daily sweep for the three KYC/license expiry dates on portal_users (passport, trade license,
 * TRN/tax registration) — emails the agent/company once per expiry event when the date passes,
 * tracked via the matching *_notified_at column so the same expiry never gets emailed twice, but
 * a later expiry date (after the profile is updated) will trigger its own fresh email.
 */
class NotifyExpiringPortalDocuments extends Command
{
    protected $signature = 'portal:notify-expiring-documents';

    protected $description = 'Email agents/companies whose passport, trade license, or TRN expiry date has passed';

    public function handle(): int
    {
        $fields = [
            ['field' => 'passport_expiry', 'notified_field' => 'passport_expiry_notified_at', 'label' => 'Passport'],
            ['field' => 'trade_license_expiry', 'notified_field' => 'trade_license_expiry_notified_at', 'label' => 'Trade License'],
            ['field' => 'trn_expiry', 'notified_field' => 'trn_expiry_notified_at', 'label' => 'Tax Registration (TRN)'],
        ];

        $totalNotified = 0;

        foreach ($fields as $config) {
            $field = $config['field'];
            $notifiedField = $config['notified_field'];
            $label = $config['label'];

            $portalUsers = PortalUser::whereNotNull($field)
                ->where($field, '<=', now()->toDateString())
                ->where(fn ($q) => $q->whereNull($notifiedField)->orWhereColumn($notifiedField, '<', $field))
                ->get();

            foreach ($portalUsers as $portalUser) {
                try {
                    Mail::to($portalUser->email)->queue(
                        (new PortalDocumentExpiredMail($portalUser, $label, $portalUser->{$field}))->afterCommit()
                    );
                } catch (\Throwable $e) {
                    Log::error("Failed to send {$label} expiry email to portal user #{$portalUser->id}: " . $e->getMessage());
                }

                $portalUser->{$notifiedField} = now();
                $portalUser->save();

                $totalNotified++;
            }
        }

        $this->info("Notified {$totalNotified} portal user(s) of expired document(s).");

        return self::SUCCESS;
    }
}
