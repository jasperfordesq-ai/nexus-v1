<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Deliverable;
use App\Models\DeliverableComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliverableCommentFactory extends Factory
{
    protected $model = DeliverableComment::class;

    public function definition(): array
    {
        return [
            'tenant_id'          => 2,
            'deliverable_id'     => Deliverable::factory(),
            'user_id'            => User::factory(),
            'comment_text'       => $this->faker->paragraph(),
            'comment_type'       => $this->faker->randomElement(['comment', 'status_change', 'mention']),
            'parent_comment_id'  => null,
            'mentioned_user_ids' => [],
            'reactions'          => [],
            'is_pinned'          => false,
            'is_edited'          => false,
            'edited_at'          => null,
            'is_deleted'         => false,
            'deleted_at'         => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
