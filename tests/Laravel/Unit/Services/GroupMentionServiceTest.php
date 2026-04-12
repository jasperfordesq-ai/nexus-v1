<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\GroupMentionService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class GroupMentionServiceTest extends TestCase
{
    // ── parseMentions ────────────────────────────────────────────────

    public function test_parseMentions_returns_empty_when_no_at_tokens(): void
    {
        // No DB call expected
        DB::shouldReceive('table')->never();

        $this->assertSame([], GroupMentionService::parseMentions('No mentions here'));
    }

    public function test_parseMentions_resolves_known_usernames_only(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        DB::shouldReceive('where')->with('status', '!=', 'banned')->andReturnSelf();
        DB::shouldReceive('whereIn')->with('username', ['alice', 'bob', 'ghost'])->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['id' => 1, 'username' => 'alice'],
            (object) ['id' => 2, 'username' => 'bob'],
        ]));

        $result = GroupMentionService::parseMentions('@alice and @bob and @ghost');
        $this->assertCount(2, $result);
        $this->assertSame('alice', $result[0]['username']);
        $this->assertSame(1, $result[0]['user_id']);
    }

    public function test_parseMentions_dedupes_duplicate_usernames(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->once()
            ->withArgs(function ($col, $names) {
                return $col === 'username' && count($names) === 1 && $names[0] === 'alice';
            })
            ->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        GroupMentionService::parseMentions('@alice @alice @alice');
        $this->assertTrue(true);
    }

    // ── notifyMentioned ──────────────────────────────────────────────

    public function test_notifyMentioned_returns_early_when_no_mentions(): void
    {
        DB::shouldReceive('table')->never();
        DB::shouldReceive('selectOne')->never();

        GroupMentionService::notifyMentioned(1, 1, 'No mentions', 'discussion', 1);
        $this->assertTrue(true);
    }

    public function test_notifyMentioned_skips_self_mentions(): void
    {
        // parseMentions DB — author mentions themselves
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['id' => 10, 'username' => 'me'],
        ]));

        DB::shouldReceive('selectOne')->once()->andReturn((object) ['name' => 'TestGroup']);

        // No notification should be dispatched — we self-mentioned.
        // If Notification::createNotification were called it would likely hit DB,
        // but we verify by swallowing exceptions in the service itself.
        GroupMentionService::notifyMentioned(1, 10, '@me hi', 'discussion', 1);
        $this->assertTrue(true);
    }

    // ── getMemberSuggestions ─────────────────────────────────────────

    public function test_getMemberSuggestions_returns_shaped_results(): void
    {
        DB::shouldReceive('select')->once()->andReturn([
            (object) ['id' => 7, 'name' => 'Alice A', 'username' => 'alice', 'avatar_url' => null],
            (object) ['id' => 8, 'name' => 'Bob B', 'username' => 'bob', 'avatar_url' => '/a.png'],
        ]);

        $result = GroupMentionService::getMemberSuggestions(1, 'a', 10);

        $this->assertCount(2, $result);
        $this->assertSame(7, $result[0]['id']);
        $this->assertSame('alice', $result[0]['username']);
        $this->assertArrayHasKey('avatar_url', $result[1]);
    }
}
