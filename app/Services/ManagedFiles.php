<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Coordinates unique uploaded files with a request's database transaction. */
class ManagedFiles
{
    private array $created = [];
    private array $deleted = [];
    private bool $tracking = false;

    public function begin(): void
    {
        $this->created = $this->deleted = [];
        $this->tracking = true;
    }

    public function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $path = $file->store($directory, $disk);
        if (!$path) {
            throw new \RuntimeException('The upload could not be stored.');
        }
        if ($this->tracking) {
            $this->created[] = [$disk, $path];
        }
        return $path;
    }

    public function delete(?string $path, string $disk = 'public'): void
    {
        if (!$path) {
            return;
        }
        if ($this->tracking) {
            $this->deleted[] = [$disk, $path];
        } else {
            $this->remove($disk, $path);
        }
    }

    public function finish(bool $committed): void
    {
        foreach ($committed ? $this->deleted : $this->created as [$disk, $path]) {
            $this->remove($disk, $path);
        }
        $this->created = $this->deleted = [];
        $this->tracking = false;
    }

    private function remove(string $disk, string $path): void
    {
        try {
            if (!Storage::disk($disk)->delete($path)) {
                throw new \RuntimeException('Storage cleanup failed.');
            }
        } catch (\Throwable $e) {
            report($e);
            \App\Jobs\DeleteStoredFile::dispatch($disk, $path)->afterCommit();
        }
    }
}
