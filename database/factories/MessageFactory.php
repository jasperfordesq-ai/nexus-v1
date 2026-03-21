<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'tenant_id'           => 2,
            'sender_id'           => User::factory(),
            'receiver_id'         => User::factory(),
            'listing_id'          => null,
            'body'                => $this->faker->paragraph(),
            'is_read'             => $this->faker->boolean(40),
            'is_edited'           => false,
            'edited_at'           => null,
            'is_deleted_sender'   => false,
            'is_deleted_receiver' => false,
            'read_at'             => null,
            'created_at'          => $this->faker->dateTimeBetween('-1 month'),
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
