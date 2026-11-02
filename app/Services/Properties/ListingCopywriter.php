<?php

namespace App\Services\Properties;

use App\Models\FilterValue;
use App\Services\Chatbot\ChatbotProvider;
use App\Support\PermitRules;

/**
 * Writes listing copy — title, key features and description — from the listing's specs, with the
 * site's AI provider (config('chatbot.provider'), the same one the chatbot and /compare use).
 * Used by the property form's "Write with AI" button and, for rows without a description, by the
 * spreadsheet import. The model only sees the facts passed in and is told to invent nothing else.
 *
 * Modes: 'write' — fresh copy from the specs; 'improve' — rewrite the agent's own draft (kept
 * factual, made clearer and more appealing).
 */
class ListingCopywriter
{
    public const TONES = ['professional', 'luxury', 'friendly', 'concise'];

    /** Spec fields the model may use, in the order it sees them. */
    private const FACT_KEYS = [
        'listing_type', 'rental_period', 'property_type', 'category', 'completion_status', 'bedrooms', 'bathrooms', 'sqft', 'price', 'currency',
        'community', 'address', 'city', 'emirate', 'furnished', 'view', 'floor', 'parking', 'year_built', 'developer', 'amenities', 'notes',
    ];

    public function __construct(private readonly ChatbotProvider $provider, private readonly ListingTextNormalizer $text)
    {
    }

    public function available(): bool
    {
        return $this->provider->isConfigured();
    }

    /**
     * @param array $specs Listing values as the form / import holds them (option codes are turned into labels here).
     * @return array{title: string, key_features: string, description: string}|null Null when the AI is off or failed.
     */
    public function write(array $specs, string $lang = 'en', string $mode = 'write', string $tone = 'professional', ?string $draft = null): ?array
    {
        if (!$this->available()) {
            return null;
        }
        $facts = $this->facts($specs);
        $tone = in_array($tone, self::TONES, true) ? $tone : 'professional';
        $task = $mode === 'improve' && trim(strip_tags((string) $draft)) !== ''
            ? "Rewrite the agent's draft below into polished listing copy. Keep every fact in it that the specs don't contradict; fix grammar, structure and repetition; don't add new facts."
            : 'Write new listing copy from the specs.';

        $system = <<<TXT
You are a real estate copywriter for MW Realty, a UAE property portal. {$task}
Use ONLY the facts given (specs and, when present, the draft). Never invent amenities, distances, views, prices, payment plans, returns or developer claims. If a fact is missing, leave it out.
Tone: {$tone}. No emoji, no ALL CAPS, no exclamation marks, no markdown. Don't put the price in the title. Mention the permit only if given.
Write in the language with code "{$lang}".
Reply with JSON only, exactly this shape:
{"title": "max 80 characters, e.g. 'Spacious 2 Bed Apartment with Marina View in Dubai Marina'", "key_features": ["4 to 8 short highlights"], "paragraphs": ["3 to 4 paragraphs: the home, the space and finishes, the building / community, who it suits"], "bullets": ["optional: up to 8 facts worth listing (size, rooms, parking...)"]}
TXT;

        $message = json_encode(['specs' => $facts, 'draft' => $draft ? trim(strip_tags($draft)) : null], JSON_UNESCAPED_UNICODE);
        $reply = $this->provider->complete($system, $message, 1500);
        $json = $reply && preg_match('/\{.*\}/s', $reply, $m) ? json_decode($m[0], true) : null;
        if (!is_array($json) || !is_string($json['title'] ?? null) || !is_array($json['paragraphs'] ?? null)) {
            return null;
        }

        $description = $this->text->fromParts($json['paragraphs'], is_array($json['bullets'] ?? null) ? $json['bullets'] : []);
        if ($description === '') {
            return null;
        }

        return [
            'title' => $this->text->title($json['title']),
            'key_features' => $this->text->keyFeatures(implode("\n", array_filter((array) ($json['key_features'] ?? []), 'is_string'))),
            'description' => $description,
        ];
    }

    /** Option codes → readable labels; empty values dropped. */
    private function facts(array $specs): array
    {
        $labels = [];
        foreach (['listing_type', 'property_type', 'category', 'completion_status', 'rental_period', 'furnished' => 'furnishing', 'emirate', 'location'] as $field => $filterKey) {
            $field = is_int($field) ? $filterKey : $field;
            $value = $specs[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $labels[$field] = $this->label($filterKey, $value) ?? str_replace(['_', '-'], ' ', $value);
            }
        }
        if (empty($specs['community']) && isset($labels['location'])) {
            $specs['community'] = $labels['location'];
        }
        if (($labels['emirate'] ?? null) && ($specs['emirate'] ?? null) === PermitRules::NORTHERN) {
            $labels['emirate'] = 'Northern Emirates';
        }

        $amenities = collect($specs['amenities'] ?? [])
            ->map(fn ($a) => is_array($a) ? ($a['label']['en'] ?? $a['key'] ?? null) : $this->label('amenity', (string) $a) ?? $a)
            ->filter()->values()->all();

        $facts = [];
        foreach (self::FACT_KEYS as $key) {
            $value = match ($key) {
                'amenities' => $amenities,
                'sqft' => !empty($specs['sqft']) ? ((int) $specs['sqft']) . ' sq.ft' : null,
                'price' => !empty($specs['price']) ? number_format((float) $specs['price']) : null,
                'bedrooms' => isset($specs['bedrooms']) && $specs['bedrooms'] !== '' ? ((int) $specs['bedrooms'] === 0 ? 'Studio' : (int) $specs['bedrooms']) : null,
                default => $labels[$key] ?? (is_scalar($specs[$key] ?? null) ? trim((string) $specs[$key]) : null),
            };
            if ($value !== null && $value !== '' && $value !== []) {
                $facts[$key] = is_string($value) ? mb_substr($value, 0, 1000) : $value;
            }
        }

        return $facts;
    }

    private function label(string $filterKey, string $value): ?string
    {
        static $cache = [];
        $options = $cache[$filterKey] ??= FilterValue::whereHas('filter', fn ($q) => $q->where('key', $filterKey))
            ->get(['value', 'translations'])->mapWithKeys(fn ($o) => [$o->value => $o->translations['en']['label'] ?? null]);

        return $options[$value] ?? null;
    }
}
