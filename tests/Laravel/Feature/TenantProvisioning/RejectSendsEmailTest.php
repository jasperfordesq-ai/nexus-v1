<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\TenantProvisioning;

use App\Models\User;
use App\Services\EmailService;
use App\Services\TenantProvisioning\TenantProvisioningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use Tests\Laravel\TestCase;

class RejectSendsEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reject_sets_status_and_dispatches_rejection_email(): void
    {
        if (! TenantProvisioningService::isAvailable()) {
            $this->markTestSkipped('tenant_provisioning_requests table not migrated');
        }

        $emailMock = Mockery::mock(EmailService::class);
        $emailMock->shouldReceive('send')
            ->atLeast()->once()
            ->withArgs(function ($to, $subject, $html) {
                return is_string($to)
                    && str_contains((string) $to, '@')
                    && is_string($subject) && $subject !== ''
                    && is_string($html) && $html !== '';
            })
            ->andReturn(true);
        $this->app->instance(EmailService::class, $emailMock);

        $superAdmin = User::factory()->create([
            'tenant_id'      => $this->testTenantId,
            'is_super_admin' => true,
        ]);
        Sanctum::actingAs($superAdmin);

        $email = 'reject+' . uniqid('', true) . '@example.org';
        $slug  = 'rej-' . substr(md5(uniqid('', true)), 0, 10);

        $requestId = DB::table(TenantProvisioningService::TABLE)->insertGetId([
            'applicant_name'   => 'Reject Me',
            'applicant_email'  => $email,
            'org_name'         => 'No Coop',
            'country_code'     => 'CH',
            'requested_slug'   => $slug,
            'tenant_category'  => 'community',
            'languages'        => json_encode(['en']),
            'default_language' => 'en',
            'status'           => 'pending',
            'status_token'     => bin2hex(random_bytes(20)),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $reason = 'Outside our supported regions for the current pilot.';

        $response = $this->postJson("/api/v2/super-admin/provisioning-requests/{$requestId}/reject", [
            'reason' => $reason,
        ]);

        $response->assertStatus(200);

        $row = DB::table(TenantProvisioningService::TABLE)->where('id', $requestId)->first();
        $this->assertSame('rejected', $row->status);
        $this->assertSame($reason, $row->rejection_reason);

        // Mockery's expectations are verified on tearDown — the assertion above
        // forces a deterministic check that send() was called.
    }

    public function test_reject_requires_reason(): void
    {
        if (! TenantProvisioningService::isAvailable()) {
            $this->markTestSkipped('tenant_provisioning_requests table not migrated');
        }

        $superAdmin = User::factory()->create([
            'tenant_id'      => $this->testTenantId,
            'is_super_admin' => true,
        ]);
        Sanctum::actingAs($superAdmin);

        $requestId = DB::table(TenantProvisioningService::TABLE)->insertGetId([
            'applicant_name'   => 'Needs Reason',
            'applicant_email'  => 'needsreason@example.org',
            'org_name'         => 'Needs Reason Org',
            'country_code'     => 'CH',
            'requested_slug'   => 'rej2-' . substr(md5(uniqid('', true)), 0, 10),
            'tenant_category'  => 'community',
            'languages'        => json_encode(['en']),
            'default_language' => 'en',
            'status'           => 'pending',
            'status_token'     => bin2hex(random_bytes(20)),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $response = $this->postJson("/api/v2/super-admin/provisioning-requests/{$requestId}/reject", [
            'reason' => '',
        ]);

        $response->assertStatus(422);
    }

    /** @phpstan-ignore-next-line */
    private function suppressUnused(MockInterface $m): void { /* no-op */ }
}
