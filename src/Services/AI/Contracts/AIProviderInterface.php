<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\AI\Contracts;

/**
 * AI Provider Interface
 *
 * Contract that all AI providers must implement.
 * Provides a consistent API for different AI backends.
 */
interface AIProviderInterface
{
    /**
     * Send a chat completion request
     *
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param array $options Additional options (model, temperature, max_tokens, etc.)
     * @return array Response with 'content', 'tokens_used', 'model', 'finish_reason'
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Simple text completion (single prompt, single response)
     *
     * @param string $prompt The prompt text
     * @param array $options Additional options
     * @return string The completion text
     */
    public function complete(string $prompt, array $options = []): string;

    /**
     * Generate embeddings for text (for similarity search)
     *
     * @param string $text Text to embed
     * @return array Embedding vector
     */
    public function embed(string $text): array;

    /**
     * Stream a chat completion with callback for each chunk
     *
     * @param array $messages Array of message objects
     * @param callable $onChunk Callback function receiving each text chunk
     * @param array $options Additional options
     * @return void
     */
    public function streamChat(array $messages, callable $onChunk, array $options = []): void;

    /**
     * Get list of available models for this provider
     *
     * @return array Array of model info ['id' => ['name' => '...', 'context_window' => ...]]
     */
    public function getModels(): array;

    /**
     * Check if the provider is properly configured (has API key, etc.)
     *
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Get the provider name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the provider ID (gemini, openai, anthropic, ollama)
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Test the connection to the AI provider
     *
     * @return array ['success' => bool, 'message' => string, 'latency_ms' => int]
     */
    public function testConnection(): array;
}
