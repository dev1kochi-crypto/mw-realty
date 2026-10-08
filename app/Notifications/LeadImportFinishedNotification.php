<?php

namespace App\Notifications;

use App\Models\LeadImport;
use Illuminate\Notifications\Notification;

/** Bell notification when a background lead file import finishes (or fails) — see ProcessLeadImport. */
class LeadImportFinishedNotification extends Notification
{
    public function __construct(public LeadImport $import)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $failed = $this->import->status === LeadImport::STATUS_FAILED;

        return [
            'lead_import_id' => $this->import->id,
            'title' => $failed ? 'Lead import failed' : 'Lead import finished',
            'message' => $failed
                ? "{$this->import->file_name} could not be imported: {$this->import->error}"
                : "{$this->import->file_name}: {$this->import->added} added, {$this->import->updated} updated, {$this->import->skipped} skipped.",
            // The Leads page shows this import's result, with its status file to download.
            'url' => route('portal.crm.leads.index', ['import' => $this->import->id]),
            'icon' => $failed ? 'fa-triangle-exclamation' : 'fa-cloud-arrow-up',
            'tone' => $failed ? 'red' : 'teal',
        ];
    }
}
