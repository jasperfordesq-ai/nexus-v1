<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\DisposableEmailService;
use App\Services\MxRecordValidator;
use App\Services\PwnedPasswordService;
use App\Services\RegistrationService;
use App\Services\EmailDispatchService;
use App\Services\TenantSettingsService;
use App\Models\User;
use Mockery;

class RegistrationServiceTest extends TestCase
{
    private RegistrationService $service;
    private $mockUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUser = Mockery::mock(User::class);
        $this->service = new RegistrationService(
            $this->mockUser,
            app(TenantSettingsService::class),
            app(PwnedPasswordService::class),
            app(DisposableEmailService::class),
            app(MxRecordValidator::class),
        );
    }

    public function test_register_fails_with_missing_first_name(): void
    {
        $result = $this->service->register([
            'last_name' => 'Doe',
            'email' => 'test@example.com',
            'password' => 'password123',
        ], $this->testTenantId);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_register_fails_with_short_password(): void
    {
        $result = $this->service->register([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'test@example.com',
            'password' => 'short',
        ], $this->testTenantId);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_register_fails_with_invalid_email(): void
    {
        $result = $this->service->register([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'not-an-email',
            'password' => 'password123',
        ], $this->testTenantId);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_register_fails_when_email_already_exists(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('exists')->andReturn(true);
        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->register([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
        ], $this->testTenantId);

        $this->assertArrayHasKey('error', $result);
    }

    // ── verifyEmail ──

    public function test_verifyEmail_returns_false_for_invalid_token(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturnNull();
        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->verifyEmail('invalid-token');
        $this->assertFalse($result);
    }

    // ── resendVerification ──

    public function test_resendVerification_returns_null_for_unknown_email(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturnNull();
        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->resendVerification('unknown@example.com');
        $this->assertNull($result);
    }

    public function test_resendVerification_returns_token_for_pending_user(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes([
            'id' => 42,
            'email' => 'pending@example.com',
            'first_name' => 'Pending',
            'preferred_language' => 'en',
        ]);
        $user->shouldReceive('getAttribute')->with('id')->andReturn(42)->byDefault();
        $user->shouldReceive('getAttribute')->with('email')->andReturn('pending@example.com')->byDefault();
        $user->shouldReceive('getAttribute')->with('first_name')->andReturn('Pending')->byDefault();
        $user->shouldReceive('getAttribute')->with('preferred_language')->andReturn('en')->byDefault();
        $user->shouldReceive('update')->once();
        app()->instance(EmailDispatchService::class, new RegistrationSuccessfulEmailDispatchService());

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturn($user);
        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->resendVerification('pending@example.com');
        $this->assertNotNull($result);
        $this->assertEquals(64, strlen($result));
    }

    public function test_resendVerification_returns_null_and_does_not_update_when_send_fails(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes([
            'id' => 42,
            'email' => 'pending@example.com',
            'first_name' => 'Pending',
            'preferred_language' => 'en',
        ]);
        $user->shouldReceive('getAttribute')->with('id')->andReturn(42)->byDefault();
        $user->shouldReceive('getAttribute')->with('email')->andReturn('pending@example.com')->byDefault();
        $user->shouldReceive('getAttribute')->with('first_name')->andReturn('Pending')->byDefault();
        $user->shouldReceive('getAttribute')->with('preferred_language')->andReturn('en')->byDefault();
        $user->shouldNotReceive('update');
        app()->instance(EmailDispatchService::class, new RegistrationFailingEmailDispatchService());

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturn($user);
        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->resendVerification('pending@example.com', $this->testTenantId);
        $this->assertNull($result);
    }
}

class RegistrationSuccessfulEmailDispatchService extends EmailDispatchService
{
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        return true;
    }
}

class RegistrationFailingEmailDispatchService extends EmailDispatchService
{
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        return false;
    }
}
