<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\NewsletterTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterTemplate>
 */
class NewsletterTemplateFactory extends Factory
{
    protected $model = NewsletterTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => 2,
            'name'         => $this->faker->words(3, true),
            'description'  => $this->faker->sentence(),
            'category'     => $this->faker->randomElement(['announcement', 'digest', 'welcome', 'event']),
            'subject'      => $this->faker->sentence(),
            'preview_text' => $this->faker->sentence(),
            'content'      => '<h1>' . $this->faker->sentence() . '</h1><p>' . $this->faker->paragraph() . '</p>',
            'thumbnail'    => null,
            'is_active'    => true,
            'use_count'    => $this->faker->numberBetween(0, 50),
            'created_by'   => User::factory(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
