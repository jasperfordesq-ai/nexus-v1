<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Safeguarding;

use App\Core\TenantContext;
use App\Models\Listing;
use App\Models\User;
use App\Services\ExchangeWorkflowService;
use App\Services\GroupExchangeService;
use App\Services\SafeguardingJurisdictionService;
use App\Services\SafeguardingTriggerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class SafeguardingExchangePolicyTest extends TestCase
{
    use DatabaseTransactions;

    private GroupExchangeService $groupExchanges;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        $this->groupExchanges = app(GroupExchangeService::class);
        $this->configureEnglandWales();
    }

    public function test_exchange_request_to_protected_listing_owner_creates_no_exchange_or_notification(): void
    {
        $requester = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $provider = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $listing = Listing::factory()->forTenant($this->testTenantId)->offer()->create([
            'user_id' => $provider->id,
        ]);
        $this->protectMember($provider);
        TenantContext::setById($this->testTenantId);

        $notificationsBefore = DB::table('notifications')->count();
        $result = ExchangeWorkflowService::createRequest(
            $requester->id,
            (int) $listing->id,
            ['message' => 'This must not be stored.'],
        );

        $this->assertNull($result);
        $this->assertDatabaseMissing('exchange_requests', [
            'tenant_id' => $this->testTenantId,
            'requester_id' => $requester->id,
            'provider_id' => $provider->id,
        ]);
        $this->assertSame($notificationsBefore, DB::table('notifications')->count());
    }

    public function test_group_exchange_inline_participants_are_validated_before_group_is_created(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $protected = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $this->protectMember($protected);
        TenantContext::setById($this->testTenantId);

        $result = $this->groupExchanges->create($organizer->id, [
            'title' => 'Protected group exchange',
            'total_hours' => 2,
            'participants' => [[
                'user_id' => $protected->id,
                'role' => 'receiver',
                'hours' => 2,
            ]],
        ]);

        $this->assertNull($result);
        $this->assertDatabaseMissing('group_exchanges', [
            'tenant_id' => $this->testTenantId,
            'title' => 'Protected group exchange',
        ]);
        $this->assertSame('VETTING_REQUIRED', $this->groupExchanges->getLastContactRestriction()?->code);
    }

    public function test_group_exchange_start_rechecks_policy_after_member_becomes_protected(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $provider = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $receiver = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        TenantContext::setById($this->testTenantId);

        $exchangeId = $this->groupExchanges->create($organizer->id, [
            'title' => 'Rechecked group exchange',
            'status' => 'draft',
            'split_type' => 'custom',
            'total_hours' => 2,
            'participants' => [
                ['user_id' => $provider->id, 'role' => 'provider', 'hours' => 2],
                ['user_id' => $receiver->id, 'role' => 'receiver', 'hours' => 2],
            ],
        ]);
        $this->assertNotNull($exchangeId);

        $this->protectMember($receiver);
        TenantContext::setById($this->testTenantId);
        $result = $this->groupExchanges->start((int) $exchangeId);

        $this->assertFalse($result['success']);
        $this->assertSame('VETTING_REQUIRED', $result['code']);
        $this->assertSame(
            'draft',
            DB::table('group_exchanges')->where('id', $exchangeId)->value('status'),
        );
    }

    private function configureEnglandWales(): void
    {
        DB::table('tenant_safeguarding_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId],
            [
                'jurisdiction' => 'england_wales',
                'policy_version' => 'safeguarded-contact-v1:exchange-test',
                'configured_by' => null,
                'configured_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        app(SafeguardingJurisdictionService::class)->forget($this->testTenantId);
    }

    private function protectMember(User $member): void
    {
        $optionId = (int) DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'option_key' => 'exchange_protected_' . uniqid(),
            'option_type' => 'checkbox',
            'label' => 'Protected exchange contact test',
            'description' => 'Protected exchange contact test',
            'sort_order' => 0,
            'is_active' => 1,
            'is_required' => 0,
            'triggers' => json_encode([
                'requires_vetted_interaction' => true,
                'vetting_type_required' => 'dbs_enhanced',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_safeguarding_preferences')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'option_id' => $optionId,
            'selected_value' => '1',
            'consent_given_at' => now(),
            'consent_ip' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SafeguardingTriggerService::invalidateCache($member->id, $this->testTenantId);
    }
}
