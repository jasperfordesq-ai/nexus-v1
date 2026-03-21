<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchLog>
 */
class SearchLogFactory extends Factory
{
    protected $model = SearchLog::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'query'        => $this->faker->words(3, true),
            'search_type'  => $this->faker->randomElement(['listings', 'members', 'events', 'groups', 'all']),
            'result_count' => $this->faker->numberBetween(0, 100),
            'filters'      => $this->faker->optional()->randomElement([
                ['category' => 'services'],
                ['location' => 'Dublin'],
                null,
            ]),
        ];
    }
}
