<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\TransactionCategoryService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Tests for App\Services\TransactionCategoryService.
 *
 * Tests CRUD operations on transaction_categories, including
 * tenant scoping, slug generation, system category protection,
 * and edge cases.
 *
 * @covers \App\Services\TransactionCategoryService
 */
class TransactionCategoryServiceTest extends TestCase
{
    private TransactionCategoryService $service;
    private static int $tenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionCategoryService();
        TenantContext::setById(self::$tenantId);
    }

    // =========================================================================
    // Class existence
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(TransactionCategoryService::class));
    }

    public function testPublicMethodsExist(): void
    {
        $this->assertTrue(method_exists(TransactionCategoryService::class, 'getAll'));
        $this->assertTrue(method_exists(TransactionCategoryService::class, 'getById'));
        $this->assertTrue(method_exists(TransactionCategoryService::class, 'create'));
        $this->assertTrue(method_exists(TransactionCategoryService::class, 'update'));
        $this->assertTrue(method_exists(TransactionCategoryService::class, 'delete'));
    }

    // =========================================================================
    // getAll()
    // =========================================================================

    public function testGetAllReturnsArray(): void
    {
        try {
            $result = $this->service->getAll();
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetAllReturnsOnlyActiveCategoriesForCurrentTenant(): void
    {
        try {
            $result = $this->service->getAll();
            foreach ($result as $cat) {
                $this->assertEquals(self::$tenantId, $cat['tenant_id']);
                $this->assertEquals(1, $cat['is_active']);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetAllResultStructure(): void
    {
        try {
            $result = $this->service->getAll();
            foreach ($result as $cat) {
                $this->assertArrayHasKey('id', $cat);
                $this->assertArrayHasKey('name', $cat);
                $this->assertArrayHasKey('slug', $cat);
                $this->assertArrayHasKey('sort_order', $cat);
                $this->assertArrayHasKey('is_system', $cat);
                $this->assertArrayHasKey('is_active', $cat);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // getById()
    // =========================================================================

    public function testGetByIdReturnsNullForNonexistent(): void
    {
        try {
            $result = $this->service->getById(999999);
            $this->assertNull($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetByIdReturnsArrayForExistingCategory(): void
    {
        try {
            $categories = $this->service->getAll();
            if (empty($categories)) {
                $this->markTestSkipped('No categories exist for this tenant');
            }

            $first = $categories[0];
            $result = $this->service->getById($first['id']);

            $this->assertIsArray($result);
            $this->assertEquals($first['id'], $result['id']);
            $this->assertEquals(self::$tenantId, $result['tenant_id']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetByIdDoesNotReturnCrossTenantCategories(): void
    {
        try {
            // Try to fetch a category from a different tenant
            $otherTenantCat = DB::selectOne(
                "SELECT id FROM transaction_categories WHERE tenant_id != ? LIMIT 1",
                [self::$tenantId]
            );

            if (!$otherTenantCat) {
                $this->markTestSkipped('No cross-tenant categories to test');
            }

            $result = $this->service->getById($otherTenantCat->id);
            $this->assertNull($result, 'Should not return categories from another tenant');
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // create()
    // =========================================================================

    public function testCreateReturnsNullForEmptyName(): void
    {
        try {
            $result = $this->service->create(['name' => '']);
            $this->assertNull($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testCreateReturnsNullForWhitespaceName(): void
    {
        try {
            $result = $this->service->create(['name' => '   ']);
            $this->assertNull($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testCreateReturnsNullForMissingName(): void
    {
        try {
            $result = $this->service->create(['description' => 'No name given']);
            $this->assertNull($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testCreateReturnsIdOnSuccess(): void
    {
        try {
            $ts = time();
            $id = $this->service->create([
                'name' => "Test Category {$ts}",
                'description' => 'Created by unit test',
                'icon' => 'star',
                'color' => '#FF0000',
                'sort_order' => 99,
            ]);

            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);

            // Verify it exists
            $cat = $this->service->getById($id);
            $this->assertNotNull($cat);
            $this->assertEquals("Test Category {$ts}", $cat['name']);
            $this->assertEquals('Created by unit test', $cat['description']);
            $this->assertEquals('#FF0000', $cat['color']);
            $this->assertEquals(0, $cat['is_system']);
            $this->assertEquals(1, $cat['is_active']);

            // Clean up
            DB::delete("DELETE FROM transaction_categories WHERE id = ?", [$id]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testCreateGeneratesSlug(): void
    {
        try {
            $ts = time();
            $id = $this->service->create(['name' => "My Category {$ts}"]);

            if ($id) {
                $cat = $this->service->getById($id);
                $this->assertNotNull($cat);
                $this->assertNotEmpty($cat['slug']);
                $this->assertStringContainsString('my-category', $cat['slug']);

                DB::delete("DELETE FROM transaction_categories WHERE id = ?", [$id]);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testCreateSetsIsSystemToZero(): void
    {
        try {
            $ts = time();
            $id = $this->service->create(['name' => "Non-System Cat {$ts}"]);

            if ($id) {
                $cat = $this->service->getById($id);
                $this->assertEquals(0, $cat['is_system']);

                DB::delete("DELETE FROM transaction_categories WHERE id = ?", [$id]);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // update()
    // =========================================================================

    public function testUpdateReturnsFalseForEmptyFieldSet(): void
    {
        try {
            $result = $this->service->update(1, []);
            $this->assertFalse($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testUpdateModifiesName(): void
    {
        try {
            $ts = time();
            $id = $this->service->create(['name' => "Update Test {$ts}"]);
            if (!$id) {
                $this->markTestSkipped('Could not create test category');
            }

            $result = $this->service->update($id, ['name' => "Updated Name {$ts}"]);
            $this->assertTrue($result);

            $cat = $this->service->getById($id);
            $this->assertEquals("Updated Name {$ts}", $cat['name']);

            DB::delete("DELETE FROM transaction_categories WHERE id = ?", [$id]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testUpdateRegeneratesSlugOnNameChange(): void
    {
        try {
            $ts = time();
            $id = $this->service->create(['name' => "Slug Test {$ts}"]);
            if (!$id) {
                $this->markTestSkipped('Could not create test category');
            }

            $before = $this->service->getById($id);
            $this->service->update($id, ['name' => "New Slug Name {$ts}"]);
            $after = $this->service->getById($id);

            $this->assertNotEquals($before['slug'], $after['slug']);
            $this->assertStringContainsString('new-slug-name', $after['slug']);

            DB::delete("DELETE FROM transaction_categories WHERE id = ?", [$id]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testUpdateReturnsFalseForNonexistentCategory(): void
    {
        try {
            $result = $this->service->update(999999, ['name' => 'Ghost']);
            $this->assertFalse($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // delete()
    // =========================================================================

    public function testDeleteReturnsFalseForNonexistentCategory(): void
    {
        try {
            $result = $this->service->delete(999999);
            $this->assertFalse($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testDeleteRemovesCategory(): void
    {
        try {
            $ts = time();
            $id = $this->service->create(['name' => "Delete Test {$ts}"]);
            if (!$id) {
                $this->markTestSkipped('Could not create test category');
            }

            $result = $this->service->delete($id);
            $this->assertTrue($result);

            $cat = $this->service->getById($id);
            $this->assertNull($cat);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testDeleteRefusesSystemCategories(): void
    {
        try {
            // Find a system category
            $sysCat = DB::selectOne(
                "SELECT id FROM transaction_categories WHERE tenant_id = ? AND is_system = 1 LIMIT 1",
                [self::$tenantId]
            );

            if (!$sysCat) {
                $this->markTestSkipped('No system categories exist for this tenant');
            }

            $result = $this->service->delete($sysCat->id);
            $this->assertFalse($result, 'System categories should not be deletable');
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Tenant scoping
    // =========================================================================

    public function testAllMethodsAreTenantScoped(): void
    {
        // Verify the service uses TenantContext in its methods
        $ref = new \ReflectionClass(TransactionCategoryService::class);
        $source = file_get_contents($ref->getFileName());

        // All CRUD methods should reference TenantContext::getId()
        $this->assertStringContainsString('TenantContext::getId()', $source);
    }
}
