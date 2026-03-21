<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobApplicationFactory extends Factory
{
    protected $model = JobApplication::class;

    public function definition(): array
    {
        return [
            'vacancy_id'     => JobVacancy::factory(),
            'user_id'        => User::factory(),
            'message'        => $this->faker->paragraph(),
            'status'         => $this->faker->randomElement(['submitted', 'under_review', 'shortlisted', 'accepted', 'rejected', 'withdrawn']),
            'stage'          => $this->faker->optional()->randomElement(['screening', 'interview', 'offer']),
            'reviewer_notes' => null,
            'reviewed_by'    => null,
            'reviewed_at'    => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
