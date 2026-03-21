<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\NewsletterAnalytics;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterAnalytics>
 */
class NewsletterAnalyticsFactory extends Factory
{
    protected $model = NewsletterAnalytics::class;

    public function definition(): array
    {
        return [
            'tenant_id'      => 2,
            'email'          => $this->faker->unique()->safeEmail(),
            'opens_by_hour'  => array_map(fn () => $this->faker->numberBetween(0, 20), range(0, 23)),
            'clicks_by_hour' => array_map(fn () => $this->faker->numberBetween(0, 10), range(0, 23)),
            'total_opens'    => $this->faker->numberBetween(0, 200),
            'total_clicks'   => $this->faker->numberBetween(0, 100),
            'best_hour'      => $this->faker->numberBetween(0, 23),
            'last_updated'   => $this->faker->dateTimeBetween('-1 month'),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
