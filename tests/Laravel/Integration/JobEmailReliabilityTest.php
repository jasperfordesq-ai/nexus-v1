<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\JobApplication;
use App\Models\JobInterview;
use App\Models\JobVacancy;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\JobInterviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class JobEmailReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_interview_reminders_send_separate_24h_and_1h_windows(): void
    {
        TenantContext::setById($this->testTenantId);
        $poster = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'job-poster@example.test',
        ]);
        $candidate = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'job-candidate@example.test',
        ]);
        $vacancy = JobVacancy::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $poster->id,
            'title' => 'Community Coordinator',
            'status' => 'open',
        ]);
        $application = JobApplication::factory()->forTenant($this->testTenantId)->create([
            'vacancy_id' => $vacancy->id,
            'user_id' => $candidate->id,
        ]);
        $interviewId = DB::table('job_interviews')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'vacancy_id' => $vacancy->id,
            'application_id' => $application->id,
            'proposed_by' => $poster->id,
            'interview_type' => 'video',
            'scheduled_at' => now()->addHours(2),
            'duration_mins' => 30,
            'status' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mailer = $this->fakeEmailDispatchService();
        $this->assertSame(1, JobInterview::withoutGlobalScopes()
            ->where('id', $interviewId)
            ->where('scheduled_at', '>', now())
            ->where('scheduled_at', '<=', now()->addHours(24))
            ->whereNull('reminder_24h_sent_at')
            ->count());
        TenantContext::reset();

        $first = JobInterviewService::sendReminders();
        TenantContext::reset();
        DB::table('job_interviews')->where('id', $interviewId)->update(['scheduled_at' => now()->addMinutes(45)]);
        $second = JobInterviewService::sendReminders();

        $row = DB::table('job_interviews')->where('id', $interviewId)->first();
        $this->assertSame(['reminders_sent' => 1, 'errors' => 0], $first);
        $this->assertSame(['reminders_sent' => 1, 'errors' => 0], $second);
        $this->assertNotNull($row->reminder_24h_sent_at);
        $this->assertNotNull($row->reminder_1h_sent_at);
        $this->assertCount(4, $mailer->sends);
        $this->assertSame(['job_interview'], array_values(array_unique(array_column(array_column($mailer->sends, 'options'), 'category'))));
    }

    private function fakeEmailDispatchService(): EmailDispatchService
    {
        $mailer = new class extends EmailDispatchService {
            /** @var list<array{to:string,subject:string,body:string,options:array<string,mixed>}> */
            public array $sends = [];

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->sends[] = [
                    'to' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'options' => $options,
                ];

                return true;
            }
        };

        app()->instance(EmailDispatchService::class, $mailer);

        return $mailer;
    }
}
