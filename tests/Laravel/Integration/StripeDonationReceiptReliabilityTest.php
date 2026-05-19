<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\StripeDonationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class StripeDonationReceiptReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_completed_donation_without_receipt_evidence_is_retryable(): void
    {
        $tenantId = $this->testTenantId;
        $user = User::factory()->forTenant($tenantId)->create([
            'email' => 'donation-receipt-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $paymentIntentId = 'pi_donation_receipt_' . uniqid();
        $donationId = (int) DB::table('vol_donations')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'amount' => 10.00,
            'currency' => 'EUR',
            'payment_method' => 'stripe',
            'payment_reference' => '',
            'donor_name' => $user->name ?? 'Donation User',
            'donor_email' => $user->email,
            'is_anonymous' => 0,
            'status' => 'pending',
            'stripe_payment_intent_id' => $paymentIntentId,
            'created_at' => now(),
        ]);

        app()->instance(EmailDispatchService::class, $this->fakeMailer(false));

        try {
            StripeDonationService::handlePaymentSucceeded((object) [
                'id' => $paymentIntentId,
                'metadata' => (object) ['nexus_tenant_id' => $tenantId],
            ]);
            $this->fail('Expected donation receipt failure to keep webhook retryable.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Donation receipt email was not sent', $e->getMessage());
        }

        $failed = DB::table('vol_donations')->where('id', $donationId)->first();
        $this->assertSame('completed', $failed->status);
        $this->assertNull($failed->receipt_email_sent_at);
        $this->assertNotNull($failed->receipt_email_failed_at);

        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);

        StripeDonationService::handlePaymentSucceeded((object) [
            'id' => $paymentIntentId,
            'metadata' => (object) ['nexus_tenant_id' => $tenantId],
        ]);

        $repaired = DB::table('vol_donations')->where('id', $donationId)->first();
        $this->assertNotNull($repaired->receipt_email_sent_at);
        $this->assertNull($repaired->receipt_email_failed_at);
        $this->assertCount(1, $mailer->calls);
        $this->assertSame('donation_receipt', $mailer->calls[0]['options']['category']);
        $this->assertSame($tenantId, $mailer->calls[0]['options']['tenant_id']);
    }

    private function fakeMailer(bool $result): EmailDispatchService
    {
        return new class($result) extends EmailDispatchService {
            public array $calls = [];

            public function __construct(private bool $result)
            {
            }

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return $this->result;
            }
        };
    }
}
