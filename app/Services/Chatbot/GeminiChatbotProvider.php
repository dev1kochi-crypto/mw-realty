<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini, via its native generateContent endpoint (not the OpenAI-compatible one) — the
 * function-calling shape here (contents[] of {role, parts}, functionCall/functionResponse parts)
 * is Gemini-specific and doesn't generalize to other providers, unlike GroqChatbotProvider's
 * OpenAI-style messages[].
 */
class GeminiChatbotProvider implements ChatbotProvider
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(private readonly string $apiKey, private readonly string $model)
    {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function firstTurn(string $systemInstruction, array $history, string $message, array $functionDeclaration): ?array
    {
        $contents = $this->historyToContents($history);
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];
        $tools = [['functionDeclarations' => [$functionDeclaration]]];

        $response = $this->generateContent($systemInstruction, $contents, $tools);
        if ($response === null) {
            return null;
        }

        $functionCallPart = $this->extractFunctionCallPart($response);
        if ($functionCallPart === null) {
            return ['text' => $this->extractText($response), 'functionCall' => null, 'state' => null];
        }

        return [
            'text' => null,
            'functionCall' => $functionCallPart['functionCall'],
            // Carries what secondTurn() needs to continue the exchange: the conversation so far,
            // the system instruction (Gemini takes it per-request, not stored server-side), the
            // tools declaration (required on every call once tools are in play), and the whole
            // original part — not just a rebuilt {functionCall: ...} — because it also carries
            // `thoughtSignature`, an opaque token this model version attaches to its own
            // function-call turns and requires back when that turn is replayed to it. Dropping
            // it doesn't error immediately but breaks the follow-up call (see git history for
            // the exact 400 this caused before it was carried through here).
            'state' => [
                'contents' => $contents,
                'systemInstruction' => $systemInstruction,
                'tools' => $tools,
                'functionCallPart' => $functionCallPart,
            ],
        ];
    }

    public function secondTurn(mixed $state, array $functionResult): ?string
    {
        $contents = $state['contents'];
        $contents[] = ['role' => 'model', 'parts' => [$state['functionCallPart']]];
        // 'function' is rejected by this model version ("Role 'function' is not supported...");
        // 'user_context' is the role it actually accepts for a tool result.
        $contents[] = ['role' => 'user_context', 'parts' => [['functionResponse' => [
            'name' => $state['functionCallPart']['functionCall']['name'],
            'response' => $functionResult,
        ]]]];

        $response = $this->generateContent($state['systemInstruction'], $contents, $state['tools']);
        return $response ? $this->extractText($response) : null;
    }

    /** @return array<int, array{role: string, parts: array}> */
    private function historyToContents(array $history): array
    {
        return array_map(
            fn (array $turn) => ['role' => $turn['role'], 'parts' => [['text' => $turn['text']]]],
            $history,
        );
    }

    private function generateContent(string $systemInstruction, array $contents, array $tools): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->post(sprintf(self::ENDPOINT, $this->model), [
                    'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => $contents,
                    'tools' => $tools,
                    'tool_config' => ['function_calling_config' => ['mode' => 'AUTO']],
                    'generation_config' => ['temperature' => 0.3, 'maxOutputTokens' => 512],
                ]);

            if (!$response->successful()) {
                Log::error('Gemini API error: ' . $response->status() . ' ' . $response->body());
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Gemini API request failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Returns the whole `part` (functionCall + its sibling thoughtSignature, if present), not
     *  just the inner functionCall object — see firstTurn()'s state for why that sibling key
     *  has to survive the round trip. */
    private function extractFunctionCallPart(array $response): ?array
    {
        foreach ($response['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['functionCall'])) {
                return $part;
            }
        }
        return null;
    }

    private function extractText(array $response): ?string
    {
        foreach ($response['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['text'])) {
                return $part['text'];
            }
        }
        return null;
    }
}
