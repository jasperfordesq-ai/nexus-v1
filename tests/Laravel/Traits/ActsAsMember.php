<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Traits;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait ActsAsMember
{
    protected function actAsMember(array $attributes = []): User
    {
        $user = $this->createMember($attributes);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    protected function createMember(array $attributes = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create($attributes);
    }
}
