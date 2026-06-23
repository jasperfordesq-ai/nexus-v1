<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\PwnedPasswordService;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\TestCase;

class PwnedPasswordServiceTest extends TestCase
{
    private PwnedPasswordService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new PwnedPasswordService();
    }

    private function suffixOf(string $password): string
    {
        return substr(strtoupper(sha1($password)), 5);
    }

    public function test_empty_password_is_not_pwned_and_makes_no_request(): void
    {
        Http::fake();
        $this->assertFalse($this->svc->isPwned(''));
        Http::assertNothingSent();
    }

    public function test_password_in_breach_corpus_is_flagged(): void
    {
        $pw = 'password123';
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response($this->suffixOf($pw) . ":42\r\nAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA:7", 200),
        ]);
        $this->assertTrue($this->svc->isPwned($pw));
    }

    public function test_password_not_in_response_is_clean(): void
    {
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response("0000000000000000000000000000000000A:3\r\nFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF:2", 200),
        ]);
        $this->assertFalse($this->svc->isPwned('a-very-unique-passphrase-xyz-2026'));
    }

    public function test_zero_count_does_not_exceed_default_threshold(): void
    {
        $pw = 'edgecase';
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response($this->suffixOf($pw) . ':0', 200),
        ]);
        $this->assertFalse($this->svc->isPwned($pw));
    }

    public function test_http_error_fails_open(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 500)]);
        $this->assertFalse($this->svc->isPwned('whatever-it-is'));
    }

    public function test_only_sha1_prefix_is_transmitted(): void
    {
        $pw = 'hunter2';
        $sha1 = strtoupper(sha1($pw));
        $prefix = substr($sha1, 0, 5);
        $suffix = substr($sha1, 5);
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

        $this->svc->isPwned($pw);

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), '/range/' . $prefix)
            && ! str_contains($req->url(), $suffix));
    }

    public function test_disabled_via_env_returns_false_without_request(): void
    {
        putenv('HIBP_ENABLED=false');
        $_ENV['HIBP_ENABLED'] = 'false';
        $_SERVER['HIBP_ENABLED'] = 'false';
        try {
            Http::fake();
            $this->assertFalse($this->svc->isPwned('anything-at-all'));
            Http::assertNothingSent();
        } finally {
            putenv('HIBP_ENABLED');
            unset($_ENV['HIBP_ENABLED'], $_SERVER['HIBP_ENABLED']);
        }
    }
}
