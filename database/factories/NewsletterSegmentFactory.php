<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\NewsletterSegment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSegment>
 */
class NewsletterSegmentFactory extends Factory
{
    protected $model = NewsletterSegment::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'name'        => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'rules'       => [['field' => 'status', 'operator' => 'equals', 'value' => 'active']],
            'is_active'   => true,
            'created_by'  => User::factory(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
