<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\TenantProvisioning;

use App\Models\User;
use App\Core\TenantContext;
use App\Services\EmailDispatchService;
use App\Services\TenantProvisioning\TenantProvisioningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class RejectSendsEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reject_sets_status_and_dispatches_rejection_email(): void
    {
        if (! TenantProvisioningService::isAvailable()) {
            $this->markTestSkipped('tenant_provisioning_requests table not migrated');
        }

        $mailer = new TenantProvisioningRejectionMailerStub(true);
        $this->app->instance(EmailDispatchService::class, $mailer);

        $superAdmin = User::factory()->create([
            'tenant_id'      => $this->testTenantId,
            'is_super_admin' => true,
        ]);

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

        TenantProvisioningService::reject($requestId, $reason, (int) $superAdmin->id);

        $row = DB::table(TenantProvisioningService::TABLE)->where('id', $requestId)->first();
        $this->assertSame('rejected', $row->status);
        $this->assertSame($reason, $row->rejection_reason);
        $this->assertCount(1, $mailer->calls);
        $this->assertSame($email, $mailer->calls[0]['to']);
        $this->assertSame('tenant_provisioning', $mailer->calls[0]['options']['category']);
        $this->assertArrayHasKey('tenant_id', $mailer->calls[0]['options']);
        $this->assertNull($mailer->calls[0]['options']['tenant_id']);
        $this->assertTrue($mailer->calls[0]['options']['allow_missing_tenant']);
    }

    public function test_reject_requires_reason(): void
    {
        if (! TenantProvisioningService::isAvailable()) {
            $this->markTestSkipped('tenant_provisioning_requests table not migrated');
        }

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

        $this->expectException(\InvalidArgumentException::class);
        TenantProvisioningService::reject($requestId, '', 1);
    }

    public function test_reject_does_not_mark_request_rejected_when_email_send_fails(): void
    {
        if (! TenantProvisioningService::isAvailable()) {
            $this->markTestSkipped('tenant_provisioning_requests table not migrated');
        }

        $this->app->instance(EmailDispatchService::class, new TenantProvisioningRejectionMailerStub(false));

        $requestId = DB::table(TenantProvisioningService::TABLE)->insertGetId([
            'applicant_name'   => 'Retry Later',
            'applicant_email'  => 'retry-later-' . uniqid('', true) . '@example.org',
            'org_name'         => 'Retry Later Org',
            'country_code'     => 'CH',
            'requested_slug'   => 'retry-' . substr(md5(uniqid('', true)), 0, 10),
            'tenant_category'  => 'community',
            'languages'        => json_encode(['en']),
            'default_language' => 'en',
            'status'           => 'pending',
            'status_token'     => bin2hex(random_bytes(20)),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        try {
            TenantProvisioningService::reject($requestId, 'Email provider is unavailable.', 1);
            $this->fail('Expected tenant provisioning rejection to fail when email dispatch fails.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('rejection email', $e->getMessage());
        }

        $row = DB::table(TenantProvisioningService::TABLE)->where('id', $requestId)->first();
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->rejection_reason);
    }

    public function test_rejection_email_renders_without_stale_tenant_context(): void
    {
        if (! TenantProvisioningService::isAvailable()) {
            $this->markTestSkipped('tenant_provisioning_requests table not migrated');
        }

        $mailer = new TenantProvisioningRejectionMailerStub(true);
        $this->app->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById($this->testTenantId);

        \App\Services\TenantProvisioning\TenantProvisioningMailer::sendRejection([
            'applicant_name' => 'Pre Tenant',
            'applicant_email' => 'pre-tenant-' . uniqid('', true) . '@example.org',
            'org_name' => 'Pre Tenant Org',
            'default_language' => 'en',
        ], 'Not approved yet.');

        $this->assertCount(1, $mailer->calls);
        $this->assertStringNotContainsString('/hour-timebank/notifications', $mailer->calls[0]['body']);
        $this->assertSame($this->testTenantId, TenantContext::currentId());
    }
}

class TenantProvisioningRejectionMailerStub extends EmailDispatchService
{
    public array $calls = [];

    public function __construct(private readonly bool $result)
    {
    }

    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $this->calls[] = compact('to', 'subject', 'body', 'options');

        return $this->result;
    }
}
