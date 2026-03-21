<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmbeddingServiceTest extends TestCase
{
    private EmbeddingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmbeddingService();
    }

    // =========================================================================
    // cosineSimilarity()
    // =========================================================================

    public function test_cosineSimilarity_identical_vectors_returns_one(): void
    {
        $vec = [1.0, 2.0, 3.0];
        $result = $this->service->cosineSimilarity($vec, $vec);
        $this->assertEqualsWithDelta(1.0, $result, 0.0001);
    }

    public function test_cosineSimilarity_opposite_vectors_returns_negative_one(): void
    {
        $a = [1.0, 0.0, 0.0];
        $b = [-1.0, 0.0, 0.0];
        $result = $this->service->cosineSimilarity($a, $b);
        $this->assertEqualsWithDelta(-1.0, $result, 0.0001);
    }

    public function test_cosineSimilarity_orthogonal_vectors_returns_zero(): void
    {
        $a = [1.0, 0.0];
        $b = [0.0, 1.0];
        $result = $this->service->cosineSimilarity($a, $b);
        $this->assertEqualsWithDelta(0.0, $result, 0.0001);
    }

    public function test_cosineSimilarity_zero_vector_returns_zero(): void
    {
        $a = [0.0, 0.0, 0.0];
        $b = [1.0, 2.0, 3.0];
        $result = $this->service->cosineSimilarity($a, $b);
        $this->assertEquals(0.0, $result);
    }

    public function test_cosineSimilarity_mismatched_dimensions_uses_minimum(): void
    {
        Log::shouldReceive('debug')->once();

        $a = [1.0, 0.0];
        $b = [1.0, 0.0, 0.0];
        $result = $this->service->cosineSimilarity($a, $b);
        $this->assertEqualsWithDelta(1.0, $result, 0.0001);
    }

    public function test_cosineSimilarity_empty_vectors_returns_zero(): void
    {
        $result = $this->service->cosineSimilarity([], []);
        $this->assertEquals(0.0, $result);
    }

    // =========================================================================
    // findSimilar()
    // =========================================================================

    public function test_findSimilar_returns_empty_when_source_not_found(): void
    {
        DB::shouldReceive('table->where->where->where->first')->andReturn(null);

        $result = $this->service->findSimilar(1, 'listing', 2);
        $this->assertEquals([], $result);
    }

    public function test_findSimilar_returns_empty_when_source_embedding_empty(): void
    {
        $row = (object) ['embedding' => null];
        DB::shouldReceive('table->where->where->where->first')->andReturn($row);

        $result = $this->service->findSimilar(1, 'listing', 2);
        $this->assertEquals([], $result);
    }

    public function test_findSimilar_returns_sorted_similar_ids(): void
    {
        $sourceVec = [1.0, 0.0, 0.0];
        $sourceRow = (object) ['embedding' => json_encode($sourceVec)];

        DB::shouldReceive('table->where->where->where->first')->andReturn($sourceRow);

        $candidates = collect([
            (object) ['content_id' => 10, 'embedding' => json_encode([0.9, 0.1, 0.0])],
            (object) ['content_id' => 20, 'embedding' => json_encode([0.5, 0.5, 0.0])],
            (object) ['content_id' => 30, 'embedding' => json_encode([0.0, 0.0, 1.0])], // orthogonal
        ]);

        DB::shouldReceive('table->where->where->where->get')->andReturn($candidates);

        $result = $this->service->findSimilar(1, 'listing', 2, 5);

        $this->assertIsArray($result);
        // ID 10 should be most similar
        $this->assertEquals(10, $result[0]);
    }

    public function test_findSimilar_respects_limit(): void
    {
        $sourceVec = [1.0, 0.0];
        $sourceRow = (object) ['embedding' => json_encode($sourceVec)];

        DB::shouldReceive('table->where->where->where->first')->andReturn($sourceRow);

        $candidates = collect([
            (object) ['content_id' => 10, 'embedding' => json_encode([0.9, 0.1])],
            (object) ['content_id' => 20, 'embedding' => json_encode([0.8, 0.2])],
            (object) ['content_id' => 30, 'embedding' => json_encode([0.7, 0.3])],
        ]);

        DB::shouldReceive('table->where->where->where->get')->andReturn($candidates);

        $result = $this->service->findSimilar(1, 'listing', 2, 2);
        $this->assertCount(2, $result);
    }

    public function test_findSimilar_catches_exceptions_and_returns_empty(): void
    {
        DB::shouldReceive('table->where->where->where->first')
            ->andThrow(new \Exception('DB error'));
        Log::shouldReceive('error')->once();

        $result = $this->service->findSimilar(1, 'listing', 2);
        $this->assertEquals([], $result);
    }
}
