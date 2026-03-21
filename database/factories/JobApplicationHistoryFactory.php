<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\JobApplication;
use App\Models\JobApplicationHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobApplicationHistoryFactory extends Factory
{
    protected $model = JobApplicationHistory::class;

    public function definition(): array
    {
        return [
            'tenant_id'      => 2,
            'application_id' => JobApplication::factory(),
            'from_status'    => $this->faker->randomElement(['submitted', 'under_review', 'shortlisted']),
            'to_status'      => $this->faker->randomElement(['under_review', 'shortlisted', 'accepted', 'rejected']),
            'changed_by'     => User::factory(),
            'changed_at'     => now(),
            'notes'          => $this->faker->optional()->sentence(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
