<?php

namespace App\Services;

use App\Models\FilterValue;
use App\Services\Chatbot\ChatbotProvider;
use Illuminate\Support\Facades\Cache;

/**
 * Grounds the AI's replies in this site's own property data via function-calling: the model
 * never gets to invent a listing — it can only call `search_properties`, which runs the exact
 * same query as the /properties listing page (PropertiesPageService), and can only talk about
 * whatever that returns. Which actual AI provider runs the two model round-trips is swappable
 * (see CHATBOT_PROVIDER in .env + AppServiceProvider's ChatbotProvider binding) — this class
 * owns everything that must stay identical regardless of provider: running the search, arg
 * sanitization, and the system prompt.
 */
class ChatbotService
{
    /** PropertiesPageService's $category param only ever recognizes these three strings
     *  (baked into its own ->when() logic) — not admin-editable data, so safe to hardcode. */
    private const CATEGORY_VALUES = ['sale', 'rent', 'off_plan'];

    private const ROOM_VALUES = ['studio', '1', '2', '3', '4', '5+'];

    public function __construct(
        private readonly PropertiesPageService $propertiesPage,
        private readonly ChatbotProvider $provider,
    ) {
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $history  prior visible turns only —
     *         function-call/response turns are reconstructed fresh below, never carried by the client.
     * @return array{reply: string, properties: array, pagination: ?array, error: bool}
     */
    public function reply(string $message, array $history, string $lang): array
    {
        if (!$this->provider->isConfigured()) {
            return $this->fallback('The AI assistant isn\'t configured yet — please use the enquiry form on any listing instead.');
        }

        $first = $this->provider->firstTurn($this->systemInstruction(), $history, $message, $this->searchPropertiesDeclaration());
        if ($first === null) {
            return $this->fallback();
        }

        if ($first['functionCall'] === null) {
            return [
                'reply' => $first['text'] ?? 'Sorry, I didn\'t catch that — could you rephrase?',
                'properties' => [],
                'pagination' => null,
                'error' => false,
            ];
        }

        $args = $this->sanitizeArgs(is_array($first['functionCall']['args'] ?? null) ? $first['functionCall']['args'] : []);
        $result = $this->propertiesPage->getListingData(
            $lang,
            $args['location'] ?? null,
            $args['property_type'] ?? null,
            $args['category'] ?? null,
            $args['bedrooms'] ?? null,
            $args['bathrooms'] ?? null,
        );

        $replyText = $this->provider->secondTurn($first['state'], [
            'properties' => $result['properties'],
            'pagination' => $result['pagination'],
        ]);

        return [
            'reply' => $replyText ?? ($result['properties']->isEmpty()
                ? 'I couldn\'t find any properties matching that — try broadening your search.'
                : 'Here\'s what I found:'),
            'properties' => $result['properties']->values()->all(),
            'pagination' => $result['pagination'],
            'error' => false,
        ];
    }

    /** Unknown keys dropped, out-of-enum values treated as "not applied" — degrades to a
     *  broader search instead of failing the whole turn on a malformed function-call arg. */
    private function sanitizeArgs(array $args): array
    {
        $clean = [];
        if (!empty($args['location']) && is_string($args['location'])) {
            $clean['location'] = $args['location'];
        }
        if (in_array($args['property_type'] ?? null, $this->propertyTypeValues(), true)) {
            $clean['property_type'] = $args['property_type'];
        }
        if (in_array($args['category'] ?? null, self::CATEGORY_VALUES, true)) {
            $clean['category'] = $args['category'];
        }
        if (in_array($args['bedrooms'] ?? null, self::ROOM_VALUES, true)) {
            $clean['bedrooms'] = $args['bedrooms'];
        }
        if (in_array($args['bathrooms'] ?? null, self::ROOM_VALUES, true)) {
            $clean['bathrooms'] = $args['bathrooms'];
        }
        return $clean;
    }

    /** Admin-editable data (Filter/FilterValue, same source PropertyFilterController reads) —
     *  cached so a newly-added property type shows up here without a deploy. */
    private function propertyTypeValues(): array
    {
        return Cache::remember('chatbot:property-type-enum', 3600, fn () => FilterValue::query()
            ->whereHas('filter', fn ($q) => $q->where('key', 'property_type'))
            ->active()
            ->pluck('value')
            ->all());
    }

    private function searchPropertiesDeclaration(): array
    {
        return [
            'name' => 'search_properties',
            'description' => 'Searches MW Realty\'s live property listings database. Always call this before describing any specific property to a user. Returns only real, currently active listings — never fabricate results.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'location' => [
                        'type' => 'STRING',
                        'description' => "Free-text community/city/area name, e.g. 'Dubai Marina', 'JVC', 'Downtown Dubai'. Matches partially, case-insensitively.",
                    ],
                    'property_type' => [
                        'type' => 'STRING',
                        'enum' => $this->propertyTypeValues(),
                        'description' => 'Exact property type.',
                    ],
                    'category' => [
                        'type' => 'STRING',
                        'enum' => self::CATEGORY_VALUES,
                        'description' => "'sale' or 'rent' for listing type, or 'off_plan' for under-construction properties.",
                    ],
                    'bedrooms' => [
                        'type' => 'STRING',
                        'enum' => self::ROOM_VALUES,
                        'description' => "Exact bedroom count, 'studio' for 0-bedroom, or '5+' for 5 or more.",
                    ],
                    'bathrooms' => [
                        'type' => 'STRING',
                        'enum' => self::ROOM_VALUES,
                        'description' => "Exact bathroom count, or '5+' for 5 or more.",
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    private function systemInstruction(): string
    {
        $name = config('chatbot.persona_name');

        return "You are {$name}, MW Realty's property search assistant. You must NEVER invent, "
            . 'estimate, or guess property details (price, location, bedrooms, availability). The '
            . 'only properties you may describe are ones returned by the search_properties function '
            . "in this conversation. If you haven't called it yet for the user's request, call it "
            . 'before answering. If it returns zero results, tell the user no matches were found and '
            . 'suggest broadening the search — do not suggest specific alternative properties from '
            . 'memory. Keep replies to 2-3 sentences; the actual property cards are rendered '
            . "separately by the app, so don't repeat every field for every result in prose.";
    }

    private function fallback(?string $message = null): array
    {
        return [
            'reply' => $message ?? 'Sorry, I\'m having trouble answering right now — please try again in a moment, or use the enquiry form on any listing.',
            'properties' => [],
            'pagination' => null,
            'error' => true,
        ];
    }
}
