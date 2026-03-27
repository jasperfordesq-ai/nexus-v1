<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\BadgeCollectionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Mockery;

class BadgeCollectionServiceTest extends TestCase
{
    /**
     * Build a mock DB connection and set it as the Eloquent model resolver.
     *
     * @param  list<list<object>>  $selectSequence  Ordered result sets for each select() call
     */
    private function mockEloquentConnection(array $selectSequence = []): void
    {
        $mockConnection = Mockery::mock(\Illuminate\Database\MySqlConnection::class)->makePartial();
        $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
        $mockConnection->shouldReceive('getDatabaseName')->andReturn('nexus_test');
        $mockConnection->shouldReceive('getName')->andReturn('mysql');
        $mockConnection->shouldReceive('getConfig')->andReturn(null);

        $mockGrammar = Mockery::mock(\Illuminate\Database\Query\Grammars\MySqlGrammar::class)->makePartial();
        $mockGrammar->shouldReceive('getDateFormat')->andReturn('Y-m-d H:i:s');
        // Wire up connection so wrapTable() can call $this->connection->getTablePrefix()
        $ref = new \ReflectionProperty(\Illuminate\Database\Grammar::class, 'connection');
        $ref->setAccessible(true);
        $ref->setValue($mockGrammar, $mockConnection);

        $mockProcessor = Mockery::mock(\Illuminate\Database\Query\Processors\MySqlProcessor::class)->makePartial();

        $mockConnection->shouldReceive('getQueryGrammar')->andReturn($mockGrammar);
        $mockConnection->shouldReceive('getPostProcessor')->andReturn($mockProcessor);

        if (empty($selectSequence)) {
            $mockConnection->shouldReceive('select')->andReturn([]);
        } elseif (count($selectSequence) === 1) {
            $mockConnection->shouldReceive('select')->andReturn($selectSequence[0]);
        } else {
            $mockConnection->shouldReceive('select')->andReturn(...$selectSequence);
        }

        $mockConnection->shouldReceive('selectOne')->andReturn(null);
        $mockConnection->shouldReceive('insert')->andReturn(true);
        $mockConnection->shouldReceive('update')->andReturn(1);
        $mockConnection->shouldReceive('delete')->andReturn(1);
        $mockConnection->shouldReceive('affectingStatement')->andReturn(1);
        $mockConnection->shouldReceive('statement')->andReturn(true);
        $mockConnection->shouldReceive('beginTransaction')->andReturn(null);
        $mockConnection->shouldReceive('commit')->andReturn(null);
        $mockConnection->shouldReceive('rollBack')->andReturn(null);
        $mockConnection->shouldReceive('transactionLevel')->andReturn(0);

        $mockResolver = Mockery::mock(\Illuminate\Database\DatabaseManager::class);
        $mockResolver->shouldReceive('connection')->andReturn($mockConnection);
        Model::setConnectionResolver($mockResolver);

        DB::shouldReceive('connection')->andReturn($mockConnection);
    }

    protected function tearDown(): void
    {
        Model::setConnectionResolver($this->app['db']);
        parent::tearDown();
    }

    // ── getCollectionsWithProgress ──────────────────────────────────────

    public function test_getCollectionsWithProgress_returns_empty_when_no_collections(): void
    {
        $this->mockEloquentConnection([[]]);

        $result = BadgeCollectionService::getCollectionsWithProgress(1);

        $this->assertSame([], $result);
    }

