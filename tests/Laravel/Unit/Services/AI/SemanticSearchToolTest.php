<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI;

use App\Services\AI\Tools\SemanticSearchTool;
use App\Services\EmbeddingService;
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
}
