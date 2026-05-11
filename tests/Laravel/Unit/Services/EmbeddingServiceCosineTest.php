<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\EmbeddingService;
use Tests\Laravel\TestCase;

/**
 * Pure-math tests for EmbeddingService::cosineSimilarity — these don't
 * touch DB or OpenAI, so they're a fast safety net against regressions in
 * the core ranking primitive.
 */
class EmbeddingServiceCosineTest extends TestCase
{
    public function test_identical_vectors_return_one(): void
    {
        $service = new EmbeddingService();
        $v = [0.1, 0.5, 0.3, 0.9];
        $this->assertEqualsWithDelta(1.0, $service->cosineSimilarity($v, $v), 1e-9);
    }

    public function test_orthogonal_vectors_return_zero(): void
    {
        $service = new EmbeddingService();
        $this->assertEqualsWithDelta(0.0, $service->cosineSimilarity([1, 0], [0, 1]), 1e-9);
    }

    public function test_opposite_vectors_return_minus_one(): void
    {
        $service = new EmbeddingService();
        $this->assertEqualsWithDelta(-1.0, $service->cosineSimilarity([1, 2, 3], [-1, -2, -3]), 1e-9);
    }

    public function test_zero_vector_returns_zero_no_division_by_zero(): void
    {
        $service = new EmbeddingService();
        $this->assertSame(0.0, $service->cosineSimilarity([0, 0, 0], [1, 2, 3]));
    }

    public function test_mismatched_dimensions_use_shorter_length(): void
    {
        $service = new EmbeddingService();
        // [1, 0, 0] vs [1, 0] — truncated to length 2 → still parallel
        $this->assertEqualsWithDelta(1.0, $service->cosineSimilarity([1, 0, 0], [1, 0]), 1e-9);
    }
}
