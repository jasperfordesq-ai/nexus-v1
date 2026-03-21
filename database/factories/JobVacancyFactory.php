<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobVacancyFactory extends Factory
{
    protected $model = JobVacancy::class;

    public function definition(): array
    {
        return [
            'tenant_id'          => 2,
            'user_id'            => User::factory(),
            'organization_id'    => null,
            'title'              => $this->faker->jobTitle(),
            'description'        => $this->faker->paragraphs(2, true),
            'location'           => $this->faker->city(),
            'is_remote'          => $this->faker->boolean(30),
            'type'               => $this->faker->randomElement(['volunteer', 'paid', 'internship']),
            'commitment'         => $this->faker->randomElement(['full_time', 'part_time', 'flexible']),
            'category'           => $this->faker->randomElement(['education', 'health', 'technology', 'community']),
            'skills_required'    => $this->faker->optional()->words(4, true),
            'hours_per_week'     => $this->faker->optional()->randomFloat(1, 5, 40),
            'time_credits'       => $this->faker->optional()->randomFloat(1, 1, 20),
            'contact_email'      => $this->faker->safeEmail(),
            'contact_phone'      => $this->faker->optional()->e164PhoneNumber(),
            'deadline'           => $this->faker->dateTimeBetween('+1 week', '+3 months'),
            'status'             => $this->faker->randomElement(['draft', 'open', 'closed', 'expired']),
            'salary_min'         => null,
            'salary_max'         => null,
            'salary_type'        => null,
            'salary_currency'    => null,
            'salary_negotiable'  => false,
            'is_featured'        => false,
            'featured_until'     => null,
            'expired_at'         => null,
            'renewed_at'         => null,
            'renewal_count'      => 0,
            'views_count'        => $this->faker->numberBetween(0, 200),
            'applications_count' => $this->faker->numberBetween(0, 30),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
