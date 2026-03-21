<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ChallengeService;
use App\Models\Challenge;
use Illuminate\Support\Facades\DB;
use Mockery;

class ChallengeServiceTest extends TestCase
{
    public function test_getAll_returns_items_and_total(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_claim_returns_false_when_challenge_not_active(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_claim_returns_false_when_already_claimed(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_claim_returns_true_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getActiveChallenges_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getLegacyById_returns_null_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }
}
