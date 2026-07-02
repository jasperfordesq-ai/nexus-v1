<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Matching;

/**
 * MatchScoreResult — immutable outcome of scoring one searcher↔candidate pair.
 *
 * score is 0–100. pillars/signals hold the 0–1 component values that produced
 * it (persisted to match_cache.score_breakdown for analytics and the member
 * "why this score" UI). adjustments are the bounded additive deltas applied
 * on top of the geometric-mean core.
 */
final class MatchScoreResult
{
    /**
     * @param float $score       Final score 0–100
     * @param array $pillars     ['relevance' => float, 'feasibility' => float, 'trust' => float] (0–1)
     * @param array $signals     Per-signal 0–1 values keyed by pillar
     * @param array $adjustments Additive deltas applied (e.g. ['mutual' => 8.0, 'freshness' => 2.5])
     * @param array $reasons     Human-readable match reasons
     * @param string $matchType  one_way | potential | mutual
     * @param float|null $distanceKm Null when unresolvable/remote
     * @param string $serviceType Candidate listing service_type
     */
    public function __construct(
        public readonly float $score,
        public readonly array $pillars,
        public readonly array $signals,
        public readonly array $adjustments,
        public readonly array $reasons,
        public readonly string $matchType,
        public readonly ?float $distanceKm,
        public readonly string $serviceType,
    ) {}

    /** Legacy-shaped array for callers of the v1 calculateMatchScore contract. */
    public function toLegacyArray(): array
    {
        return [
            'score' => $this->score,
            'reasons' => $this->reasons,
            'breakdown' => $this->signals,
            'distance' => $this->distanceKm,
            'type' => $this->matchType,
            'service_type' => $this->serviceType,
        ];
    }

    /** Structured breakdown persisted to match_cache.score_breakdown. */
    public function toBreakdownArray(): array
    {
        return [
            'pillars' => array_map(fn ($v) => round((float) $v, 4), $this->pillars),
            'signals' => array_map(
                fn ($group) => is_array($group)
                    ? array_map(fn ($v) => round((float) $v, 4), $group)
                    : round((float) $group, 4),
                $this->signals
            ),
            'adjustments' => array_map(fn ($v) => round((float) $v, 2), $this->adjustments),
        ];
    }
}
