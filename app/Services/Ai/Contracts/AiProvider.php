<?php

namespace App\Services\Ai\Contracts;

interface AiProvider
{
    /**
     * Send structured conversation and tools to AI model and return standard response.
     *
     * @param array $messages Normalized array of ['role' => 'user'|'model'|'function', 'content' => string, 'function_call' => ?array]
     * @param array $tools Array of tool definitions with JSON schema
     * @param array $options Model options (temperature, system_instruction, etc.)
     * @return array Standardized response array:
     *               ['content' => ?string, 'tool_calls' => array, 'tokens_used' => int, 'model' => string]
     */
    public function generate(array $messages, array $tools = [], array $options = []): array;

    public function providerName(): string;
}
