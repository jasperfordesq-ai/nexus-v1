<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\TestCase;
use Nexus\Core\Csrf;

/**
 * CSRF Protection Tests
 *
 * Tests CSRF token generation, validation, and helper methods including:
 * - Token generation and persistence
 * - Token verification from POST, header, and JSON body
 * - Bearer token bypass
 * - HTML field generation
 *
 * @covers \Nexus\Core\Csrf
 */
class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Clear session state
        unset($_SESSION['csrf_token']);
        unset($_POST['csrf_token']);
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        unset($_SERVER['CONTENT_TYPE']);
    }

    protected function tearDown(): void
    {
        // Clean up session state
        if (isset($_SESSION)) {
            unset($_SESSION['csrf_token']);
        }
        unset($_POST['csrf_token']);
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        unset($_SERVER['CONTENT_TYPE']);

        parent::tearDown();
    }

    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Csrf::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'generate',
            'verify',
            'verifyOrDie',
            'verifyOrDieJson',
            'input',
            'field',
            'token',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(Csrf::class, $method),
                "Method {$method} should exist on Csrf"
            );
        }
    }

    // =========================================================================
    // TOKEN GENERATION TESTS
    // =========================================================================

    public function testGenerateCreatesToken(): void
    {
        $token = Csrf::generate();

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertEquals($token, $_SESSION['csrf_token']);
    }

    public function testGenerateDoesNotRegenerateExistingToken(): void
    {
        $token1 = Csrf::generate();
        $token2 = Csrf::generate();

        // Should return the same token, not regenerate
        $this->assertEquals($token1, $token2);
        $this->assertEquals($token1, $_SESSION['csrf_token']);
    }

    public function testGenerateCreatesUniqueTokens(): void
    {
        $token1 = Csrf::generate();
        unset($_SESSION['csrf_token']);
        $token2 = Csrf::generate();

        // Different tokens when session is cleared
        $this->assertNotEquals($token1, $token2);
    }

    public function testTokenIsAlias(): void
    {
        $token = Csrf::token();

        $this->assertNotEmpty($token);
        $this->assertEquals($_SESSION['csrf_token'], $token);
    }

    // =========================================================================
    // TOKEN VERIFICATION - POST DATA
    // =========================================================================

    public function testVerifyReturnsTrueWithValidPostToken(): void
    {
        $token = Csrf::generate();
        $_POST['csrf_token'] = $token;

        $this->assertTrue(Csrf::verify());
    }

    public function testVerifyReturnsFalseWithInvalidPostToken(): void
    {
        Csrf::generate();
        $_POST['csrf_token'] = 'invalid_token';

        $this->assertFalse(Csrf::verify());
    }

    public function testVerifyReturnsFalseWithNoToken(): void
    {
        Csrf::generate();

        $this->assertFalse(Csrf::verify());
    }

    public function testVerifyReturnsFalseWithNoSessionToken(): void
    {
        $_POST['csrf_token'] = 'some_token';

        $this->assertFalse(Csrf::verify());
    }

    // =========================================================================
    // TOKEN VERIFICATION - HEADER
    // =========================================================================

    public function testVerifyChecksXCsrfTokenHeader(): void
    {
        $token = Csrf::generate();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        $this->assertTrue(Csrf::verify());
    }

    public function testVerifyPrefersPostOverHeader(): void
    {
        $token = Csrf::generate();
        $_POST['csrf_token'] = $token;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong_token';

        // POST takes precedence
        $this->assertTrue(Csrf::verify());
    }

    // =========================================================================
    // TOKEN VERIFICATION - JSON BODY
    // =========================================================================

    public function testVerifyMethodSignature(): void
    {
        $ref = new \ReflectionMethod(Csrf::class, 'verify');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('token', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertNull($params[0]->getDefaultValue());
    }

    // =========================================================================
    // BEARER TOKEN BYPASS TESTS
    // =========================================================================

    public function testHasBearerTokenDetectsAuthorizationHeader(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_token_123';

        $ref = new \ReflectionClass(Csrf::class);
        $method = $ref->getMethod('hasBearerToken');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null));
    }

    public function testHasBearerTokenDetectsRedirectHeader(): void
    {
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer test_token_123';

        $ref = new \ReflectionClass(Csrf::class);
        $method = $ref->getMethod('hasBearerToken');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null));
    }

    public function testHasBearerTokenIsCaseInsensitive(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'bearer test_token_123';

        $ref = new \ReflectionClass(Csrf::class);
        $method = $ref->getMethod('hasBearerToken');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null));
    }

    public function testHasBearerTokenReturnsFalseForNonBearerAuth(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

        $ref = new \ReflectionClass(Csrf::class);
        $method = $ref->getMethod('hasBearerToken');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null));
    }

    public function testHasBearerTokenReturnsFalseWhenNoAuth(): void
    {
        $ref = new \ReflectionClass(Csrf::class);
        $method = $ref->getMethod('hasBearerToken');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null));
    }

    // =========================================================================
    // HTML FIELD GENERATION TESTS
    // =========================================================================

    public function testInputGeneratesHiddenField(): void
    {
        $token = Csrf::generate();
        $html = Csrf::input();

        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString('value="' . htmlspecialchars($token) . '"', $html);
    }

    public function testFieldIsAliasForInput(): void
    {
        $input = Csrf::input();
        $field = Csrf::field();

        $this->assertEquals($input, $field);
    }

    public function testInputEscapesHtmlInToken(): void
    {
        // Manually set a token with special chars (shouldn't happen in practice)
        $_SESSION['csrf_token'] = '<script>alert("xss")</script>';
        $html = Csrf::input();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString(htmlspecialchars('<script>alert("xss")</script>'), $html);
    }

    // =========================================================================
    // HASH TIMING ATTACK PREVENTION
    // =========================================================================

    public function testVerifyUsesTimingSafeComparison(): void
    {
        // Verify method uses hash_equals for timing-safe comparison
        $ref = new \ReflectionClass(Csrf::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString('hash_equals', $source);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testVerifyWithEmptyStringToken(): void
    {
        Csrf::generate();
        $_POST['csrf_token'] = '';

        $this->assertFalse(Csrf::verify());
    }

    public function testVerifyWithNullToken(): void
    {
        Csrf::generate();

        // Explicitly passing null
        $this->assertFalse(Csrf::verify(null));
    }

    public function testVerifyWithDirectTokenParameter(): void
    {
        $token = Csrf::generate();

        // Pass token directly
        $this->assertTrue(Csrf::verify($token));
        $this->assertFalse(Csrf::verify('wrong_token'));
    }

    public function testGenerateWorksWithoutExistingSession(): void
    {
        // Session should auto-start if needed
        if (session_status() !== PHP_SESSION_NONE) {
            session_write_close();
        }

        $token = Csrf::generate();

        $this->assertNotEmpty($token);
        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
    }

    public function testVerifyWorksWithoutExistingSession(): void
    {
        // Session should auto-start if needed
        if (session_status() !== PHP_SESSION_NONE) {
            session_write_close();
        }

        $result = Csrf::verify('some_token');

        $this->assertFalse($result); // No session token = fail
        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
    }
}
