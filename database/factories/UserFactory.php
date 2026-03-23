<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName  = $this->faker->lastName();

        return [
            'tenant_id'     => 2,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'name'          => $firstName . ' ' . $lastName,
            'email'         => uniqid('u') . '_' . $this->faker->unique()->safeEmail(),
            'password_hash' => bcrypt('password'),
            'role'          => 'member',
            'status'        => 'active',
            'bio'           => $this->faker->optional()->sentence(),
            'location'      => $this->faker->optional()->city(),
            'phone'         => $this->faker->optional()->e164PhoneNumber(),
            'is_verified'   => $this->faker->boolean(80),
            'is_approved'   => true,
            'balance'       => $this->faker->randomFloat(2, 0, 50),
            'profile_type'  => 'individual',
            'onboarding_completed' => true,
            'created_at'    => $this->faker->dateTimeBetween('-1 year'),
        ];
    }

    /**
     * Indicate the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role'          => 'admin',
            'is_verified'   => true,
            'is_approved'   => true,
        ]);
    }

    /**
     * Scope the user to a specific tenant.
     */
    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
