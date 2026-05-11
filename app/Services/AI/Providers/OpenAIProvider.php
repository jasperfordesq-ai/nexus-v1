<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI\Providers;

/**
 * OpenAI GPT Provider
 *
 * Supports GPT-4, GPT-4o, GPT-3.5-turbo and other OpenAI models, including
 * function/tool calling for the AI chat agent.
 *
 * https://platform.openai.com/docs/api-reference
 */
class OpenAIProvider extends BaseProvider
{
    public function getId(): string
    {
        return 'openai';
    }

    public function getName(): string
    {
        return 'OpenAI';
    }

    /**
     * Send a chat completion request to OpenAI API.
     *
     * Accepts the provider-neutral message format used by the platform:
     *   - role: system|user|assistant|tool
     *   - assistant messages may carry a 'tool_calls' array
     *   - tool messages carry 'tool_call_id' and 'content'
     *
     * Options:
     *   - model, temperature, max_tokens, top_p (passthrough)
     *   - tools  => OpenAI-style tool schemas [{ type: 'function', function: {...} }]
     *   - tool_choice => 'auto' | 'required' | 'none' | { type, function: {name} }
     */
    public function chat(array $messages, array $options = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('OpenAI API key not configured');
        }

        $model = $this->getModel($options);

        $data = [
            'model' => $model,
            'messages' => $this->translateMessages($messages),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2048,
        ];

        if (isset($options['top_p'])) {
            $data['top_p'] = $options['top_p'];
        }

        if (!empty($options['tools']) && is_array($options['tools'])) {
            $data['tools'] = $options['tools'];
            if (!empty($options['tool_choice'])) {
                $data['tool_choice'] = $options['tool_choice'];
            }
        }

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
        ];

        if (!empty($this->config['org_id'])) {
            $headers[] = 'OpenAI-Organization: ' . $this->config['org_id'];
        }

        $response = $this->request('chat/completions', $data, $headers);

        $choice = $response['choices'][0] ?? [];
        $msg = $choice['message'] ?? [];
        $content = $msg['content'] ?? '';
        $finishReason = $choice['finish_reason'] ?? 'stop';
        $usage = $response['usage'] ?? [];

        $toolCalls = [];
        if (!empty($msg['tool_calls']) && is_array($msg['tool_calls'])) {
            foreach ($msg['tool_calls'] as $tc) {
                $fn = $tc['function'] ?? [];
                $rawArgs = $fn['arguments'] ?? '{}';
                $parsedArgs = is_string($rawArgs) ? json_decode($rawArgs, true) : $rawArgs;
                $toolCalls[] = [
                    'id' => $tc['id'] ?? '',
                    'name' => $fn['name'] ?? '',
                    'arguments' => is_array($parsedArgs) ? $parsedArgs : [],
                    'arguments_raw' => is_string($rawArgs) ? $rawArgs : json_encode($rawArgs),
                ];
            }
        }

        return [
            'content' => is_string($content) ? $content : '',
            'tool_calls' => $toolCalls,
            'tokens_used' => ($usage['total_tokens'] ?? 0),
            'tokens_input' => ($usage['prompt_tokens'] ?? 0),
            'tokens_output' => ($usage['completion_tokens'] ?? 0),
            'model' => $model,
            'finish_reason' => $finishReason,
            'provider' => 'openai',
        ];
    }

    /**
     * Translate provider-neutral messages to OpenAI's wire format.
     *
     * Most fields pass through unchanged. Assistant messages with tool_calls
     * need the tool_calls array reconstructed in OpenAI's function-arguments
     * shape (arguments must be a JSON string).
     */
    private function translateMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            if ($role === 'tool') {
                $out[] = [
                    'role' => 'tool',
                    'tool_call_id' => $m['tool_call_id'] ?? '',
                    'content' => is_string($m['content'] ?? null) ? $m['content'] : json_encode($m['content']),
                ];
                continue;
            }
            if ($role === 'assistant' && !empty($m['tool_calls'])) {
                $tc = [];
                foreach ($m['tool_calls'] as $call) {
                    $args = $call['arguments_raw'] ?? null;
                    if ($args === null) {
                        $args = isset($call['arguments']) ? json_encode($call['arguments']) : '{}';
                    }
                    $tc[] = [
                        'id' => $call['id'] ?? '',
                        'type' => 'function',
                        'function' => [
                            'name' => $call['name'] ?? '',
                            'arguments' => is_string($args) ? $args : json_encode($args),
                        ],
                    ];
                }
                $out[] = [
                    'role' => 'assistant',
                    'content' => isset($m['content']) && is_string($m['content']) ? $m['content'] : null,
                    'tool_calls' => $tc,
                ];
                continue;
            }
            $out[] = [
                'role' => $role,
                'content' => is_string($m['content'] ?? null) ? $m['content'] : json_encode($m['content']),
            ];
        }
        return $out;
    }

    /**
     * Stream chat with OpenAI.
     *
     * Note: tool calling is not yet supported in the streaming path. The
     * non-streaming `chat()` method is used for the tool-call loop.
     */
    public function streamChat(array $messages, callable $onChunk, array $options = []): void
    {
        if (!$this->isConfigured()) {
            throw new \Exception('OpenAI API key not configured');
        }

        $model = $this->getModel($options);

        $data = [
            'model' => $model,
            'messages' => $this->translateMessages($messages),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'stream' => true,
        ];

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
        ];

        if (!empty($this->config['org_id'])) {
            $headers[] = 'OpenAI-Organization: ' . $this->config['org_id'];
        }

        $this->streamRequest('chat/completions', $data, function ($chunk) use ($onChunk) {
            $content = $chunk['choices'][0]['delta']['content'] ?? '';
            $finishReason = $chunk['choices'][0]['finish_reason'] ?? null;

            if ($content) {
                $onChunk([
                    'content' => $content,
                    'done' => $finishReason === 'stop',
                ]);
            }
        }, $headers);
    }

    public function embed(string $text): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('OpenAI API key not configured');
        }

        $data = [
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ];

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $response = $this->request('embeddings', $data, $headers);

        return $response['data'][0]['embedding'] ?? [];
    }
}
