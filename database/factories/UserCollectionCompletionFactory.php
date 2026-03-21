<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\UserCollectionCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserCollectionCompletion>
 */
class UserCollectionCompletionFactory extends Factory
{
    protected $model = UserCollectionCompletion::class;

    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'collection_id' => $this->faker->randomNumber(3),
            'bonus_claimed' => $this->faker->boolean(30),
        ];
    }
}
