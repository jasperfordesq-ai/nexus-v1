<?php

namespace Nexus\Services\AI\Providers;

/**
 * Anthropic Claude Provider
 *
 * Supports Claude 3 (Opus, Sonnet, Haiku) and Claude 3.5 models.
 * https://docs.anthropic.com/claude/reference
 *
 * CRITICAL FIX: All API keys are strictly sanitized (trimmed) before every request
 * to prevent 401 errors caused by hidden whitespace or newlines.
 */
class AnthropicProvider extends BaseProvider
{
    private string $apiVersion = '2023-06-01';

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->apiVersion = $config['api_version'] ?? '2023-06-01';
    }

    public function getId(): string
    {
        return 'anthropic';
    }

    public function getName(): string
    {
        return 'Anthropic Claude';
    }

    /**
     * Send a chat completion request to Anthropic API
     * CRITICAL FIX: API key is strictly sanitized before every request
     */
    public function chat(array $messages, array $options = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Anthropic API key not configured');
        }

        // CRITICAL: Strictly sanitize the API key to remove any whitespace/newlines
        // This prevents 401 errors from hidden characters
        $apiKey = trim($this->apiKey);

        // DEBUG: Log sanitized key info to verify it's clean
        error_log("Anthropic Request: Key Length: " . strlen($apiKey) . " | Preview: " . substr($apiKey, 0, 10) . "...");

        $model = $this->getModel($options);

        // Extract system message if present
        $systemPrompt = '';
        $chatMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemPrompt .= $message['content'] . "\n";
            } else {
                $chatMessages[] = [
                    'role' => $message['role'],
                    'content' => $message['content'],
                ];
            }
        }

        $data = [
            'model' => $model,
            'messages' => $chatMessages,
            'max_tokens' => $options['max_tokens'] ?? 2048,
        ];

        if ($systemPrompt) {
            $data['system'] = trim($systemPrompt);
        }

        if (isset($options['temperature'])) {
            $data['temperature'] = $options['temperature'];
        }

        // CRITICAL: Use sanitized $apiKey, NOT $this->apiKey
        $headers = [
            'x-api-key: ' . $apiKey,
            'anthropic-version: ' . $this->apiVersion,
            'content-type: application/json',
        ];

        $response = $this->request('messages', $data, $headers);

        // Parse response
        $content = '';
        if (isset($response['content']) && is_array($response['content'])) {
            foreach ($response['content'] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }
        }

        $usage = $response['usage'] ?? [];

        return [
            'content' => $content,
            'tokens_used' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
            'tokens_input' => ($usage['input_tokens'] ?? 0),
            'tokens_output' => ($usage['output_tokens'] ?? 0),
            'model' => $model,
            'finish_reason' => $response['stop_reason'] ?? 'stop',
            'provider' => 'anthropic',
        ];
    }

    /**
     * Stream chat with Anthropic
     * CRITICAL FIX: API key is strictly sanitized before every request
     */
    public function streamChat(array $messages, callable $onChunk, array $options = []): void
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Anthropic API key not configured');
        }

        // CRITICAL: Strictly sanitize the API key to remove any whitespace/newlines
        // This prevents 401 errors from hidden characters
        $apiKey = trim($this->apiKey);

        // DEBUG: Log sanitized key info to verify it's clean
        error_log("Anthropic Stream Request: Key Length: " . strlen($apiKey) . " | Preview: " . substr($apiKey, 0, 10) . "...");

        $model = $this->getModel($options);

        // Extract system message
        $systemPrompt = '';
        $chatMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemPrompt .= $message['content'] . "\n";
            } else {
                $chatMessages[] = [
                    'role' => $message['role'],
                    'content' => $message['content'],
                ];
            }
        }

        $data = [
            'model' => $model,
            'messages' => $chatMessages,
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'stream' => true,
        ];

        if ($systemPrompt) {
            $data['system'] = trim($systemPrompt);
        }

        // CRITICAL: Use sanitized $apiKey, NOT $this->apiKey
        $headers = [
            'x-api-key: ' . $apiKey,
            'anthropic-version: ' . $this->apiVersion,
            'content-type: application/json',
        ];

        $url = rtrim($this->apiUrl, '/') . '/messages';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($onChunk) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    if (str_starts_with($line, 'data: ')) {
                        $json = json_decode(substr($line, 6), true);
                        if ($json) {
                            $type = $json['type'] ?? '';

                            if ($type === 'content_block_delta') {
                                $text = $json['delta']['text'] ?? '';
                                if ($text) {
                                    $onChunk([
                                        'content' => $text,
                                        'done' => false,
                                    ]);
                                }
                            } elseif ($type === 'message_stop') {
                                $onChunk([
                                    'content' => '',
                                    'done' => true,
                                ]);
                            }
                        }
                    }
                }
                return strlen($data);
            },
        ]);

        curl_exec($ch);

        // Check for curl errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Anthropic stream error: $error");
        }

        curl_close($ch);
    }

    /**
     * Embeddings not directly supported by Anthropic API
     */
    public function embed(string $text): array
    {
        throw new \Exception("Anthropic Claude doesn't support embeddings. Use Voyager or another embedding service.");
    }
}
