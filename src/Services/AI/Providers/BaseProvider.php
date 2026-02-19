<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\AI\Providers;

use Nexus\Services\AI\Contracts\AIProviderInterface;

/**
 * Base AI Provider
 *
 * Common functionality for all AI providers.
 */
abstract class BaseProvider implements AIProviderInterface
{
    protected string $apiKey = '';
    protected string $apiUrl = '';
    protected string $defaultModel = '';
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->apiKey = $config['api_key'] ?? '';
        $this->apiUrl = $config['api_url'] ?? '';
        $this->defaultModel = $config['default_model'] ?? '';
    }

    /**
     * Make an HTTP request to the AI API
     */
    protected function request(string $endpoint, array $data, array $headers = []): array
    {
        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');

        $defaultHeaders = [
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("AI API request failed: $error");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decoded['error']['message'] ?? $decoded['message'] ?? $response;
            throw new \Exception("AI API error ($httpCode): $errorMessage");
        }

        return $decoded ?? [];
    }

    /**
     * Stream an HTTP request to the AI API
     */
    protected function streamRequest(string $endpoint, array $data, callable $onChunk, array $headers = []): void
    {
        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: text/event-stream',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_TIMEOUT => 300,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($onChunk) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || $line === 'data: [DONE]') {
                        continue;
                    }
                    if (str_starts_with($line, 'data: ')) {
                        $json = json_decode(substr($line, 6), true);
                        if ($json) {
                            $onChunk($json);
                        }
                    }
                }
                return strlen($data);
            },
        ]);

        curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("AI streaming request failed: $error");
        }
    }

    /**
     * Simple completion using chat API
     */
    public function complete(string $prompt, array $options = []): string
    {
        $response = $this->chat([
            ['role' => 'user', 'content' => $prompt]
        ], $options);

        return $response['content'] ?? '';
    }

    /**
     * Default embed implementation (not all providers support this)
     */
    public function embed(string $text): array
    {
        throw new \Exception("Embeddings not supported by " . $this->getName());
    }

    /**
     * Check if configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) || ($this->config['self_hosted'] ?? false);
    }

    /**
     * Get models from config
     */
    public function getModels(): array
    {
        return $this->config['models'] ?? [];
    }

    /**
     * Get the model to use (from options or default)
     */
    protected function getModel(array $options): string
    {
        return $options['model'] ?? $this->defaultModel;
    }

    /**
     * Test connection with a simple request
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Provider not configured (missing API key)',
                'latency_ms' => 0,
                'model' => $this->defaultModel,
                'provider' => $this->getName()
            ];
        }

        $start = microtime(true);

        try {
            $response = $this->chat([
                ['role' => 'user', 'content' => 'Say "OK" if you can hear me.']
            ], ['max_tokens' => 10]);

            $latency = (int) ((microtime(true) - $start) * 1000);

            return [
                'success' => true,
                'message' => 'Connection successful',
                'latency_ms' => $latency,
                'model' => $response['model'] ?? $this->defaultModel,
                'provider' => $this->getName(),
                'response' => $response['content'] ?? ''
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
                'model' => $this->defaultModel,
                'provider' => $this->getName()
            ];
        }
    }
}
