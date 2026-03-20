<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\AI\Providers;

/**
 * OpenAIProvider — Thin delegate forwarding to \App\Services\AI\Providers\OpenAIProvider.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\AI\Providers\OpenAIProvider
 */
class OpenAIProvider
{

    public function getId(): string
    {
        return (new \App\Services\AI\Providers\OpenAIProvider())->getId();
    }

    public function getName(): string
    {
        return (new \App\Services\AI\Providers\OpenAIProvider())->getName();
    }

    public function chat(array $messages, array $options = []): array
    {
        return (new \App\Services\AI\Providers\OpenAIProvider())->chat($messages, $options);
    }

    public function streamChat(array $messages, callable $onChunk, array $options = []): void
    {
        (new \App\Services\AI\Providers\OpenAIProvider())->streamChat($messages, $onChunk, $options);
    }

    public function embed(string $text): array
    {
        return (new \App\Services\AI\Providers\OpenAIProvider())->embed($text);
    }
}
