<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Wallet;

use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\User;
use App\Services\CreditDonationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: the user-facing error strings returned by the credit-donation
 * service must be translated. `donateToCommunityFund`/`donateToMember` returned
 * hardcoded English ("Amount must be greater than 0", "Donation failed. Check
 * balance and recipient."), and the wallet `donate` controller surfaces
 * `$result['error']` straight to the caller — so a non-English member saw
 * English. They now render via `__()`.
 */
class CreditDonationLocaleTest extends TestCase
{
    use DatabaseTransactions;

    private function donorInTenant(float $balance): User
    {
        $u = User::factory()->forTenant($this->testTenantId)->create(['balance' => $balance]);
        DB::table('users')->where('id', $u->id)->update(['tenant_id' => $this->testTenantId]);

        return $u;
    }

    public function test_donation_failure_message_is_localised(): void
    {
        $donor = $this->donorInTenant(100);
        $svc = app(CreditDonationService::class);

        // Recipient 999999 does not exist -> donate() returns false -> wrapper
        // returns the (now localised) failure message.
        $resultGa = TenantContext::runForTenant($this->testTenantId, fn () =>
            LocaleContext::withLocale('ga', fn () => $svc->donateToMember((int) $donor->id, 999999, 5.0, '')));

        $this->assertFalse($resultGa['success']);
        $expectedGa = LocaleContext::withLocale('ga', fn () => __('api.donation_failed_check_recipient'));
        $expectedEn = LocaleContext::withLocale('en', fn () => __('api.donation_failed_check_recipient'));
        $this->assertSame($expectedGa, $resultGa['error']);
        $this->assertNotSame($expectedEn, $resultGa['error'], 'failure message must be in the caller locale, not English');
        $this->assertStringNotContainsString('Donation failed. Check balance', (string) $resultGa['error']);
    }
}