    public function test_getCollectionsWithProgress_returns_collections_with_progress(): void
    {
        $this->mockEloquentConnection([
            // 1st: BadgeCollection::query()->orderBy('display_order')->get()
            [
                (object) ['id' => 1, 'tenant_id' => 2, 'collection_key' => 'starter',
                    'name' => 'Starter Set', 'description' => 'First badges',
                    'icon' => null, 'bonus_xp' => 100, 'bonus_badge_key' => null,
                    'display_order' => 0, 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
            ],
            // 2nd: BadgeCollectionItem::whereIn('collection_id', [1])->...->get()
            [
                (object) ['id' => 1, 'collection_id' => 1, 'badge_key' => 'vol_1h', 'display_order' => 0],
            ],
            // 3rd: UserBadge::where('user_id', 1)->pluck('badge_key')
            [
                (object) ['badge_key' => 'vol_1h'],
            ],
            // 4th: UserCollectionCompletion::where('user_id', 1)->pluck('collection_id')
            [],
        ]);

        $result = BadgeCollectionService::getCollectionsWithProgress(1);

        $this->assertCount(1, $result);
        $this->assertSame('Starter Set', $result[0]['name']);
        $this->assertSame(1, $result[0]['earned_count']);
        $this->assertSame(1, $result[0]['total_count']);
        $this->assertSame(100.0, $result[0]['progress_percent']);
        $this->assertTrue($result[0]['is_completed']);
        $this->assertFalse($result[0]['bonus_claimed']);
    }

    public function test_getCollectionsWithProgress_calculates_progress_percentage(): void
    {
        $this->mockEloquentConnection([
            // Collections
            [
                (object) ['id' => 1, 'tenant_id' => 2, 'collection_key' => 'explorer',
                    'name' => 'Explorer Set', 'description' => 'Explore more',
                    'icon' => null, 'bonus_xp' => 200, 'bonus_badge_key' => null,
                    'display_order' => 0, 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
            ],
            // Items: 2 badges
            [
                (object) ['id' => 1, 'collection_id' => 1, 'badge_key' => 'vol_1h', 'display_order' => 0],
                (object) ['id' => 2, 'collection_id' => 1, 'badge_key' => 'vol_10h', 'display_order' => 1],
            ],
            // User earned only vol_1h
            [
                (object) ['badge_key' => 'vol_1h'],
            ],
            // No completions
            [],
        ]);

        $result = BadgeCollectionService::getCollectionsWithProgress(1);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['earned_count']);
        $this->assertSame(2, $result[0]['total_count']);
        $this->assertSame(50.0, $result[0]['progress_percent']);
        $this->assertFalse($result[0]['is_completed']);
    }

    // ── checkCollectionCompletion ──────────────────────────────────────

    public function test_checkCollectionCompletion_returns_empty_when_no_collections(): void
    {
        $this->mockEloquentConnection([[]]);

        $result = BadgeCollectionService::checkCollectionCompletion(1);

        $this->assertSame([], $result);
    }

    public function test_checkCollectionCompletion_skips_already_completed(): void
    {
        $this->mockEloquentConnection([
            // Collections
            [
                (object) ['id' => 1, 'tenant_id' => 2, 'collection_key' => 'starter',
                    'name' => 'Starter Set', 'description' => '', 'icon' => null,
                    'bonus_xp' => 100, 'bonus_badge_key' => null, 'display_order' => 0,
                    'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
            ],
            // UserBadge
            [(object) ['badge_key' => 'vol_1h']],
            // Already completed — collection_id 1 present
            [(object) ['collection_id' => 1]],
            // BadgeCollectionItem
            [(object) ['id' => 1, 'collection_id' => 1, 'badge_key' => 'vol_1h', 'display_order' => 0]],
        ]);

        $result = BadgeCollectionService::checkCollectionCompletion(1);

        $this->assertEmpty($result);
    }

    // ── addBadgeToCollection ──────────────────────────────────────────

    public function test_addBadgeToCollection_creates_item(): void
    {
        $this->mockEloquentConnection([[]]);

        BadgeCollectionService::addBadgeToCollection(1, 'vol_1h', 0);

        $this->assertTrue(true);
    }

    // ── removeBadgeFromCollection ─────────────────────────────────────

    public function test_removeBadgeFromCollection_does_nothing_when_collection_not_found(): void
    {
        $this->mockEloquentConnection([[]]);

        BadgeCollectionService::removeBadgeFromCollection(999, 'some_badge');

        $this->assertTrue(true);
    }

    public function test_removeBadgeFromCollection_deletes_item_when_collection_exists(): void
    {
        $this->mockEloquentConnection([
            [
                (object) ['id' => 1, 'tenant_id' => 2, 'collection_key' => 'starter',
                    'name' => 'Starter', 'description' => '', 'icon' => null,
                    'bonus_xp' => 100, 'bonus_badge_key' => null, 'display_order' => 0,
                    'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
            ],
        ]);

        BadgeCollectionService::removeBadgeFromCollection(1, 'old_badge');

        $this->assertTrue(true);
    }

    // ── Contract / structure tests ────────────────────────────────────

    public function test_create_has_correct_signature(): void
    {
        $ref = new \ReflectionMethod(BadgeCollectionService::class, 'create');
        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());

        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('data', $params[0]->getName());
        $this->assertSame('array', $params[0]->getType()->getName());

        $returnType = $ref->getReturnType();
        $this->assertTrue($returnType->allowsNull());
        $this->assertSame('int', $returnType->getName());
    }

    public function test_constructor_accepts_dependencies(): void
    {
        $ref = new \ReflectionClass(BadgeCollectionService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('collection', $params[0]->getName());
        $this->assertSame('gamificationService', $params[1]->getName());
    }

    public function test_awardCollectionCompletion_is_private(): void
    {
        $ref = new \ReflectionMethod(BadgeCollectionService::class, 'awardCollectionCompletion');
        $this->assertTrue($ref->isPrivate());
        $this->assertTrue($ref->isStatic());
    }

    public function test_getBadgeDefinitionsMap_is_private(): void
    {
        $ref = new \ReflectionMethod(BadgeCollectionService::class, 'getBadgeDefinitionsMap');
        $this->assertTrue($ref->isPrivate());
        $this->assertTrue($ref->isStatic());
    }
}
