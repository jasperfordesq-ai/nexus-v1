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
 * SOC10 — Bulk check returns correct flags for saved/unsaved pairs.
 */
class IsSavedCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function test_is_saved_bulk_returns_correct_flags(): void
    {
        if (!Schema::hasTable('saved_items')) {
            $this->markTestSkipped('saved_items schema not present.');
        }

        $user = User::factory()->forTenant($this->testTenantId)->create();
        $svc = new SavedCollectionService();
        $col = $svc->createCollection($user->id, 'C');

        $svc->saveItem($user->id, $col->id, 'listing', 100);
        $svc->saveItem($user->id, $col->id, 'event', 200);
        $svc->forgetSavedCache($user->id);

        $result = $svc->isSavedBulk($user->id, [
            ['item_type' => 'listing', 'item_id' => 100],
            ['item_type' => 'listing', 'item_id' => 999],
            ['item_type' => 'event',   'item_id' => 200],
        ]);

        $this->assertTrue($result['listing:100']);
        $this->assertFalse($result['listing:999']);
        $this->assertTrue($result['event:200']);
    }
}
