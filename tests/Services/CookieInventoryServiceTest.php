<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\CookieInventoryService;

class CookieInventoryServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CookieInventoryService::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'getCookiesByCategory', 'getAllCookies', 'getBannerCookieList',
            'addCookie', 'updateCookie', 'deleteCookie', 'getCookie',
            'getCookieByName', 'getCookieCounts', 'searchCookies',
            'getAllCookiesAdmin'
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(CookieInventoryService::class, $method),
                "Method {$method} should exist on CookieInventoryService"
            );
        }
    }

    public function testAddCookieValidatesRequiredFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: cookie_name');

        CookieInventoryService::addCookie([
            'category' => 'essential',
            'purpose' => 'Test',
            'duration' => '1 year'
            // missing cookie_name
        ]);
    }

    public function testAddCookieValidatesCategory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid category');

        CookieInventoryService::addCookie([
            'cookie_name' => 'test_cookie',
            'category' => 'invalid_category',
            'purpose' => 'Test',
            'duration' => '1 year'
        ]);
    }

    public function testAddCookieValidatesMissingPurpose(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: purpose');

        CookieInventoryService::addCookie([
            'cookie_name' => 'test_cookie',
            'category' => 'essential',
            'duration' => '1 year'
            // missing purpose
        ]);
    }

    public function testAddCookieValidatesMissingDuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: duration');

        CookieInventoryService::addCookie([
            'cookie_name' => 'test_cookie',
            'category' => 'essential',
            'purpose' => 'Test'
            // missing duration
        ]);
    }

    public function testAddCookieAcceptsValidCategories(): void
    {
        $validCategories = ['essential', 'functional', 'analytics', 'marketing'];

        foreach ($validCategories as $category) {
            // This will fail on DB access, but should NOT fail on validation
            try {
                CookieInventoryService::addCookie([
                    'cookie_name' => 'test_cookie',
                    'category' => $category,
                    'purpose' => 'Test purpose',
                    'duration' => '1 year'
                ]);
                // If we get here, DB is available (integration test context)
                $this->assertTrue(true);
            } catch (\InvalidArgumentException $e) {
                $this->fail("Category '{$category}' should be valid, got: " . $e->getMessage());
            } catch (\Exception $e) {
                // DB error is expected in unit test - validation passed
                $this->assertTrue(true);
            }
        }
    }

    public function testMethodSignatures(): void
    {
        $ref = new \ReflectionClass(CookieInventoryService::class);

        // getCookiesByCategory(string $category, ?int $tenantId = null)
        $method = $ref->getMethod('getCookiesByCategory');
        $this->assertTrue($method->isStatic());
        $params = $method->getParameters();
        $this->assertEquals('category', $params[0]->getName());
        $this->assertEquals('tenantId', $params[1]->getName());
        $this->assertTrue($params[1]->isOptional());
    }
}
