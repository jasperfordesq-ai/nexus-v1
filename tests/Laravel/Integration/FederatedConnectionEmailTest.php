<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\FederatedConnectionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class FederatedConnectionEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cross_tenant_connection_request_and_acceptance_send_federation_emails(): void
    {
        $requesterTenantId = $this->createFederationTenant('requester');
        $receiverTenantId = $this->createFederationTenant('receiver');
        $this->createActivePartnership($requesterTenantId, $receiverTenantId);

        $requester = $this->createFederatedUser($requesterTenantId, 'fed-requester');
        $receiver = $this->createFederatedUser($receiverTenantId, 'fed-receiver');

        $mailer = $this->fakeDispatcher();
        app()->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById($requesterTenantId);
        $result = app(FederatedConnectionService::class)->sendRequest(
            (int) $requester->id,
            (int) $receiver->id,
            $receiverTenantId,
            'Hello from a partner community'
        );

        $this->assertTrue($result['success']);
        $this->assertCount(1, $mailer->calls);
        $this->assertSame($receiver->email, $mailer->calls[0]['to']);
        $this->assertSame('federation_connection', $mailer->calls[0]['options']['category']);
        $this->assertSame($receiverTenantId, $mailer->calls[0]['options']['tenant_id']);

        TenantContext::setById($receiverTenantId);
        $accepted = app(FederatedConnectionService::class)->acceptRequest((int) $result['connection_id'], (int) $receiver->id);

        $this->assertTrue($accepted['success']);
        $this->assertCount(2, $mailer->calls);
        $this->assertSame($requester->email, $mailer->calls[1]['to']);
        $this->assertSame('federation_connection', $mailer->calls[1]['options']['category']);
        $this->assertSame($requesterTenantId, $mailer->calls[1]['options']['tenant_id']);
    }

    public function test_connection_request_email_honours_federation_email_opt_out(): void
    {
        $requesterTenantId = $this->createFederationTenant('requester-optout');
        $receiverTenantId = $this->createFederationTenant('receiver-optout');
        $this->createActivePartnership($requesterTenantId, $receiverTenantId);

        $requester = $this->createFederatedUser($requesterTenantId, 'fed-requester-optout');
        $receiver = $this->createFederatedUser($receiverTenantId, 'fed-receiver-optout', emailNotifications: false);

        $mailer = $this->fakeDispatcher();
        app()->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById($requesterTenantId);
        $result = app(FederatedConnectionService::class)->sendRequest(
            (int) $requester->id,
            (int) $receiver->id,
            $receiverTenantId
        );

        $this->assertTrue($result['success']);
        $this->assertCount(0, $mailer->calls);
    }

    private function createFederationTenant(string $suffix): int
    {
        $slug = 'fed-email-' . $suffix . '-' . uniqid();

        return (int) DB::table('tenants')->insertGetId([
            'name' => 'Federation Email ' . $suffix,
            'slug' => $slug,
            'domain' => $slug . '.example.test',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createFederatedUser(int $tenantId, string $prefix, bool $emailNotifications = true): User
    {
        $user = User::factory()->forTenant($tenantId)->create([
            'email' => $prefix . '-' . uniqid('', true) . '@example.test',
            'status' => 'active',
            'is_approved' => true,
            'preferred_language' => 'en',
            'federation_notifications_enabled' => 1,
        ]);

        DB::table('federation_user_settings')->updateOrInsert(
            ['user_id' => (int) $user->id],
            [
                'federation_optin' => 1,
                'profile_visible_federated' => 1,
                'messaging_enabled_federated' => 1,
                'transactions_enabled_federated' => 1,
                'appear_in_federated_search' => 1,
                'email_notifications' => $emailNotifications ? 1 : 0,
                'updated_at' => now(),
            ]
        );

        return $user;
    }

    private function createActivePartnership(int $tenantId, int $partnerTenantId): void
    {
        DB::table('federation_partnerships')->insert([
            'tenant_id' => $tenantId,
            'partner_tenant_id' => $partnerTenantId,
            'canonical_pair' => min($tenantId, $partnerTenantId) . '-' . max($tenantId, $partnerTenantId),
            'status' => 'active',
            'federation_level' => 3,
            'profiles_enabled' => 1,
            'messaging_enabled' => 1,
            'transactions_enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fakeDispatcher(): EmailDispatchService
    {
        return new class extends EmailDispatchService {
            public array $calls = [];

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return true;
            }
        };
    }
}
