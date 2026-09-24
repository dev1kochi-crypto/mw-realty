<?php

namespace App\Console\Commands;

use App\Services\CloudinaryMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

/**
 * One-off move of every public media file (storage/app/public) to Cloudinary, then a rewrite of
 * every database reference to it. KYC documents are never touched.
 *
 *   php artisan media:migrate-to-cloudinary --dry-run      # what would happen
 *   php artisan media:migrate-to-cloudinary                # backup DB → upload → rewrite DB
 *   php artisan media:migrate-to-cloudinary --delete-local # afterwards: remove migrated local files
 *
 * Resumable: uploads are recorded in storage/app/cloudinary-migration.json and skipped on re-run.
 */
class MigrateMediaToCloudinary extends Command
{
    protected $signature = 'media:migrate-to-cloudinary
        {--dry-run : List what would be uploaded/rewritten without changing anything}
        {--skip-backup : Do not run mysqldump first}
        {--delete-local : Delete local files that are recorded as uploaded (run after verifying the site)}
        {--fix-folders : Put already-uploaded assets into their Media Library folder (dynamic-folder accounts)}';

    protected $description = 'Upload public media to Cloudinary and point the database at the new URLs';

    /** Never uploaded: identity documents + editor temp uploads. */
    private const EXCLUDED_PREFIXES = ['portal-kyc/', 'landing-pages/content/tmp/'];

    /** Framework bookkeeping tables — no media references in them. */
    private const SKIP_TABLES = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    private string $manifestPath;
    private array $manifest = [];

