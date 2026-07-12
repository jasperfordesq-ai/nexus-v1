<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Tests\Laravel\TestCase;

final class EventSyncQueueTenantContextTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sync_model_observer_job_preserves_the_outer_tenant(): void
    {
        TenantContext::setById($this->testTenantId);

        User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        self::assertSame($this->testTenantId, TenantContext::currentId());
    }

    public function test_successful_and_failed_sync_jobs_restore_the_outer_tenant(): void
    {
        TenantContext::setById($this->testTenantId);

        dispatch_sync(new SwitchEventTestTenantJob(999, false));
        self::assertSame($this->testTenantId, TenantContext::currentId());

        try {
            dispatch_sync(new SwitchEventTestTenantJob(999, true));
            self::fail('The failing sync job did not throw.');
        } catch (RuntimeException $exception) {
            self::assertSame('sync-tenant-fixture-failure', $exception->getMessage());
        }

        self::assertSame($this->testTenantId, TenantContext::currentId());
    }
}

final class SwitchEventTestTenantJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $tenantId,
        private readonly bool $fail,
    ) {
    }

    public function handle(): void
    {
        TenantContext::setById($this->tenantId);
        if ($this->fail) {
            throw new RuntimeException('sync-tenant-fixture-failure');
        }
    }
}
