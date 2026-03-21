<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\Csrf;
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure a session is started for tests
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        // Clear any existing CSRF token
        unset($_SESSION['csrf_token']);
        unset($_POST['csrf_token']);
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        unset($_SERVER['CONTENT_TYPE']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['csrf_token']);
        unset($_POST['csrf_token']);
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        unset($_SERVER['CONTENT_TYPE']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    // -------------------------------------------------------
    // generate()
    // -------------------------------------------------------

    public function test_generate_creates_token(): void
    {
        $token = Csrf::generate();
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function test_generate_returns_same_token_in_same_session(): void
    {
        $token1 = Csrf::generate();
        $token2 = Csrf::generate();
        $this->assertSame($token1, $token2);
    }

    // -------------------------------------------------------
    // token()
    // -------------------------------------------------------

    public function test_token_is_alias_for_generate(): void
    {
        $generated = Csrf::generate();
        $token = Csrf::token();
        $this->assertSame($generated, $token);
    }

    // -------------------------------------------------------
    // verify()
    // -------------------------------------------------------

    public function test_verify_with_correct_token_returns_true(): void
    {
        $token = Csrf::generate();
        $this->assertTrue(Csrf::verify($token));
    }

    public function test_verify_with_wrong_token_returns_false(): void
    {
        Csrf::generate();
        $this->assertFalse(Csrf::verify('wrong-token'));
    }

    public function test_verify_with_empty_session_token_returns_false(): void
    {
        unset($_SESSION['csrf_token']);
        $this->assertFalse(Csrf::verify('some-token'));
    }

    public function test_verify_auto_detects_post_token(): void
    {
        $token = Csrf::generate();
        $_POST['csrf_token'] = $token;
        $this->assertTrue(Csrf::verify());
    }

    public function test_verify_auto_detects_header_token(): void
    {
        $token = Csrf::generate();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $this->assertTrue(Csrf::verify());
    }

    // -------------------------------------------------------
    // input()
    // -------------------------------------------------------

    public function test_input_returns_hidden_field_html(): void
    {
        $html = Csrf::input();
        $this->assertStringContainsString('<input type="hidden"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString('value="', $html);
    }

    // -------------------------------------------------------
    // field()
    // -------------------------------------------------------

    public function test_field_is_alias_for_input(): void
    {
        $input = Csrf::input();
        // Reset token to get same value
        $field = Csrf::field();
        $this->assertSame($input, $field);
    }
}
