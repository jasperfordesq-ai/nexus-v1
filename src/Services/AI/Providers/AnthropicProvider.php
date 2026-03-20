<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\AI\Providers;

/**
 * AnthropicProvider — Thin delegate forwarding to \App\Services\AI\Providers\AnthropicProvider.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\AI\Providers\AnthropicProvider
 */
class AnthropicProvider
{

    public function getId(): string
    {
        return (new \App\Services\AI\Providers\AnthropicProvider())->getId();
    }

    public function getName(): string
    {
        return (new \App\Services\AI\Providers\AnthropicProvider())->getName();
    }

    public function chat(array $messages, array $options = []): array
    {
        return (new \App\Services\AI\Providers\AnthropicProvider())->chat($messages, $options);
    }

    public function streamChat(array $messages, callable $onChunk, array $options = []): void
    {
        (new \App\Services\AI\Providers\AnthropicProvider())->streamChat($messages, $onChunk, $options);
    }

    public function embed(string $text): array
    {
        return (new \App\Services\AI\Providers\AnthropicProvider())->embed($text);
    }
}
