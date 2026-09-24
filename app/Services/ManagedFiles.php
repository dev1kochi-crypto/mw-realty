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

    /**
     * Public media goes to Cloudinary (the returned value is its secure URL); anything on another
     * disk — i.e. KYC documents on `kyc` — stays local, as before.
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        if ($disk === 'public' && CloudinaryMedia::enabled()) {
            $url = app(CloudinaryMedia::class)->storeUploaded($file, $directory);
            if ($this->tracking) {
                $this->created[] = ['cloudinary', $url];
            }
            return $url;
        }

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
        if (CloudinaryMedia::isCloudinaryUrl($path)) {
            $disk = 'cloudinary';
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
        if ($disk === 'cloudinary') {
            app(CloudinaryMedia::class)->delete($path); // logs its own failures
            return;
        }

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
