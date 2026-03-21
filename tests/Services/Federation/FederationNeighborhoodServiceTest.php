<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services\Federation;

use App\Tests\DatabaseTestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\FederationNeighborhoodService;

/**
 * FederationNeighborhoodService Tests
 *
 * Tests CRUD operations for federation neighborhoods (geographic groupings of tenants).
 */
class FederationNeighborhoodServiceTest extends DatabaseTestCase
{
    protected static ?int $tenantId = null;
    protected static ?int $testAdminId = null;
    protected static bool $dbAvailable = false;
    protected static array $createdNeighborhoodIds = [];

    private FederationNeighborhoodService $service;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tenantId = 2;

        try {
            TenantContext::setById(self::$tenantId);
        } catch (\Throwable $e) {
            return;
        }

        try {
            $timestamp = time() . rand(1000, 9999);

            Database::query(
                "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, role, is_approved, status, created_at)
                 VALUES (?, ?, ?, 'Neigh', 'Admin', 'Neigh Admin', 'admin', 1, 'active', NOW())",
                [self::$tenantId, "neigh_admin_{$timestamp}@test.com", "neigh_admin_{$timestamp}"]
            );
            self::$testAdminId = (int) Database::getInstance()->lastInsertId();

            self::$dbAvailable = true;
        } catch (\Throwable $e) {
            error_log("FederationNeighborhoodServiceTest setup failed: " . $e->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$dbAvailable) {
            return;
        }

        try {
            foreach (self::$createdNeighborhoodIds as $id) {
                Database::query("DELETE FROM federation_neighborhood_tenants WHERE neighborhood_id = ?", [$id]);
                Database::query("DELETE FROM federation_neighborhoods WHERE id = ?", [$id]);
            }
            if (self::$testAdminId) {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testAdminId]);
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available for integration test');
        }

