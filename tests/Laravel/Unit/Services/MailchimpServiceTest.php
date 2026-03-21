<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\MailchimpService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

class MailchimpServiceTest extends TestCase
{
    public function test_subscribe_skips_when_not_configured(): void
    {
        // env() returns null when not set — service should no-op
        Log::shouldReceive('debug')->once()->with(\Mockery::pattern('/not configured/'));

        $service = new MailchimpService();
        $service->subscribe('test@example.com');
    }

    public function test_unsubscribe_skips_when_not_configured(): void
    {
        Log::shouldReceive('debug')->once()->with(\Mockery::pattern('/not configured/'));

        $service = new MailchimpService();
        $service->unsubscribe('test@example.com');
    }
}
