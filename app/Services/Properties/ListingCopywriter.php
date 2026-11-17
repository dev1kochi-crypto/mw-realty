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
Use ONLY the facts given (specs and, when present, the draft). Every physical feature you mention must appear in those facts.
Never invent: rooms or layout (open-plan, dining area, en-suite / own bathroom, maid's room, study, balcony, terrace, storage, laundry), kitchen or appliances, finishes or materials (marble, wooden floors, high-quality finishes, colour palette), condition (renovated, upgraded, brand new), light, ceilings, amenities, distances, views, prices, payment plans, returns or developer claims. If a fact is missing, leave it out — a shorter text is better than an invented one.
Tone: {$tone}. The tone changes the wording only, never the facts. No emoji, no ALL CAPS, no exclamation marks, no markdown. Don't put the price in the title. Mention the permit only if given.
Write in the language with code "{$lang}".
Reply with JSON only, exactly this shape:
{"title": "max 80 characters, e.g. '2 Bed Apartment in Dubai Marina' (add a view only if a view is given)", "key_features": ["2 to 8 short highlights, each one a given fact"], "paragraphs": ["1 to 3 short paragraphs built only from the facts: the home (type, size, rooms, furnishing), the location / community, who it may suit"], "bullets": ["optional: up to 8 given facts (size, rooms, parking...)"]}
TXT;

        $message = json_encode(['specs' => $facts, 'draft' => $draft ? trim(strip_tags($draft)) : null], JSON_UNESCAPED_UNICODE);
        $json = $this->ask($system, $message);
        if ($json === null) {
            return null;
        }

        // The model still slips in features now and then ("open-plan", "own bathroom", "high-quality
        // finishes"). Ask once more naming them; whatever is still unsupported is dropped.
        $source = mb_strtolower(json_encode($facts, JSON_UNESCAPED_UNICODE) . ' ' . strip_tags((string) $draft));
        if ($invented = $this->inventedFeatures($json, $source)) {
            $retry = $this->ask($system . "\nYour previous reply mentioned features that are NOT in the facts: " . implode(', ', $invented) . '. Leave them out.', $message);
            if ($retry !== null && count($this->inventedFeatures($retry, $source)) < count($invented)) {
                $json = $retry;
            }
            $json = $this->withoutInventedFeatures($json, $source, $facts);
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

    /**
     * Physical features the model tends to make up. Key = the feature's name (what the retry is
     * told); `facts` = words that, found in the facts / draft, allow it; `text` = how it shows in
     * the copy, English and Arabic (the facts themselves are always English).
     */
    private const FEATURE_CHECKS = [
        'open-plan layout' => ['facts' => ['open-plan', 'open plan'], 'text' => ['open-plan', 'open plan', 'مفتوح', 'مفتوحة']],
        'dining area' => ['facts' => ['dining'], 'text' => ['dining', 'طعام']],
        'en-suite / own bathroom' => ['facts' => ['en-suite', 'ensuite', 'en suite', 'own bathroom', 'private bathroom'], 'text' => ['en-suite', 'ensuite', 'en suite', 'own bathroom', 'private bathroom', 'its own bath', 'حمام خاص', 'حمامها الخاص', 'حمام داخلي']],
        'kitchen' => ['facts' => ['kitchen'], 'text' => ['kitchen', 'مطبخ']],
        'appliances' => ['facts' => ['appliance'], 'text' => ['appliance', 'أجهزة']],
        'finishes' => ['facts' => ['finish'], 'text' => ['finishes', 'finishing', 'high-quality', 'high quality', 'premium quality', 'تشطيب', 'عالية الجودة']],
        'colour palette' => ['facts' => ['palette'], 'text' => ['palette', 'neutral tones', 'ألوان محايدة']],
        'flooring' => ['facts' => ['floor tile', 'flooring', 'marble', 'parquet', 'wooden floor'], 'text' => ['marble', 'parquet', 'wooden floor', 'hardwood', 'flooring', 'رخام', 'باركيه']],
        'balcony / terrace' => ['facts' => ['balcon', 'terrace'], 'text' => ['balcony', 'balconies', 'terrace', 'شرفة', 'بلكونة', 'تراس']],
        "maid's room" => ['facts' => ['maid'], 'text' => ['maid', 'غرفة خادمة']],
        'study' => ['facts' => ['study'], 'text' => ['study room', 'a study', 'غرفة مكتب']],
        'storage / laundry' => ['facts' => ['storage', 'laundry'], 'text' => ['storage', 'laundry', 'built-in wardrobe', 'walk-in', 'غسيل', 'تخزين']],
        'renovated / upgraded' => ['facts' => ['renovat', 'upgrad', 'brand new'], 'text' => ['renovated', 'upgraded', 'brand new', 'brand-new', 'مجدد', 'تم تجديد']],
        'natural light' => ['facts' => ['natural light', 'sunlight', 'floor-to-ceiling'], 'text' => ['natural light', 'sunlight', 'light-filled', 'floor-to-ceiling', 'إضاءة طبيعية', 'ضوء طبيعي']],
    ];

    /** One request → the parsed JSON, or null when the reply isn't usable. */
    private function ask(string $system, string $message): ?array
    {
        $reply = $this->provider->complete($system, $message, 1500);
        $json = $reply && preg_match('/\{.*\}/s', $reply, $m) ? json_decode($m[0], true) : null;

        return is_array($json) && is_string($json['title'] ?? null) && is_array($json['paragraphs'] ?? null) ? $json : null;
    }

    /** @return string[] names of the features the copy mentions but the facts don't support */
    private function inventedFeatures(array $json, string $source): array
    {
        $copy = mb_strtolower(implode(' ', array_filter(array_merge(
            [$json['title'] ?? ''], (array) ($json['key_features'] ?? []), (array) ($json['paragraphs'] ?? []), (array) ($json['bullets'] ?? []),
        ), 'is_string')));

        return array_keys(array_filter(self::FEATURE_CHECKS, fn ($check) => $this->mentionsAny($copy, $check['text']) && !$this->mentionsAny($source, $check['facts'])));
    }

    /** Drop every sentence / highlight / bullet that still names an unsupported feature. */
    private function withoutInventedFeatures(array $json, string $source, array $facts): array
    {
        $unsupported = array_merge(...array_values(array_map(fn ($check) => $check['text'],
            array_filter(self::FEATURE_CHECKS, fn ($check) => !$this->mentionsAny($source, $check['facts'])))));
        $clean = fn (string $text) => !$this->mentionsAny(mb_strtolower($text), $unsupported);

        $json['paragraphs'] = array_values(array_filter(array_map(function ($paragraph) use ($clean) {
            $sentences = preg_split('/(?<=[.!?؟])\s+/u', (string) $paragraph);

            return implode(' ', array_filter($sentences, fn ($s) => trim($s) !== '' && $clean($s)));
        }, array_filter((array) $json['paragraphs'], 'is_string')), fn ($p) => trim($p) !== ''));
        foreach (['key_features', 'bullets'] as $key) {
            $json[$key] = array_values(array_filter((array) ($json[$key] ?? []), fn ($line) => is_string($line) && $clean($line)));
        }
        if (!$clean($json['title'])) {
            // e.g. "Luxury 2 Bed Apartment with Open-Plan Living" → a plain title from the facts.
            $json['title'] = trim(implode(' ', array_filter([
                isset($facts['bedrooms']) ? ($facts['bedrooms'] === 'Studio' ? 'Studio' : "{$facts['bedrooms']} Bed") : null,
                $facts['property_type'] ?? 'Property',
                ($facts['community'] ?? $facts['city'] ?? null) ? 'in ' . ($facts['community'] ?? $facts['city']) : null,
            ])));
        }

        return $json;
    }

    private function mentionsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, mb_strtolower($needle)) !== false) {
                return true;
            }
        }

        return false;
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
