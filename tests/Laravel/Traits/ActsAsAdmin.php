<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Traits;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait ActsAsAdmin
{
    protected function actAsAdmin(array $attributes = []): User
    {
        $user = $this->createAdmin($attributes);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    protected function createAdmin(array $attributes = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->admin()->create($attributes);
    }
}
