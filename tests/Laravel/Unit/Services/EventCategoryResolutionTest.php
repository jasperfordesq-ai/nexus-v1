<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\EventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Laravel\TestCase;

/**
 * Regression tests for the 2026-07-09 audit P2 finding: event category
 * resolution crossed tenants. `category_name` resolved via an unscoped
 * lookup (first match from ANY tenant) and a caller-supplied `category_id`
 * was stored with no ownership check, surfacing foreign category name/color
 * in event joins. resolveCategoryId() must be tenant-scoped in both branches.
 */
class EventCategoryResolutionTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    /** Secondary tenant seeded by TestCase::setUpTenantContext() — satisfies FK checks. */
    private const OTHER_TENANT_ID = 999;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    private function insertCategory(int $tenantId, string $name, string $type = 'events'): int
    {
        return (int) DB::table('categories')->insertGetId([
            'tenant_id'  => $tenantId,
            'name'       => $name,
            'slug'       => strtolower(str_replace(' ', '-', $name)) . '-' . $tenantId,
            'type'       => $type,
            'is_active'  => 1,
            'created_at' => now(),
        ]);
    }

    private function resolve(array $data): ?int
    {
        $method = new \ReflectionMethod(EventService::class, 'resolveCategoryId');
        $method->setAccessible(true);

        return $method->invoke(null, $data);
    }

    public function test_foreign_tenant_category_id_fails_closed(): void
    {
        $foreignId = $this->insertCategory(self::OTHER_TENANT_ID, 'Foreign Cat ' . uniqid());

        $this->expectException(ValidationException::class);
        $this->resolve(['category_id' => $foreignId]);
    }

    public function test_own_tenant_category_id_resolves_to_itself(): void
    {
        $ownId = $this->insertCategory(self::TENANT_ID, 'Own Cat ' . uniqid());

        $this->assertSame($ownId, $this->resolve(['category_id' => $ownId]));
    }

    public function test_category_name_never_matches_foreign_tenant(): void
    {
        $name = 'Gardening ' . uniqid();
        $this->insertCategory(self::OTHER_TENANT_ID, $name);

        $this->assertNull($this->resolve(['category_name' => $name]));
    }

    public function test_category_name_resolves_within_own_tenant(): void
    {
        $name = 'Gardening ' . uniqid();
        // Foreign tenant row inserted FIRST so an unscoped "first match" query
        // would pick it up — the fix must skip it and find the own-tenant row.
        $this->insertCategory(self::OTHER_TENANT_ID, $name);
        $ownId = $this->insertCategory(self::TENANT_ID, $name);

        $this->assertSame($ownId, $this->resolve(['category_name' => $name]));
    }

    public function test_nonexistent_category_id_fails_closed(): void
    {
        $this->expectException(ValidationException::class);
        $this->resolve(['category_id' => 999999999]);
    }
}
