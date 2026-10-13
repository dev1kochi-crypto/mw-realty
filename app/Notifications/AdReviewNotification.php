<?php

namespace App\Notifications;

use App\Models\CmsKit\Ad;
use App\Services\AdBookingService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Database-only bell notification TO the advertiser about MW Realty's review of their ad: approved,
 * rejected (with the reason), or closed because it wasn't reviewed before its booked time ended.
 */
class AdReviewNotification extends Notification
{
    public function __construct(public Ad $ad, public string $event, public ?string $note = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $when = $this->ad->starts_at?->copy()->timezone(AdBookingService::TIMEZONE)->format('d M, H:i');
        $refund = $this->ad->paid_with === 'credits' ? ' Your credits have been refunded.' : ' It no longer counts toward your plan.';

        [$heading, $message, $icon, $tone] = match ($this->event) {
            'approved' => ['Ad approved', "“{$this->ad->name}” was approved and goes live on {$when}.", 'fa-circle-check', 'teal'],
            'rejected' => ['Ad not approved', "“{$this->ad->name}”: " . Str::limit((string) $this->note, 160) . $refund, 'fa-triangle-exclamation', 'amber'],
            default => ['Ad not reviewed in time', "“{$this->ad->name}” wasn't reviewed before its booked time ended, so it didn't run." . $refund, 'fa-hourglass-end', 'amber'],
        };

        return [
            'title' => $heading,
            'message' => $message,
            'url' => route('crm.app', 'ads/' . $this->ad->id),
            'icon' => $icon,
            'tone' => $tone,
        ];
    }
}
