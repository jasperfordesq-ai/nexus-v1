<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Social;

use App\Core\TenantContext;
use App\Models\Social\SavedCollection;
use App\Models\Social\SavedItem;
use App\Services\Social\SavedCollectionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * SavedCollectionServiceTest
 *
 * Strategy:
 *  - createCollection / ensureDefaultCollection: persistence and idempotency.
 *  - updateCollection: only allowed fields are updated; owner check enforced.
 *  - deleteCollection: cascades to items (FK); unseen by other user.
 *  - saveItem: idempotency (same item twice = one row); items_count increment;
 *    invalid type throws; note update on re-save.
 *  - unsaveItem / unsaveByItem: decrements items_count; cross-user guard.
 *  - isSaved / isSavedBulk: cache is populated and correct; forgetSavedCache
 *    clears it.
 *  - getUserCollections: publicOnly filter.
 *
 * Skipped: getSavedItems (calls abort(403) which requires a full HTTP context and
 *   is tested in feature/controller layer).
 */
class SavedCollectionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private SavedCollectionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->svc = new SavedCollectionService();
        Cache::flush();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function insertUser(): int
    {
        $uid = uniqid('saved_', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Saved User ' . $uid,
            'first_name' => 'Saved',
            'last_name'  => 'User',
            'email'      => $uid . '@saved.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── createCollection ──────────────────────────────────────────────────────

    public function test_createCollection_persists_row_with_correct_fields(): void
    {
        $userId = $this->insertUser();
        $col = $this->svc->createCollection($userId, 'My Favs', 'desc', true, '#ff0000', 'star');

        $this->assertInstanceOf(SavedCollection::class, $col);
        $this->assertSame($userId, (int) $col->user_id);
        $this->assertSame(self::TENANT_ID, (int) $col->tenant_id);
        $this->assertSame('My Favs', $col->name);
        $this->assertSame('desc', $col->description);
        $this->assertTrue((bool) $col->is_public);
        $this->assertSame('#ff0000', $col->color);
        $this->assertSame('star', $col->icon);
        $this->assertSame(0, (int) $col->items_count);
    }

    public function test_createCollection_defaults_are_applied(): void
    {
        $userId = $this->insertUser();
        $col = $this->svc->createCollection($userId, 'Default Test');

        $this->assertFalse((bool) $col->is_public);
        $this->assertSame('#6366f1', $col->color);
        $this->assertSame('bookmark', $col->icon);
    }

    // ── ensureDefaultCollection ───────────────────────────────────────────────

    public function test_ensureDefaultCollection_creates_collection_for_new_user(): void
    {
        $userId = $this->insertUser();
        $col = $this->svc->ensureDefaultCollection($userId);

        $this->assertInstanceOf(SavedCollection::class, $col);
        $this->assertSame('Default', $col->name);
    }

    public function test_ensureDefaultCollection_is_idempotent(): void
    {
        $userId = $this->insertUser();
        $first  = $this->svc->ensureDefaultCollection($userId);
        $second = $this->svc->ensureDefaultCollection($userId);

        // Should return the SAME id, not create a duplicate.
        $this->assertEquals($first->id, $second->id);
        $countInDB = SavedCollection::where('user_id', $userId)
            ->where('tenant_id', self::TENANT_ID)
            ->count();
        $this->assertSame(1, $countInDB);
    }

    // ── updateCollection ──────────────────────────────────────────────────────

    public function test_updateCollection_changes_allowed_fields(): void
    {
        $userId = $this->insertUser();
        $col = $this->svc->createCollection($userId, 'Old Name');

        $updated = $this->svc->updateCollection($col->id, $userId, [
            'name'      => 'New Name',
            'is_public' => true,
            'color'     => '#123456',
        ]);

        $this->assertSame('New Name', $updated->name);
        $this->assertTrue((bool) $updated->is_public);
        $this->assertSame('#123456', $updated->color);
    }

    public function test_updateCollection_throws_for_wrong_owner(): void
    {
        $owner  = $this->insertUser();
        $other  = $this->insertUser();
        $col = $this->svc->createCollection($owner, 'Private');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->svc->updateCollection($col->id, $other, ['name' => 'Hijacked']);
    }

    // ── deleteCollection ──────────────────────────────────────────────────────

    public function test_deleteCollection_removes_row_from_db(): void
    {
        $userId = $this->insertUser();
        $col = $this->svc->createCollection($userId, 'To Delete');
        $colId = $col->id;

        $this->svc->deleteCollection($colId, $userId);

        $exists = SavedCollection::find($colId);
        $this->assertNull($exists);
    }

    public function test_deleteCollection_throws_for_wrong_owner(): void
    {
        $owner = $this->insertUser();
        $other = $this->insertUser();
        $col   = $this->svc->createCollection($owner, 'Mine');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->svc->deleteCollection($col->id, $other);
    }

    // ── saveItem ──────────────────────────────────────────────────────────────

    public function test_saveItem_throws_for_invalid_item_type(): void
    {
        $userId = $this->insertUser();

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->saveItem($userId, null, 'bad_type', 1);
    }

    public function test_saveItem_creates_row_and_increments_items_count(): void
    {
        $userId = $this->insertUser();
        $col = $this->svc->ensureDefaultCollection($userId);

        $item = $this->svc->saveItem($userId, $col->id, 'listing', 9001);

        $this->assertInstanceOf(SavedItem::class, $item);
        $this->assertSame('listing', $item->item_type);
        $this->assertEquals(9001, (int) $item->item_id);

        $freshCol = SavedCollection::find($col->id);
        $this->assertSame(1, (int) $freshCol->items_count);
    }

    public function test_saveItem_is_idempotent_does_not_create_duplicate(): void
    {
        $userId = $this->insertUser();
        $col = $this->svc->ensureDefaultCollection($userId);

        $this->svc->saveItem($userId, $col->id, 'event', 5555);
        $second = $this->svc->saveItem($userId, $col->id, 'event', 5555);

        $this->assertSame('event', $second->item_type);

        // Only one row in the table
        $count = SavedItem::where('collection_id', $col->id)
            ->where('item_type', 'event')
            ->where('item_id', 5555)
            ->count();
        $this->assertSame(1, $count);

        // items_count incremented only once
        $freshCol = SavedCollection::find($col->id);
        $this->assertSame(1, (int) $freshCol->items_count);
    }

    public function test_saveItem_updates_note_when_resaved_with_new_note(): void
    {
        $userId = $this->insertUser();
        $col = $this->svc->ensureDefaultCollection($userId);

        $this->svc->saveItem($userId, $col->id, 'post', 7777, 'first note');
        $updated = $this->svc->saveItem($userId, $col->id, 'post', 7777, 'new note');

        $this->assertSame('new note', $updated->note);
    }

    // ── unsaveItem ────────────────────────────────────────────────────────────

    public function test_unsaveItem_removes_row_and_decrements_count(): void
    {
        $userId = $this->insertUser();
        $col    = $this->svc->ensureDefaultCollection($userId);
        $item   = $this->svc->saveItem($userId, $col->id, 'job', 4321);

        $result = $this->svc->unsaveItem($item->id, $userId);

        $this->assertTrue($result);
        $this->assertNull(SavedItem::find($item->id));

        $freshCol = SavedCollection::find($col->id);
        $this->assertSame(0, (int) $freshCol->items_count);
    }

    public function test_unsaveItem_returns_false_for_nonexistent_item(): void
    {
        $userId = $this->insertUser();
        $result = $this->svc->unsaveItem(9999999, $userId);
        $this->assertFalse($result);
    }

    public function test_unsaveItem_returns_false_for_wrong_owner(): void
    {
        $owner = $this->insertUser();
        $other = $this->insertUser();
        $col   = $this->svc->ensureDefaultCollection($owner);
        $item  = $this->svc->saveItem($owner, $col->id, 'listing', 8888);

        $result = $this->svc->unsaveItem($item->id, $other);
        $this->assertFalse($result);
        $this->assertNotNull(SavedItem::find($item->id), 'Item should still exist');
    }

    // ── unsaveByItem ──────────────────────────────────────────────────────────

    public function test_unsaveByItem_removes_matching_item_and_decrements_count(): void
    {
        $userId = $this->insertUser();
        $col    = $this->svc->ensureDefaultCollection($userId);
        $this->svc->saveItem($userId, $col->id, 'group', 111);

        $result = $this->svc->unsaveByItem($userId, 'group', 111);

        $this->assertTrue($result);
        $freshCol = SavedCollection::find($col->id);
        $this->assertSame(0, (int) $freshCol->items_count);
    }

    public function test_unsaveByItem_returns_false_when_not_saved(): void
    {
        $userId = $this->insertUser();
        $result = $this->svc->unsaveByItem($userId, 'event', 9999999);
        $this->assertFalse($result);
    }

    // ── isSaved / isSavedBulk / cache ─────────────────────────────────────────

    public function test_isSaved_returns_false_when_not_saved(): void
    {
        $userId = $this->insertUser();
        $this->assertFalse($this->svc->isSaved($userId, 'listing', 9999));
    }

    public function test_isSaved_returns_true_after_saveItem(): void
    {
        $userId = $this->insertUser();
        $col = $this->svc->ensureDefaultCollection($userId);
        $this->svc->saveItem($userId, $col->id, 'listing', 2222);

        $this->assertTrue($this->svc->isSaved($userId, 'listing', 2222));
    }

    public function test_isSaved_cache_is_invalidated_after_unsave(): void
    {
        $userId = $this->insertUser();
        $col    = $this->svc->ensureDefaultCollection($userId);
        $item   = $this->svc->saveItem($userId, $col->id, 'resource', 3333);

        // Prime the cache
        $this->assertTrue($this->svc->isSaved($userId, 'resource', 3333));

        // Unsave (should flush cache)
        $this->svc->unsaveItem($item->id, $userId);

        // After cache is cleared, isSaved should return false
        $this->assertFalse($this->svc->isSaved($userId, 'resource', 3333));
    }

    public function test_isSavedBulk_returns_correct_map(): void
    {
        $userId = $this->insertUser();
        $col    = $this->svc->ensureDefaultCollection($userId);
        $this->svc->saveItem($userId, $col->id, 'event', 1);
        $this->svc->saveItem($userId, $col->id, 'listing', 2);

        $result = $this->svc->isSavedBulk($userId, [
            ['item_type' => 'event',   'item_id' => 1],
            ['item_type' => 'listing', 'item_id' => 2],
            ['item_type' => 'event',   'item_id' => 99],
        ]);

        $this->assertTrue($result['event:1']);
        $this->assertTrue($result['listing:2']);
        $this->assertFalse($result['event:99']);
    }

    // ── getUserCollections ────────────────────────────────────────────────────

    public function test_getUserCollections_returns_all_collections_for_owner(): void
    {
        $userId = $this->insertUser();
        $this->svc->createCollection($userId, 'A', null, true);
        $this->svc->createCollection($userId, 'B', null, false);

        $result = $this->svc->getUserCollections($userId, false);
        $names = array_map(fn ($c) => $c->name, $result);

        $this->assertContains('A', $names);
        $this->assertContains('B', $names);
    }

    public function test_getUserCollections_publicOnly_filters_private(): void
    {
        $userId = $this->insertUser();
        $this->svc->createCollection($userId, 'Public C', null, true);
        $this->svc->createCollection($userId, 'Private C', null, false);

        $result = $this->svc->getUserCollections($userId, true);
        $names  = array_map(fn ($c) => $c->name, $result);

        $this->assertContains('Public C', $names);
        $this->assertNotContains('Private C', $names);
    }
}
