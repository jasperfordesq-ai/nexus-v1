<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Error404Log;
use Illuminate\Database\Eloquent\Factories\Factory;

class Error404LogFactory extends Factory
{
    protected $model = Error404Log::class;

    public function definition(): array
    {
        return [
            'url'           => '/' . $this->faker->slug(3),
            'referer'       => $this->faker->optional()->url(),
            'user_agent'    => $this->faker->userAgent(),
            'ip_address'    => $this->faker->ipv4(),
            'user_id'       => null,
            'hit_count'     => $this->faker->numberBetween(1, 100),
            'first_seen_at' => $this->faker->dateTimeBetween('-3 months'),
            'last_seen_at'  => $this->faker->dateTimeBetween('-1 week'),
            'resolved'      => false,
            'redirect_id'   => null,
            'notes'         => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
