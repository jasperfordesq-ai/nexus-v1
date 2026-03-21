<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\ChallengeIdea;
use App\Models\Group;
use App\Models\IdeaTeamLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IdeaTeamLinkFactory extends Factory
{
    protected $model = IdeaTeamLink::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => 2,
            'idea_id'      => ChallengeIdea::factory(),
            'group_id'     => Group::factory(),
            'challenge_id' => $this->faker->numberBetween(1, 100),
            'converted_by' => User::factory(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
