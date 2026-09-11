<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class DeleteStoredFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public function __construct(public string $disk, public string $path) {}
    public function backoff(): array { return [10, 60, 300, 900]; }
    public function handle(): void
    {
        if (!Storage::disk($this->disk)->delete($this->path)) {
            throw new \RuntimeException('Unable to remove stored file.');
        }
    }
}
