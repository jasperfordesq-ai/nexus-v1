<?php

namespace Nexus\Services\AI\Providers;

/**
 * Ollama Provider (Self-hosted)
 *
 * Supports locally running Ollama with Llama, Mistral, and other models.
 * https://github.com/ollama/ollama/blob/main/docs/api.md
 */
class OllamaProvider extends BaseProvider
{
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->apiUrl = $config['api_url'] ?? 'http://localhost:11434';
    }

    public function getId(): string
    {
        return 'ollama';
    }

    public function getName(): string
    {
        return 'Ollama (Self-hosted)';
    }

    /**
     * Ollama doesn't need API key
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl);
    }

    /**
     * Send a chat completion request to Ollama API
     */
    public function chat(array $messages, array $options = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Ollama host URL not configured');
        }

        $model = $this->getModel($options);

        $data = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'temperature' => $options['temperature'] ?? 0.7,
            ],
        ];

        if (isset($options['max_tokens'])) {
            $data['options']['num_predict'] = $options['max_tokens'];
        }

        try {
            $response = $this->request('api/chat', $data);
        } catch (\Exception $e) {
            // Check if Ollama is running
            if (str_contains($e->getMessage(), 'Connection refused')) {
                throw new \Exception('Ollama is not running. Please start Ollama with: ollama serve');
            }
            throw $e;
        }

        // Parse response
        $content = $response['message']['content'] ?? '';

        // Ollama doesn't provide token counts, estimate
        $tokensEstimate = (int) (strlen($content) / 4);

        return [
            'content' => $content,
            'tokens_used' => $tokensEstimate,
            'tokens_input' => 0,
            'tokens_output' => $tokensEstimate,
            'model' => $model,
            'finish_reason' => 'stop',
            'provider' => 'ollama',
        ];
    }

    /**
     * Stream chat with Ollama
     */
    public function streamChat(array $messages, callable $onChunk, array $options = []): void
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Ollama host URL not configured');
        }

        $model = $this->getModel($options);

        $data = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'options' => [
                'temperature' => $options['temperature'] ?? 0.7,
            ],
        ];

        $url = rtrim($this->apiUrl, '/') . '/api/chat';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 300,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($onChunk) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    $json = json_decode($line, true);
                    if ($json) {
                        $content = $json['message']['content'] ?? '';
                        $done = $json['done'] ?? false;

                        if ($content) {
                            $onChunk([
                                'content' => $content,
                                'done' => $done,
                            ]);
                        }
                    }
                }
                return strlen($data);
            },
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Generate embeddings using Ollama
     */
    public function embed(string $text): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Ollama host URL not configured');
        }

        $data = [
            'model' => $this->defaultModel,
            'prompt' => $text,
        ];

        $response = $this->request('api/embeddings', $data);

        return $response['embedding'] ?? [];
    }

    /**
     * Get available models from Ollama
     */
    public function getModels(): array
    {
        try {
            $url = rtrim($this->apiUrl, '/') . '/api/tags';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            $models = [];

            if (isset($data['models'])) {
                foreach ($data['models'] as $model) {
                    $name = $model['name'] ?? '';
                    $models[$name] = [
                        'name' => $name,
                        'size' => $model['size'] ?? 0,
                        'modified' => $model['modified_at'] ?? null,
                    ];
                }
            }

            return $models;
        } catch (\Exception $e) {
            // Return config models if can't connect
            return $this->config['models'] ?? [];
        }
    }

    /**
     * Test connection with simple ping
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Ollama host URL not configured',
                'latency_ms' => 0
            ];
        }

        $start = microtime(true);

        try {
            $url = rtrim($this->apiUrl, '/') . '/api/tags';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $latency = (int) ((microtime(true) - $start) * 1000);

            if ($error) {
                return [
                    'success' => false,
                    'message' => "Cannot connect to Ollama: $error",
                    'latency_ms' => $latency
                ];
            }

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $modelCount = count($data['models'] ?? []);

                return [
                    'success' => true,
                    'message' => "Connected to Ollama ($modelCount models available)",
                    'latency_ms' => $latency
                ];
            }

            return [
                'success' => false,
                'message' => "Ollama returned HTTP $httpCode",
                'latency_ms' => $latency
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'latency_ms' => (int) ((microtime(true) - $start) * 1000)
            ];
        }
    }
}
