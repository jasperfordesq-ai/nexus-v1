<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\ContentModerationQueue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentModerationQueueFactory extends Factory
{
    protected $model = ContentModerationQueue::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => 2,
            'content_type'     => $this->faker->randomElement(['listing', 'event', 'feed_post', 'comment', 'message']),
            'content_id'       => $this->faker->numberBetween(1, 1000),
            'author_id'        => User::factory(),
            'title'            => $this->faker->sentence(4),
            'status'           => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'reviewer_id'      => null,
            'reviewed_at'      => null,
            'rejection_reason' => null,
            'auto_flagged'     => $this->faker->boolean(20),
            'flag_reason'      => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
