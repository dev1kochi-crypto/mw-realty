<?php

namespace App\Services\Chatbot;

/**
 * One AI provider's half of the grounded function-calling exchange — ChatbotService owns the
 * parts that must never depend on which provider is active (running the real property search,
 * sanitizing arguments, the system prompt); this only wraps the two raw model round-trips, in
 * whatever wire format that provider actually speaks.
 *
 * Swappable via config('chatbot.provider') / CHATBOT_PROVIDER — see AppServiceProvider's binding.
 */
interface ChatbotProvider
{
    /** Whether this provider actually has an API key set — lets ChatbotService distinguish
     *  "nothing configured yet" from a transient API failure and show a more useful message. */
    public function isConfigured(): bool;

    /**
     * First call — the model either answers directly or asks to call `search_properties`.
     *
     * @param  array<int, array{role: string, text: string}>  $history  prior visible turns only
     * @param  array  $functionDeclaration  the search_properties tool, in Gemini's own schema
     *         shape (uppercase JSON-Schema `type` values) — providers that need OpenAI-style
     *         lowercase types convert it themselves; see GroqChatbotProvider::toToolSchema().
     * @return array{text: ?string, functionCall: ?array{name: string, args: array}, state: mixed}|null
     *         null only on a hard failure (network/API error) — the caller falls back to a
     *         generic message. `state` is opaque, provider-specific conversation state that
     *         secondTurn() needs to continue the exchange (e.g. Gemini's thoughtSignature, or an
     *         OpenAI-style provider's running messages[] array).
     */
    public function firstTurn(string $systemInstruction, array $history, string $message, array $functionDeclaration): ?array;

    /**
     * Second call — feeds the function's real result back and asks for the final reply text.
     * Returns null on failure; the caller then falls back to a canned "here's what I found".
     */
    public function secondTurn(mixed $state, array $functionResult): ?string;
}
