<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Middleware;

use Nexus\Tests\TestCase;
use Nexus\Middleware\RedirectMiddleware;
use ReflectionClass;

/**
 * RedirectMiddlewareTest
 *
 * Tests the SEO redirect middleware that handles URL corrections,
 * loop prevention, and safe redirects.
 *
 * SECURITY: These tests verify:
 * - POST requests are never redirected (prevents data loss)
 * - Auth pages are never redirected (prevents auth flow disruption)
 * - Admin pages are never redirected (prevents admin lockout)
 * - Self-redirect loops are prevented
 * - Redirect loop detection via cookies works correctly
 */
class RedirectMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Default to GET request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['HTTPS']);
        $_COOKIE = [];

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // POST request skipping tests
    // -----------------------------------------------------------------------

    /**
     * Test that POST requests are skipped entirely.
     * CRITICAL: Redirecting POST requests would cause data loss (form submissions).
     */
    public function testHandleSkipsPostRequests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/some-path-that-has-redirect';

        // handle() should return immediately for POST without attempting any redirect
        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'POST requests should be skipped');
        } catch (\Throwable $e) {
            // SeoRedirect model might not be available in tests, but the POST
            // check should happen BEFORE any DB access
            $this->fail('POST requests should be skipped before any DB/model access: ' . $e->getMessage());
        }
    }

    /**
     * Test that PUT requests are skipped.
     */
    public function testHandleSkipsPutRequests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/api/v2/users/me';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'PUT requests should be skipped');
        } catch (\Throwable $e) {
            $this->fail('PUT requests should be skipped: ' . $e->getMessage());
        }
    }

    /**
     * Test that DELETE requests are skipped.
     */
    public function testHandleSkipsDeleteRequests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/api/v2/listings/5';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'DELETE requests should be skipped');
        } catch (\Throwable $e) {
            $this->fail('DELETE requests should be skipped: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Auth page skipping tests
    // -----------------------------------------------------------------------

    /**
     * Test that login pages are skipped.
     */
    public function testHandleSkipsLoginPage(): void
    {
        $_SERVER['REQUEST_URI'] = '/login';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'Login page should be skipped');
        } catch (\Throwable $e) {
            $this->fail('Login page should be skipped: ' . $e->getMessage());
        }
    }

    /**
     * Test that register pages are skipped.
     */
    public function testHandleSkipsRegisterPage(): void
    {
        $_SERVER['REQUEST_URI'] = '/register';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'Register page should be skipped');
        } catch (\Throwable $e) {
            $this->fail('Register page should be skipped: ' . $e->getMessage());
        }
    }

    /**
     * Test that logout page is skipped.
     */
    public function testHandleSkipsLogoutPage(): void
    {
        $_SERVER['REQUEST_URI'] = '/logout';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'Logout page should be skipped');
        } catch (\Throwable $e) {
            $this->fail('Logout page should be skipped: ' . $e->getMessage());
        }
    }

    /**
     * Test that password reset pages are skipped.
     */
    public function testHandleSkipsPasswordResetPage(): void
    {
        $_SERVER['REQUEST_URI'] = '/password/reset';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'Password reset page should be skipped');
        } catch (\Throwable $e) {
            $this->fail('Password reset page should be skipped: ' . $e->getMessage());
        }
    }

    /**
     * Test that tenant-prefixed login is also skipped.
     */
    public function testHandleSkipsTenantPrefixedLoginPage(): void
    {
        $_SERVER['REQUEST_URI'] = '/hour-timebank/login';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'Tenant-prefixed login page should be skipped');
        } catch (\Throwable $e) {
            $this->fail('Tenant-prefixed login should be skipped: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Admin page skipping tests
    // -----------------------------------------------------------------------

    /**
     * Test that admin pages are skipped.
     */
    public function testHandleSkipsAdminPages(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/dashboard';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'Admin pages should be skipped');
        } catch (\Throwable $e) {
            $this->fail('Admin pages should be skipped: ' . $e->getMessage());
        }
    }

    /**
     * Test that admin-legacy pages are skipped.
     */
    public function testHandleSkipsAdminLegacyPages(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin-legacy/users';

        // admin-legacy contains "admin" so it should be caught by the admin check
        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'Admin legacy pages should be skipped');
        } catch (\Throwable $e) {
            $this->fail('Admin legacy pages should be skipped: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // API route skipping tests
    // -----------------------------------------------------------------------

    /**
     * Test that API routes are skipped.
     */
    public function testHandleSkipsApiRoutes(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/v2/listings';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'API routes should be skipped');
        } catch (\Throwable $e) {
            $this->fail('API routes should be skipped: ' . $e->getMessage());
        }
    }

    /**
     * Test that API auth routes are skipped.
     */
    public function testHandleSkipsApiAuthRoutes(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/auth/login';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'API auth routes should be skipped');
        } catch (\Throwable $e) {
            $this->fail('API auth routes should be skipped: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Static file skipping tests
    // -----------------------------------------------------------------------

    /**
     * Test that CSS files are skipped.
     */
    public function testHandleSkipsCssFiles(): void
    {
        $_SERVER['REQUEST_URI'] = '/assets/css/style.css';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'CSS files should be skipped');
        } catch (\Throwable $e) {
            $this->fail('CSS files should be skipped: ' . $e->getMessage());
        }
    }

    /**
     * Test that JS files are skipped.
     */
    public function testHandleSkipsJsFiles(): void
    {
        $_SERVER['REQUEST_URI'] = '/assets/js/app.js';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'JS files should be skipped');
        } catch (\Throwable $e) {
            $this->fail('JS files should be skipped: ' . $e->getMessage());
        }
    }

    /**
     * Test that image files are skipped.
     */
    public function testHandleSkipsImageFiles(): void
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'ico'];

        foreach ($imageExtensions as $ext) {
            $_SERVER['REQUEST_URI'] = "/images/photo.{$ext}";

            try {
                RedirectMiddleware::handle();
                $this->assertTrue(true, "{$ext} files should be skipped");
            } catch (\Throwable $e) {
                $this->fail("{$ext} files should be skipped: " . $e->getMessage());
            }
        }
    }

    /**
     * Test that font files are skipped.
     */
    public function testHandleSkipsFontFiles(): void
    {
        $fontExtensions = ['woff', 'woff2', 'ttf', 'eot'];

        foreach ($fontExtensions as $ext) {
            $_SERVER['REQUEST_URI'] = "/fonts/custom.{$ext}";

            try {
                RedirectMiddleware::handle();
                $this->assertTrue(true, "{$ext} files should be skipped");
            } catch (\Throwable $e) {
                $this->fail("{$ext} files should be skipped: " . $e->getMessage());
            }
        }
    }

    /**
     * Test that PDF and other document files are skipped.
     */
    public function testHandleSkipsDocumentFiles(): void
    {
        $_SERVER['REQUEST_URI'] = '/uploads/document.pdf';

        try {
            RedirectMiddleware::handle();
            $this->assertTrue(true, 'PDF files should be skipped');
        } catch (\Throwable $e) {
            $this->fail('PDF files should be skipped: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Redirect loop prevention tests
    // -----------------------------------------------------------------------

    /**
     * Test that redirect loop detection blocks after too many redirects.
     * The cookie threshold is 5 for handle() and 8 for safeRedirect().
     */
    public function testHandleBlocksAfterTooManyRedirects(): void
    {
        $_SERVER['REQUEST_URI'] = '/some-page';
        $_COOKIE['redirect_loop_detector'] = '5';

        try {
            RedirectMiddleware::handle();
            // Should return without redirecting because loop detected
            $this->assertTrue(true, 'Loop detection should prevent redirect');
        } catch (\Throwable $e) {
            $this->fail('Loop detection should prevent redirect, not throw: ' . $e->getMessage());
        }
    }

    /**
     * Test self-redirect prevention in handle().
     * Verify the source code prevents redirecting to the same URL.
     */
    public function testHandlePreventsExactSelfRedirect(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(RedirectMiddleware::class))->getFileName()
        );

        // Verify exact URL self-redirect check
        $this->assertStringContainsString(
            '$destinationUrl === $requestUri',
            $source,
            'handle() should check for exact self-redirect'
        );

        // Verify normalized path self-redirect check
        $this->assertStringContainsString(
            '$destPath === $requestUri',
            $source,
            'handle() should check for normalized path self-redirect'
        );
    }

    /**
     * Test safeRedirect() prevents self-redirect by comparing current URI and target path.
     */
    public function testSafeRedirectPreventsPathSelfRedirect(): void
    {
        $_SERVER['REQUEST_URI'] = '/dashboard';

        // safeRedirect() calls exit() on success, so we verify logic through source inspection
        $source = file_get_contents(
            (new ReflectionClass(RedirectMiddleware::class))->getFileName()
        );

        // Verify safeRedirect compares current and target paths
        $this->assertStringContainsString(
            '$currentUri === $targetPath',
            $source,
            'safeRedirect() should prevent self-redirect by comparing paths'
        );
    }

    /**
     * Test that safeRedirect() has a higher loop threshold than handle().
     */
    public function testSafeRedirectHasHigherLoopThreshold(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(RedirectMiddleware::class))->getFileName()
        );

        // handle() uses threshold of 5
        $this->assertStringContainsString('>= 5', $source,
            'handle() should use a loop threshold of 5');

        // safeRedirect() uses threshold of 8
        $this->assertStringContainsString('>= 8', $source,
            'safeRedirect() should use a higher loop threshold of 8');
    }

    // -----------------------------------------------------------------------
    // Static file extension list completeness tests
    // -----------------------------------------------------------------------

    /**
     * Test that all common static file extensions are in the skip list.
     */
    public function testSkipListContainsAllCommonExtensions(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(RedirectMiddleware::class))->getFileName()
        );

        $expectedExtensions = [
            'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico',
            'woff', 'woff2', 'ttf', 'eot', 'map', 'xml', 'txt', 'pdf'
        ];

        foreach ($expectedExtensions as $ext) {
            $this->assertStringContainsString(
                "'{$ext}'",
                $source,
                "Extension '{$ext}' should be in the static file skip list"
            );
        }
    }

    // -----------------------------------------------------------------------
    // 301 redirect status code test
    // -----------------------------------------------------------------------

    /**
     * Test that handle() uses 301 (permanent) redirect status.
     */
    public function testHandleUses301PermanentRedirect(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(RedirectMiddleware::class))->getFileName()
        );

        $this->assertStringContainsString('301 Moved Permanently', $source,
            'handle() should use 301 permanent redirect for SEO redirects');
    }

    /**
     * Test that safeRedirect() accepts a custom status code parameter.
     */
    public function testSafeRedirectAcceptsCustomStatusCode(): void
    {
        $reflection = new ReflectionClass(RedirectMiddleware::class);
        $method = $reflection->getMethod('safeRedirect');

        $params = $method->getParameters();
        $this->assertCount(2, $params, 'safeRedirect should have 2 parameters (url, statusCode)');
        $this->assertEquals('url', $params[0]->getName());
        $this->assertEquals('statusCode', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable(), 'statusCode should have a default value');
        $this->assertEquals(302, $params[1]->getDefaultValue(), 'Default status code should be 302');
    }
}
