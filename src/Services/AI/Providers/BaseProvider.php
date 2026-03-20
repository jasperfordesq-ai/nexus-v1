<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\AI\Providers;

/**
 * BaseProvider — Thin delegate forwarding to \App\Services\AI\Providers\BaseProvider.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\AI\Providers\BaseProvider
 */
class BaseProvider
{

    public function complete(string $prompt, array $options = []): string
    {
        return (new \App\Services\AI\Providers\BaseProvider())->complete($prompt, $options);
    }

    public function embed(string $text): array
    {
        return (new \App\Services\AI\Providers\BaseProvider())->embed($text);
    }

    public function isConfigured(): bool
    {
        return (new \App\Services\AI\Providers\BaseProvider())->isConfigured();
    }

    public function getModels(): array
    {
        return (new \App\Services\AI\Providers\BaseProvider())->getModels();
    }

    public function testConnection(): array
    {
        return (new \App\Services\AI\Providers\BaseProvider())->testConnection();
    }
}
