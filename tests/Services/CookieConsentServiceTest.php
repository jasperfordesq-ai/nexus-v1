<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\CookieConsentService;

class CookieConsentServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CookieConsentService::class));
    }

    public function testIsConsentValidReturnsTrueForValidConsent(): void
    {
        $consent = [
            'withdrawal_date' => null,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'consent_version' => '1.0'
        ];

        $this->assertTrue(CookieConsentService::isConsentValid($consent));
    }

    public function testIsConsentValidReturnsFalseWhenWithdrawn(): void
    {
        $consent = [
            'withdrawal_date' => '2026-01-01 00:00:00',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'consent_version' => '1.0'
        ];

        $this->assertFalse(CookieConsentService::isConsentValid($consent));
    }

    public function testIsConsentValidReturnsFalseWhenExpired(): void
    {
        $consent = [
            'withdrawal_date' => null,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'consent_version' => '1.0'
        ];

        $this->assertFalse(CookieConsentService::isConsentValid($consent));
    }

    public function testIsConsentValidReturnsFalseForWrongVersion(): void
    {
        $consent = [
            'withdrawal_date' => null,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'consent_version' => '0.9'
        ];

        $this->assertFalse(CookieConsentService::isConsentValid($consent));
    }

    public function testIsConsentValidHandlesMissingExpiresAt(): void
    {
        $consent = [
            'withdrawal_date' => null,
            'expires_at' => null,
            'consent_version' => '1.0'
        ];

        // No expires_at means it doesn't fail the expiry check
        $this->assertTrue(CookieConsentService::isConsentValid($consent));
    }

    public function testIsConsentValidHandlesMissingVersion(): void
    {
        $consent = [
            'withdrawal_date' => null,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ];

        // Missing version defaults to '1.0' which matches CONSENT_VERSION
        $this->assertTrue(CookieConsentService::isConsentValid($consent));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'recordConsent', 'getConsent', 'getCurrentConsent', 'hasConsent',
            'updateConsent', 'withdrawConsent', 'isConsentValid',
            'getTenantSettings', 'updateTenantSettings', 'getStatistics',
            'getConsentSummary', 'cleanExpiredConsents'
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(CookieConsentService::class, $method),
                "Method {$method} should exist on CookieConsentService"
            );
        }
    }

    public function testGetTenantSettingsReturnsDefaults(): void
    {
        // Since we can't connect to DB in unit tests, we test the structure
        // The method will throw on DB access, but we verify the class interface
        $reflection = new \ReflectionMethod(CookieConsentService::class, 'getTenantSettings');
        $this->assertTrue($reflection->isPublic());
        $this->assertTrue($reflection->isStatic());

        $params = $reflection->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('tenantId', $params[0]->getName());
    }
}
