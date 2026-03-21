<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\GroupDiscussion;
use App\Models\GroupPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupPostFactory extends Factory
{
    protected $model = GroupPost::class;

    public function definition(): array
    {
        return [
            'tenant_id'     => 2,
            'discussion_id' => GroupDiscussion::factory(),
            'user_id'       => User::factory(),
            'content'       => $this->faker->paragraph(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
