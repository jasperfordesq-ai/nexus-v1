<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\JobVacancy;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\JobExpiryNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class JobExpiryNotificationServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_notifyExpiringSoon_sends_email_bell_and_idempotency_record(): void
    {
        [$poster, $vacancy] = $this->makeExpiringVacancy('Neighbourhood Coordinator');
        $mailer = $this->fakeEmailDispatchService();
        TenantContext::reset();

        $result = JobExpiryNotificationService::notifyExpiringSoon();

        $this->assertSame(1, $result);
        $this->assertCount(1, $mailer->sends);
        $this->assertSame($poster->email, $mailer->sends[0]['to']);
        $this->assertStringContainsString('Neighbourhood Coordinator', $mailer->sends[0]['subject']);
        $this->assertSame('job_expiry', $mailer->sends[0]['options']['category']);
        $this->assertSame($this->testTenantId, $mailer->sends[0]['options']['tenant_id']);
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $poster->id,
            'type' => 'job_expiry',
            'link' => "/jobs/{$vacancy->id}",
        ]);
        $this->assertDatabaseHas('job_expiry_notifications', [
            'tenant_id' => $this->testTenantId,
            'vacancy_id' => $vacancy->id,
            'notification_type' => 'expiring_soon',
        ]);
    }

    public function test_notifyExpiringSoon_does_not_duplicate_previous_send(): void
    {
        [, $vacancy] = $this->makeExpiringVacancy('Community Designer');
        $mailer = $this->fakeEmailDispatchService();
        TenantContext::reset();

        $first = JobExpiryNotificationService::notifyExpiringSoon();
        $second = JobExpiryNotificationService::notifyExpiringSoon();

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertCount(1, $mailer->sends);
        $this->assertSame(1, DB::table('job_expiry_notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('vacancy_id', $vacancy->id)
            ->where('notification_type', 'expiring_soon')
            ->count());
    }

    public function test_notifyExpiringSoon_does_not_mark_sent_when_email_fails(): void
    {
        [, $vacancy] = $this->makeExpiringVacancy('Volunteer Lead');
        $this->fakeEmailDispatchService(sendResult: false);
        TenantContext::reset();

        $result = JobExpiryNotificationService::notifyExpiringSoon();

        $this->assertSame(0, $result);
        $this->assertDatabaseMissing('job_expiry_notifications', [
            'tenant_id' => $this->testTenantId,
            'vacancy_id' => $vacancy->id,
            'notification_type' => 'expiring_soon',
        ]);
    }

    private function makeExpiringVacancy(string $title): array
    {
        TenantContext::setById($this->testTenantId);
        $poster = User::factory()->forTenant($this->testTenantId)->create([
            'email' => uniqid('job-expiry-', true) . '@example.test',
            'first_name' => 'Jordan',
        ]);
        $vacancy = JobVacancy::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $poster->id,
            'title' => $title,
            'status' => 'open',
            'deadline' => now()->addDays(5),
            'expired_at' => null,
        ]);

        return [$poster, $vacancy];
    }

    private function fakeEmailDispatchService(bool $sendResult = true): EmailDispatchService
    {
        $mailer = new class($sendResult) extends EmailDispatchService {
            /** @var list<array{to:string,subject:string,body:string,options:array<string,mixed>}> */
            public array $sends = [];

            public function __construct(private readonly bool $sendResult)
            {
            }

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->sends[] = [
                    'to' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'options' => $options,
                ];

                return $this->sendResult;
            }
        };

        app()->instance(EmailDispatchService::class, $mailer);

        return $mailer;
    }
}
