<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\FederationLogRedactor;
use Tests\Laravel\TestCase;

final class FederationLogRedactorTest extends TestCase
{
    public function test_redacts_sensitive_json_fields_recursively(): void
    {
        $json = json_encode([
            'message' => 'private message body',
            'profile' => [
                'bio' => 'private bio',
                'avatar_url' => 'https://example.test/avatar.png',
                'metadata' => ['token' => 'nested-token'],
            ],
            'safe' => 'visible',
        ]);

        $redacted = FederationLogRedactor::redactJsonString($json);

        $this->assertStringContainsString('visible', (string) $redacted);
        $this->assertStringContainsString('[REDACTED]', (string) $redacted);
        $this->assertStringNotContainsString('private message body', (string) $redacted);
        $this->assertStringNotContainsString('private bio', (string) $redacted);
        $this->assertStringNotContainsString('avatar.png', (string) $redacted);
        $this->assertStringNotContainsString('nested-token', (string) $redacted);
    }

    public function test_redacts_sensitive_text_error_surfaces(): void
    {
        $redacted = FederationLogRedactor::redactText(
            'Authorization: Bearer abc123 token=secret-token password=hunter2'
        );

        $this->assertStringContainsString('[REDACTED]', (string) $redacted);
        $this->assertStringNotContainsString('abc123', (string) $redacted);
        $this->assertStringNotContainsString('secret-token', (string) $redacted);
        $this->assertStringNotContainsString('hunter2', (string) $redacted);
    }
}
