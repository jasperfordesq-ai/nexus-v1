<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Discussion;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscussionFactory extends Factory
{
    protected $model = Discussion::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 2,
            'group_id'  => Group::factory(),
            'user_id'   => User::factory(),
            'title'     => $this->faker->sentence(5),
            'is_pinned' => $this->faker->boolean(10),
            'is_locked' => false,
            'status'    => $this->faker->randomElement(['active', 'closed', 'archived']),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
