<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProvider
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $model = null
    ) {
        $this->apiKey = $apiKey ?: config('services.gemini.key', env('GEMINI_API_KEY'));
        $this->model = $model ?: config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-latest'));
    }

    public function providerName(): string
    {
        return 'gemini';
    }

    public function generate(array $messages, array $tools = [], array $options = []): array
    {
        if (empty($this->apiKey)) {
            // Fallback to MockAiProvider if no key is configured, avoiding hard crashes
            $mock = new MockAiProvider;

            return $mock->generate($messages, $tools, $options);
        }

        $model = $options['model'] ?? $this->model;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        // Format contents for Gemini API
        $contents = [];
        foreach ($messages as $msg) {
            $role = match ($msg['role']) {
                'assistant', 'model' => 'model',
                'tool', 'function' => 'function',
                default => 'user',
            };

            if ($role === 'function') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $msg['name'] ?? 'tool_result',
                                'response' => [
                                    'name' => $msg['name'] ?? 'tool_result',
                                    'content' => is_string($msg['content']) ? json_decode($msg['content'], true) ?: ['result' => $msg['content']] : $msg['content'],
                                ],
                            ],
                        ],
                    ],
                ];
            } elseif (! empty($msg['tool_calls'])) {
                $parts = [];
                if (! empty($msg['content'])) {
                    $parts[] = ['text' => (string) $msg['content']];
                }
                foreach ($msg['tool_calls'] as $tc) {
                    $callPart = [
                        'functionCall' => [
                            'name' => $tc['name'],
                            'args' => (object) ($tc['arguments'] ?? []),
                        ],
                    ];
                    if (! empty($tc['id'])) {
                        $callPart['functionCall']['id'] = $tc['id'];
                    }
                    if (! empty($tc['thought_signature'])) {
                        $callPart['thoughtSignature'] = $tc['thought_signature'];
                    }
                    $parts[] = $callPart;
                }
                $contents[] = ['role' => 'model', 'parts' => $parts];
            } else {
                $contents[] = [
                    'role' => $role,
                    'parts' => [
                        ['text' => (string) ($msg['content'] ?? '')],
                    ],
                ];
            }
        }

        $payload = [
            'contents' => $contents,
        ];

        // System instructions
        if (! empty($options['system_instruction'])) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => (string) $options['system_instruction']],
                ],
            ];
        }

        // Tools / Function declarations
        if (! empty($tools)) {
            $functionDeclarations = [];
            foreach ($tools as $t) {
                $functionDeclarations[] = [
                    'name' => $t['name'],
                    'description' => $t['description'],
                    'parameters' => $t['parameters'],
                ];
            }
            $payload['tools'] = [
                ['functionDeclarations' => $functionDeclarations],
            ];
        }

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(300)
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning("Gemini API error ({$response->status()}): {$response->body()}. Fallback automático a MockAiProvider.");
                $mock = new MockAiProvider;

                return $mock->generate($messages, $tools, $options);
            }
        } catch (\Throwable $e) {
            Log::warning("Gemini API connection/timeout exception: {$e->getMessage()}. Fallback automático a MockAiProvider.");
            $mock = new MockAiProvider;

            return $mock->generate($messages, $tools, $options);
        }

        $data = $response->json();
        $candidate = $data['candidates'][0]['content'] ?? [];
        $parts = $candidate['parts'] ?? [];

        $text = null;
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text = ($text ? $text."\n" : '').$part['text'];
            }
            if (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => $part['functionCall']['id'] ?? ('call_'.uniqid()),
                    'name' => $part['functionCall']['name'],
                    'arguments' => $part['functionCall']['args'] ?? [],
                    'thought_signature' => $part['thoughtSignature'] ?? null,
                ];
            }
        }

        $tokensUsed = $data['usageMetadata']['totalTokenCount'] ?? 0;

        return [
            'content' => $text,
            'tool_calls' => $toolCalls,
            'tokens_used' => $tokensUsed,
            'model' => $model,
        ];
    }
}
