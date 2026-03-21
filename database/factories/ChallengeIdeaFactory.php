<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\ChallengeIdea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeIdeaFactory extends Factory
{
    protected $model = ChallengeIdea::class;

    public function definition(): array
    {
        return [
            'challenge_id'   => $this->faker->numberBetween(1, 100),
            'user_id'        => User::factory(),
            'title'          => $this->faker->sentence(5),
            'description'    => $this->faker->paragraphs(2, true),
            'votes_count'    => $this->faker->numberBetween(0, 50),
            'comments_count' => $this->faker->numberBetween(0, 20),
            'status'         => $this->faker->randomElement(['submitted', 'under_review', 'approved', 'rejected']),
            'image_url'      => $this->faker->optional()->imageUrl(640, 480),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
