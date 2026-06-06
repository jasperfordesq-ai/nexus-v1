<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\Laravel\TestCase;

/**
 * Feature tests for SendGridWebhookController — SendGrid event webhooks (public).
 */
class SendGridWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    private string $sendGridPrivateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureSendGridSigningKey();
    }

    // ------------------------------------------------------------------
    //  POST /webhooks/sendgrid/events (PUBLIC — no auth)
    // ------------------------------------------------------------------

    public function test_events_webhook_is_public(): void
    {
        $response = $this->postSignedSendGridEvents([
            [
                'email' => 'test@example.com',
                'event' => 'delivered',
                'timestamp' => time(),
            ],
        ]);

        // Should NOT return 401 — this is a public webhook
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_events_webhook_accepts_payload(): void
    {
        $response = $this->postSignedSendGridEvents([
            [
                'email' => 'user@example.com',
                'event' => 'open',
                'timestamp' => time(),
                'sg_message_id' => 'abc123',
            ],
        ]);

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }

    public function test_events_webhook_rejects_stale_signed_payload(): void
    {
        $response = $this->postSignedSendGridEvents([
            [
                'email' => 'stale@example.com',
                'event' => 'delivered',
                'timestamp' => time() - 900,
            ],
        ], time() - 900);

        $response->assertStatus(401);
    }

    public function test_events_webhook_rejects_replayed_signed_payload(): void
    {
        $events = [
            [
                'email' => 'replay@example.com',
                'event' => 'delivered',
                'timestamp' => time(),
            ],
        ];
        $timestamp = time();

        $first = $this->postSignedSendGridEvents($events, $timestamp);
        $second = $this->postSignedSendGridEvents($events, $timestamp);

        $first->assertStatus(200);
        $second->assertStatus(401);
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function postSignedSendGridEvents(array $events, ?int $timestamp = null): TestResponse
    {
        $timestamp ??= time();
        $payload = json_encode($events);
        $signature = '';
        openssl_sign((string) $timestamp . $payload, $signature, $this->sendGridPrivateKey, OPENSSL_ALGO_SHA256);

        return $this->apiPost('/webhooks/sendgrid/events', $events, [
            'X-Twilio-Email-Event-Webhook-Timestamp' => (string) $timestamp,
            'X-Twilio-Email-Event-Webhook-Signature' => base64_encode($signature),
        ]);
    }

    private function configureSendGridSigningKey(): void
    {
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ];

        $opensslConfigPath = $this->localOpenSslConfigPath();
        if ($opensslConfigPath !== null) {
            $config['config'] = $opensslConfigPath;
        }

        $privateKey = openssl_pkey_new([
            ...$config,
        ]);

        if ($privateKey === false) {
            $this->markTestSkipped('OpenSSL key generation is unavailable in this PHP environment.');
        }

        openssl_pkey_export($privateKey, $privatePem, null, $config);
        $details = openssl_pkey_get_details($privateKey);
        $publicPem = (string) ($details['key'] ?? '');
        $publicKeyBody = str_replace(
            ["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\r", "\n"],
            '',
            $publicPem
        );

        $this->sendGridPrivateKey = $privatePem;
        $_ENV['SENDGRID_WEBHOOK_VERIFICATION_KEY'] = trim($publicKeyBody);
        $_SERVER['SENDGRID_WEBHOOK_VERIFICATION_KEY'] = trim($publicKeyBody);
        putenv('SENDGRID_WEBHOOK_VERIFICATION_KEY=' . trim($publicKeyBody));
    }

    private function localOpenSslConfigPath(): ?string
    {
        $candidates = [
            getenv('OPENSSL_CONF') ?: null,
            'C:/laragon/bin/apache/httpd-2.4.66-260223-Win64-VS18/conf/openssl.cnf',
            'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/extras/ssl/openssl.cnf',
            'C:/laragon/bin/git/mingw64/etc/ssl/openssl.cnf',
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
