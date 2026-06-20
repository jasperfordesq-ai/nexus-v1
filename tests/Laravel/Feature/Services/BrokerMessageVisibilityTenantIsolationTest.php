<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Services;

use App\Core\TenantContext;
use App\Models\Message;
use App\Models\User;
use App\Services\BrokerMessageVisibilityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Cross-tenant isolation for broker message visibility (journey J7 seam).
 *
 * A broker operating in tenant A must NEVER be able to pull a message that
 * belongs to tenant B into the broker review queue. copyMessageForBroker()
 * scopes the message lookup by TenantContext::getId(), so a foreign message is
 * invisible: it returns null and creates no broker_message_copies row.
 *
 * Companion to the mock-based BrokerMessageVisibilityServiceTest (unit) — this
 * one asserts the tenant boundary against the real database.
 */
class BrokerMessageVisibilityTenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private BrokerMessageVisibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BrokerMessageVisibilityService::class);
    }

    public function test_cannot_copy_a_message_from_another_tenant(): void
    {
        $otherTenantId = 999; // seeded active by the base TestCase

        // Build the foreign message entirely within tenant 999 so any model
        // observer stamps the correct tenant.
        TenantContext::setById($otherTenantId);
        $sender = User::factory()->forTenant($otherTenantId)->create();
        $receiver = User::factory()->forTenant($otherTenantId)->create();
        $foreign = Message::factory()->create([
            'tenant_id' => $otherTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => 'Private message that lives in another tenant',
        ]);

        // Now act as a broker in the test tenant (2).
        TenantContext::setById($this->testTenantId);

        $result = $this->service->copyMessageForBroker(
            (int) $foreign->id,
            BrokerMessageVisibilityService::REASON_FIRST_CONTACT
        );

        $this->assertNull($result, 'A broker in tenant 2 must not be able to copy a tenant-999 message');
        $this->assertSame(
            0,
            (int) DB::table('broker_message_copies')->where('original_message_id', $foreign->id)->count(),
            'No broker copy row may be created for a cross-tenant message'
        );
    }

    public function test_in_tenant_message_is_copyable_positive_control(): void
    {
        // Proves the null above is tenant scoping, not a broken method: the same
        // call on an in-tenant message DOES create exactly one broker copy.
        TenantContext::setById($this->testTenantId);
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();
        $msg = Message::factory()->create([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => 'An in-tenant message',
        ]);

        // Factory create() observers drift TenantContext; re-pin before the
        // tenant-scoped service call (same gotcha as the wallet suite).
        TenantContext::setById($this->testTenantId);

        $copyId = $this->service->copyMessageForBroker(
            (int) $msg->id,
            BrokerMessageVisibilityService::REASON_FIRST_CONTACT
        );

        $this->assertNotNull($copyId, 'An in-tenant message should be copyable to the broker queue');
        $this->assertSame(
            1,
            (int) DB::table('broker_message_copies')
                ->where('tenant_id', $this->testTenantId)
                ->where('original_message_id', $msg->id)
                ->count(),
            'Exactly one broker copy row for an in-tenant message'
        );
    }
}
