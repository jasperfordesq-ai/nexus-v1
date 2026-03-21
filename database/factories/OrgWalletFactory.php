<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\OrgWallet;
use App\Models\VolOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgWallet>
 */
class OrgWalletFactory extends Factory
{
    protected $model = OrgWallet::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'organization_id' => VolOrganization::factory(),
            'balance'         => $this->faker->randomFloat(2, 0, 500),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
