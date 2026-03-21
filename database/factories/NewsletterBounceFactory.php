<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Newsletter;
use App\Models\NewsletterBounce;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterBounce>
 */
class NewsletterBounceFactory extends Factory
{
    protected $model = NewsletterBounce::class;

    public function definition(): array
    {
        return [
            'tenant_id'     => 2,
            'email'         => $this->faker->safeEmail(),
            'newsletter_id' => Newsletter::factory(),
            'queue_id'      => null,
            'bounce_type'   => $this->faker->randomElement(['hard', 'soft']),
            'bounce_reason' => $this->faker->sentence(),
            'bounce_code'   => $this->faker->randomElement(['550', '552', '421', '450']),
            'bounced_at'    => $this->faker->dateTimeBetween('-1 month'),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
