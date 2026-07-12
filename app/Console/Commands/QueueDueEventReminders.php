<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Services\EventReminderScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Bounded recovery/reconciliation and due-fact producer for Event reminders. */
final class QueueDueEventReminders extends Command
{
    protected $signature = 'events:queue-reminders
        {--tenant= : Process one tenant only}
        {--limit=200 : Maximum registrations and due schedules per tenant}';

    protected $description = 'Reconcile Event reminder schedules and queue durable due facts.';

    public function handle(EventReminderScheduleService $schedules): int
    {
        if (! Schema::hasTable('event_reminder_schedules')
            || ! Schema::hasTable('event_domain_outbox')
            || ! Schema::hasTable('tenants')) {
            $this->warn('Event reminder schedule schema is unavailable.');
            return self::SUCCESS;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);
        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 1000.');
            return self::INVALID;
        }
        $tenantId = $this->tenantOption();
        if ($tenantId === false) {
            return self::INVALID;
        }

        $tenantIds = DB::table('tenants')
            ->where('is_active', 1)
            ->when($tenantId !== null, static fn ($query) => $query->where('id', $tenantId))
            ->orderBy('id')
            ->pluck('id');
        $previousTenantId = TenantContext::currentId();
        $summary = ['reconciled' => 0, 'queued' => 0, 'shadowed' => 0, 'suppressed' => 0, 'cancelled' => 0, 'errors' => 0];
        try {
            foreach ($tenantIds as $currentTenantId) {
                $currentTenantId = (int) $currentTenantId;
                try {
                    if (! TenantContext::setById($currentTenantId)) {
                        throw new \RuntimeException('event_reminder_tenant_unavailable');
                    }
                    if (! TenantContext::hasFeature('events')) {
                        $summary['cancelled'] += $schedules->cancelForDisabledTenant();
                        continue;
                    }
                    if ($schedules->rolloutMode() === 'legacy') {
                        continue;
                    }
                    $summary['reconciled'] += $schedules->reconcileDrift((int) $limit);
                    $queued = $schedules->queueDueSchedules((int) $limit);
                    $summary['queued'] += $queued['queued'];
                    $summary['shadowed'] += $queued['shadowed'];
                    $summary['suppressed'] += $queued['suppressed'];
                } catch (Throwable $exception) {
                    $summary['errors']++;
                    Log::error('[EventReminderQueue] tenant pass failed', [
                        'tenant_id' => $currentTenantId,
                        'exception' => $exception::class,
                        'reason_code' => $exception->getMessage(),
                    ]);
                }
            }
        } finally {
            TenantContext::restoreAfterScopedListener($previousTenantId);
        }

        $this->info(sprintf(
            'Event reminders: reconciled=%d queued=%d shadowed=%d suppressed=%d cancelled=%d errors=%d',
            $summary['reconciled'],
            $summary['queued'],
            $summary['shadowed'],
            $summary['suppressed'],
            $summary['cancelled'],
            $summary['errors'],
        ));

        return $summary['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function tenantOption(): int|false|null
    {
        $value = $this->option('tenant');
        if ($value === null || $value === '') {
            return null;
        }
        $tenantId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($tenantId === false) {
            $this->error('The --tenant option must be a positive integer.');
            return false;
        }

        return (int) $tenantId;
    }
}
