<?php

namespace App\Notifications;

use App\Models\PropertyImport;
use Illuminate\Notifications\Notification;

/** Bell notification when a background listing spreadsheet import finishes (or fails) — see ProcessPropertyImport. */
class PropertyImportFinishedNotification extends Notification
{
    public function __construct(public PropertyImport $import)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $failed = $this->import->status === PropertyImport::STATUS_FAILED;
        $needsInfo = max(0, $this->import->added - $this->import->complete);

        return [
            'property_import_id' => $this->import->id,
            'title' => $failed ? 'Listing import failed' : 'Listing import finished',
            'message' => $failed
                ? "{$this->import->file_name} could not be imported: {$this->import->error}"
                : "{$this->import->file_name}: {$this->import->added} listings added ({$needsInfo} need details), {$this->import->duplicates} duplicates, {$this->import->skipped} skipped.",
            // The Properties page opens this import's result, with its status file to download.
            'url' => route('crm.app', $this->import->segment === 'commercial' ? 'commercial' : 'properties') . '?import=' . $this->import->id,
            'icon' => $failed ? 'fa-triangle-exclamation' : 'fa-file-import',
            'tone' => $failed ? 'red' : 'teal',
        ];
    }
}
