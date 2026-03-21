<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\EmbeddingService;

/**
 * EmbeddingService Tests
 *
 * Tests cosine similarity math and findSimilar edge cases.
 * Does NOT call OpenAI — tests only pure logic and DB queries.
 */
class EmbeddingServiceTest extends TestCase
{
    private function svc(): EmbeddingService
    {
        return new EmbeddingService();
    }

    // =========================================================================
    // cosineSimilarity
    // =========================================================================

    public function test_cosine_similarity_identical_vectors(): void
    {
        $vec = [1.0, 0.0, 0.0];
        $result = $this->svc()->cosineSimilarity($vec, $vec);
        $this->assertEqualsWithDelta(1.0, $result, 0.0001);
    }

    public function test_cosine_similarity_orthogonal_vectors(): void
    {
        $a = [1.0, 0.0, 0.0];
        $b = [0.0, 1.0, 0.0];
        $result = $this->svc()->cosineSimilarity($a, $b);
        $this->assertEqualsWithDelta(0.0, $result, 0.0001);
    }

    public function test_cosine_similarity_opposite_vectors(): void
    {
        $a = [1.0, 0.0];
        $b = [-1.0, 0.0];
        $result = $this->svc()->cosineSimilarity($a, $b);
        $this->assertEqualsWithDelta(-1.0, $result, 0.0001);
    }

    public function test_cosine_similarity_zero_vector(): void
    {
        $a = [0.0, 0.0, 0.0];
        $b = [1.0, 2.0, 3.0];
        $result = $this->svc()->cosineSimilarity($a, $b);
        $this->assertSame(0.0, $result);
    }

    public function test_cosine_similarity_both_zero_vectors(): void
    {
        $a = [0.0, 0.0];
        $b = [0.0, 0.0];
        $result = $this->svc()->cosineSimilarity($a, $b);
        $this->assertSame(0.0, $result);
    }

    public function test_cosine_similarity_mismatched_dimensions(): void
    {
        // Should handle gracefully — uses min(count) length
        $a = [1.0, 2.0, 3.0];
        $b = [1.0, 2.0];
        $result = $this->svc()->cosineSimilarity($a, $b);
        $this->assertIsFloat($result);
    }

    public function test_cosine_similarity_normalized_vectors(): void
    {
        // Two normalized vectors at ~45 degrees
        $a = [1.0, 0.0];
        $b = [0.7071, 0.7071]; // ~unit vector at 45 degrees
        $result = $this->svc()->cosineSimilarity($a, $b);
        $this->assertEqualsWithDelta(0.7071, $result, 0.001);
    }

    public function test_cosine_similarity_result_bounded(): void
    {
        // Result should always be in [-1, 1]
        $a = [100.0, -50.0, 75.0, -25.0];
        $b = [-30.0, 60.0, -10.0, 80.0];
        $result = $this->svc()->cosineSimilarity($a, $b);
        $this->assertGreaterThanOrEqual(-1.0, $result);
        $this->assertLessThanOrEqual(1.0, $result);
    }

    // =========================================================================
    // findSimilar
    // =========================================================================

    public function test_find_similar_returns_array(): void
    {
        $result = $this->svc()->findSimilar(999999, 'listing', 2);
        $this->assertIsArray($result);
    }

    public function test_find_similar_returns_empty_for_nonexistent_source(): void
    {
        $result = $this->svc()->findSimilar(999999, 'listing', 2);
        $this->assertEmpty($result);
    }

    public function test_find_similar_respects_limit(): void
    {
        $result = $this->svc()->findSimilar(999999, 'listing', 2, 3);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(3, count($result));
    }
}
