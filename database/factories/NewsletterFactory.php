<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Newsletter>
 */
class NewsletterFactory extends Factory
{
    protected $model = Newsletter::class;

    public function definition(): array
    {
        return [
            'tenant_id'          => 2,
            'subject'            => $this->faker->sentence(),
            'preview_text'       => $this->faker->sentence(),
            'content'            => $this->faker->paragraphs(3, true),
            'status'             => $this->faker->randomElement(['draft', 'scheduled', 'sent']),
            'scheduled_at'       => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
            'sent_at'            => null,
            'created_by'         => User::factory(),
            'total_recipients'   => $this->faker->numberBetween(0, 500),
            'total_sent'         => $this->faker->numberBetween(0, 500),
            'total_failed'       => $this->faker->numberBetween(0, 10),
            'total_opens'        => $this->faker->numberBetween(0, 300),
            'unique_opens'       => $this->faker->numberBetween(0, 200),
            'total_clicks'       => $this->faker->numberBetween(0, 100),
            'unique_clicks'      => $this->faker->numberBetween(0, 80),
            'target_audience'    => $this->faker->randomElement(['all', 'active', 'new']),
            'segment_id'         => null,
            'is_recurring'       => false,
            'recurring_frequency' => null,
            'recurring_day'      => null,
            'recurring_day_of_month' => null,
            'recurring_time'     => null,
            'recurring_end_date' => null,
            'last_recurring_sent' => null,
            'template_id'        => null,
            'ab_test_enabled'    => false,
            'subject_b'          => null,
            'ab_split_percentage' => null,
            'ab_winner'          => null,
            'ab_winner_metric'   => null,
            'ab_auto_select_winner' => false,
            'ab_auto_select_after_hours' => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => 'sent',
            'sent_at' => $this->faker->dateTimeBetween('-1 month'),
        ]);
    }
}
