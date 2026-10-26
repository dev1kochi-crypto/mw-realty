<?php

namespace App\Services;

use CMS\SiteManager\Services\StaticTranslationService;
use Illuminate\Support\Facades\File;

/**
 * Static texts split in two so the server's git pull never conflicts:
 *
 *  - resources/lang/cms-static/{code}.json — the defaults, in git. Developers add keys here.
 *  - storage/app/private/cms-static/{code}.json — only the values changed in Admin > Languages >
 *    Translations, git-ignored, so they survive every deploy.
 *
 * Reads return defaults with the admin's changes on top; writes keep only what differs from the
 * defaults, and never touch the tracked files.
 */
class StaticTextOverrides extends StaticTranslationService
{
    public function overridesDirectory(): string
    {
        return storage_path('app/private/cms-static');
    }

    public function overridesPathForCode(string $code): string
    {
        return $this->overridesDirectory() . DIRECTORY_SEPARATOR . strtolower($code) . '.json';
    }

    public function readMaster(): array
    {
        return $this->applyOverrides($this->defaults($this->masterCode()), $this->masterCode());
    }

    public function read(string $code): array
    {
        $code = strtolower($code);

        return $code === $this->masterCode()
            ? $this->readMaster()
            : $this->applyOverrides($this->defaults($code), $code);
    }

    /**
     * The developers' version: the tracked file, with keys it lacks taken from the English one.
     *
     * @return array<string, mixed>
     */
    public function defaults(string $code): array
    {
        $this->ensureMasterFileOnDisk();
        $master = $this->decodeFile($this->pathForCode($this->masterCode())) ?? [];
        if (strtolower($code) === $this->masterCode()) {
            return $master;
        }

        return $this->mergeWithMaster($master, $this->decodeFile($this->pathForCode($code)) ?? []);
    }

    /**
     * Saves only the values that differ from the defaults.
     *
     * @param  array<string, mixed>  $data
     */
    public function write(string $code, array $data): void
    {
        $defaults = $this->flatten($this->defaults($code));
        $changed = array_diff_assoc(
            array_intersect_key($this->flatten($data), $defaults),
            $defaults
        );

        $path = $this->overridesPathForCode($code);
        if ($changed === []) {
            File::delete($path);

            return;
        }
        File::ensureDirectoryExists($this->overridesDirectory());
        File::put($path, json_encode($this->unflatten($changed), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    }

    public function deleteLanguageFile(string $code): void
    {
        if (strtolower($code) !== $this->masterCode()) {
            File::delete($this->overridesPathForCode($code));
        }
    }

    public function renameLanguageFile(string $fromCode, string $toCode): void
    {
        $data = $this->read($fromCode);
        $this->deleteLanguageFile($fromCode);
        $this->write($toCode, $data);
    }

    /**
     * Server edits are kept only for keys the defaults still have — a key removed in code drops out.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    protected function applyOverrides(array $defaults, string $code): array
    {
        $flat = $this->flatten($defaults);
        $overrides = $this->flatten($this->decodeFile($this->overridesPathForCode($code)) ?? []);

        return $this->mergeWithMaster($defaults, $this->unflatten(array_intersect_key($overrides, $flat)));
    }
}
