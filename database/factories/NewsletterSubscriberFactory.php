<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    public function definition(): array
    {
        return [
            'tenant_id'          => 2,
            'email'              => $this->faker->unique()->safeEmail(),
            'first_name'         => $this->faker->firstName(),
            'last_name'          => $this->faker->lastName(),
            'user_id'            => User::factory(),
            'source'             => $this->faker->randomElement(['signup', 'import', 'manual', 'member_sync']),
            'status'             => 'active',
            'confirmation_token' => Str::random(32),
            'unsubscribe_token'  => Str::random(32),
            'confirmed_at'       => $this->faker->dateTimeBetween('-6 months'),
            'unsubscribed_at'    => null,
            'unsubscribe_reason' => null,
            'is_active'          => true,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }

    public function unsubscribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'             => 'unsubscribed',
            'unsubscribed_at'    => $this->faker->dateTimeBetween('-1 month'),
            'unsubscribe_reason' => $this->faker->sentence(),
            'is_active'          => false,
        ]);
    }
}
