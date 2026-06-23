<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\TurnstileService;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\TestCase;

class TurnstileServiceTest extends TestCase
{
    private TurnstileService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new TurnstileService();
        $this->clearSecret();
    }

    protected function tearDown(): void
    {
        $this->clearSecret();
        parent::tearDown();
    }

    private function clearSecret(): void
    {
        putenv('TURNSTILE_SECRET_KEY');
        unset($_ENV['TURNSTILE_SECRET_KEY'], $_SERVER['TURNSTILE_SECRET_KEY']);
    }

    private function setSecret(string $value): void
    {
        putenv('TURNSTILE_SECRET_KEY=' . $value);
        $_ENV['TURNSTILE_SECRET_KEY'] = $value;
        $_SERVER['TURNSTILE_SECRET_KEY'] = $value;
    }

    public function test_skips_verification_when_secret_unset(): void
    {
        Http::fake();
        $this->assertTrue($this->svc->verify('any-token'));
        Http::assertNothingSent();
    }

    public function test_skips_verification_with_test_pass_key(): void
    {
        $this->setSecret('1x0000000000000000000000000000000AA');
        Http::fake();
        $this->assertTrue($this->svc->verify('token'));
        Http::assertNothingSent();
    }

    public function test_missing_token_with_real_secret_is_rejected(): void
    {
        $this->setSecret('real-secret-key');
        Http::fake();
        $this->assertFalse($this->svc->verify(null));
        $this->assertFalse($this->svc->verify('   '));
        Http::assertNothingSent();
    }

    public function test_cloudflare_success_passes(): void
    {
        $this->setSecret('real-secret-key');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true], 200)]);
        $this->assertTrue($this->svc->verify('good-token'));
    }

    public function test_cloudflare_failure_is_rejected(): void
    {
        $this->setSecret('real-secret-key');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']], 200)]);
        $this->assertFalse($this->svc->verify('bad-token'));
    }

    public function test_http_error_is_rejected(): void
    {
        $this->setSecret('real-secret-key');
        Http::fake(['challenges.cloudflare.com/*' => Http::response('', 500)]);
        $this->assertFalse($this->svc->verify('token'));
    }

    public function test_posts_secret_token_and_remoteip(): void
    {
        $this->setSecret('real-secret-key');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true], 200)]);

        $this->svc->verify('my-token', '203.0.113.5');

        Http::assertSent(function ($req) {
            $d = $req->data();
            return ($d['secret'] ?? null) === 'real-secret-key'
                && ($d['response'] ?? null) === 'my-token'
                && ($d['remoteip'] ?? null) === '203.0.113.5';
        });
    }
}
