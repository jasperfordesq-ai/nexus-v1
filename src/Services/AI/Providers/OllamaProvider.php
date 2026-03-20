<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\AI\Providers;

/**
 * OllamaProvider — Thin delegate forwarding to \App\Services\AI\Providers\OllamaProvider.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\AI\Providers\OllamaProvider
 */
class OllamaProvider
{

    public function getId(): string
    {
        return (new \App\Services\AI\Providers\OllamaProvider())->getId();
    }

    public function getName(): string
    {
        return (new \App\Services\AI\Providers\OllamaProvider())->getName();
    }

    public function isConfigured(): bool
    {
        return (new \App\Services\AI\Providers\OllamaProvider())->isConfigured();
    }

    public function chat(array $messages, array $options = []): array
    {
        return (new \App\Services\AI\Providers\OllamaProvider())->chat($messages, $options);
    }

    public function streamChat(array $messages, callable $onChunk, array $options = []): void
    {
        (new \App\Services\AI\Providers\OllamaProvider())->streamChat($messages, $onChunk, $options);
    }

    public function embed(string $text): array
    {
        return (new \App\Services\AI\Providers\OllamaProvider())->embed($text);
    }

    public function getModels(): array
    {
        return (new \App\Services\AI\Providers\OllamaProvider())->getModels();
    }

    public function testConnection(): array
    {
        return (new \App\Services\AI\Providers\OllamaProvider())->testConnection();
    }
}
