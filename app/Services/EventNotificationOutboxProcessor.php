<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EventNotificationOutboxHandler;
use App\Core\TenantContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Bounded scheduled processor for authoritative Event notification facts. */
final class EventNotificationOutboxProcessor
{
    public function __construct(
        private readonly EventNotificationOutboxConsumer $consumer,
        private readonly EventNotificationOutboxHandler $handler,
    ) {}

    /** @return array{claimed:int,processed:int,retrying:int,dead_lettered:int,delivered:int,suppressed:int,stale_released:int,disabled:bool} */
    public function processBatch(?int $limit = null, ?int $tenantId = null): array
    {
        $summary = [
            'claimed' => 0,
            'processed' => 0,
            'retrying' => 0,
            'dead_lettered' => 0,
            'delivered' => 0,
            'suppressed' => 0,
            'stale_released' => 0,
            'disabled' => ! EventNotificationDeliveryModeResolver::consumerEnabled(),
        ];
        if ($summary['disabled']) {
            return $summary;
        }

        $summary['stale_released'] = $this->consumer->releaseStaleClaims($tenantId);
        $rows = $this->consumer->claimBatch(
            $limit ?? max(1, (int) config('events.notification_delivery.batch_size', 50)),
            $tenantId,
        );
        $summary['claimed'] = count($rows);

        foreach ($rows as $row) {
            $outboxId = (int) $row['id'];
            $claimToken = (string) $row['claim_token'];
            try {
                $result = TenantContext::runForTenant(
                    (int) $row['tenant_id'],
                    fn () => $this->handler->handle($row),
                );
                if (! $this->consumer->markProcessed($outboxId, $claimToken)) {
                    throw new \RuntimeException('event_notification_outbox_completion_lost_claim');
                }
                $summary['processed']++;
                $summary['delivered'] += $result->delivered;
                $summary['suppressed'] += $result->suppressed;
            } catch (Throwable $exception) {
                $safeError = EventNotificationErrorSanitizer::sanitize($exception->getMessage());
                $this->consumer->markFailed($outboxId, $claimToken, $safeError);
                $status = $this->status($outboxId);
                $summary[$status === 'dead_letter' ? 'dead_lettered' : 'retrying']++;
                Log::warning('[EventNotificationOutbox] Fact processing failed', [
                    'tenant_id' => (int) $row['tenant_id'],
                    'event_id' => (int) $row['event_id'],
                    'outbox_id' => $outboxId,
                    'action' => (string) $row['action'],
                    'attempt' => (int) $row['attempts'],
                    'status' => $status,
                    'exception' => $exception::class,
                    'reason_code' => mb_substr($safeError, 0, 191),
                ]);
            }
        }

        return $summary;
    }

    private function status(int $outboxId): string
    {
        return (string) \Illuminate\Support\Facades\DB::table('event_domain_outbox')
            ->where('id', $outboxId)
            ->value('status');
    }
}
