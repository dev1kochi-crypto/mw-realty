<?php

namespace App\Jobs;

use App\Models\PortalUser;
use App\Services\AutoTranslator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class TranslatePortalBio implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 120;
    public function __construct(public int $userId, public string $source, public string $locale) {}
    public function handle(AutoTranslator $translator): void
    {
        $user = PortalUser::find($this->userId);
        if (!$user || ($user->translations['bio'][$this->locale] ?? '') !== $this->source) return;
        if ($this->source === '') return;
        $translator->fillMissingTranslations($user, 'bio', $this->source, $this->locale);
        $translated = $user->translations['bio'] ?? [];
        DB::transaction(function () use ($translated) {
            $current = PortalUser::lockForUpdate()->find($this->userId);
            if (!$current || ($current->translations['bio'][$this->locale] ?? '') !== $this->source) return;
            $values = $current->translations ?? [];
            foreach ($translated as $locale => $text) {
                if (empty($values['bio'][$locale])) $values['bio'][$locale] = $text;
            }
            $current->update(['translations' => $values]);
        });
    }
}
