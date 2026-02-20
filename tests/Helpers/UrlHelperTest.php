<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Helpers;

use Nexus\Tests\TestCase;
use Nexus\Helpers\UrlHelper;

/**
 * UrlHelper Tests
 *
 * Tests URL safety and validation utilities including:
 * - Safe redirect URL validation (prevent open redirects)
 * - Allowed host checking
 * - Absolute URL conversion
 * - Avatar URL handling
 *
 * @covers \Nexus\Helpers\UrlHelper
 */
class UrlHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset allowed hosts cache
        $ref = new \ReflectionClass(UrlHelper::class);
        $prop = $ref->getProperty('allowedHosts');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        // Clear environment
        putenv('ALLOWED_HOSTS');
        unset($_ENV['ALLOWED_HOSTS']);
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['HTTPS']);
    }

    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(UrlHelper::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'safeRedirect',
            'safeReferer',
            'isAllowedHost',
            'addAllowedHost',
            'getBaseUrl',
            'absolute',
            'absoluteAvatar',
            'absoluteAll',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(UrlHelper::class, $method),
                "Method {$method} should exist on UrlHelper"
            );
        }
    }

    // =========================================================================
    // SAFE REDIRECT - RELATIVE URLS
    // =========================================================================

    public function testSafeRedirectAllowsRelativeUrls(): void
    {
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('/dashboard'));
        $this->assertEquals('/admin/users', UrlHelper::safeRedirect('/admin/users'));
        $this->assertEquals('/some/path', UrlHelper::safeRedirect('/some/path'));
    }

    public function testSafeRedirectBlocksBackslashAfterSlash(): void
    {
        // /\ can be misinterpreted as protocol-relative in some contexts
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('/\\evil.com'));
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('/\\\\evil.com'));
    }

    public function testSafeRedirectBlocksProtocolRelativeUrls(): void
    {
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('//evil.com'));
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('//evil.com/path'));
    }

    // =========================================================================
    // SAFE REDIRECT - ABSOLUTE URLS
    // =========================================================================

    public function testSafeRedirectAllowsAllowedHosts(): void
    {
        $this->assertEquals(
            'https://project-nexus.ie/dashboard',
            UrlHelper::safeRedirect('https://project-nexus.ie/dashboard')
        );

        $this->assertEquals(
            'https://hour-timebank.ie/listings',
            UrlHelper::safeRedirect('https://hour-timebank.ie/listings')
        );
    }

    public function testSafeRedirectBlocksDisallowedHosts(): void
    {
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('https://evil.com/steal'));
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('http://malicious.site/'));
    }

    public function testSafeRedirectAllowsSubdomains(): void
    {
        $result = UrlHelper::safeRedirect('https://tenant.project-nexus.ie/path');

        // Should allow subdomains of allowed hosts
        $this->assertEquals('https://tenant.project-nexus.ie/path', $result);
    }

    // =========================================================================
    // SAFE REDIRECT - DANGEROUS SCHEMES
    // =========================================================================

    public function testSafeRedirectBlocksJavascriptUrls(): void
    {
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('javascript:alert(1)'));
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('JAVASCRIPT:alert(1)'));
    }

    public function testSafeRedirectBlocksDataUrls(): void
    {
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('data:text/html,<script>alert(1)</script>'));
    }

    public function testSafeRedirectBlocksVbscriptUrls(): void
    {
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('vbscript:msgbox(1)'));
    }

    public function testSafeRedirectAllowsHttpAndHttps(): void
    {
        $this->assertEquals(
            'https://project-nexus.ie/path',
            UrlHelper::safeRedirect('https://project-nexus.ie/path')
        );

        $this->assertEquals(
            'http://project-nexus.ie/path',
            UrlHelper::safeRedirect('http://project-nexus.ie/path')
        );
    }

    // =========================================================================
    // SAFE REDIRECT - EDGE CASES
    // =========================================================================

    public function testSafeRedirectWithNullUrl(): void
    {
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect(null));
    }

    public function testSafeRedirectWithEmptyString(): void
    {
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect(''));
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('   '));
    }

    public function testSafeRedirectWithCustomFallback(): void
    {
        $this->assertEquals('/admin', UrlHelper::safeRedirect(null, '/admin'));
        $this->assertEquals('/admin', UrlHelper::safeRedirect('', '/admin'));
        $this->assertEquals('/admin', UrlHelper::safeRedirect('javascript:alert(1)', '/admin'));
    }

    public function testSafeRedirectWithUrlWithoutScheme(): void
    {
        // URL without scheme (not starting with /) should use fallback
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('evil.com/path'));
        $this->assertEquals('/dashboard', UrlHelper::safeRedirect('path/to/page'));
    }

    // =========================================================================
    // SAFE REFERER TESTS
    // =========================================================================

    public function testSafeRefererWithValidReferer(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://project-nexus.ie/dashboard';

        $this->assertEquals('https://project-nexus.ie/dashboard', UrlHelper::safeReferer());
    }

    public function testSafeRefererWithMaliciousReferer(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://evil.com/phishing';

        $this->assertEquals('/dashboard', UrlHelper::safeReferer());
    }

    public function testSafeRefererWithNoReferer(): void
    {
        $this->assertEquals('/dashboard', UrlHelper::safeReferer());
    }

    public function testSafeRefererWithCustomFallback(): void
    {
        $this->assertEquals('/home', UrlHelper::safeReferer('/home'));
    }

    // =========================================================================
    // ALLOWED HOST TESTS
    // =========================================================================

    public function testIsAllowedHostWithDefaultHosts(): void
    {
        $this->assertTrue(UrlHelper::isAllowedHost('project-nexus.ie'));
        $this->assertTrue(UrlHelper::isAllowedHost('www.project-nexus.ie'));
        $this->assertTrue(UrlHelper::isAllowedHost('hour-timebank.ie'));
        $this->assertTrue(UrlHelper::isAllowedHost('www.hour-timebank.ie'));
    }

    public function testIsAllowedHostIsCaseInsensitive(): void
    {
        $this->assertTrue(UrlHelper::isAllowedHost('Project-Nexus.IE'));
        $this->assertTrue(UrlHelper::isAllowedHost('HOUR-TIMEBANK.IE'));
    }

    public function testIsAllowedHostWithSubdomain(): void
    {
        $this->assertTrue(UrlHelper::isAllowedHost('tenant.project-nexus.ie'));
        $this->assertTrue(UrlHelper::isAllowedHost('app.project-nexus.ie'));
        $this->assertTrue(UrlHelper::isAllowedHost('api.hour-timebank.ie'));
    }

    public function testIsAllowedHostBlocksUnrelatedDomains(): void
    {
        $this->assertFalse(UrlHelper::isAllowedHost('evil.com'));
        $this->assertFalse(UrlHelper::isAllowedHost('project-nexus-phishing.com'));
    }

    public function testAddAllowedHost(): void
    {
        $this->assertFalse(UrlHelper::isAllowedHost('new-domain.com'));

        UrlHelper::addAllowedHost('new-domain.com');

        $this->assertTrue(UrlHelper::isAllowedHost('new-domain.com'));
        $this->assertTrue(UrlHelper::isAllowedHost('subdomain.new-domain.com'));
    }

    public function testAddAllowedHostNormalizes(): void
    {
        UrlHelper::addAllowedHost('  UPPERCASE.COM  ');

        $this->assertTrue(UrlHelper::isAllowedHost('uppercase.com'));
        $this->assertTrue(UrlHelper::isAllowedHost('UPPERCASE.COM'));
    }

    public function testAddAllowedHostIgnoresDuplicates(): void
    {
        UrlHelper::addAllowedHost('test.com');
        UrlHelper::addAllowedHost('test.com');
        UrlHelper::addAllowedHost('TEST.COM');

        $this->assertTrue(UrlHelper::isAllowedHost('test.com'));
    }

    // =========================================================================
    // ABSOLUTE URL TESTS
    // =========================================================================

    public function testAbsoluteWithRelativeUrl(): void
    {
        $_SERVER['HTTP_HOST'] = 'project-nexus.ie';
        $_SERVER['HTTPS'] = 'on';

        $this->assertEquals(
            'https://project-nexus.ie/uploads/image.jpg',
            UrlHelper::absolute('/uploads/image.jpg')
        );
    }

    public function testAbsoluteWithAlreadyAbsoluteUrl(): void
    {
        $url = 'https://cdn.example.com/image.jpg';

        $this->assertEquals($url, UrlHelper::absolute($url));
    }

    public function testAbsoluteWithNullUrl(): void
    {
        $this->assertNull(UrlHelper::absolute(null));
    }

    public function testAbsoluteWithEmptyString(): void
    {
        $this->assertEquals('', UrlHelper::absolute(''));
    }

    public function testAbsoluteWithProtocolRelativeUrl(): void
    {
        $this->assertEquals(
            'https://cdn.example.com/image.jpg',
            UrlHelper::absolute('//cdn.example.com/image.jpg')
        );
    }

    public function testAbsoluteWithUrlMissingLeadingSlash(): void
    {
        $_SERVER['HTTP_HOST'] = 'project-nexus.ie';
        $_SERVER['HTTPS'] = 'on';

        $this->assertEquals(
            'https://project-nexus.ie/path/to/file.jpg',
            UrlHelper::absolute('path/to/file.jpg')
        );
    }

    // =========================================================================
    // ABSOLUTE AVATAR TESTS
    // =========================================================================

    public function testAbsoluteAvatarWithValidUrl(): void
    {
        $_SERVER['HTTP_HOST'] = 'project-nexus.ie';
        $_SERVER['HTTPS'] = 'on';

        $this->assertEquals(
            'https://project-nexus.ie/uploads/avatars/user.jpg',
            UrlHelper::absoluteAvatar('/uploads/avatars/user.jpg')
        );
    }

    public function testAbsoluteAvatarWithNullUrl(): void
    {
        $_SERVER['HTTP_HOST'] = 'project-nexus.ie';
        $_SERVER['HTTPS'] = 'on';

        $result = UrlHelper::absoluteAvatar(null);

        $this->assertStringContainsString('default_avatar.png', $result);
        $this->assertStringStartsWith('https://', $result);
    }

    public function testAbsoluteAvatarWithEmptyString(): void
    {
        $_SERVER['HTTP_HOST'] = 'project-nexus.ie';
        $_SERVER['HTTPS'] = 'on';

        $result = UrlHelper::absoluteAvatar('');

        $this->assertStringContainsString('default_avatar.png', $result);
    }

    public function testAbsoluteAvatarWithCustomDefault(): void
    {
        $_SERVER['HTTP_HOST'] = 'project-nexus.ie';
        $_SERVER['HTTPS'] = 'on';

        $result = UrlHelper::absoluteAvatar(null, '/custom/default.png');

        $this->assertEquals('https://project-nexus.ie/custom/default.png', $result);
    }

    // =========================================================================
    // ABSOLUTE ALL TESTS
    // =========================================================================

    public function testAbsoluteAllWithMixedUrls(): void
    {
        $_SERVER['HTTP_HOST'] = 'project-nexus.ie';
        $_SERVER['HTTPS'] = 'on';

        $urls = [
            '/uploads/image1.jpg',
            '/uploads/image2.jpg',
            'https://cdn.example.com/image3.jpg',
        ];

        $result = UrlHelper::absoluteAll($urls);

        $this->assertEquals('https://project-nexus.ie/uploads/image1.jpg', $result[0]);
        $this->assertEquals('https://project-nexus.ie/uploads/image2.jpg', $result[1]);
        $this->assertEquals('https://cdn.example.com/image3.jpg', $result[2]);
    }

    public function testAbsoluteAllWithEmptyArray(): void
    {
        $this->assertEquals([], UrlHelper::absoluteAll([]));
    }

    // =========================================================================
    // GET BASE URL TESTS
    // =========================================================================

    public function testGetBaseUrlFromHttpHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'project-nexus.ie';
        $_SERVER['HTTPS'] = 'on';

        $this->assertEquals('https://project-nexus.ie', UrlHelper::getBaseUrl());
    }

    public function testGetBaseUrlWithHttp(): void
    {
        $_SERVER['HTTP_HOST'] = 'project-nexus.ie';
        unset($_SERVER['HTTPS']);

        $this->assertEquals('http://project-nexus.ie', UrlHelper::getBaseUrl());
    }

    public function testGetBaseUrlWithPort(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost:8080';
        $_SERVER['HTTPS'] = 'on';

        $this->assertEquals('https://localhost:8080', UrlHelper::getBaseUrl());
    }
}
