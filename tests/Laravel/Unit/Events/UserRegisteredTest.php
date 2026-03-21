<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Events;

use App\Events\UserRegistered;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\Laravel\TestCase;

class UserRegisteredTest extends TestCase
{
    public function test_instantiation_stores_properties(): void
    {
        $user = new User();
        $user->id = 42;
        $tenantId = 5;

        $event = new UserRegistered($user, $tenantId);

        $this->assertSame($user, $event->user);
        $this->assertSame(5, $event->tenantId);
    }

    public function test_does_not_implement_should_broadcast(): void
    {
        $this->assertFalse(
            in_array(ShouldBroadcast::class, class_implements(UserRegistered::class))
        );
    }
}
