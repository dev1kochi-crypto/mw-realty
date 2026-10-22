<?php

namespace App\Services;

use App\Models\CmsKit\Language;
use App\Models\PortalUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Google Cloud Translate v2 REST API (simple API-key auth, no OAuth
 * client needed). Used to auto-fill a client-entered field (e.g. an agent's bio) into every
 * other active site language, so a non-technical agent/company never has to touch a language
 * switcher themselves — see App\Models\PortalUser::getTranslation() for how it's read back.
 *
 * Google's free tier covers the first 500,000 characters/month, which comfortably covers this
 * app's scale (short bios, a handful of accounts); usage beyond that is billed to whatever
 * Google Cloud project GOOGLE_TRANSLATE_API_KEY belongs to.
 */
class AutoTranslator
{
    /**
     * Translate $text into $targetLang, or null if translation isn't configured or fails —
     * callers must treat a null return as "leave this locale slot empty," never as fatal.
     */
    public function translate(string $text, string $targetLang, ?string $sourceLang = null): ?string
    {
        if (self::driver() === 'mymemory') {
            return trim($text) === '' ? null : ($this->myMemoryMany(['t' => $text], $targetLang, $sourceLang, 'text')['t'] ?? null);
        }

        $key = config('services.google_translate.key');
        if (!$key || trim($text) === '') {
            return null;
        }

        try {
            $response = Http::asForm()->connectTimeout(3)->timeout(10)->post('https://translation.googleapis.com/language/translate/v2', array_filter([
                'key' => $key,
                'q' => $text,
                'target' => $targetLang,
                'source' => $sourceLang,
                'format' => 'text',
            ]));

            if (!$response->successful()) {
                Log::error('Google Translate request failed: ' . $response->body());
                return null;
            }

            return html_entity_decode(
                $response->json('data.translations.0.translatedText'),
                ENT_QUOTES
            ) ?: null;
        } catch (\Throwable $e) {
            Log::error('Google Translate request threw: ' . $e->getMessage());
            return null;
        }
    }

    /** 'google' (default, production) or 'mymemory' (free, no key — for testing). See config/services.php. */
    public static function driver(): string
    {
        return config('services.translate.driver') === 'mymemory' ? 'mymemory' : 'google';
    }

    public static function configured(): bool
    {
        return self::driver() === 'mymemory' || filled(config('services.google_translate.key'));
    }

    /**
     * Translate several strings into $targetLang in one request, keeping their keys. $format is
     * 'html' for rich text (tags are kept as-is) or 'text'. Returns null when not configured or
     * the request fails; empty strings come back empty without being sent.
     *
     * @param  array<string, string>  $texts
     * @return array<string, string>|null
     */
    public function translateMany(array $texts, string $targetLang, ?string $sourceLang = null, string $format = 'text'): ?array
    {
        if (self::driver() === 'mymemory') {
            return $this->myMemoryMany($texts, $targetLang, $sourceLang, $format);
        }

        $key = config('services.google_translate.key');
        $toSend = array_filter($texts, fn ($t) => is_string($t) && trim($t) !== '');
        if (!$key) {
            return null;
        }
        if (!$toSend) {
            return array_map(fn () => '', $texts);
        }

        try {
            // q repeated once per string — Google returns translations in the same order.
            $body = http_build_query(array_filter(['key' => $key, 'target' => $targetLang, 'source' => $sourceLang, 'format' => $format]));
            foreach ($toSend as $text) {
                $body .= '&q=' . rawurlencode($text);
            }
            $response = Http::withBody($body, 'application/x-www-form-urlencoded')->connectTimeout(3)->timeout(20)
                ->post('https://translation.googleapis.com/language/translate/v2');

            if (!$response->successful()) {
                Log::error('Google Translate request failed: ' . $response->body());
                return null;
            }

            $translated = $response->json('data.translations', []);
            $out = array_map(fn () => '', $texts);
            foreach (array_keys($toSend) as $i => $field) {
                $value = $translated[$i]['translatedText'] ?? '';
                $out[$field] = $format === 'html' ? $value : html_entity_decode($value, ENT_QUOTES);
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('Google Translate request threw: ' . $e->getMessage());
            return null;
        }
    }

    /** MyMemory's per-request limit is 500 bytes of query text — longer text is sent in pieces. */
    private const MYMEMORY_MAX_BYTES = 450;

    /**
     * Same contract as translateMany(), via the free MyMemory API (testing only — small daily
     * quota). Text is split into ≤450-byte pieces at sentence/word boundaries; for HTML only the
     * text between tags is translated, so the markup survives. Pieces go out in parallel.
     */
    private function myMemoryMany(array $texts, string $targetLang, ?string $sourceLang, string $format): ?array
    {
        $out = array_map(fn () => '', $texts);
        $plan = []; // field => list of [isText, string]
        $pieces = [];
        foreach ($texts as $field => $text) {
            if (!is_string($text) || trim($text) === '') {
                continue;
            }
            $parts = $format === 'html'
                ? preg_split('/(<[^>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY)
                : [$text];
            foreach ($parts as $part) {
                $isText = !($format === 'html' && str_starts_with($part, '<')) && trim(html_entity_decode($part)) !== '';
                if (!$isText) {
                    $plan[$field][] = [false, $part];
                    continue;
                }
                // Keep the leading/trailing whitespace outside the translated chunk.
                preg_match('/^(\s*)(.*?)(\s*)$/su', $format === 'html' ? html_entity_decode($part, ENT_QUOTES) : $part, $m);
                $plan[$field][] = [false, $m[1]];
                foreach ($this->chunk($m[2]) as $n => $chunk) {
                    if ($n > 0) {
                        $plan[$field][] = [false, ' '];
                    }
                    $plan[$field][] = [true, count($pieces)];
                    $pieces[] = $chunk;
                }
                $plan[$field][] = [false, $m[3]];
            }
        }
        if (!$pieces) {
            return $out;
        }

        try {
            $langpair = ($sourceLang ?: 'en') . '|' . $targetLang;
            $email = config('services.translate.mymemory_email');
            $responses = Http::pool(fn ($pool) => array_map(
                fn ($q) => $pool->connectTimeout(5)->timeout(20)->get('https://api.mymemory.translated.net/get', array_filter(['q' => $q, 'langpair' => $langpair, 'de' => $email])),
                $pieces
            ));

            $translated = [];
            foreach ($responses as $i => $response) {
                $text = $response instanceof \Illuminate\Http\Client\Response ? $response->json('responseData.translatedText') : null;
                if (!$response instanceof \Illuminate\Http\Client\Response || !$response->successful() || (int) $response->json('responseStatus') !== 200 || !is_string($text) || str_contains($text, 'MYMEMORY WARNING')) {
                    Log::warning('MyMemory translate failed: ' . ($response instanceof \Illuminate\Http\Client\Response ? $response->body() : get_class($response)));
                    return null;
                }
                $translated[$i] = html_entity_decode($text, ENT_QUOTES);
            }
        } catch (\Throwable $e) {
            Log::error('MyMemory translate threw: ' . $e->getMessage());
            return null;
        }

        foreach ($plan as $field => $steps) {
            $out[$field] = implode('', array_map(function ($step) use ($translated, $format) {
                [$isPiece, $value] = $step;
                if (!$isPiece) {
                    return $value;
                }
                $text = $translated[$value];
                return $format === 'html' ? htmlspecialchars($text, ENT_NOQUOTES) : $text;
            }, $steps));
        }

        return $out;
    }

    /** Splits $text into ≤ MYMEMORY_MAX_BYTES pieces, preferring sentence, then word, boundaries. */
    private function chunk(string $text): array
    {
        if (strlen($text) <= self::MYMEMORY_MAX_BYTES) {
            return [$text];
        }
        $chunks = [];
        $current = '';
        foreach (preg_split('/(?<=[.!?\n])\s+|\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (strlen($candidate) > self::MYMEMORY_MAX_BYTES && $current !== '') {
                $chunks[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Save $text as $portalUser->translations[$attribute][$defaultLocale], then auto-fill every
     * other active site language that doesn't already have a value for this attribute — an
     * existing value (whether admin-corrected or a prior translation) is never overwritten, so a
     * manual fix survives future saves. Does NOT call ->save(); the caller persists the model.
     */
    public function fillMissingTranslations(PortalUser $portalUser, string $attribute, string $text, string $defaultLocale): void
    {
        $translations = $portalUser->translations ?? [];
        $translations[$attribute][$defaultLocale] = $text;

        $otherLocales = Language::active()->where('code', '!=', $defaultLocale)->pluck('code');

        foreach ($otherLocales as $locale) {
            if (!empty($translations[$attribute][$locale])) {
                continue;
            }

            $translated = $this->translate($text, $locale, $defaultLocale);
            if ($translated !== null) {
                $translations[$attribute][$locale] = $translated;
            }
        }

        $portalUser->translations = $translations;
    }
}
