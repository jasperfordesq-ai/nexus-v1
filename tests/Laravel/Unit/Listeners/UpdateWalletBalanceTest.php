<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Events\TransactionCompleted;
use App\Listeners\UpdateWalletBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

class UpdateWalletBalanceTest extends TestCase
{
    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(UpdateWalletBalance::class))
        );
    }

    public function test_handle_awards_xp_and_runs_badge_checks(): void
    {
        $transaction = new Transaction();
        $transaction->id = 500;

        $sender = new User();
        $sender->id = 10;

        $receiver = new User();
        $receiver->id = 20;

        $event = new TransactionCompleted($transaction, $sender, $receiver, 2);

        $gamificationMock = Mockery::mock(GamificationService::class);

        // Expect XP award for sender
        $gamificationMock->shouldReceive('awardXP')
            ->once()
            ->with(10, GamificationService::XP_VALUES['send_credits'], 'send_credits', Mockery::type('string'));

        // Expect XP award for receiver
        $gamificationMock->shouldReceive('awardXP')
            ->once()
            ->with(20, GamificationService::XP_VALUES['receive_credits'], 'receive_credits', Mockery::type('string'));

        // Expect badge checks for both users
        $gamificationMock->shouldReceive('runAllBadgeChecks')
            ->once()
            ->with(10);
        $gamificationMock->shouldReceive('runAllBadgeChecks')
            ->once()
            ->with(20);

        $this->app->instance(GamificationService::class, $gamificationMock);

        $listener = new UpdateWalletBalance();
        $listener->handle($event);
    }

    public function test_handle_catches_exceptions_and_logs_error(): void
    {
        $transaction = new Transaction();
        $transaction->id = 500;

        $sender = new User();
        $sender->id = 10;

        $receiver = new User();
        $receiver->id = 20;

        $event = new TransactionCompleted($transaction, $sender, $receiver, 2);

        $gamificationMock = Mockery::mock(GamificationService::class);
        $gamificationMock->shouldReceive('awardXP')
            ->andThrow(new \RuntimeException('Gamification error'));

        $this->app->instance(GamificationService::class, $gamificationMock);

        Log::shouldReceive('error')
            ->once()
            ->with('UpdateWalletBalance listener failed', Mockery::type('array'));

        $listener = new UpdateWalletBalance();
        $listener->handle($event);
    }
}
