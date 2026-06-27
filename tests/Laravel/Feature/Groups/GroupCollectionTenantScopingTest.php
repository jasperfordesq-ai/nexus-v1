<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Groups;

use App\Core\TenantContext;
use App\Services\GroupCollectionService;
use App\Services\GroupCustomFieldService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: deleting a group collection / custom field must not delete ANOTHER
 * tenant's child rows.
 *
 * Both services deleted the child rows (group_collection_items /
 * group_custom_field_values — neither has a tenant_id column) scoped only by the
 * parent id, BEFORE the tenant-scoped parent delete. So an admin of tenant A who
 * passed a tenant-B (e.g. enumerated) parent id would wipe B's child rows while
 * the parent delete silently no-opped. The services now verify the parent belongs
 * to the current tenant before touching any child rows.
 */
class GroupCollectionTenantScopingTest extends TestCase
{
    use DatabaseTransactions;

    private const VICTIM_TENANT = 999777;

    public function test_cross_tenant_collection_delete_does_not_remove_victim_items(): void
    {
        $attacker = $this->testTenantId;

        $victimCollectionId = (int) DB::table('group_collections')->insertGetId([
            'tenant_id'  => self::VICTIM_TENANT,
            'name'       => 'Victim collection',
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('group_collection_items')->insert([
            'collection_id' => $victimCollectionId,
            'group_id'      => 4242,
            'sort_order'    => 0,
        ]);

        // Attacker (a different tenant) tries to delete the victim's collection.
        $result = TenantContext::runForTenant($attacker, fn () => GroupCollectionService::delete($victimCollectionId));

        $this->assertFalse($result, 'cross-tenant delete must report failure');
        $this->assertSame(
            1,
            DB::table('group_collection_items')->where('collection_id', $victimCollectionId)->count(),
            'the victim tenant\'s collection items must NOT be deleted'
        );
        $this->assertTrue(
            DB::table('group_collections')->where('id', $victimCollectionId)->exists(),
            'the victim tenant\'s collection must still exist'
        );
    }

    public function test_same_tenant_collection_delete_still_works(): void
    {
        $tid = $this->testTenantId;

        $collectionId = (int) DB::table('group_collections')->insertGetId([
            'tenant_id'  => $tid,
            'name'       => 'Own collection',
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('group_collection_items')->insert([
            'collection_id' => $collectionId,
            'group_id'      => 4243,
            'sort_order'    => 0,
        ]);

        $result = TenantContext::runForTenant($tid, fn () => GroupCollectionService::delete($collectionId));

        $this->assertTrue($result, 'same-tenant delete should succeed');
        $this->assertSame(0, DB::table('group_collection_items')->where('collection_id', $collectionId)->count(), 'own items removed');
        $this->assertFalse(DB::table('group_collections')->where('id', $collectionId)->exists(), 'own collection removed');
    }

    public function test_cross_tenant_custom_field_delete_does_not_remove_victim_values(): void
    {
        $attacker = $this->testTenantId;

        $victimFieldId = (int) DB::table('group_custom_fields')->insertGetId([
            'tenant_id'  => self::VICTIM_TENANT,
            'field_name' => 'Victim field',
            'field_key'  => 'victim_field',
            'created_at' => now(),
        ]);
        DB::table('group_custom_field_values')->insert([
            'group_id'    => 4242,
            'field_id'    => $victimFieldId,
            'field_value' => 'secret',
        ]);

        $result = TenantContext::runForTenant($attacker, fn () => GroupCustomFieldService::deleteField($victimFieldId));

        $this->assertFalse($result, 'cross-tenant field delete must report failure');
        $this->assertSame(
            1,
            DB::table('group_custom_field_values')->where('field_id', $victimFieldId)->count(),
            'the victim tenant\'s custom field values must NOT be deleted'
        );
    }
}
