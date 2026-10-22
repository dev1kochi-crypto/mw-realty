<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Groq's OpenAI-compatible chat-completions endpoint — free tier, generous request limits,
 * fast inference. Tool calling here follows the OpenAI convention (messages[] with an
 * assistant `tool_calls` entry, answered by a `role: 'tool'` message), which is a different
 * shape from GeminiChatbotProvider's native contents[]/functionCall format, not a variant of it.
 */
class GroqChatbotProvider implements ChatbotProvider
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(private readonly string $apiKey, private readonly string $model)
    {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function firstTurn(string $systemInstruction, array $history, string $message, array $functionDeclaration): ?array
    {
        $messages = $this->historyToMessages($systemInstruction, $history);
        $messages[] = ['role' => 'user', 'content' => $message];
        $tools = [$this->toToolSchema($functionDeclaration)];

        $response = $this->chatCompletion($messages, $tools);
        if ($response === null) {
            return null;
        }

        $choiceMessage = $response['choices'][0]['message'] ?? null;
        $toolCall = $choiceMessage['tool_calls'][0] ?? null;

        if (!$toolCall) {
            return ['text' => $choiceMessage['content'] ?? null, 'functionCall' => null, 'state' => null];
        }

        $args = json_decode($toolCall['function']['arguments'] ?? '{}', true);

        return [
            'text' => null,
            'functionCall' => ['name' => $toolCall['function']['name'], 'args' => is_array($args) ? $args : []],
            // secondTurn() needs the assistant's own tool-call message echoed back verbatim
            // (OpenAI-style APIs require that, same spirit as Gemini needing its functionCall
            // part replayed) plus the tool_call's id to address the result message at it.
            'state' => [
                'messages' => array_merge($messages, [$choiceMessage]),
                'tools' => $tools,
                'toolCallId' => $toolCall['id'],
            ],
        ];
    }

    public function secondTurn(mixed $state, array $functionResult): ?string
    {
        $messages = $state['messages'];
        $messages[] = [
            'role' => 'tool',
            'tool_call_id' => $state['toolCallId'],
            'content' => json_encode($functionResult),
        ];

        $response = $this->chatCompletion($messages, $state['tools']);
        return $response['choices'][0]['message']['content'] ?? null;
    }

    /** @return array<int, array{role: string, content: string}> */
    private function historyToMessages(string $systemInstruction, array $history): array
    {
        $messages = [['role' => 'system', 'content' => $systemInstruction]];
        foreach ($history as $turn) {
            $messages[] = ['role' => $turn['role'] === 'model' ? 'assistant' : 'user', 'content' => $turn['text']];
        }
        return $messages;
    }

    private function chatCompletion(array $messages, array $tools): ?array
    {
        try {
            $send = fn () => Http::timeout(15)
                ->withToken($this->apiKey)
                ->post(self::ENDPOINT, [
                    'model' => $this->model,
                    'messages' => $messages,
                    'tools' => $tools,
                    'tool_choice' => 'auto',
                    'temperature' => 0.3,
                    'max_tokens' => 512,
                ]);
            $response = $send();

            // Free-tier per-minute token limit: Groq says how long to wait (usually ~1s) — wait
            // once and retry, instead of failing the chat turn.
            if ($response->status() === 429) {
                $wait = (float) ($response->header('retry-after') ?: 2);
                if ($wait > 0 && $wait <= 5) {
                    usleep((int) ($wait * 1_000_000));
                    $response = $send();
                }
            }

            if (!$response->successful()) {
                Log::error('Groq API error: ' . $response->status() . ' ' . $response->body());
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Groq API request failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Gemini's declaration uses JSON-Schema `type` values in uppercase (its own quirk, e.g.
     *  'OBJECT', 'STRING'); OpenAI-style tool schemas expect the standard lowercase forms. */
    private function toToolSchema(array $declaration): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $declaration['name'],
                'description' => $declaration['description'],
                'parameters' => $this->lowercaseTypes($declaration['parameters']),
            ],
        ];
    }

    private function lowercaseTypes(array $schema, bool $nullable = false): array
    {
        if (isset($schema['type']) && is_string($schema['type'])) {
            $schema['type'] = strtolower($schema['type']);
        }
        // Groq validates tool arguments strictly, and for a broad request ("show me properties")
        // the model sends every filter as null — so each filter must also accept null
        // (ChatbotService::sanitizeArgs() drops nulls, i.e. "no filter").
        if ($nullable && isset($schema['type']) && $schema['type'] !== 'object') {
            $schema['type'] = [$schema['type'], 'null'];
            if (isset($schema['enum'])) {
                $schema['enum'] = [...$schema['enum'], null];
            }
        }
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $schema['properties'] = array_map(fn ($prop) => is_array($prop) ? $this->lowercaseTypes($prop, true) : $prop, $schema['properties']);
        }
        return $schema;
    }
}
