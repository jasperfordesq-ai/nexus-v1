<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Social\Collections;

use App\Models\User;
use App\Services\Social\SavedCollectionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * SOC10 — CreateCollectionTest
 */
class CreateCollectionTest extends TestCase
{
    use DatabaseTransactions;

    private function schemaReady(): bool
    {
        return Schema::hasTable('saved_collections') && Schema::hasTable('saved_items');
    }

    public function test_create_collection_persists_with_defaults(): void
    {
        if (!$this->schemaReady()) {
            $this->markTestSkipped('saved_collections schema not present.');
        }
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $svc = new SavedCollectionService();
        $col = $svc->createCollection($user->id, 'Reading List');

        $this->assertNotNull($col->id);
        $this->assertSame('Reading List', $col->name);
        $this->assertSame(0, $col->items_count);
        $this->assertFalse((bool) $col->is_public);
        $this->assertSame($this->testTenantId, $col->tenant_id);
    }
}
