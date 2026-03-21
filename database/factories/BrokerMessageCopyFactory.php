<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\BrokerMessageCopy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BrokerMessageCopyFactory extends Factory
{
    protected $model = BrokerMessageCopy::class;

    public function definition(): array
    {
        return [
            'tenant_id'            => 2,
            'original_message_id'  => null,
            'conversation_key'     => $this->faker->uuid(),
            'sender_id'            => User::factory(),
            'receiver_id'          => User::factory(),
            'message_body'         => $this->faker->paragraph(),
            'sent_at'              => $this->faker->dateTimeBetween('-1 month'),
            'copy_reason'          => $this->faker->randomElement(['broker_cc', 'moderation', 'audit']),
            'related_listing_id'   => null,
            'reviewed_by'          => null,
            'reviewed_at'          => null,
            'flagged'              => false,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
