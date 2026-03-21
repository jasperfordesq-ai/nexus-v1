<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VolOrganization>
 */
class VolOrganizationFactory extends Factory
{
    protected $model = VolOrganization::class;

    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'tenant_id'        => 2,
            'user_id'          => User::factory(),
            'name'             => $name,
            'description'      => $this->faker->paragraph(),
            'contact_email'    => $this->faker->companyEmail(),
            'website'          => $this->faker->optional()->url(),
            'slug'             => Str::slug($name),
            'status'           => $this->faker->randomElement(['active', 'pending', 'inactive']),
            'logo_url'         => null,
            'auto_pay_enabled' => false,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
