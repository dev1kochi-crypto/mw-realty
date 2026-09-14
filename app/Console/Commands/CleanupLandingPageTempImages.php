<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sweeps landing-pages/content/tmp for images that were uploaded via the Custom HTML editor's
 * "Insert Image"/"Replace" buttons but never made it into a saved page (abandoned edits, testing,
 * or replaced before submit) — otherwise they'd sit in storage forever.
 */
class CleanupLandingPageTempImages extends Command
{
    protected $signature = 'landing-pages:cleanup-temp-images {--hours=24 : Delete temp images older than this many hours}';

    protected $description = 'Delete unsaved landing-page content images left in temp storage';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $cutoff = now()->subHours((int) $this->option('hours'))->timestamp;
        $deleted = 0;

        foreach ($disk->files('landing-pages/content/tmp') as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} stale temp image(s).");

        return self::SUCCESS;
    }
}
