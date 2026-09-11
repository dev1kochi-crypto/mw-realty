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
