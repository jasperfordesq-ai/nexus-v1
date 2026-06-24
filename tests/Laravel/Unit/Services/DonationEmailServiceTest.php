<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\DonationEmailService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;

/**
 * DonationEmailServiceTest
 *
 * Strategy:
 *  DonationEmailService::sendDonationEmails() is STATIC and returns
 *  ['donor_sent' => bool, 'recipient_sent' => bool].
 *
 *  In the test environment Mail is set to 'array' driver (see phpunit.xml /
 *  .env.testing), so EmailDispatchService::sendRaw returns false (SMTP
 *  connect fails / mail logged but not delivered).  We do NOT assert that
 *  the emails actually land in an inbox — we assert:
 *    (a) RETURN SHAPE: always an array with exactly the two bool keys.
 *    (b) GUARD — empty email on donor/recipient → that side is false and
 *        the OTHER side is still attempted independently.
 *    (c) TenantContext is restored to its prior value after the call.
 *    (d) Both sides return the send-attempt result (bool) for valid emails.
 *    (e) Null message is handled gracefully (falls back to translation key).
 *    (f) Optional personal message is accepted without error.
 *    (g) Amount=0 is accepted (no guard for zero amount in this service).
 *    (h) Both empty emails → both false, no exception thrown.
 *
 * Skipped: live SMTP / SendGrid delivery (no credentials in test env).
 */
class DonationEmailServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    /** A minimal donor object with all fields the service may access. */
    private function makeDonor(string $email = 'donor@example.test'): object
    {
        return (object) [
            'id'         => 1,
            'email'      => $email,
            'first_name' => 'Dee',
            'last_name'  => 'Donor',
            'name'       => 'Dee Donor',
            'preferred_language' => 'en',
        ];
    }

    /** A minimal recipient object. */
    private function makeRecipient(string $email = 'recipient@example.test'): object
    {
        return (object) [
            'id'         => 2,
            'email'      => $email,
            'first_name' => 'Rex',
            'last_name'  => 'Receiver',
            'name'       => 'Rex Receiver',
            'preferred_language' => 'en',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ── Return shape ──────────────────────────────────────────────────────────

    public function test_sendDonationEmails_returns_array_with_two_bool_keys(): void
    {
        $result = DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $this->makeDonor(),
            $this->makeRecipient(),
            5.0,
            null
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('donor_sent', $result);
        $this->assertArrayHasKey('recipient_sent', $result);
        $this->assertIsBool($result['donor_sent']);
        $this->assertIsBool($result['recipient_sent']);
    }

    // ── Guard: empty donor email ───────────────────────────────────────────

    public function test_sendDonationEmails_donor_sent_false_when_donor_email_empty(): void
    {
        $donor = $this->makeDonor('');   // empty email

        $result = DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $donor,
            $this->makeRecipient(),
            3.0,
            null
        );

        $this->assertFalse($result['donor_sent']);
        // Recipient side must still have been attempted
        $this->assertIsBool($result['recipient_sent']);
    }

    // ── Guard: empty recipient email ───────────────────────────────────────

    public function test_sendDonationEmails_recipient_sent_false_when_recipient_email_empty(): void
    {
        $recipient = $this->makeRecipient('');

        $result = DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $this->makeDonor(),
            $recipient,
            3.0,
            null
        );

        $this->assertFalse($result['recipient_sent']);
        // Donor side must still have been attempted
        $this->assertIsBool($result['donor_sent']);
    }

    // ── Guard: both emails empty ──────────────────────────────────────────

    public function test_sendDonationEmails_both_false_when_both_emails_empty(): void
    {
        $result = DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $this->makeDonor(''),
            $this->makeRecipient(''),
            5.0,
            'A personal note'
        );

        $this->assertFalse($result['donor_sent']);
        $this->assertFalse($result['recipient_sent']);
    }

    // ── TenantContext restoration ─────────────────────────────────────────

    public function test_sendDonationEmails_restores_tenant_context_afterward(): void
    {
        $priorTenant = TenantContext::currentId();

        DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $this->makeDonor(),
            $this->makeRecipient(),
            1.0,
            null
        );

        // TenantContext should be the same as before the call
        $this->assertSame($priorTenant, TenantContext::currentId());
    }

    public function test_sendDonationEmails_restores_context_even_with_empty_emails(): void
    {
        $priorTenant = TenantContext::currentId();

        DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $this->makeDonor(''),
            $this->makeRecipient(''),
            2.0,
            null
        );

        $this->assertSame($priorTenant, TenantContext::currentId());
    }

    // ── Null vs string message ────────────────────────────────────────────

    public function test_sendDonationEmails_accepts_null_message_without_exception(): void
    {
        $result = DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $this->makeDonor(),
            $this->makeRecipient(),
            4.0,
            null     // no message → falls back to translation key
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('donor_sent', $result);
        $this->assertArrayHasKey('recipient_sent', $result);
    }

    public function test_sendDonationEmails_accepts_personal_message_without_exception(): void
    {
        $result = DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $this->makeDonor(),
            $this->makeRecipient(),
            2.5,
            'Thank you so much for your help!'
        );

        $this->assertIsArray($result);
        $this->assertIsBool($result['donor_sent']);
        $this->assertIsBool($result['recipient_sent']);
    }

    // ── Amount edge cases ─────────────────────────────────────────────────

    public function test_sendDonationEmails_accepts_zero_amount(): void
    {
        // Service has no guard for zero amount; it should complete without exception
        $result = DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $this->makeDonor(),
            $this->makeRecipient(),
            0.0,
            null
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('donor_sent', $result);
        $this->assertArrayHasKey('recipient_sent', $result);
    }

    public function test_sendDonationEmails_accepts_large_amount(): void
    {
        $result = DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $this->makeDonor(),
            $this->makeRecipient(),
            1000.0,
            null
        );

        $this->assertIsArray($result);
        $this->assertIsBool($result['donor_sent']);
        $this->assertIsBool($result['recipient_sent']);
    }

    // ── Minimal objects (only email field) ───────────────────────────────

    public function test_sendDonationEmails_works_with_minimal_objects(): void
    {
        // Objects with ONLY email — no first_name, last_name, etc.
        $minimalDonor     = (object) ['email' => 'min-donor@example.test', 'id' => 99];
        $minimalRecipient = (object) ['email' => 'min-recip@example.test', 'id' => 98];

        $result = DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $minimalDonor,
            $minimalRecipient,
            7.0,
            null
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('donor_sent', $result);
        $this->assertArrayHasKey('recipient_sent', $result);
        // No exception should have been thrown
        $this->assertTrue(true);
    }

    // ── Return count invariant ────────────────────────────────────────────

    public function test_sendDonationEmails_result_has_exactly_two_keys(): void
    {
        $result = DonationEmailService::sendDonationEmails(
            self::TENANT_ID,
            $this->makeDonor(),
            $this->makeRecipient(),
            1.0,
            'Note'
        );

        $this->assertCount(2, $result);
    }
}
