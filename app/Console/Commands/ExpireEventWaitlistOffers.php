<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Exceptions\EventWaitlistException;
use App\Services\EventWaitlistService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Bounded tenant-scoped expiry pass for timed waitlist offers. */
final class ExpireEventWaitlistOffers extends Command
{
    protected $signature = 'events:expire-waitlist-offers
        {--tenant= : Process one tenant only}
        {--limit=250 : Maximum due offers processed across the run}';

    protected $description = 'Expire due event waitlist offers and advance one queue place per release.';

    public function handle(EventWaitlistService $waitlist): int
    {
        if (! Schema::hasTable('event_waitlist_entries')
            || ! Schema::hasTable('event_waitlist_offer_envelopes')
            || ! Schema::hasTable('tenants')) {
            $this->warn('Event waitlist offer schema is unavailable.');
            return self::SUCCESS;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);
        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 1000.');
            return self::INVALID;
        }
        $tenantOption = $this->option('tenant');
        $tenantId = null;
        if ($tenantOption !== null && $tenantOption !== '') {
            $tenantId = filter_var($tenantOption, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($tenantId === false) {
                $this->error('The --tenant option must be a positive integer.');
                return self::INVALID;
            }
            $tenantId = (int) $tenantId;
        }

        $now = now();
        $due = DB::table('event_waitlist_entries as entry')
            ->join('tenants as tenant', 'tenant.id', '=', 'entry.tenant_id')
            ->where('tenant.is_active', 1)
            ->where('entry.queue_state', 'offered')
            ->whereNotNull('entry.offer_expires_at')
            ->where('entry.offer_expires_at', '<=', $now)
            ->when($tenantId !== null, fn ($query) => $query->where('entry.tenant_id', $tenantId))
            ->orderBy('entry.offer_expires_at')
            ->orderBy('entry.id')
            ->limit((int) $limit)
            ->get(['entry.tenant_id', 'entry.event_id', 'entry.id']);
        if ($due->isEmpty()) {
            $this->info('No due event waitlist offers.');
            return self::SUCCESS;
        }

        $previousTenantId = TenantContext::currentId();
        $processed = 0;
        $advanced = 0;
        $errors = 0;
        try {
            foreach ($due->groupBy(fn (object $row): string => (
                (int) $row->tenant_id . ':' . (int) $row->event_id
            )) as $rows) {
                if ($processed >= $limit) {
                    break;
                }
                $first = $rows->first();
                if (! is_object($first)) {
                    continue;
                }
                $currentTenantId = (int) $first->tenant_id;
                $eventId = (int) $first->event_id;
                try {
                    if (! TenantContext::setById($currentTenantId)
                        || ! TenantContext::hasFeature('events')) {
                        continue;
                    }
                    $remaining = (int) $limit - $processed;
                    $results = $waitlist->expireDueForEvent(
                        $eventId,
                        null,
                        min($remaining, $rows->count()),
                        $now,
                    );
                    $processed += count($results);
                    foreach ($results as $result) {
                        if ($result->nextOfferedEntry !== null) {
                            $advanced++;
                        }
                    }
                } catch (Throwable $exception) {
                    $errors++;
                    Log::error('[EventsWaitlistExpiry] tenant/event failure', [
                        'tenant_id' => $currentTenantId,
                        'event_id' => $eventId,
                        'exception' => $exception::class,
                        'reason_code' => $exception instanceof EventWaitlistException
                            ? $exception->reasonCode
                            : null,
                    ]);
                }
            }
        } finally {
            TenantContext::restoreAfterScopedListener($previousTenantId);
        }

        $this->info(sprintf(
            'Event waitlist offers: expired=%d advanced=%d errors=%d limit=%d',
            $processed,
            $advanced,
            $errors,
            $limit,
        ));

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
