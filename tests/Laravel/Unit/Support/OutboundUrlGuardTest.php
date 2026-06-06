<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Support;

use App\Support\OutboundUrlGuard;
use Tests\Laravel\TestCase;

class OutboundUrlGuardTest extends TestCase
{
    public function testBlocksPrivateIpv4Literal(): void
    {
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('https://127.0.0.1/internal'));
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('https://10.0.0.5/internal'));
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('https://192.168.1.9/internal'));
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('https://172.16.4.8/internal'));
    }

    public function testBlocksPrivateIpv6Literal(): void
    {
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('https://[::1]/internal'));
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('https://[fe80::1]/internal'));
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('https://[fc00::1]/internal'));
    }

    public function testBlocksLocalhostAndLocalNames(): void
    {
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('https://localhost/internal'));
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('https://service.local/internal'));
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('https://admin.localhost/internal'));
    }

    public function testBlocksDnsFailureByDefault(): void
    {
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('https://definitely-not-real.invalid/path'));
    }

    public function testCanRequireHttps(): void
    {
        $this->assertFalse(OutboundUrlGuard::isSafeHttpUrl('http://example.com/webhook', requireHttps: true));
    }

    public function testAllowsPublicHttpWhenHttpsIsNotRequired(): void
    {
        $this->assertTrue(OutboundUrlGuard::isSafeHttpUrl('http://93.184.216.34/webhook'));
    }

    public function testAllowsPublicHttps(): void
    {
        $this->assertTrue(OutboundUrlGuard::isSafeHttpUrl('https://93.184.216.34/webhook', requireHttps: true));
    }

    public function testBuildsCurlSafetyOptionsForPublicUrl(): void
    {
        $options = OutboundUrlGuard::curlOptionsForUrl('https://93.184.216.34/webhook', requireHttps: true);

        $this->assertSame(false, $options[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
        $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options[CURLOPT_REDIR_PROTOCOLS]);
    }
}
