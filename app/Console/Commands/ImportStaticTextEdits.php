<?php

namespace App\Console\Commands;

use App\Services\StaticTextOverrides;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * One-off, for a server whose tracked resources/lang/cms-static/*.json were edited through the
 * admin (before edits moved to storage): copies those edits into the git-ignored overrides.
 *
 *   mkdir -p storage/app/private/cms-static-backup
 *   cp resources/lang/cms-static/*.json storage/app/private/cms-static-backup/
 *   git checkout -- resources/lang/cms-static && git pull
 *   php artisan static-texts:import-edits storage/app/private/cms-static-backup --base=ORIG_HEAD
 *
 * --base is the commit the server's files were edited from, so only values the admin actually
 * changed are imported (without it, the backup is compared with the current defaults, and any
 * default updated in the pull would be pinned to its old text).
 */
class ImportStaticTextEdits extends Command
{
    protected $signature = 'static-texts:import-edits {dir : Folder with the edited {code}.json files} {--base= : Git commit the edited files started from}';

    protected $description = 'Move admin edits from old tracked static-text files into the storage overrides';

    public function handle(StaticTextOverrides $texts): int
    {
        $files = File::glob(rtrim($this->argument('dir'), '/\\') . '/*.json');
        if ($files === []) {
            $this->error('No .json files in that folder.');

            return self::FAILURE;
        }

        foreach ($files as $file) {
            $code = strtolower(pathinfo($file, PATHINFO_FILENAME));
            $edited = $texts->flatten(json_decode(File::get($file), true) ?: []);
            $base = $this->option('base') ? $this->baseFile($code) : $texts->defaults($code);
            if ($base === null) {
                $this->warn("  {$code}: not in {$this->option('base')}, skipped");

                continue;
            }

            $current = $texts->flatten($texts->read($code));
            $changed = array_intersect_key(array_diff_assoc($edited, $texts->flatten($base)), $current);
            $texts->write($code, $texts->unflatten(array_replace($current, $changed)));
            $this->line("  {$code}: " . count($changed) . ' edited value(s) kept');
        }

        return self::SUCCESS;
    }

    protected function baseFile(string $code): ?array
    {
        $result = Process::path(base_path())->run(['git', 'show', $this->option('base') . ":resources/lang/cms-static/{$code}.json"]);

        return $result->successful() ? (json_decode($result->output(), true) ?: []) : null;
    }
}
