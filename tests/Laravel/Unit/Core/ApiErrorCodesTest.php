<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\ApiErrorCodes;
use PHPUnit\Framework\TestCase;

class ApiErrorCodesTest extends TestCase
{
    // -------------------------------------------------------
    // getHttpStatus()
    // -------------------------------------------------------

    public function test_getHttpStatus_auth_token_expired_returns_401(): void
    {
        $this->assertSame(401, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_TOKEN_EXPIRED));
    }

    public function test_getHttpStatus_auth_token_invalid_returns_401(): void
    {
        $this->assertSame(401, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_TOKEN_INVALID));
    }

    public function test_getHttpStatus_auth_token_missing_returns_401(): void
    {
        $this->assertSame(401, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_TOKEN_MISSING));
    }

    public function test_getHttpStatus_auth_insufficient_permissions_returns_403(): void
    {
        $this->assertSame(403, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS));
    }

    public function test_getHttpStatus_validation_required_field_returns_400(): void
    {
        $this->assertSame(400, ApiErrorCodes::getHttpStatus(ApiErrorCodes::VALIDATION_REQUIRED_FIELD));
    }

    public function test_getHttpStatus_validation_duplicate_returns_409(): void
    {
        $this->assertSame(409, ApiErrorCodes::getHttpStatus(ApiErrorCodes::VALIDATION_DUPLICATE));
    }

    public function test_getHttpStatus_resource_not_found_returns_404(): void
    {
        $this->assertSame(404, ApiErrorCodes::getHttpStatus(ApiErrorCodes::RESOURCE_NOT_FOUND));
    }

    public function test_getHttpStatus_resource_deleted_returns_410(): void
    {
        $this->assertSame(410, ApiErrorCodes::getHttpStatus(ApiErrorCodes::RESOURCE_DELETED));
    }

    public function test_getHttpStatus_upload_too_large_returns_413(): void
    {
        $this->assertSame(413, ApiErrorCodes::getHttpStatus(ApiErrorCodes::UPLOAD_TOO_LARGE));
    }

    public function test_getHttpStatus_rate_limit_exceeded_returns_429(): void
    {
        $this->assertSame(429, ApiErrorCodes::getHttpStatus(ApiErrorCodes::RATE_LIMIT_EXCEEDED));
    }

    public function test_getHttpStatus_server_internal_error_returns_500(): void
    {
        $this->assertSame(500, ApiErrorCodes::getHttpStatus(ApiErrorCodes::SERVER_INTERNAL_ERROR));
    }

    public function test_getHttpStatus_server_maintenance_returns_503(): void
    {
        $this->assertSame(503, ApiErrorCodes::getHttpStatus(ApiErrorCodes::SERVER_MAINTENANCE));
    }

    public function test_getHttpStatus_unknown_code_returns_400(): void
    {
        $this->assertSame(400, ApiErrorCodes::getHttpStatus('UNKNOWN_CODE'));
    }

    public function test_getHttpStatus_invalid_tenant_returns_400(): void
    {
        $this->assertSame(400, ApiErrorCodes::getHttpStatus(ApiErrorCodes::INVALID_TENANT));
    }

    public function test_getHttpStatus_tenant_mismatch_returns_403(): void
    {
        $this->assertSame(403, ApiErrorCodes::getHttpStatus(ApiErrorCodes::TENANT_MISMATCH));
    }

    public function test_getHttpStatus_super_panel_access_denied_returns_403(): void
    {
        $this->assertSame(403, ApiErrorCodes::getHttpStatus(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED));
    }

    public function test_getHttpStatus_webauthn_challenge_expired_returns_401(): void
    {
        $this->assertSame(401, ApiErrorCodes::getHttpStatus(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED));
    }

    public function test_getHttpStatus_registration_closed_returns_400(): void
    {
        // REGISTRATION_CLOSED hits the default branch
        $this->assertSame(400, ApiErrorCodes::getHttpStatus(ApiErrorCodes::REGISTRATION_CLOSED));
    }

    // -------------------------------------------------------
    // isAuthError()
    // -------------------------------------------------------

    public function test_isAuthError_with_auth_prefix_returns_true(): void
    {
        $this->assertTrue(ApiErrorCodes::isAuthError(ApiErrorCodes::AUTH_TOKEN_EXPIRED));
        $this->assertTrue(ApiErrorCodes::isAuthError(ApiErrorCodes::AUTH_INVALID_CREDENTIALS));
        $this->assertTrue(ApiErrorCodes::isAuthError(ApiErrorCodes::AUTH_2FA_REQUIRED));
        $this->assertTrue(ApiErrorCodes::isAuthError(ApiErrorCodes::AUTH_WEBAUTHN_FAILED));
    }

    public function test_isAuthError_with_non_auth_prefix_returns_false(): void
    {
        $this->assertFalse(ApiErrorCodes::isAuthError(ApiErrorCodes::VALIDATION_ERROR));
        $this->assertFalse(ApiErrorCodes::isAuthError(ApiErrorCodes::RESOURCE_NOT_FOUND));
        $this->assertFalse(ApiErrorCodes::isAuthError(ApiErrorCodes::RATE_LIMIT_EXCEEDED));
        $this->assertFalse(ApiErrorCodes::isAuthError('SOME_RANDOM_CODE'));
    }

    // -------------------------------------------------------
    // isValidationError()
    // -------------------------------------------------------

    public function test_isValidationError_with_validation_prefix_returns_true(): void
    {
        $this->assertTrue(ApiErrorCodes::isValidationError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD));
        $this->assertTrue(ApiErrorCodes::isValidationError(ApiErrorCodes::VALIDATION_DUPLICATE));
        $this->assertTrue(ApiErrorCodes::isValidationError(ApiErrorCodes::VALIDATION_ERROR));
    }

    public function test_isValidationError_with_non_validation_prefix_returns_false(): void
    {
        $this->assertFalse(ApiErrorCodes::isValidationError(ApiErrorCodes::AUTH_TOKEN_EXPIRED));
        $this->assertFalse(ApiErrorCodes::isValidationError(ApiErrorCodes::RESOURCE_NOT_FOUND));
    }

    // -------------------------------------------------------
    // isRetryable()
    // -------------------------------------------------------

    public function test_isRetryable_with_retryable_codes_returns_true(): void
    {
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::RATE_LIMIT_EXCEEDED));
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::RATE_LIMITED));
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::SERVER_MAINTENANCE));
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::SERVER_DEPENDENCY_FAILED));
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::AUTH_TOKEN_EXPIRED));
        $this->assertTrue(ApiErrorCodes::isRetryable(ApiErrorCodes::AUTH_REFRESH_REQUIRED));
    }

    public function test_isRetryable_with_non_retryable_codes_returns_false(): void
    {
        $this->assertFalse(ApiErrorCodes::isRetryable(ApiErrorCodes::AUTH_INVALID_CREDENTIALS));
        $this->assertFalse(ApiErrorCodes::isRetryable(ApiErrorCodes::VALIDATION_ERROR));
        $this->assertFalse(ApiErrorCodes::isRetryable(ApiErrorCodes::RESOURCE_NOT_FOUND));
        $this->assertFalse(ApiErrorCodes::isRetryable(ApiErrorCodes::FORBIDDEN));
    }

    // -------------------------------------------------------
    // Constants existence
    // -------------------------------------------------------

    public function test_constants_are_defined(): void
    {
        $this->assertSame('AUTH_TOKEN_EXPIRED', ApiErrorCodes::AUTH_TOKEN_EXPIRED);
        $this->assertSame('VALIDATION_ERROR', ApiErrorCodes::VALIDATION_ERROR);
        $this->assertSame('RESOURCE_NOT_FOUND', ApiErrorCodes::RESOURCE_NOT_FOUND);
        $this->assertSame('RATE_LIMIT_EXCEEDED', ApiErrorCodes::RATE_LIMIT_EXCEEDED);
        $this->assertSame('UPLOAD_TOO_LARGE', ApiErrorCodes::UPLOAD_TOO_LARGE);
        $this->assertSame('SERVER_INTERNAL_ERROR', ApiErrorCodes::SERVER_INTERNAL_ERROR);
        $this->assertSame('FORBIDDEN', ApiErrorCodes::FORBIDDEN);
    }
}
