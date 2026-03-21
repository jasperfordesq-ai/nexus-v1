<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Helpers;

use App\Helpers\UrlHelper;
use PHPUnit\Framework\TestCase;

class UrlHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset static allowedHosts cache
        $ref = new \ReflectionClass(UrlHelper::class);
        $prop = $ref->getProperty('allowedHosts');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(UrlHelper::class);
        $prop = $ref->getProperty('allowedHosts');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        parent::tearDown();
    }

    // -------------------------------------------------------
    // safeRedirect()
    // -------------------------------------------------------

    public function test_safeRedirect_allows_relative_url(): void
    {
        $this->assertSame('/dashboard', UrlHelper::safeRedirect('/dashboard'));
    }

    public function test_safeRedirect_allows_relative_path(): void
    {
        $this->assertSame('/listings/42', UrlHelper::safeRedirect('/listings/42'));
    }

    public function test_safeRedirect_blocks_javascript_protocol(): void
    {
        $result = UrlHelper::safeRedirect('javascript:alert(1)');
        $this->assertSame('/dashboard', $result);
    }

    public function test_safeRedirect_blocks_data_protocol(): void
    {
        $result = UrlHelper::safeRedirect('data:text/html,<script>alert(1)</script>');
        $this->assertSame('/dashboard', $result);
    }

    public function test_safeRedirect_blocks_vbscript(): void
    {
        $result = UrlHelper::safeRedirect('vbscript:MsgBox("xss")');
        $this->assertSame('/dashboard', $result);
    }

    public function test_safeRedirect_blocks_protocol_relative(): void
    {
        $result = UrlHelper::safeRedirect('//evil.com/path');
        $this->assertSame('/dashboard', $result);
    }

    public function test_safeRedirect_blocks_unknown_host(): void
    {
        $result = UrlHelper::safeRedirect('https://evil.com/path');
        $this->assertSame('/dashboard', $result);
    }

    public function test_safeRedirect_allows_known_host(): void
    {
        $result = UrlHelper::safeRedirect('https://project-nexus.ie/dashboard');
        $this->assertSame('https://project-nexus.ie/dashboard', $result);
    }

    public function test_safeRedirect_returns_fallback_for_null(): void
    {
        $this->assertSame('/dashboard', UrlHelper::safeRedirect(null));
    }

    public function test_safeRedirect_returns_fallback_for_empty(): void
    {
        $this->assertSame('/dashboard', UrlHelper::safeRedirect(''));
    }

    public function test_safeRedirect_uses_custom_fallback(): void
    {
        $result = UrlHelper::safeRedirect(null, '/home');
        $this->assertSame('/home', $result);
    }

    public function test_safeRedirect_blocks_backslash_after_slash(): void
    {
        $result = UrlHelper::safeRedirect('/\\evil.com');
        $this->assertSame('/dashboard', $result);
    }

    // -------------------------------------------------------
    // isAllowedHost()
    // -------------------------------------------------------

    public function test_isAllowedHost_with_default_hosts(): void
    {
        $this->assertTrue(UrlHelper::isAllowedHost('project-nexus.ie'));
        $this->assertTrue(UrlHelper::isAllowedHost('hour-timebank.ie'));
    }

    public function test_isAllowedHost_with_subdomain(): void
    {
        $this->assertTrue(UrlHelper::isAllowedHost('app.project-nexus.ie'));
    }

    public function test_isAllowedHost_rejects_unknown(): void
    {
        $this->assertFalse(UrlHelper::isAllowedHost('evil.com'));
    }

    // -------------------------------------------------------
    // addAllowedHost()
    // -------------------------------------------------------

    public function test_addAllowedHost_adds_to_list(): void
    {
        UrlHelper::addAllowedHost('custom-timebank.org');
        $this->assertTrue(UrlHelper::isAllowedHost('custom-timebank.org'));
    }

    // -------------------------------------------------------
    // absolute()
    // -------------------------------------------------------

    public function test_absolute_returns_null_for_null(): void
    {
        $this->assertNull(UrlHelper::absolute(null));
    }

    public function test_absolute_returns_empty_for_empty(): void
    {
        $this->assertSame('', UrlHelper::absolute(''));
    }

    public function test_absolute_preserves_already_absolute_url(): void
    {
        $url = 'https://example.com/path';
        $this->assertSame($url, UrlHelper::absolute($url));
    }

    public function test_absolute_converts_relative_path(): void
    {
        $result = UrlHelper::absolute('/uploads/image.jpg');
        $this->assertMatchesRegularExpression('#^https?://.+/uploads/image\.jpg$#', $result);
    }

    public function test_absolute_handles_protocol_relative(): void
    {
        $result = UrlHelper::absolute('//cdn.example.com/image.jpg');
        $this->assertSame('https://cdn.example.com/image.jpg', $result);
    }

    public function test_absolute_prepends_slash_to_bare_path(): void
    {
        $result = UrlHelper::absolute('uploads/image.jpg');
        $this->assertStringContainsString('/uploads/image.jpg', $result);
    }

    // -------------------------------------------------------
    // absoluteAvatar()
    // -------------------------------------------------------

    public function test_absoluteAvatar_returns_default_for_empty(): void
    {
        $result = UrlHelper::absoluteAvatar(null);
        $this->assertStringContainsString('default_avatar', $result);
    }

    public function test_absoluteAvatar_converts_relative_avatar(): void
    {
        $result = UrlHelper::absoluteAvatar('/uploads/avatars/user.jpg');
        $this->assertMatchesRegularExpression('#^https?://.+/uploads/avatars/user\.jpg$#', $result);
    }

    // -------------------------------------------------------
    // absoluteAll()
    // -------------------------------------------------------

    public function test_absoluteAll_converts_array_of_urls(): void
    {
        $urls = ['/path/a.jpg', '/path/b.jpg'];
        $result = UrlHelper::absoluteAll($urls);
        $this->assertCount(2, $result);
        foreach ($result as $url) {
            $this->assertMatchesRegularExpression('#^https?://#', $url);
        }
    }

    // -------------------------------------------------------
    // getBaseUrl()
    // -------------------------------------------------------

    public function test_getBaseUrl_returns_string(): void
    {
        $result = UrlHelper::getBaseUrl();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
