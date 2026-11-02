<?php

namespace App\Services;

use App\Models\FilterValue;
use App\Services\Chatbot\ChatbotProvider;
use App\Services\Chatbot\PropertyMatcher;
use Illuminate\Support\Facades\Cache;

/**
 * Grounds the AI's replies in this site's own property data via function-calling: the model
 * never gets to invent a listing — it can only call `search_properties`, and can only talk about
 * whatever that returns. Which actual AI provider runs the two model round-trips is swappable
 * (see CHATBOT_PROVIDER in .env + AppServiceProvider's ChatbotProvider binding) — this class
 * owns everything that must stay identical regardless of provider: running the search, arg
 * sanitization, and the system prompt.
 *
 * The function's arguments double as the buyer's brief: whatever the visitor describes ("2 beds
 * in Abu Dhabi near schools, rental income, 20-minute commute") is captured, merged with what
 * they said earlier in the conversation, saved on it (Api\ChatbotController) and used to rank
 * listings by how well they fit (PropertyMatcher) rather than only filter them.
 */
class ChatbotService
{
    /** Listing category values — PropertyMatcher's own filter logic, not admin-editable data. */
    private const CATEGORY_VALUES = ['sale', 'rent', 'off_plan'];

    private const ROOM_VALUES = ['studio', '1', '2', '3', '4', '5+'];

    private const PURPOSE_VALUES = ['live', 'invest'];

    private const FINANCING_VALUES = ['cash', 'mortgage'];

    public function __construct(
        private readonly PropertyMatcher $matcher,
        private readonly ChatbotProvider $provider,
    ) {
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $history  prior visible turns only —
     *         function-call/response turns are reconstructed fresh below, never carried by the client.
     * @param  array  $brief  the buyer's brief captured so far in this conversation
     * @param  string  $lang  language of the listing content on the result cards (a site language)
     * @param  string|null  $language  the chat language the visitor picked — see self::language()
     * @return array{reply: string, properties: array, pagination: ?array, requirements: array, error: bool}
     */
    public function reply(string $message, array $history, string $lang, array $brief = [], ?string $language = null): array
    {
        $language = self::language($language);

        if (!$this->provider->isConfigured()) {
            return $this->fallback(self::text($language, 'unavailable'), $brief, $language);
        }

        $first = $this->provider->firstTurn($this->systemInstruction($brief, $language), $history, $message, $this->searchPropertiesDeclaration());
        if ($first === null) {
            return $this->fallback(null, $brief, $language);
        }

        if ($first['functionCall'] === null) {
            return [
                'reply' => $first['text'] ?? self::text($language, 'error'),
                'properties' => [],
                'pagination' => null,
                'requirements' => $brief,
                'error' => false,
            ];
        }

        // New details add to / replace what was captured earlier; anything not mentioned again is kept.
        $brief = array_merge($brief, $this->sanitizeArgs(is_array($first['functionCall']['args'] ?? null) ? $first['functionCall']['args'] : []));
        $result = $this->matcher->match($brief, $lang, PropertyMatcher::nearbyLabels($lang));
        $properties = $result['properties'];

        $replyText = $this->provider->secondTurn($first['state'], [
            'buyer_brief' => $brief,
            // Ranked best match first; `match.score` is 0-100 (null when the brief has nothing to score).
            'properties' => array_map(fn ($card) => array_intersect_key($card, array_flip(['name', 'location', 'price', 'beds', 'baths', 'area', 'type', 'purpose_badge', 'match'])), $properties),
            'listings_considered' => $result['total'],
            'not_in_listing_data' => array_values(array_intersect(['max_service_charge', 'commute'], array_keys($brief))),
        ]);

        return [
            'reply' => $replyText ?? self::text($language, $properties ? 'matches' : 'no_matches'),
            'properties' => $properties,
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'total' => count($properties)],
            'requirements' => $brief,
            'error' => false,
        ];
    }

