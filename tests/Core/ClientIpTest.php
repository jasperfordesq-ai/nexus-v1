<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Core;

use App\Core\ClientIp;
use App\Tests\TestCase;

/**
 * Tests for ClientIp — secure client IP resolution behind proxies.
 *
 * Scenarios tested:
 *   a) Direct request (no proxy)
 *   b) Behind one proxy (Docker gateway)
 *   c) Behind Cloudflare
 *   d) Malicious spoof attempt (untrusted X-Forwarded-For)
 *   e) Multiple proxies (Cloudflare → Docker)
 *   f) IPv6 support
 */
class ClientIpTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        ClientIp::clearCache();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        ClientIp::clearCache();
        parent::tearDown();
    }

    /**
     * Helper: reset $_SERVER to a minimal state with given values.
     */
    private function setServer(array $overrides): void
    {
        // Keep non-IP-related server vars, override IP-related ones
        unset(
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_X_FORWARDED'],
            $_SERVER['HTTP_FORWARDED'],
            $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'],
            $_SERVER['HTTP_FORWARDED_FOR']
        );
        foreach ($overrides as $key => $value) {
            $_SERVER[$key] = $value;
        }
        ClientIp::clearCache();
    }

    // =====================================================================
    // (a) Direct request — no proxy
    // =====================================================================

    public function testDirectRequest_ReturnsRemoteAddr(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '203.0.113.42',
        ]);

        $this->assertSame('203.0.113.42', ClientIp::get());
    }

    public function testDirectRequest_IgnoresForwardedHeaders(): void
    {
        // A direct client (non-proxy REMOTE_ADDR) sends forged headers.
        // They MUST be ignored because REMOTE_ADDR is not a trusted proxy.
        $this->setServer([
            'REMOTE_ADDR' => '203.0.113.42', // public IP — NOT a trusted proxy
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
            'HTTP_CF_CONNECTING_IP' => '10.0.0.2',
        ]);

        // Must return REMOTE_ADDR, NOT the spoofed headers
        $this->assertSame('203.0.113.42', ClientIp::get());
    }

    // =====================================================================
    // (b) Behind one proxy (Docker gateway)
    // =====================================================================

    public function testBehindDockerProxy_UsesXForwardedFor(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '172.21.0.1', // Docker bridge network gateway
            'HTTP_X_FORWARDED_FOR' => '198.51.100.25',
        ]);

        $this->assertSame('198.51.100.25', ClientIp::get());
    }

    public function testBehindDockerProxy_172_17(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '172.17.0.1', // Default Docker bridge
            'HTTP_X_FORWARDED_FOR' => '198.51.100.25',
        ]);

        $this->assertSame('198.51.100.25', ClientIp::get());
    }

    public function testBehindLocalhost_UsesXForwardedFor(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '127.0.0.1', // Localhost
            'HTTP_X_FORWARDED_FOR' => '198.51.100.25',
        ]);

        $this->assertSame('198.51.100.25', ClientIp::get());
    }

    // =====================================================================
    // (c) Behind Cloudflare
    // =====================================================================

    public function testBehindCloudflare_PrefersCloudflareHeader(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '172.21.0.1', // Docker gateway
            'HTTP_CF_CONNECTING_IP' => '203.0.113.42', // Cloudflare's verified IP
            'HTTP_X_FORWARDED_FOR' => '203.0.113.42, 162.158.90.1', // Also set by CF
        ]);

        // CF-Connecting-IP takes priority over X-Forwarded-For
        $this->assertSame('203.0.113.42', ClientIp::get());
    }

    public function testBehindCloudflareProxy_TrustedCloudflareIP(): void
    {
        // When Cloudflare's own IP is REMOTE_ADDR (no Docker between them)
        $this->setServer([
            'REMOTE_ADDR' => '162.158.90.1', // Cloudflare IP range 162.158.0.0/15
            'HTTP_CF_CONNECTING_IP' => '203.0.113.42',
        ]);

        $this->assertSame('203.0.113.42', ClientIp::get());
    }

    // =====================================================================
    // (d) Malicious spoof attempts
    // =====================================================================

    public function testSpoof_UntrustedRemoteAddr_IgnoresXFF(): void
    {
        // Attacker connects directly (public IP) and forges X-Forwarded-For
        $this->setServer([
            'REMOTE_ADDR' => '198.51.100.99', // Attacker's real IP (public)
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 127.0.0.1', // Forged
            'HTTP_CF_CONNECTING_IP' => '10.0.0.2', // Forged
        ]);

        // Must return REMOTE_ADDR — untrusted source, ignore all headers
        $this->assertSame('198.51.100.99', ClientIp::get());
    }

    public function testSpoof_XFFWithMaliciousPrefix(): void
    {
        // Behind Docker, attacker sets X-Forwarded-For with a spoofed prefix
        // Cloudflare normally appends the real IP, so the real client is the
        // rightmost non-trusted IP.
        $this->setServer([
            'REMOTE_ADDR' => '172.21.0.1',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.42', // Cloudflare says this is real
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 203.0.113.42, 162.158.90.1',
        ]);

        // CF-Connecting-IP takes priority — returns the real IP
        $this->assertSame('203.0.113.42', ClientIp::get());
    }

    public function testSpoof_NoCFHeader_XFFRightToLeft(): void
    {
        // Behind Docker, no CF-Connecting-IP, only X-Forwarded-For
        // Attacker prepends a fake IP
        $this->setServer([
            'REMOTE_ADDR' => '172.21.0.1',
            'HTTP_X_FORWARDED_FOR' => '10.99.99.99, 203.0.113.42', // fake, real
        ]);

        // Right-to-left parsing: 203.0.113.42 is not a trusted proxy → use it
        $this->assertSame('203.0.113.42', ClientIp::get());
    }

    public function testSpoof_InvalidIPInHeaders(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '172.21.0.1',
            'HTTP_CF_CONNECTING_IP' => 'not-an-ip',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.42',
        ]);

        // Invalid CF-Connecting-IP should be skipped, fall through to XFF
        $this->assertSame('203.0.113.42', ClientIp::get());
    }

    // =====================================================================
    // (e) Multiple proxies
    // =====================================================================

    public function testMultipleProxies_CloudflareAndDocker(): void
    {
        // Client → Cloudflare → Docker host → Docker container
        $this->setServer([
            'REMOTE_ADDR' => '172.21.0.1', // Docker container sees Docker gateway
            'HTTP_CF_CONNECTING_IP' => '2001:db8::1', // IPv6 client
            'HTTP_X_FORWARDED_FOR' => '2001:db8::1, 172.64.0.1', // Client, CF IP
        ]);

        $this->assertSame('2001:db8::1', ClientIp::get());
    }

    // =====================================================================
    // (f) IPv6 support
    // =====================================================================

    public function testIPv6_DirectRequest(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '2001:db8::1',
        ]);

        $this->assertSame('2001:db8::1', ClientIp::get());
    }

    public function testIPv6_Localhost(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '::1',
            'HTTP_X_FORWARDED_FOR' => '2001:db8::1',
        ]);

        $this->assertSame('2001:db8::1', ClientIp::get());
    }

    // =====================================================================
    // (g) Edge cases
    // =====================================================================

    public function testCaching_ReturnsSameResultOnSecondCall(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '172.21.0.1',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.42',
        ]);

        $first = ClientIp::get();

        // Change the header — should still return cached value
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '10.0.0.99';
        $second = ClientIp::get();

        $this->assertSame($first, $second);
        $this->assertSame('203.0.113.42', $second);
    }

    public function testClearCache_ResolvesAgain(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '172.21.0.1',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.42',
        ]);

        $first = ClientIp::get();
        $this->assertSame('203.0.113.42', $first);

        // Clear cache and change header
        ClientIp::clearCache();
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.10';

        $second = ClientIp::get();
        $this->assertSame('198.51.100.10', $second);
    }

    public function testNoRemoteAddr_FallsBackToLoopback(): void
    {
        $this->setServer([]); // No REMOTE_ADDR at all
        unset($_SERVER['REMOTE_ADDR']);

        // Should fall back to 127.0.0.1
        $this->assertSame('127.0.0.1', ClientIp::get());
    }

    public function testXRealIP_FallbackWhenNoOtherHeaders(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '172.21.0.1',
            'HTTP_X_REAL_IP' => '203.0.113.42',
        ]);

        $this->assertSame('203.0.113.42', ClientIp::get());
    }

    public function testDebug_ReturnsExpectedKeys(): void
    {
        $this->setServer([
            'REMOTE_ADDR' => '172.21.0.1',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.42',
        ]);

        $debug = ClientIp::debug();

        $this->assertArrayHasKey('resolved_ip', $debug);
        $this->assertArrayHasKey('REMOTE_ADDR', $debug);
        $this->assertArrayHasKey('HTTP_CF_CONNECTING_IP', $debug);
        $this->assertArrayHasKey('HTTP_X_FORWARDED_FOR', $debug);
        $this->assertArrayHasKey('remote_addr_is_trusted', $debug);
        $this->assertSame('203.0.113.42', $debug['resolved_ip']);
        $this->assertTrue($debug['remote_addr_is_trusted']);
    }

    // =====================================================================
    // (h) Trusted proxy CIDR matching
    // =====================================================================

    public function testTrustedProxy_AllDockerRanges(): void
    {
        $dockerIps = ['172.16.0.1', '172.31.255.254', '10.0.0.1', '10.255.255.254', '192.168.1.1'];

        foreach ($dockerIps as $dockerIp) {
            $this->setServer([
                'REMOTE_ADDR' => $dockerIp,
                'HTTP_X_FORWARDED_FOR' => '203.0.113.42',
            ]);

            $this->assertSame('203.0.113.42', ClientIp::get(), "Failed for Docker IP: $dockerIp");
        }
    }

    public function testUntrustedProxy_PublicIpRanges(): void
    {
        // These public IPs are NOT trusted proxies and should NOT allow header inspection
        $publicIps = ['1.1.1.1', '8.8.8.8', '203.0.113.42', '93.184.216.34'];

        foreach ($publicIps as $publicIp) {
            $this->setServer([
                'REMOTE_ADDR' => $publicIp,
                'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
            ]);

            $this->assertSame($publicIp, ClientIp::get(), "Should NOT trust headers from public IP: $publicIp");
        }
    }
}