        TenantContext::setById(self::$tenantId);
        $this->service = new FederationNeighborhoodService();
    }

    // ==========================================
    // create Tests
    // ==========================================

    public function testCreateNeighborhoodSuccess(): void
    {
        $timestamp = time() . rand(1000, 9999);
        $id = $this->service->create(
            "Test Neighborhood {$timestamp}",
            'A test neighborhood for PHPUnit',
            'Test Region',
            self::$testAdminId
        );

        $this->assertNotNull($id);
        $this->assertIsInt($id);
        self::$createdNeighborhoodIds[] = $id;
    }

    public function testCreateNeighborhoodWithEmptyNameFails(): void
    {
        $id = $this->service->create('');

        $this->assertNull($id);
        $this->assertNotEmpty($this->service->getErrors());
        $this->assertStringContainsString('required', $this->service->getErrors()[0]);
    }

    public function testCreateNeighborhoodWithWhitespaceOnlyNameFails(): void
    {
        $id = $this->service->create('   ');

        $this->assertNull($id);
        $this->assertNotEmpty($this->service->getErrors());
    }

    public function testCreateNeighborhoodWithNullOptionalFields(): void
    {
        $timestamp = time() . rand(1000, 9999);
        $id = $this->service->create("Minimal Neighborhood {$timestamp}");

        $this->assertNotNull($id);
        self::$createdNeighborhoodIds[] = $id;
    }

    public function testCreateStaticProxy(): void
    {
        $timestamp = time() . rand(1000, 9999);
        $id = FederationNeighborhoodService::createStatic(
            "Static Neighborhood {$timestamp}",
            'Created via static proxy',
            'Static Region',
            self::$testAdminId
        );

        $this->assertNotNull($id);
        self::$createdNeighborhoodIds[] = $id;
    }

    // ==========================================
    // getById Tests
    // ==========================================

    public function testGetByIdSuccess(): void
    {
        $timestamp = time() . rand(1000, 9999);
        $id = $this->service->create(
            "Fetch Test Neighborhood {$timestamp}",
            'For getById test',
            'Fetch Region',
            self::$testAdminId
        );
        $this->assertNotNull($id);
        self::$createdNeighborhoodIds[] = $id;

        $neighborhood = $this->service->getById($id);

        $this->assertNotNull($neighborhood);
        $this->assertEquals($id, $neighborhood['id']);
        $this->assertStringContainsString('Fetch Test Neighborhood', $neighborhood['name']);
        $this->assertEquals('For getById test', $neighborhood['description']);
        $this->assertEquals('Fetch Region', $neighborhood['region']);
        $this->assertEquals(self::$testAdminId, $neighborhood['created_by']);
        $this->assertArrayHasKey('tenants', $neighborhood);
        $this->assertIsArray($neighborhood['tenants']);
        $this->assertArrayHasKey('created_at', $neighborhood);
    }

    public function testGetByIdNonExistentReturnsNull(): void
    {
        $result = $this->service->getById(999999);

        $this->assertNull($result);
    }

    // ==========================================
    // update Tests
    // ==========================================

    public function testUpdateNameSuccess(): void
    {
        $timestamp = time() . rand(1000, 9999);
        $id = $this->service->create("Original Name {$timestamp}", 'Desc', 'Region');
        $this->assertNotNull($id);
        self::$createdNeighborhoodIds[] = $id;

        $result = $this->service->update($id, ['name' => "Updated Name {$timestamp}"]);

        $this->assertTrue($result);

        $neighborhood = $this->service->getById($id);
        $this->assertStringContainsString('Updated Name', $neighborhood['name']);
    }

    public function testUpdateDescriptionSuccess(): void
    {
        $timestamp = time() . rand(1000, 9999);
        $id = $this->service->create("Desc Update Test {$timestamp}");
        $this->assertNotNull($id);
        self::$createdNeighborhoodIds[] = $id;

        $result = $this->service->update($id, ['description' => 'New description']);

        $this->assertTrue($result);

        $neighborhood = $this->service->getById($id);
        $this->assertEquals('New description', $neighborhood['description']);
    }

    public function testUpdateWithEmptyNameFails(): void
    {
        $timestamp = time() . rand(1000, 9999);
        $id = $this->service->create("Empty Name Test {$timestamp}");
        $this->assertNotNull($id);
        self::$createdNeighborhoodIds[] = $id;

        $result = $this->service->update($id, ['name' => '']);

        $this->assertFalse($result);
        $this->assertNotEmpty($this->service->getErrors());
    }

    public function testUpdateWithEmptyDataReturnsTrue(): void
    {
        $timestamp = time() . rand(1000, 9999);
        $id = $this->service->create("No Update Test {$timestamp}");
        $this->assertNotNull($id);
        self::$createdNeighborhoodIds[] = $id;

        $result = $this->service->update($id, []);

        $this->assertTrue($result);
    }

    public function testUpdateNonExistentNeighborhoodFails(): void
    {
        $result = $this->service->update(999999, ['name' => 'Does Not Exist']);

        $this->assertFalse($result);
        $this->assertNotEmpty($this->service->getErrors());
    }

    public function testUpdateMultipleFieldsAtOnce(): void
    {
        $timestamp = time() . rand(1000, 9999);
        $id = $this->service->create("Multi Update {$timestamp}");
        $this->assertNotNull($id);
        self::$createdNeighborhoodIds[] = $id;

        $result = $this->service->update($id, [
            'name' => "Multi Updated {$timestamp}",
            'description' => 'Updated desc',
            'region' => 'Updated Region',
        ]);

        $this->assertTrue($result);

        $neighborhood = $this->service->getById($id);
        $this->assertStringContainsString('Multi Updated', $neighborhood['name']);
        $this->assertEquals('Updated desc', $neighborhood['description']);
        $this->assertEquals('Updated Region', $neighborhood['region']);
    }

    // ==========================================
    // delete Tests
    // ==========================================

    public function testDeleteSuccess(): void
    {
        $timestamp = time() . rand(1000, 9999);
        $id = $this->service->create("Delete Test {$timestamp}");
        $this->assertNotNull($id);

        $result = $this->service->delete($id);

        $this->assertTrue($result);

        $neighborhood = $this->service->getById($id);
        $this->assertNull($neighborhood);
    }

    public function testDeleteNonExistentFails(): void
    {
        $result = $this->service->delete(999999);

        $this->assertFalse($result);
        $this->assertNotEmpty($this->service->getErrors());
    }

    // ==========================================
    // listAllStatic Tests
    // ==========================================

    public function testListAllStaticReturnsArray(): void
    {
        $result = FederationNeighborhoodService::listAllStatic();

        $this->assertIsArray($result);
    }

    public function testListAllStaticResultStructure(): void
    {
        $timestamp = time() . rand(1000, 9999);
        $id = $this->service->create(
            "List Test Neighborhood {$timestamp}",
            'For listing test',
            'List Region',
            self::$testAdminId
        );
        $this->assertNotNull($id);
        self::$createdNeighborhoodIds[] = $id;

        $all = FederationNeighborhoodService::listAllStatic();
        $this->assertNotEmpty($all);

        $found = false;
        foreach ($all as $item) {
            if ($item['id'] === $id) {
                $found = true;
                $this->assertArrayHasKey('name', $item);
                $this->assertArrayHasKey('description', $item);
                $this->assertArrayHasKey('region', $item);
                $this->assertArrayHasKey('tenant_count', $item);
                $this->assertArrayHasKey('tenants', $item);
                $this->assertArrayHasKey('created_by', $item);
                $this->assertArrayHasKey('created_by_name', $item);
                $this->assertIsArray($item['tenants']);
                $this->assertIsInt($item['tenant_count']);
                break;
            }
        }
        $this->assertTrue($found, 'Created neighborhood should appear in listAllStatic');
    }

    // ==========================================
    // getErrors Tests
    // ==========================================

    public function testGetErrorsInitiallyEmpty(): void
    {
        $service = new FederationNeighborhoodService();
        $this->assertEmpty($service->getErrors());
    }

    public function testGetErrorsResetBetweenOperations(): void
    {
        // Trigger an error
        $this->service->create('');
        $this->assertNotEmpty($this->service->getErrors());

        // Successful operation should reset errors
        $timestamp = time() . rand(1000, 9999);
        $id = $this->service->create("Reset Errors Test {$timestamp}");
        $this->assertNotNull($id);
        self::$createdNeighborhoodIds[] = $id;
    }
}
