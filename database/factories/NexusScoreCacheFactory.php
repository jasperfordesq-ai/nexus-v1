<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\NexusScoreCache;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NexusScoreCache>
 */
class NexusScoreCacheFactory extends Factory
{
    protected $model = NexusScoreCache::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => 2,
            'user_id'          => User::factory(),
            'total_score'      => $this->faker->randomFloat(2, 0, 1000),
            'engagement_score' => $this->faker->randomFloat(2, 0, 200),
            'quality_score'    => $this->faker->randomFloat(2, 0, 200),
            'volunteer_score'  => $this->faker->randomFloat(2, 0, 200),
            'activity_score'   => $this->faker->randomFloat(2, 0, 200),
            'badge_score'      => $this->faker->randomFloat(2, 0, 100),
            'impact_score'     => $this->faker->randomFloat(2, 0, 100),
            'percentile'       => $this->faker->numberBetween(1, 100),
            'tier'             => $this->faker->randomElement(['bronze', 'silver', 'gold', 'platinum']),
            'calculated_at'    => $this->faker->dateTimeBetween('-1 week'),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
