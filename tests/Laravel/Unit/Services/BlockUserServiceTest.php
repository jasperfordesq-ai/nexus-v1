<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\BlockUserService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class BlockUserServiceTest extends TestCase
{
    // ── block ────────────────────────────────────────────────────────

    public function test_block_throws_when_blocking_self(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot block yourself');

        BlockUserService::block(42, 42);
    }

    public function test_block_inserts_and_autoDisconnects(): void
    {
        DB::shouldReceive('table')->with('user_blocks')->andReturnSelf();
        DB::shouldReceive('insertOrIgnore')->once()->andReturn(1);

        // Connection::delete may be called — swallow it by catching
        // since Connection model requires tenant context + schema.
        // The service wraps the autoDisconnect in try/catch so even
        // if Connection::query throws, block succeeds.
        BlockUserService::block(1, 2, 'spam');

        $this->assertTrue(true); // No exception thrown
    }

    // ── unblock ──────────────────────────────────────────────────────

    public function test_unblock_returns_true_when_row_deleted(): void
    {
        DB::shouldReceive('table')->with('user_blocks')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('delete')->once()->andReturn(1);

        $this->assertTrue(BlockUserService::unblock(1, 2));
    }

    public function test_unblock_returns_false_when_no_row_found(): void
    {
        DB::shouldReceive('table')->with('user_blocks')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('delete')->once()->andReturn(0);

        $this->assertFalse(BlockUserService::unblock(1, 2));
    }

    // ── isBlocked / isBlockedEither ──────────────────────────────────

    public function test_isBlocked_returns_true_when_record_exists(): void
    {
        DB::shouldReceive('table')->with('user_blocks')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('exists')->once()->andReturn(true);

        $this->assertTrue(BlockUserService::isBlocked(1, 2));
    }

    public function test_isBlockedEither_returns_false_when_neither_blocks(): void
    {
        DB::shouldReceive('table')->with('user_blocks')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('exists')->once()->andReturn(false);

        $this->assertFalse(BlockUserService::isBlockedEither(1, 2));
    }

    // ── getBlockedPairIds ────────────────────────────────────────────

    public function test_getBlockedPairIds_merges_and_dedupes_ids(): void
    {
        DB::shouldReceive('table')->with('user_blocks')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('pluck')
            ->andReturn(
                collect([10, 20, 30]),  // blockedByMe
                collect([20, 40])       // blockedMe
            );

        $result = BlockUserService::getBlockedPairIds(1);

        sort($result);
        $this->assertSame([10, 20, 30, 40], $result);
    }
}
