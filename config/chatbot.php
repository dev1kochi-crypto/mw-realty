<?php

return [
    // Not a credential (unlike config/services.php's provider keys) — just the
    // display name shown in the widget and used in the system instruction.
    'persona_name' => env('CHATBOT_NAME', 'Remi'),

    // Which AI provider actually answers — see App\Services\Chatbot\ChatbotProvider and its
    // AppServiceProvider binding. 'groq' (OpenAI-compatible, generous free tier) or 'gemini'
    // (Google's native API) — both implementations stay in the codebase either way, so
    // switching back is just this one env value, no code change.
    'provider' => env('CHATBOT_PROVIDER', 'groq'),
];
