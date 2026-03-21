<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\ClientIp;
use PHPUnit\Framework\TestCase;

class ClientIpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ClientIp::clearCache();
        // Reset relevant $_SERVER vars
        unset(
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_X_REAL_IP']
        );
    }

    protected function tearDown(): void
    {
        ClientIp::clearCache();
        unset(
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_X_REAL_IP']
        );
        parent::tearDown();
    }

    // -------------------------------------------------------
    // get()
    // -------------------------------------------------------

    public function test_get_returns_remote_addr_when_not_trusted_proxy(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.50';
        $this->assertSame('203.0.113.50', ClientIp::get());
    }

    public function test_get_returns_cf_connecting_ip_when_remote_is_trusted(): void
    {
        $_SERVER['REMOTE_ADDR'] = '172.20.0.1'; // Docker bridge (trusted)
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.10';
        $this->assertSame('198.51.100.10', ClientIp::get());
    }

    public function test_get_returns_x_forwarded_for_when_cf_absent(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1'; // Trusted
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.100, 172.20.0.1';
        $this->assertSame('203.0.113.100', ClientIp::get());
    }

    public function test_get_returns_x_real_ip_when_others_absent(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // Trusted
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.200';
        $this->assertSame('203.0.113.200', ClientIp::get());
    }

    public function test_get_returns_fallback_when_no_server_vars(): void
    {
        // REMOTE_ADDR defaults to 127.0.0.1 in resolve(), which is trusted,
        // and no headers are set, so it falls back to REMOTE_ADDR
        $this->assertSame('127.0.0.1', ClientIp::get());
    }

    public function test_get_caches_result(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        $first = ClientIp::get();

        // Change REMOTE_ADDR; cached value should still be returned
        $_SERVER['REMOTE_ADDR'] = '203.0.113.2';
        $second = ClientIp::get();

        $this->assertSame($first, $second);
        $this->assertSame('203.0.113.1', $second);
    }

    // -------------------------------------------------------
    // clearCache()
    // -------------------------------------------------------

    public function test_clearCache_allows_re_resolution(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        ClientIp::get();

        ClientIp::clearCache();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.2';
        $this->assertSame('203.0.113.2', ClientIp::get());
    }

    // -------------------------------------------------------
    // debug()
    // -------------------------------------------------------

    public function test_debug_returns_expected_keys(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.50';
        $debug = ClientIp::debug();

        $this->assertArrayHasKey('resolved_ip', $debug);
        $this->assertArrayHasKey('REMOTE_ADDR', $debug);
        $this->assertArrayHasKey('HTTP_CF_CONNECTING_IP', $debug);
        $this->assertArrayHasKey('HTTP_X_FORWARDED_FOR', $debug);
        $this->assertArrayHasKey('HTTP_X_REAL_IP', $debug);
        $this->assertArrayHasKey('remote_addr_is_trusted', $debug);
    }

    // -------------------------------------------------------
    // Trusted proxy handling
    // -------------------------------------------------------

    public function test_get_ignores_headers_when_remote_addr_is_public(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.50'; // Public, not trusted
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.20';

        // Should return REMOTE_ADDR since it's not a trusted proxy
        $this->assertSame('203.0.113.50', ClientIp::get());
    }

    public function test_get_xff_strips_trusted_proxies_from_right(): void
    {
        $_SERVER['REMOTE_ADDR'] = '172.20.0.1'; // Trusted
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 10.0.0.5, 172.20.0.2';

        // Should walk from right, skip trusted 172.20.0.2 and 10.0.0.5, return 203.0.113.50
        $this->assertSame('203.0.113.50', ClientIp::get());
    }
}
