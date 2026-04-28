<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\CaringCommunity;

use App\Core\TenantContext;
use App\Services\CaringCommunity\CaringHourGiftService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class HourGiftTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2; // hour-timebank

    protected function setUp(): void
    {
        parent::setUp();
        $this->setCaringCommunityFeature(true);
        TenantContext::setById(self::TENANT_ID);
    }

    private function setCaringCommunityFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        $features = [];
        if ($tenant && !empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }
        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode($features)]);
    }

    private function makeUser(string $email, float $balance = 0): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => $email,
            'username'   => 'u_' . substr(md5($email . microtime(true)), 0, 8),
            'password'   => password_hash('password', PASSWORD_BCRYPT),
            'balance'    => $balance,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_send_debits_sender_and_holds_in_pending(): void
    {
        $sender = $this->makeUser('giftsender.' . uniqid() . '@example.com', 20);
        $recipient = $this->makeUser('giftrecip.' . uniqid() . '@example.com', 5);

        $service = app(CaringHourGiftService::class);
        $result = $service->send($sender, $recipient, 8.0, 'For grandma');

        $this->assertSame('pending', $result['status']);

        // Sender debited
        $newBalance = (float) DB::table('users')->where('id', $sender)->value('balance');
        $this->assertEqualsWithDelta(12.0, $newBalance, 0.001);

        // Recipient NOT yet credited
        $recipBal = (float) DB::table('users')->where('id', $recipient)->value('balance');
        $this->assertEqualsWithDelta(5.0, $recipBal, 0.001);

        // Gift row in pending
        $gift = DB::table('caring_hour_gifts')->where('id', $result['gift_id'])->first();
        $this->assertSame('pending', $gift->status);
        $this->assertSame('For grandma', $gift->message);
        $this->assertEqualsWithDelta(8.0, (float) $gift->hours, 0.001);
    }

    public function test_accept_credits_recipient(): void
    {
        $sender = $this->makeUser('s.' . uniqid() . '@x.test', 10);
        $recipient = $this->makeUser('r.' . uniqid() . '@x.test', 0);

        $service = app(CaringHourGiftService::class);
        $sent = $service->send($sender, $recipient, 4.0, null);

        $service->accept($sent['gift_id'], $recipient);

        $recipBal = (float) DB::table('users')->where('id', $recipient)->value('balance');
        $this->assertEqualsWithDelta(4.0, $recipBal, 0.001);

        $gift = DB::table('caring_hour_gifts')->where('id', $sent['gift_id'])->first();
        $this->assertSame('accepted', $gift->status);
        $this->assertNotNull($gift->accepted_at);
    }

    public function test_decline_refunds_sender(): void
    {
        $sender = $this->makeUser('s.' . uniqid() . '@x.test', 10);
        $recipient = $this->makeUser('r.' . uniqid() . '@x.test', 0);

        $service = app(CaringHourGiftService::class);
        $sent = $service->send($sender, $recipient, 3.0, null);

        // Sender went 10 -> 7
        $this->assertEqualsWithDelta(7.0, (float) DB::table('users')->where('id', $sender)->value('balance'), 0.001);

        $service->decline($sent['gift_id'], $recipient, 'Not needed');

        // Sender refunded back to 10
        $this->assertEqualsWithDelta(10.0, (float) DB::table('users')->where('id', $sender)->value('balance'), 0.001);
        // Recipient still 0
        $this->assertEqualsWithDelta(0.0, (float) DB::table('users')->where('id', $recipient)->value('balance'), 0.001);

        $gift = DB::table('caring_hour_gifts')->where('id', $sent['gift_id'])->first();
        $this->assertSame('declined', $gift->status);
        $this->assertSame('Not needed', $gift->decline_reason);
    }

    public function test_revert_refunds_sender(): void
    {
        $sender = $this->makeUser('s.' . uniqid() . '@x.test', 10);
        $recipient = $this->makeUser('r.' . uniqid() . '@x.test', 0);

        $service = app(CaringHourGiftService::class);
        $sent = $service->send($sender, $recipient, 5.0, null);

        $service->revert($sent['gift_id'], $sender);

        $this->assertEqualsWithDelta(10.0, (float) DB::table('users')->where('id', $sender)->value('balance'), 0.001);

        $gift = DB::table('caring_hour_gifts')->where('id', $sent['gift_id'])->first();
        $this->assertSame('reverted', $gift->status);
        $this->assertNotNull($gift->reverted_at);
    }

    public function test_send_fails_with_insufficient_balance(): void
    {
        $sender = $this->makeUser('s.' . uniqid() . '@x.test', 2);
        $recipient = $this->makeUser('r.' . uniqid() . '@x.test', 0);

        $service = app(CaringHourGiftService::class);

        $this->expectExceptionMessage('Insufficient banked hours.');
        $service->send($sender, $recipient, 5.0, null);
    }

    public function test_inbox_returns_only_pending_gifts_for_user(): void
    {
        $sender = $this->makeUser('s.' . uniqid() . '@x.test', 50);
        $recipient = $this->makeUser('r.' . uniqid() . '@x.test', 0);
        $other = $this->makeUser('o.' . uniqid() . '@x.test', 0);

        $service = app(CaringHourGiftService::class);

        // Two pending gifts for recipient
        $service->send($sender, $recipient, 1.0, null);
        $service->send($sender, $recipient, 2.0, null);
        // One pending for someone else
        $service->send($sender, $other, 3.0, null);
        // One that we accept (so it disappears from inbox)
        $accepted = $service->send($sender, $recipient, 4.0, null);
        $service->accept($accepted['gift_id'], $recipient);

        $inbox = $service->myInbox($recipient);
        $this->assertCount(2, $inbox);
        foreach ($inbox as $gift) {
            $this->assertSame('pending', $gift['status']);
        }
    }
}
