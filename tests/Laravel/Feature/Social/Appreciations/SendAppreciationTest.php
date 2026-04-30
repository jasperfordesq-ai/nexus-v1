<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Social\Appreciations;

use App\Models\Social\Appreciation;
use App\Models\User;
use App\Services\Social\AppreciationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * SOC14 — Sending an appreciation creates a row.
 */
class SendAppreciationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_send_creates_appreciation_row(): void
    {
        if (!Schema::hasTable('appreciations')) {
            $this->markTestSkipped('appreciations schema not present.');
        }
        Cache::flush();

        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $svc = new AppreciationService();
        $a = $svc->send($sender->id, $receiver->id, 'Thanks for the help!', 'general', null, true);

        $this->assertNotNull($a->id);
        $this->assertSame($sender->id, $a->sender_id);
        $this->assertSame($receiver->id, $a->receiver_id);
        $this->assertSame($this->testTenantId, $a->tenant_id);
        $this->assertTrue((bool) $a->is_public);

        $this->assertDatabaseHas('appreciations', [
            'id' => $a->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
        ]);
    }

    public function test_cannot_thank_self(): void
    {
        if (!Schema::hasTable('appreciations')) {
            $this->markTestSkipped('appreciations schema not present.');
        }
        Cache::flush();
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $svc = new AppreciationService();

        $this->expectException(\DomainException::class);
        $svc->send($user->id, $user->id, 'Thanks me');
    }
}
