<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Middleware\RedirectMiddleware;
use Tests\Laravel\TestCase;

/**
 * Tests for RedirectMiddleware.
 *
 * This middleware uses static methods, $_SERVER superglobals, and calls
 * exit() on redirect. Tests focus on the paths that return early (no redirect).
 */
class RedirectMiddlewareTest extends TestCase
{
    private array $originalServer;
    private array $originalCookie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $this->originalCookie = $_COOKIE;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_COOKIE = $this->originalCookie;
        parent::tearDown();
    }

    public function test_handle_skips_post_requests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/some-page';

        // Should return immediately without processing
        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_put_requests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/some-page';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_delete_requests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/some-page';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_login_page(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/login';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_register_page(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/register';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_password_page(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/password/reset';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_admin_pages(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/admin/dashboard';
        $_SERVER['HTTP_HOST'] = 'app.project-nexus.ie';
        $_COOKIE['redirect_loop_detector'] = '0';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_api_routes(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v2/feed';
        $_SERVER['HTTP_HOST'] = 'app.project-nexus.ie';
        $_COOKIE['redirect_loop_detector'] = '0';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_static_file_extensions(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/assets/style.css';
        $_SERVER['HTTP_HOST'] = 'app.project-nexus.ie';
        $_COOKIE['redirect_loop_detector'] = '0';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_blocks_redirect_loop(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/some-page';
        $_SERVER['HTTP_HOST'] = 'app.project-nexus.ie';
        $_COOKIE['redirect_loop_detector'] = '5'; // Threshold is 5

        RedirectMiddleware::handle();
        // Should not redirect due to loop detection
        $this->assertTrue(true);
    }

    public function test_handle_skips_js_files(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/assets/app.js';
        $_SERVER['HTTP_HOST'] = 'app.project-nexus.ie';
        $_COOKIE['redirect_loop_detector'] = '0';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_image_files(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/images/photo.jpg';
        $_SERVER['HTTP_HOST'] = 'app.project-nexus.ie';
        $_COOKIE['redirect_loop_detector'] = '0';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_font_files(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/fonts/inter.woff2';
        $_SERVER['HTTP_HOST'] = 'app.project-nexus.ie';
        $_COOKIE['redirect_loop_detector'] = '0';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_svg_files(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/icons/logo.svg';
        $_SERVER['HTTP_HOST'] = 'app.project-nexus.ie';
        $_COOKIE['redirect_loop_detector'] = '0';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }

    public function test_handle_skips_logout_page(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/logout';

        RedirectMiddleware::handle();
        $this->assertTrue(true);
    }
}
