<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\CloudinaryMedia;
use App\Support\DemoPropertyMedia;
use Illuminate\Console\Command;

/**
 * Demo listings (slug "demo-…", DemoFilterCoveragePropertiesSeeder) seeded before their photos
 * went to Cloudinary point at local /storage folders — which don't exist on a server that got the
 * database but not the local files (404s). This re-uploads their galleries + floor plans from the
 * demo images bundled in the repo and points the listings at Cloudinary. Real listings are never
 * touched. Resumable: listings already on Cloudinary are skipped.
 *
 *   php artisan demo:media-to-cloudinary --dry-run
 *   php artisan demo:media-to-cloudinary
 */
class MoveDemoMediaToCloudinary extends Command
{
    protected $signature = 'demo:media-to-cloudinary
        {--dry-run : Only count the demo listings that would be moved}
        {--shard= : "i/n" — only handle listings with id % n = i, to run n copies in parallel (e.g. 0/8 … 7/8)}';

    protected $description = 'Upload demo listings\' photos (seeded locally) to Cloudinary and re-point them';

    public function handle(DemoPropertyMedia $media): int
    {
        if (!CloudinaryMedia::enabled()) {
            $this->error('Cloudinary is not configured (CLOUDINARY_CLOUD_NAME / API_KEY / API_SECRET).');
            return self::FAILURE;
        }

        $query = Property::where('slug', 'like', 'demo-%')
            ->where(fn ($q) => $q->whereNull('image_path')->orWhere('image_path', 'not like', 'http%'));
        if ($shard = $this->option('shard')) {
            if (!preg_match('#^(\d+)/(\d+)$#', $shard, $m) || (int) $m[2] < 1 || (int) $m[1] >= (int) $m[2]) {
                $this->error('--shard must look like 0/8 (index/total, index < total).');
                return self::FAILURE;
            }
            $query->whereRaw('id % ? = ?', [(int) $m[2], (int) $m[1]]);
        }
        $total = (clone $query)->count();
        $this->info("{$total} demo listings have local (or no) photos.");
        if ($this->option('dry-run') || !$total) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $failed = 0;
        $query->orderBy('id')->chunkById(50, function ($properties) use ($media, $bar, &$failed) {
            foreach ($properties as $property) {
                try {
                    $media->moveToCloudinary($property, $property->id);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("{$property->reference_no}: {$e->getMessage()}");
                }
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine();
        $this->info(($total - $failed) . " moved, {$failed} failed" . ($failed ? ' — re-run to retry.' : '.'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
