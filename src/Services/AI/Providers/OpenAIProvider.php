<?php

namespace Nexus\Services\AI\Providers;

/**
 * OpenAI GPT Provider
 *
 * Supports GPT-4, GPT-4o, GPT-3.5-turbo and other OpenAI models.
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
     * Send a chat completion request to OpenAI API
     */
    public function chat(array $messages, array $options = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('OpenAI API key not configured');
        }

        $model = $this->getModel($options);

        $data = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2048,
        ];

        if (isset($options['top_p'])) {
            $data['top_p'] = $options['top_p'];
        }

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
        ];

        // Add organization header if configured
        if (!empty($this->config['org_id'])) {
            $headers[] = 'OpenAI-Organization: ' . $this->config['org_id'];
        }

        $response = $this->request('chat/completions', $data, $headers);

        // Parse response
        $content = $response['choices'][0]['message']['content'] ?? '';
        $finishReason = $response['choices'][0]['finish_reason'] ?? 'stop';
        $usage = $response['usage'] ?? [];

        return [
            'content' => $content,
            'tokens_used' => ($usage['total_tokens'] ?? 0),
            'tokens_input' => ($usage['prompt_tokens'] ?? 0),
            'tokens_output' => ($usage['completion_tokens'] ?? 0),
            'model' => $model,
            'finish_reason' => $finishReason,
            'provider' => 'openai',
        ];
    }

    /**
     * Stream chat with OpenAI
     */
    public function streamChat(array $messages, callable $onChunk, array $options = []): void
    {
        if (!$this->isConfigured()) {
            throw new \Exception('OpenAI API key not configured');
        }

        $model = $this->getModel($options);

        $data = [
            'model' => $model,
            'messages' => $messages,
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

    /**
     * Generate embeddings using OpenAI
     */
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