    /** Unknown keys dropped, out-of-enum / non-positive values treated as "not given" — degrades
     *  to a broader search instead of failing the whole turn on a malformed function-call arg. */
    private function sanitizeArgs(array $args): array
    {
        $clean = [];
        foreach (['location', 'commute', 'notes'] as $key) {
            if (!empty($args[$key]) && is_string($args[$key])) {
                $clean[$key] = mb_substr(trim($args[$key]), 0, 255);
            }
        }
        $enums = [
            'property_type' => $this->propertyTypeValues(),
            'category' => self::CATEGORY_VALUES,
            'bedrooms' => self::ROOM_VALUES,
            'bathrooms' => self::ROOM_VALUES,
            'purpose' => self::PURPOSE_VALUES,
            'financing' => self::FINANCING_VALUES,
        ];
        foreach ($enums as $key => $values) {
            // Some models send 2 rather than "2" for bedrooms.
            $value = is_int($args[$key] ?? null) ? (string) $args[$key] : ($args[$key] ?? null);
            // ...or "5"/"6" rather than "5+", "0" rather than "studio".
            if ($values === self::ROOM_VALUES && is_numeric($value)) {
                $value = (int) $value >= 5 ? '5+' : ((int) $value === 0 ? 'studio' : (string) (int) $value);
            }
            if (in_array($value, $values, true)) {
                $clean[$key] = $value;
            }
        }
        foreach (['min_price', 'max_price', 'min_area', 'max_service_charge'] as $key) {
            if (is_numeric($args[$key] ?? null) && $args[$key] > 0) {
                $clean[$key] = (float) $args[$key];
            }
        }
        if (!empty($clean['min_price']) && !empty($clean['max_price']) && $clean['min_price'] > $clean['max_price']) {
            unset($clean['min_price']);
        }
        if (is_bool($args['furnished'] ?? null)) {
            $clean['furnished'] = $args['furnished'];
        }
        if (is_array($args['nearby'] ?? null)) {
            $nearby = array_values(array_unique(array_intersect($args['nearby'], array_keys(PropertyMatcher::nearbyLabels('en')))));
            if ($nearby) {
                $clean['nearby'] = $nearby;
            }
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
            'description' => 'Searches MW Realty\'s live property listings and ranks them against the buyer\'s brief. '
                . 'Always call this before describing any specific property to a user. Fill in EVERY detail the buyer '
                . 'has given about what they want — it is also saved as their requirements for the agent. Returns only '
                . 'real, currently active listings, best match first — never fabricate results.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'location' => [
                        'type' => 'STRING',
                        'description' => "Free-text community/city/area name, e.g. 'Dubai Marina', 'JVC', 'Abu Dhabi'. Matches partially, case-insensitively.",
                    ],
                    'property_type' => [
                        'type' => 'STRING',
                        'enum' => $this->propertyTypeValues(),
                        'description' => 'Exact property type.',
                    ],
                    'category' => [
                        'type' => 'STRING',
                        'enum' => self::CATEGORY_VALUES,
                        'description' => "'sale' (buy) or 'rent' for listing type, or 'off_plan' for under-construction properties.",
                    ],
                    'bedrooms' => [
                        'type' => 'STRING',
                        'enum' => self::ROOM_VALUES,
                        'description' => "Bedroom count, 'studio' for 0-bedroom, or '5+' for 5 or more.",
                    ],
                    'bathrooms' => [
                        'type' => 'STRING',
                        'enum' => self::ROOM_VALUES,
                        'description' => "Minimum bathroom count, or '5+' for 5 or more.",
                    ],
                    'min_price' => [
                        'type' => 'NUMBER',
                        'description' => 'Lowest price in AED (total price to buy, or yearly rent). Convert "1.5M" to 1500000.',
                    ],
                    'max_price' => [
                        'type' => 'NUMBER',
                        'description' => 'Budget — highest price in AED (total price to buy, or yearly rent). Convert "2M" to 2000000.',
                    ],
                    'min_area' => [
                        'type' => 'NUMBER',
                        'description' => 'Minimum size in square feet.',
                    ],
                    'nearby' => [
                        'type' => 'ARRAY',
                        // An empty enum is rejected by the APIs — only before any nearby type is set up.
                        'items' => array_filter(['type' => 'STRING', 'enum' => array_keys(PropertyMatcher::nearbyLabels('en')) ?: null]),
                        'description' => "Places the buyer wants close by, e.g. 'near schools' => ['school'], 'close to the metro' => ['metro_station'].",
                    ],
                    'furnished' => [
                        'type' => 'BOOLEAN',
                        'description' => 'true if they want it furnished, false if unfurnished. Leave out if not mentioned.',
                    ],
                    'purpose' => [
                        'type' => 'STRING',
                        'enum' => self::PURPOSE_VALUES,
                        'description' => "'invest' when they want rental income / an investment / ROI, 'live' when it is their own home.",
                    ],
                    'financing' => [
                        'type' => 'STRING',
                        'enum' => self::FINANCING_VALUES,
                        'description' => "How they'll pay, if mentioned: 'cash' or 'mortgage'.",
                    ],
                    'max_service_charge' => [
                        'type' => 'NUMBER',
                        'description' => "Highest yearly service charge in AED they'd accept, if mentioned.",
                    ],
                    'commute' => [
                        'type' => 'STRING',
                        'description' => "Commute wish in their words, e.g. '20 minutes to Downtown Dubai'.",
                    ],
                    'notes' => [
                        'type' => 'STRING',
                        'description' => 'Any other wish not covered above (lifestyle, view, pets, quiet area, …), short.',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    /** A chat language from config('chatbot.languages'); anything else falls back to the default. */
    public static function language(?string $code): string
    {
        $code = strtolower((string) $code);

        return config("chatbot.languages.{$code}") ? $code : config('chatbot.default_language');
    }

    /** One of the fixed lines (`strings`) in the chat language, English when it's missing there. */
    public static function text(string $language, string $key): string
    {
        return config("chatbot.languages.{$language}.strings.{$key}")
            ?? config("chatbot.languages.en.strings.{$key}", '');
    }

    /**
     * The picker's list: code, English and native name, text direction, speech locale and the
     * widget's fixed lines (welcome with the persona name filled in, placeholder, switch offer …).
     */
    public static function languages(): array
    {
        $name = config('chatbot.persona_name');

        return collect(config('chatbot.languages'))->map(fn ($l, $code) => [
            'code' => $code,
            'name' => $l['name'],
            'native' => $l['native'],
            'dir' => $l['dir'],
            'speech' => $l['speech'],
            'strings' => array_merge(
                array_intersect_key($l['strings'], array_flip(['welcome', 'placeholder', 'switch_offer', 'switched', 'error'])),
                ['welcome' => str_replace('{name}', $name, $l['strings']['welcome'])],
            ),
        ])->values()->all();
    }

    private function systemInstruction(array $brief, string $language): string
    {
        $name = config('chatbot.persona_name');
        $languageName = config("chatbot.languages.{$language}.name");

        return "You are {$name}, MW Realty's property search assistant. Buyers describe what they want "
            . 'in their own words — budget, location, lifestyle, schools, commute, financing, investment goals. '
            . 'You must NEVER invent, estimate, or guess property details (price, location, bedrooms, '
            . 'availability). The only properties you may describe are ones returned by the search_properties '
            . "function in this conversation. If you haven't called it yet for the user's request, call it "
            . 'before answering, passing every requirement they have described. Results come ranked with a '
            . 'match score and reasons — explain briefly why the top ones fit and mention any important miss. '
            . 'Service charge and commute time are not in the listing data: never claim a property meets them; '
            . 'say the agent can confirm. If it returns zero results, tell the user no matches were found and '
            . 'suggest which requirement to relax — do not suggest specific alternative properties from '
            . 'memory. Keep replies to 2-3 sentences; the actual property cards are rendered separately by '
            . "the app, so don't repeat every field for every result in prose."
            . ' Base every fact on the listing data the search returns and say so when something is an estimate or '
            . 'not in the data. You are not a lawyer, financial adviser or licensed real-estate broker: for legal, tax, '
            . 'visa or financing decisions, give general information only and suggest the buyer confirm with a '
            . 'qualified professional or the listing\'s licensed agency.'
            . " LANGUAGE: the visitor chose {$languageName}. Write every reply entirely in {$languageName}, in a warm, "
            . "natural tone that suits {$languageName} speakers — even when they write in another language, when earlier "
            . 'messages in this conversation are in another language, or when listing data is in English. Understand '
            . "messages in any language. Keep property names, community names and amounts as they are. Always call "
            . 'search_properties with English values (locations as written in the listings, e.g. "Dubai Marina").'
            . ($brief ? ' The buyer\'s requirements captured so far in this conversation: ' . json_encode($brief)
                . '. When they refine or add something, search again — earlier requirements are kept automatically.' : '');
    }

    private function fallback(?string $message, array $brief, string $language): array
    {
        return [
            'reply' => $message ?? self::text($language, 'error'),
            'properties' => [],
            'pagination' => null,
            'requirements' => $brief,
            'error' => true,
        ];
    }
}
