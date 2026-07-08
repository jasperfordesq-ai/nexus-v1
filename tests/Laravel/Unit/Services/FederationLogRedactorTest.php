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

    public function test_redacts_client_secret_and_private_key_keys(): void
    {
        // Regression (audit L5): these confidential keys were absent from the
        // allow-list, so partner credential payloads logged them in cleartext.
        $json = json_encode([
            'client_secret' => 'cs-abcdef',
            'private_key' => '-----BEGIN KEY-----abc-----END KEY-----',
            'credential' => 'cred-xyz',
        ]);

        $redacted = (string) FederationLogRedactor::redactJsonString($json);

        $this->assertStringNotContainsString('cs-abcdef', $redacted);
        $this->assertStringNotContainsString('BEGIN KEY', $redacted);
        $this->assertStringNotContainsString('cred-xyz', $redacted);
    }

    public function test_redacts_entire_subtree_under_a_sensitive_key(): void
    {
        // Regression (audit L5): array_walk_recursive only reached scalar leaves,
        // so a secret nested as an object under a sensitive key leaked in full.
        $json = json_encode([
            'credential' => [
                'oauth' => ['refresh' => 'super-secret-refresh', 'expires' => 3600],
            ],
        ]);

        $redacted = (string) FederationLogRedactor::redactJsonString($json);

        $this->assertStringNotContainsString('super-secret-refresh', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }

    public function test_redacts_keys_by_sensitive_fragment(): void
    {
        // A prefixed key (not an exact allow-list entry) is still redacted.
        $json = json_encode([
            'partner_client_secret' => 'frag-secret',
            'webhook_token' => 'frag-token',
            'safe_field' => 'visible',
        ]);

        $redacted = (string) FederationLogRedactor::redactJsonString($json);

        $this->assertStringNotContainsString('frag-secret', $redacted);
        $this->assertStringNotContainsString('frag-token', $redacted);
        $this->assertStringContainsString('visible', $redacted);
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
