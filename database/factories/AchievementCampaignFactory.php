<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\AchievementCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

class AchievementCampaignFactory extends Factory
{
    protected $model = AchievementCampaign::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'name'            => $this->faker->words(3, true) . ' Campaign',
            'description'     => $this->faker->sentence(),
            'campaign_type'   => $this->faker->randomElement(['badge', 'xp', 'challenge']),
            'badge_key'       => $this->faker->slug(2),
            'xp_amount'       => $this->faker->numberBetween(10, 500),
            'target_audience' => $this->faker->randomElement(['all', 'new_members', 'active_members']),
            'audience_config' => [],
            'schedule'        => $this->faker->randomElement(['once', 'daily', 'weekly']),
            'status'          => $this->faker->randomElement(['draft', 'active', 'paused', 'completed']),
            'activated_at'    => null,
            'last_run_at'     => null,
            'total_awards'    => 0,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
