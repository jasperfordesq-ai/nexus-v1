<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\UserService;

class UserServiceTest extends TestCase
{
    public function test_validateProfileUpdate_returns_true_for_valid_data(): void
    {
        $this->assertTrue(UserService::validateProfileUpdate([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
        ]));
    }

    public function test_validateProfileUpdate_rejects_long_first_name(): void
    {
        $result = UserService::validateProfileUpdate(['first_name' => str_repeat('a', 101)]);

        $this->assertFalse($result);
        $this->assertNotEmpty(UserService::getErrors());
    }

    public function test_validateProfileUpdate_rejects_long_last_name(): void
    {
        $result = UserService::validateProfileUpdate(['last_name' => str_repeat('a', 101)]);

        $this->assertFalse($result);
    }

    public function test_validateProfileUpdate_rejects_long_bio(): void
    {
        $result = UserService::validateProfileUpdate(['bio' => str_repeat('a', 5001)]);

        $this->assertFalse($result);
    }

    public function test_validateProfileUpdate_rejects_invalid_profile_type(): void
    {
        $result = UserService::validateProfileUpdate(['profile_type' => 'corporation']);

        $this->assertFalse($result);
    }

    public function test_validateProfileUpdate_accepts_valid_profile_types(): void
    {
        $this->assertTrue(UserService::validateProfileUpdate(['profile_type' => 'individual']));
        $this->assertTrue(UserService::validateProfileUpdate(['profile_type' => 'organisation']));
    }

    public function test_validateProfileUpdate_rejects_short_phone(): void
    {
        $result = UserService::validateProfileUpdate(['phone' => '123']);

        $this->assertFalse($result);
    }

    public function test_validateProfileUpdate_accepts_valid_phone(): void
    {
        $this->assertTrue(UserService::validateProfileUpdate(['phone' => '+353891234567']));
    }

    public function test_validateProfileUpdate_accepts_empty_phone(): void
    {
        $this->assertTrue(UserService::validateProfileUpdate(['phone' => '']));
    }

    public function test_getErrors_returns_array(): void
    {
        $this->assertIsArray(UserService::getErrors());
    }
}
