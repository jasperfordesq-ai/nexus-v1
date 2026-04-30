<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Social\Collections;

use App\Models\Social\SavedItem;
use App\Models\User;
use App\Services\Social\SavedCollectionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * SOC10 — Saving the same item twice should NOT create a duplicate row,
 * and the item count on the collection should remain at 1.
 */
class SaveItemIdempotentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_double_save_is_idempotent(): void
    {
        if (!Schema::hasTable('saved_collections') || !Schema::hasTable('saved_items')) {
            $this->markTestSkipped('saved_collections schema not present.');
        }

        $user = User::factory()->forTenant($this->testTenantId)->create();
        $svc = new SavedCollectionService();
        $col = $svc->createCollection($user->id, 'Faves');

        $svc->saveItem($user->id, $col->id, 'listing', 1234);
        $svc->saveItem($user->id, $col->id, 'listing', 1234);

        $count = SavedItem::where('collection_id', $col->id)
            ->where('item_type', 'listing')
            ->where('item_id', 1234)
            ->count();
        $this->assertSame(1, $count);

        $col->refresh();
        $this->assertSame(1, (int) $col->items_count);
    }
}