    public function handle(CloudinaryMedia $cloudinary): int
    {
        if (!CloudinaryMedia::enabled()) {
            $this->error('Cloudinary is not configured (CLOUDINARY_CLOUD_NAME / API_KEY / API_SECRET).');
            return self::FAILURE;
        }

        $this->manifestPath = storage_path('app/cloudinary-migration.json');
        $this->manifest = is_file($this->manifestPath) ? (json_decode(file_get_contents($this->manifestPath), true) ?: []) : [];

        if ($this->option('delete-local')) {
            return $this->deleteLocal();
        }

        if ($this->option('fix-folders')) {
            $ok = 0;
            $bar = $this->output->createProgressBar(count($this->manifest));
            foreach ($this->manifest as $url) {
                $ok += $cloudinary->assignFolder($url) ? 1 : 0;
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info("{$ok} of " . count($this->manifest) . ' assets placed in their Media Library folders.');
            return self::SUCCESS;
        }

        $files = $this->localFiles();
        $this->info(count($files) . ' local media files found (' . count(array_intersect_key($files, $this->manifest)) . ' already uploaded).');

        if ($this->option('dry-run')) {
            $this->table(['File', 'Size'], collect($files)->map(fn ($size, $rel) => [$rel, round($size / 1024) . ' KB'])->take(30)->values()->all());
            $this->line('…');
            $this->rewriteDatabase($cloudinary, dryRun: true);
            return self::SUCCESS;
        }

        if (!$this->option('skip-backup') && !$this->backupDatabase()) {
            return self::FAILURE;
        }

        $this->uploadAll($cloudinary, $files);
        $this->rewriteDatabase($cloudinary, dryRun: false);

        $this->newLine();
        $this->info('Done. Check the site, then run with --delete-local to remove the local copies.');

        return self::SUCCESS;
    }

    /** relative path => bytes */
    private function localFiles(): array
    {
        $root = storage_path('app/public');
        $files = [];
        foreach (Finder::create()->files()->in($root)->ignoreDotFiles(true) as $file) {
            $rel = str_replace('\\', '/', $file->getRelativePathname());
            if (collect(self::EXCLUDED_PREFIXES)->contains(fn ($p) => str_starts_with($rel, $p))) {
                continue;
            }
            $files[$rel] = $file->getSize();
        }
        ksort($files);

        return $files;
    }

    private function backupDatabase(): bool
    {
        $db = config('database.connections.' . config('database.default'));
        $dir = storage_path('app/backups');
        @mkdir($dir, 0755, true);
        $file = $dir . '/' . $db['database'] . '-before-cloudinary-' . now()->format('Ymd-His') . '.sql';

        $binary = collect([env('MYSQLDUMP_PATH'), 'C:/xampp/mysql/bin/mysqldump.exe', 'mysqldump'])->filter()->first(fn ($b) => $b === 'mysqldump' || is_file($b));
        $cmd = sprintf('"%s" --host=%s --port=%s --user=%s %s --single-transaction --routines --result-file=%s %s 2>&1',
            $binary, escapeshellarg($db['host']), escapeshellarg((string) $db['port']), escapeshellarg($db['username']),
            $db['password'] !== '' && $db['password'] !== null ? '--password=' . escapeshellarg($db['password']) : '',
            escapeshellarg($file), escapeshellarg($db['database']));

        exec($cmd, $output, $code);
        if ($code !== 0 || !is_file($file) || filesize($file) < 1000) {
            $this->error('Database backup failed — nothing was changed. ' . implode(' ', $output));
            return false;
        }

        $this->info('Database backed up to ' . $file . ' (' . round(filesize($file) / 1048576, 1) . ' MB).');
        return true;
    }

    private function uploadAll(CloudinaryMedia $cloudinary, array $files): void
    {
        $pending = array_diff_key($files, $this->manifest);
        $this->info('Uploading ' . count($pending) . ' files to Cloudinary…');
        $bar = $this->output->createProgressBar(count($pending));
        $failed = [];

        foreach ($pending as $rel => $size) {
            try {
                $this->manifest[$rel] = $cloudinary->uploadFile(storage_path('app/public/' . $rel), $rel);
                // Persist after every file so an interruption loses nothing.
                file_put_contents($this->manifestPath, json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } catch (\Throwable $e) {
                $failed[$rel] = $e->getMessage();
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        foreach ($failed as $rel => $error) {
            $this->warn("  failed: {$rel} — {$error}");
        }
        $this->info(count($this->manifest) . ' files on Cloudinary, ' . count($failed) . ' failed' . ($failed ? ' (re-run to retry)' : '') . '.');
    }

    /** Rewrites every text/JSON column that references a migrated file. */
    private function rewriteDatabase(CloudinaryMedia $cloudinary, bool $dryRun): void
    {
        $map = $dryRun ? array_fill_keys(array_keys($this->localFiles()), 'https://res.cloudinary.com/…') + $this->manifest : $this->manifest;
        if (!$map) {
            $this->warn('Nothing uploaded yet — database not changed.');
            return;
        }

        $appUrl = rtrim(config('app.url'), '/');
        $folders = collect(array_keys($map))->map(fn ($p) => dirname($p))->unique()->flip()->all();
        $database = DB::getDatabaseName();
        $columns = collect(DB::select(
            "SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','json')",
            [$database]
        ))->groupBy('t');

        $totalRows = 0;
        $report = [];

        foreach ($columns as $table => $cols) {
            if (in_array($table, self::SKIP_TABLES, true) || !Schema::hasColumn($table, 'id')) {
                continue;
            }
            $colNames = collect($cols)->pluck('c')->all();

            DB::table($table)->select(array_merge(['id'], $colNames))->orderBy('id')->chunkById(200, function ($rows) use ($table, $colNames, $map, $appUrl, $folders, $cloudinary, $dryRun, &$totalRows, &$report) {
                foreach ($rows as $row) {
                    $changes = [];
                    foreach ($colNames as $col) {
                        $value = $row->{$col};
                        if (!is_string($value) || $value === '') {
                            continue;
                        }

                        // Property gallery folder ("properties/PROP021") → Cloudinary folder URL.
                        if ($table === 'properties' && $col === 'image_path' && isset($folders[trim($value, '/')])) {
                            $changes[$col] = $cloudinary->folderUrl(trim($value, '/'));
                            continue;
                        }

                        $new = $this->rewriteValue($value, $map, $appUrl);
                        if ($new !== $value) {
                            $changes[$col] = $new;
                        }
                    }

                    if ($changes) {
                        $totalRows++;
                        foreach (array_keys($changes) as $col) {
                            $report["{$table}.{$col}"] = ($report["{$table}.{$col}"] ?? 0) + 1;
                        }
                        if (!$dryRun) {
                            DB::table($table)->where('id', $row->id)->update($changes);
                        }
                    }
                }
            });
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry run] would update ' : 'Updated ') . $totalRows . ' database rows:');
        $this->table(['Column', 'Rows'], collect($report)->map(fn ($n, $k) => [$k, $n])->values()->all());
    }

    /** Plain path, "storage/…", full local URL, JSON (recursive) or HTML with embedded /storage/ links. */
    private function rewriteValue(string $value, array $map, string $appUrl): string
    {
        $direct = $this->mapPath($value, $map, $appUrl);
        if ($direct !== null) {
            return $direct;
        }

        $trimmed = ltrim($value);
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $changed = false;
                array_walk_recursive($decoded, function (&$leaf) use ($map, $appUrl, &$changed) {
                    if (is_string($leaf) && $leaf !== '') {
                        $new = $this->mapPath($leaf, $map, $appUrl) ?? $this->rewriteEmbedded($leaf, $map);
                        if ($new !== $leaf) {
                            $leaf = $new;
                            $changed = true;
                        }
                    }
                });

                return $changed ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $value;
            }
        }

        return str_contains($value, '/storage/') ? $this->rewriteEmbedded($value, $map) : $value;
    }

    /** Whole value is a reference to one migrated file → its URL, else null. */
    private function mapPath(string $value, array $map, string $appUrl): ?string
    {
        $candidate = trim($value);
        foreach ([$appUrl . '/storage/', '/storage/', 'storage/'] as $prefix) {
            if (str_starts_with($candidate, $prefix)) {
                $candidate = substr($candidate, strlen($prefix));
                break;
            }
        }
        $candidate = ltrim(strtok($candidate, '?'), '/');

        return $map[$candidate] ?? null;
    }

    /** "…<img src="http://host/storage/blogs/a.jpg">…" → Cloudinary URLs for every migrated file. */
    private function rewriteEmbedded(string $text, array $map): string
    {
        return preg_replace_callback('#(?:https?://[^\s"\'()<>]+?)?/storage/([A-Za-z0-9_\-./%]+)#', function ($m) use ($map) {
            $path = rawurldecode($m[1]);
            return $map[$path] ?? $m[0];
        }, $text);
    }

    private function deleteLocal(): int
    {
        if (!$this->manifest) {
            $this->error('No migration manifest found — nothing to delete.');
            return self::FAILURE;
        }

        $deleted = 0;
        foreach (array_keys($this->manifest) as $rel) {
            if (collect(self::EXCLUDED_PREFIXES)->contains(fn ($p) => str_starts_with($rel, $p))) {
                continue; // never delete KYC / temp
            }
            $path = storage_path('app/public/' . $rel);
            if (is_file($path) && @unlink($path)) {
                $deleted++;
            }
        }

        // Tidy now-empty folders (keeps portal-kyc and anything not migrated).
        $dirs = iterator_to_array(Finder::create()->directories()->in(storage_path('app/public'))->sort(fn ($a, $b) => strlen($b->getPathname()) <=> strlen($a->getPathname())));
        foreach ($dirs as $dir) {
            @rmdir($dir->getPathname());
        }

        $this->info("Deleted {$deleted} local files that are now on Cloudinary. Manifest kept at {$this->manifestPath}.");

        return self::SUCCESS;
    }
}
