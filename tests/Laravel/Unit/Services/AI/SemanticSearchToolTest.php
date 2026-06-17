<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\AI\Tools\SemanticSearchTool;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class SemanticSearchToolTest extends TestCase
{
    public function test_empty_query_returns_err(): void
    {
        $tool = new SemanticSearchTool();
        // Tenant scope is required for execute() to even reach the empty-query
        // guard. Stub it via TenantContext only if available; otherwise expect
        // a runtime exception that we still treat as a failure case.
        $this->markTestSkipped('Tenant context bootstrap required — covered by integration suite.');
    }

    public function test_describes_itself_with_function_schema(): void
    {
        $tool = new SemanticSearchTool();
        $schema = $tool->toOpenAiFunction();

        $this->assertSame('function', $schema['type']);
        $this->assertSame('semantic_search', $schema['function']['name']);
        $this->assertArrayHasKey('query', $schema['function']['parameters']['properties']);
        $this->assertContains('query', $schema['function']['parameters']['required']);
    }

    public function test_hydration_skips_hits_with_missing_source_rows(): void
    {
        // The hydrate step joins back to source tables. If a hit references a
        // row that no longer exists (e.g. deleted between embed and search),
        // the result must be silently dropped rather than producing a card
        // with null fields.
        $service = $this->createMock(EmbeddingService::class);
        $service->method('semanticSearch')->willReturn([
            ['content_type' => 'listing', 'content_id' => 999999, 'score' => 0.9],
        ]);

        $this->markTestSkipped('Tenant context bootstrap + DB fixtures required — covered by integration suite.');
    }

    public function test_semantic_search_filters_non_public_listing_hits(): void
    {
        TenantContext::setById($this->testTenantId);
        $this->assertSame($this->testTenantId, TenantContext::getId());
        $owner = null;
        $listingIds = [];

        try {
            $owner = User::factory()->forTenant($this->testTenantId)->create();

            $visibleId = DB::table('listings')->insertGetId([
                'tenant_id' => $this->testTenantId,
                'user_id' => $owner->id,
                'title' => 'Visible gardening help',
                'description' => 'Raised beds and pruning',
                'type' => 'offer',
                'status' => 'active',
                'moderation_status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $hiddenId = DB::table('listings')->insertGetId([
                'tenant_id' => $this->testTenantId,
                'user_id' => $owner->id,
                'title' => 'Hidden draft listing',
                'description' => 'This draft should not be returned',
                'type' => 'offer',
                'status' => 'inactive',
                'moderation_status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $listingIds = [$visibleId, $hiddenId];
            $this->assertSame(2, DB::table('listings')->whereIn('id', $listingIds)->count());

            $service = new class($hiddenId, $visibleId) extends EmbeddingService {
                public function __construct(private readonly int $hiddenId, private readonly int $visibleId) {}

                public function semanticSearch(string $query, int $tenantId, array $contentTypes = [], int $limit = 10, int $candidateCap = 2000): array
                {
                    return [
                        ['content_type' => 'listing', 'content_id' => $this->hiddenId, 'score' => 0.99],
                        ['content_type' => 'listing', 'content_id' => $this->visibleId, 'score' => 0.9],
                    ];
                }
            };
            $this->assertSame(
                [$hiddenId, $visibleId],
                array_column($service->semanticSearch('gardening', $this->testTenantId, ['listing']), 'content_id')
            );

            $tool = new SemanticSearchTool($service);
            TenantContext::setById($this->testTenantId);
            $result = $tool->execute([
                'query' => 'gardening',
                'types' => ['listing'],
            ], 42);

            $this->assertTrue($result['ok']);
            $this->assertSame([$visibleId], array_column($result['results'], 'id'));
        } finally {
            if ($listingIds !== []) {
                DB::table('listings')->whereIn('id', $listingIds)->delete();
            }
            if ($owner !== null) {
                DB::table('users')->where('id', $owner->id)->delete();
            }
        }
    }
}
