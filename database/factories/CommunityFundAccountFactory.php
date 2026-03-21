<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\CommunityFundAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommunityFundAccountFactory extends Factory
{
    protected $model = CommunityFundAccount::class;

    public function definition(): array
    {
        $deposited = $this->faker->randomFloat(2, 100, 5000);
        $withdrawn = $this->faker->randomFloat(2, 0, $deposited * 0.5);
        $donated   = $this->faker->randomFloat(2, 0, $deposited * 0.3);

        return [
            'tenant_id'       => 2,
            'balance'         => $deposited - $withdrawn - $donated,
            'total_deposited' => $deposited,
            'total_withdrawn' => $withdrawn,
            'total_donated'   => $donated,
            'description'     => $this->faker->optional()->sentence(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
