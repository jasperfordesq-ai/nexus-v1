<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company() . ' Timebank';

        return [
            'name'               => $name,
            'slug'               => Str::slug($name),
            'domain'             => Str::slug($name) . '.project-nexus.ie',
            'configuration'      => [],
            'path'               => '/' . Str::slug($name),
            'depth'              => 0,
            'parent_id'          => null,
            'allows_subtenants'  => false,
            'is_active'          => true,
        ];
    }
}
