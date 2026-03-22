<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\JobExpiryNotificationService;
use App\Models\JobVacancy;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;

class JobExpiryNotificationServiceTest extends TestCase
{
    // ── notifyExpiringSoon ───────────────────────────────────────

    public function test_notifyExpiringSoon_returns_zero_when_no_expiring_vacancies(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->andReturnSelf();
        $builder->shouldReceive('whereBetween')->andReturnSelf();
        $builder->shouldReceive('whereNull')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([]));

        $mock = Mockery::mock('alias:' . JobVacancy::class);
        $mock->shouldReceive('with')->andReturn($builder);

        $result = JobExpiryNotificationService::notifyExpiringSoon();
        $this->assertSame(0, $result);
    }

    public function test_notifyExpiringSoon_sends_in_app_notification(): void
    {
        $creator = new User();
        $creator->id = 5;
        $creator->first_name = 'John';
        $creator->last_name = 'Doe';
        $creator->email = 'john@example.com';

        $vacancy = Mockery::mock();
        $vacancy->id = 10;
        $vacancy->title = 'Developer';
        $vacancy->user_id = 5;
        $vacancy->creator = $creator;
        $vacancy->deadline = now()->addDays(3);
        $vacancy->shouldReceive('getAttribute')->with('deadline')->andReturn(now()->addDays(3));

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->andReturnSelf();
        $builder->shouldReceive('whereBetween')->andReturnSelf();
        $builder->shouldReceive('whereNull')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([$vacancy]));

        $mock = Mockery::mock('alias:' . JobVacancy::class);
        $mock->shouldReceive('with')->andReturn($builder);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->once()->with(
            5,
            Mockery::on(fn($msg) => str_contains($msg, 'Developer') && str_contains($msg, 'expires')),
            '/jobs/10',
            'job_application'
        );

        Mail::shouldReceive('html')->once();

        $result = JobExpiryNotificationService::notifyExpiringSoon();
        $this->assertSame(1, $result);
    }

    public function test_notifyExpiringSoon_sends_email_to_creator(): void
    {
        $creator = new User();
        $creator->id = 5;
        $creator->first_name = 'John';
        $creator->last_name = 'Doe';
        $creator->email = 'john@example.com';

        $vacancy = Mockery::mock();
        $vacancy->id = 10;
        $vacancy->title = 'Designer';
        $vacancy->user_id = 5;
        $vacancy->creator = $creator;
        $vacancy->deadline = now()->addDays(5);
        $vacancy->shouldReceive('getAttribute')->with('deadline')->andReturn(now()->addDays(5));

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->andReturnSelf();
        $builder->shouldReceive('whereBetween')->andReturnSelf();
        $builder->shouldReceive('whereNull')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([$vacancy]));

        $mock = Mockery::mock('alias:' . JobVacancy::class);
        $mock->shouldReceive('with')->andReturn($builder);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->once();

        Mail::shouldReceive('html')->once()->withArgs(function ($html, $callback) {
            $message = Mockery::mock();
            $message->shouldReceive('to')->with('john@example.com', Mockery::type('string'))->andReturnSelf();
            $message->shouldReceive('subject')->with(Mockery::on(fn($s) => str_contains($s, 'Designer')))->andReturnSelf();
            $callback($message);
            return str_contains($html, 'expiring soon');
        });

        $result = JobExpiryNotificationService::notifyExpiringSoon();
        $this->assertSame(1, $result);
    }

    public function test_notifyExpiringSoon_returns_zero_on_outer_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobVacancy::class);
        $mock->shouldReceive('with')->andThrow(new \Exception('DB error'));

        $result = JobExpiryNotificationService::notifyExpiringSoon();
        $this->assertSame(0, $result);
    }

    public function test_notifyExpiringSoon_continues_on_individual_vacancy_failure(): void
    {
        Log::shouldReceive('warning')->once();

        $vacancy = Mockery::mock();
        $vacancy->id = 10;
        $vacancy->title = 'Developer';
        $vacancy->user_id = null; // causes notification to be skipped
        $vacancy->creator = null;
        $vacancy->deadline = now()->addDays(3);
        $vacancy->shouldReceive('getAttribute')->with('deadline')->andThrow(new \Exception('Bad date'));

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->andReturnSelf();
        $builder->shouldReceive('whereBetween')->andReturnSelf();
        $builder->shouldReceive('whereNull')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([$vacancy]));

        $mock = Mockery::mock('alias:' . JobVacancy::class);
        $mock->shouldReceive('with')->andReturn($builder);

        $result = JobExpiryNotificationService::notifyExpiringSoon();
        $this->assertIsInt($result);
    }

    public function test_notifyExpiringSoon_returns_integer_type(): void
    {
        $result = JobExpiryNotificationService::notifyExpiringSoon();
        $this->assertIsInt($result);
    }
}
