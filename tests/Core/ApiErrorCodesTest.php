<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\TestCase;
use Nexus\Core\ApiErrorCodes;

/**
 * ApiErrorCodes Tests
 *
 * Tests standardized API error code constants and helper methods including:
 * - Constant definitions for all error categories
 * - HTTP status code mapping
 * - Error type detection (auth, validation, retryable)
 *
 * @covers \Nexus\Core\ApiErrorCodes
 */
class ApiErrorCodesTest extends TestCase
{
    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ApiErrorCodes::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'getHttpStatus',
            'isAuthError',
            'isValidationError',
            'isRetryable',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(ApiErrorCodes::class, $method),
                "Method {$method} should exist on ApiErrorCodes"
            );
        }
    }

    // =========================================================================
    // AUTHENTICATION ERROR CONSTANTS
    // =========================================================================

    public function testAuthErrorConstantsExist(): void
    {
        $this->assertIsString(ApiErrorCodes::AUTH_TOKEN_EXPIRED);
        $this->assertIsString(ApiErrorCodes::AUTH_TOKEN_INVALID);
        $this->assertIsString(ApiErrorCodes::AUTH_TOKEN_MISSING);
        $this->assertIsString(ApiErrorCodes::AUTH_REFRESH_REQUIRED);
        $this->assertIsString(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS);
        $this->assertIsString(ApiErrorCodes::AUTH_ACCOUNT_SUSPENDED);
        $this->assertIsString(ApiErrorCodes::AUTH_ACCOUNT_DELETED);
        $this->assertIsString(ApiErrorCodes::AUTH_INVALID_CREDENTIALS);
        $this->assertIsString(ApiErrorCodes::AUTH_CSRF_INVALID);
    }

    public function testTenantErrorConstantsExist(): void
    {
        $this->assertIsString(ApiErrorCodes::INVALID_TENANT);
        $this->assertIsString(ApiErrorCodes::TENANT_MISMATCH);
    }

    public function test2FAErrorConstantsExist(): void
    {
        $this->assertIsString(ApiErrorCodes::AUTH_2FA_REQUIRED);
        $this->assertIsString(ApiErrorCodes::AUTH_2FA_INVALID);
        $this->assertIsString(ApiErrorCodes::AUTH_2FA_EXPIRED);
        $this->assertIsString(ApiErrorCodes::AUTH_2FA_TOKEN_EXPIRED);
        $this->assertIsString(ApiErrorCodes::AUTH_2FA_TOKEN_INVALID);
        $this->assertIsString(ApiErrorCodes::AUTH_2FA_MAX_ATTEMPTS);
    }

    public function testWebAuthnErrorConstantsExist(): void
    {
        $this->assertIsString(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED);
        $this->assertIsString(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID);
        $this->assertIsString(ApiErrorCodes::AUTH_WEBAUTHN_CREDENTIAL_NOT_FOUND);
        $this->assertIsString(ApiErrorCodes::AUTH_WEBAUTHN_FAILED);
    }

    // =========================================================================
    // VALIDATION ERROR CONSTANTS
    // =========================================================================

    public function testValidationErrorConstantsExist(): void
    {
        $this->assertIsString(ApiErrorCodes::VALIDATION_REQUIRED_FIELD);
        $this->assertIsString(ApiErrorCodes::VALIDATION_INVALID_FORMAT);
        $this->assertIsString(ApiErrorCodes::VALIDATION_INVALID_VALUE);
        $this->assertIsString(ApiErrorCodes::VALIDATION_DUPLICATE);
        $this->assertIsString(ApiErrorCodes::VALIDATION_ERROR);
        $this->assertIsString(ApiErrorCodes::VALIDATION_TOO_SHORT);
        $this->assertIsString(ApiErrorCodes::VALIDATION_TOO_LONG);
        $this->assertIsString(ApiErrorCodes::VALIDATION_OUT_OF_RANGE);
    }

    // =========================================================================
    // RESOURCE ERROR CONSTANTS
    // =========================================================================

    public function testResourceErrorConstantsExist(): void
    {
        $this->assertIsString(ApiErrorCodes::RESOURCE_NOT_FOUND);
        $this->assertIsString(ApiErrorCodes::RESOURCE_ALREADY_EXISTS);
        $this->assertIsString(ApiErrorCodes::RESOURCE_CONFLICT);
        $this->assertIsString(ApiErrorCodes::RESOURCE_DELETED);
        $this->assertIsString(ApiErrorCodes::RESOURCE_FORBIDDEN);
    }

    // =========================================================================
    // OTHER ERROR CONSTANTS
    // =========================================================================

    public function testRateLimitErrorConstantsExist(): void
    {
        $this->assertIsString(ApiErrorCodes::RATE_LIMIT_EXCEEDED);
        $this->assertIsString(ApiErrorCodes::RATE_LIMITED);
    }

    public function testUploadErrorConstantsExist(): void
    {
        $this->assertIsString(ApiErrorCodes::UPLOAD_TOO_LARGE);
        $this->assertIsString(ApiErrorCodes::UPLOAD_INVALID_TYPE);
        $this->assertIsString(ApiErrorCodes::UPLOAD_FAILED);
        $this->assertIsString(ApiErrorCodes::UPLOAD_NO_FILE);
    }

    public function testServerErrorConstantsExist(): void
    {
        $this->assertIsString(ApiErrorCodes::SERVER_INTERNAL_ERROR);
        $this->assertIsString(ApiErrorCodes::SERVER_MAINTENANCE);
        $this->assertIsString(ApiErrorCodes::SERVER_DEPENDENCY_FAILED);
    }

    public function testLegacyErrorConstantsExist(): void
    {
        $this->assertIsString(ApiErrorCodes::FORBIDDEN);
    }

    // =========================================================================
    // GET HTTP STATUS TESTS - 400 BAD REQUEST
    // =========================================================================

    public function testGetHttpStatusFor400Errors(): void
    {
        $this->assertEquals(400, ApiErrorCodes::getHttpStatus(ApiErrorCodes::VALIDATION_REQUIRED_FIELD));
        $this->assertEquals(400, ApiErrorCodes::getHttpStatus(ApiErrorCodes::VALIDATION_INVALID_FORMAT));
        $this->assertEquals(400, ApiErrorCodes::getHttpStatus(ApiErrorCodes::VALIDATION_INVALID_VALUE));
        $this->assertEquals(400, ApiErrorCodes::getHttpStatus(ApiErrorCodes::VALIDATION_ERROR));
        $this->assertEquals(400, ApiErrorCodes::getHttpStatus(ApiErrorCodes::UPLOAD_NO_FILE));
        $this->assertEquals(400, ApiErrorCodes::getHttpStatus(ApiErrorCodes::INVALID_TENANT));
    }

    // =========================================================================
    // GET HTTP STATUS TESTS - 401 UNAUTHORIZED
    // =========================================================================

    public function testGetHttpStatusFor401Errors(): void
    {
        $this->assertEquals(401, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_TOKEN_EXPIRED));
        $this->assertEquals(401, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_TOKEN_INVALID));
        $this->assertEquals(401, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_TOKEN_MISSING));
        $this->assertEquals(401, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_REFRESH_REQUIRED));
        $this->assertEquals(401, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_INVALID_CREDENTIALS));
        $this->assertEquals(401, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_2FA_REQUIRED));
        $this->assertEquals(401, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_WEBAUTHN_FAILED));
    }

    // =========================================================================
    // GET HTTP STATUS TESTS - 403 FORBIDDEN
    // =========================================================================

    public function testGetHttpStatusFor403Errors(): void
    {
        $this->assertEquals(403, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS));
        $this->assertEquals(403, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_ACCOUNT_SUSPENDED));
        $this->assertEquals(403, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_ACCOUNT_DELETED));
        $this->assertEquals(403, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_CSRF_INVALID));
        $this->assertEquals(403, ApiErrorCodes::getHttpStatus(ApiErrorCodes::RESOURCE_FORBIDDEN));
        $this->assertEquals(403, ApiErrorCodes::getHttpStatus(ApiErrorCodes::FORBIDDEN));
        $this->assertEquals(403, ApiErrorCodes::getHttpStatus(ApiErrorCodes::TENANT_MISMATCH));
    }

    // =========================================================================
    // GET HTTP STATUS TESTS - 404 NOT FOUND
    // =========================================================================

    public function testGetHttpStatusFor404Errors(): void
    {
        $this->assertEquals(404, ApiErrorCodes::getHttpStatus(ApiErrorCodes::RESOURCE_NOT_FOUND));
    }

    // =========================================================================
    // GET HTTP STATUS TESTS - 409 CONFLICT
    // =========================================================================

    public function testGetHttpStatusFor409Errors(): void
    {
        $this->assertEquals(409, ApiErrorCodes::getHttpStatus(ApiErrorCodes::VALIDATION_DUPLICATE));
        $this->assertEquals(409, ApiErrorCodes::getHttpStatus(ApiErrorCodes::RESOURCE_ALREADY_EXISTS));
        $this->assertEquals(409, ApiErrorCodes::getHttpStatus(ApiErrorCodes::RESOURCE_CONFLICT));
    }

    // =========================================================================
    // GET HTTP STATUS TESTS - 410 GONE
    // =========================================================================

    public function testGetHttpStatusFor410Errors(): void
    {
        $this->assertEquals(410, ApiErrorCodes::getHttpStatus(ApiErrorCodes::RESOURCE_DELETED));
    }

    // =========================================================================
    // GET HTTP STATUS TESTS - 413 PAYLOAD TOO LARGE
    // =========================================================================

    public function testGetHttpStatusFor413Errors(): void
    {
        $this->assertEquals(413, ApiErrorCodes::getHttpStatus(ApiErrorCodes::UPLOAD_TOO_LARGE));
    }

    // =========================================================================
    // GET HTTP STATUS TESTS - 429 TOO MANY REQUESTS
    // =========================================================================

    public function testGetHttpStatusFor429Errors(): void
    {
        $this->assertEquals(429, ApiErrorCodes::getHttpStatus(ApiErrorCodes::RATE_LIMIT_EXCEEDED));
        $this->assertEquals(429, ApiErrorCodes::getHttpStatus(ApiErrorCodes::RATE_LIMITED));
    }

    // =========================================================================
    // GET HTTP STATUS TESTS - 500 INTERNAL SERVER ERROR
    // =========================================================================

    public function testGetHttpStatusFor500Errors(): void
    {
        $this->assertEquals(500, ApiErrorCodes::getHttpStatus(ApiErrorCodes::SERVER_INTERNAL_ERROR));
        $this->assertEquals(500, ApiErrorCodes::getHttpStatus(ApiErrorCodes::UPLOAD_FAILED));
    }

    // =========================================================================
    // GET HTTP STATUS TESTS - 503 SERVICE UNAVAILABLE
    // =========================================================================

    public function testGetHttpStatusFor503Errors(): void
    {
        $this->assertEquals(503, ApiErrorCodes::getHttpStatus(ApiErrorCodes::SERVER_MAINTENANCE));
        $this->assertEquals(503, ApiErrorCodes::getHttpStatus(ApiErrorCodes::SERVER_DEPENDENCY_FAILED));
    }

    // =========================================================================
    // GET HTTP STATUS TESTS - DEFAULT
    // =========================================================================

    public function testGetHttpStatusReturns400ForUnknownCode(): void
    {
        $this->assertEquals(400, ApiErrorCodes::getHttpStatus('UNKNOWN_ERROR_CODE'));
        $this->assertEquals(400, ApiErrorCodes::getHttpStatus('CUSTOM_ERROR'));
    }

    // =========================================================================
    // IS AUTH ERROR TESTS
    // =========================================================================

    public function testIsAuthErrorReturnsTrueForAuthErrors(): void
    {
        $this->assertTrue(ApiErrorCodes::isAuthError(ApiErrorCodes::AUTH_TOKEN_EXPIRED));
        $this->assertTrue(ApiErrorCodes::isAuthError(ApiErrorCodes::AUTH_TOKEN_INVALID));
        $this->assertTrue(ApiErrorCodes::isAuthError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS));
        $this->assertTrue(ApiErrorCodes::isAuthError(ApiErrorCodes::AUTH_2FA_REQUIRED));
        $this->assertTrue(ApiErrorCodes::isAuthError(ApiErrorCodes::AUTH_WEBAUTHN_FAILED));
    }

    public function testIsAuthErrorReturnsFalseForNonAuthErrors(): void
    {
        $this->assertFalse(ApiErrorCodes::isAuthError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD));
        $this->assertFalse(ApiErrorCodes::isAuthError(ApiErrorCodes::RESOURCE_NOT_FOUND));
        $this->assertFalse(ApiErrorCodes::isAuthError(ApiErrorCodes::RATE_LIMIT_EXCEEDED));
        $this->assertFalse(ApiErrorCodes::isAuthError(ApiErrorCodes::SERVER_INTERNAL_ERROR));
        $this->assertFalse(ApiErrorCodes::isAuthError(ApiErrorCodes::FORBIDDEN));
    }

    // =========================================================================
    // IS VALIDATION ERROR TESTS
    // =========================================================================

    public function testIsValidationErrorReturnsTrueForValidationErrors(): void
    {
        $this->assertTrue(ApiErrorCodes::isValidationError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD));
        $this->assertTrue(ApiErrorCodes::isValidationError(ApiErrorCodes::VALIDATION_INVALID_FORMAT));
        $this->assertTrue(ApiErrorCodes::isValidationError(ApiErrorCodes::VALIDATION_DUPLICATE));
        $this->assertTrue(ApiErrorCodes::isValidationError(ApiErrorCodes::VALIDATION_TOO_SHORT));
        $this->assertTrue(ApiErrorCodes::isValidationError(ApiErrorCodes::VALIDATION_OUT_OF_RANGE));
    }

    public function testIsValidationErrorReturnsFalseForNonValidationErrors(): void
    {
        $this->assertFalse(ApiErrorCodes::isValidationError(ApiErrorCodes::AUTH_TOKEN_EXPIRED));
        $this->assertFalse(ApiErrorCodes::isValidationError(ApiErrorCodes::RESOURCE_NOT_FOUND));
        $this->assertFalse(ApiErrorCodes::isValidationError(ApiErrorCodes::RATE_LIMIT_EXCEEDED));
        $this->assertFalse(ApiErrorCodes::isValidationError(ApiErrorCodes::SERVER_INTERNAL_ERROR));
    }

    // =========================================================================
    // IS RETRYABLE TESTS
    // =========================================================================

    public function testIsRetryableReturnsTrueForRetryableErrors(): void
    {
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::RATE_LIMIT_EXCEEDED));
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::RATE_LIMITED));
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::SERVER_MAINTENANCE));
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::SERVER_DEPENDENCY_FAILED));
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::AUTH_TOKEN_EXPIRED));
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::AUTH_REFRESH_REQUIRED));
    }

    public function testIsRetryableReturnsFalseForNonRetryableErrors(): void
    {
        $this->assertFalse(ApiErrorCodes::isRetryable(ApiErrorCodes::AUTH_INVALID_CREDENTIALS));
        $this->assertFalse(ApiErrorCodes::isRetryable(ApiErrorCodes::VALIDATION_REQUIRED_FIELD));
        $this->assertFalse(ApiErrorCodes::isRetryable(ApiErrorCodes::RESOURCE_NOT_FOUND));
        $this->assertFalse(ApiErrorCodes::isRetryable(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS));
        $this->assertFalse(ApiErrorCodes::isRetryable(ApiErrorCodes::UPLOAD_TOO_LARGE));
    }

    // =========================================================================
    // ERROR CODE FORMAT TESTS
    // =========================================================================

    public function testAuthErrorCodesHaveAuthPrefix(): void
    {
        $authCodes = [
            ApiErrorCodes::AUTH_TOKEN_EXPIRED,
            ApiErrorCodes::AUTH_TOKEN_INVALID,
            ApiErrorCodes::AUTH_TOKEN_MISSING,
            ApiErrorCodes::AUTH_REFRESH_REQUIRED,
            ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS,
            ApiErrorCodes::AUTH_ACCOUNT_SUSPENDED,
            ApiErrorCodes::AUTH_ACCOUNT_DELETED,
            ApiErrorCodes::AUTH_INVALID_CREDENTIALS,
            ApiErrorCodes::AUTH_CSRF_INVALID,
            ApiErrorCodes::AUTH_2FA_REQUIRED,
            ApiErrorCodes::AUTH_WEBAUTHN_FAILED,
        ];

        foreach ($authCodes as $code) {
            $this->assertStringStartsWith('AUTH_', $code);
        }
    }

    public function testValidationErrorCodesHaveValidationPrefix(): void
    {
        $validationCodes = [
            ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
            ApiErrorCodes::VALIDATION_INVALID_FORMAT,
            ApiErrorCodes::VALIDATION_INVALID_VALUE,
            ApiErrorCodes::VALIDATION_DUPLICATE,
            ApiErrorCodes::VALIDATION_ERROR,
            ApiErrorCodes::VALIDATION_TOO_SHORT,
            ApiErrorCodes::VALIDATION_TOO_LONG,
            ApiErrorCodes::VALIDATION_OUT_OF_RANGE,
        ];

        foreach ($validationCodes as $code) {
            $this->assertStringStartsWith('VALIDATION_', $code);
        }
    }

    public function testErrorCodesAreUpperCaseWithUnderscores(): void
    {
        $ref = new \ReflectionClass(ApiErrorCodes::class);
        $constants = $ref->getConstants();

        foreach ($constants as $name => $value) {
            // Should be UPPER_CASE_WITH_UNDERSCORES (may contain digits like 2FA)
            $this->assertEquals(
                strtoupper($value),
                $value,
                "Constant {$name} value should be uppercase"
            );

            $this->assertMatchesRegularExpression(
                '/^[A-Z0-9_]+$/',
                $value,
                "Constant {$name} value should only contain uppercase letters, digits, and underscores"
            );
        }
    }
}
