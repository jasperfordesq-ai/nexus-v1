<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Core\TenantContext;
use App\Models\Event;
use App\Observers\Concerns\IndexesEmbeddings;
use App\Services\EventFederationPublisher;
use App\Services\EventSearchIndexService;
use App\Support\Events\EventSearchVisibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the Meilisearch events index in sync with the events table.
 *
 * Every external-index mutation runs after the surrounding database commit.
 * Draft/moderation/template/archived/cancelled/completed rows are removals,
 * never index additions.
 */
class EventObserver
{
    use IndexesEmbeddings;

    public function __construct(
        private readonly EventSearchIndexService $searchIndex = new EventSearchIndexService(),
    ) {
    }

    /** @var list<string> */
    private const SEARCH_AFFECTING_FIELDS = [
        'title',
        'description',
        'location',
        'status',
        'publication_status',
        'operational_status',
        'is_recurring_template',
        'start_time',
        'allow_remote_attendance',
        'user_id',
    ];

    public function created(Event $event): void
    {
        $this->scheduleSynchronization($event, 'created');
    }

    public function updated(Event $event): void
    {
        $dirty = array_keys($event->getDirty());

        if (array_intersect($dirty, self::SEARCH_AFFECTING_FIELDS) === []) {
            return;
        }

        $this->scheduleSynchronization($event, 'updated');
    }

    public function deleted(Event $event): void
    {
        $tenantId = (int) $event->getAttribute('tenant_id');
        $eventId = (int) $event->getKey();
        if ($tenantId <= 0 || $eventId <= 0) {
            return;
        }

        if (Schema::hasColumn('events', 'federation_version')
            && Schema::hasTable('event_federation_deliveries')
            && Schema::hasTable('federation_external_partners')) {
            TenantContext::runForTenant($tenantId, static function () use ($event, $tenantId, $eventId): void {
                app(EventFederationPublisher::class)->publishDeletion(
                    $tenantId,
                    $eventId,
                    max(1, (int) ($event->getRawOriginal('federation_version') ?? 1)) + 1,
                    max(0, (int) ($event->getRawOriginal('calendar_sequence') ?? 0)),
                    now(),
                );
            });
        }

        DB::afterCommit(function () use ($tenantId, $eventId): void {
            $identity = $this->identity($tenantId, $eventId);
            try {
                $this->searchIndex->remove($eventId);
            } catch (\Throwable $exception) {
                Log::error('EventObserver: failed to remove deleted event from index', [
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'error' => $exception->getMessage(),
                ]);
            }
            $this->deleteEmbedding($identity, 'event');
        });
    }

    private function scheduleSynchronization(Event $event, string $operation): void
    {
        $tenantId = (int) $event->getAttribute('tenant_id');
        $eventId = (int) $event->getKey();
        if ($tenantId <= 0 || $eventId <= 0) {
            return;
        }

        DB::afterCommit(function () use ($tenantId, $eventId, $operation): void {
            try {
                TenantContext::runForTenant($tenantId, function () use ($tenantId, $eventId, $operation): void {
                    $current = Event::withoutGlobalScopes()
                        ->with('user')
                        ->where('tenant_id', $tenantId)
                        ->whereKey($eventId)
                        ->first();

                    if ($current === null) {
                        try {
                            $this->searchIndex->remove($eventId);
                        } catch (\Throwable $exception) {
                            $this->logSynchronizationFailure($operation, $tenantId, $eventId, $exception);
                        }
                        $this->deleteEmbedding($this->identity($tenantId, $eventId), 'event');
                        return;
                    }

                    try {
                        $this->searchIndex->synchronize($current);
                    } catch (\Throwable $exception) {
                        $this->logSynchronizationFailure($operation, $tenantId, $eventId, $exception);
                    }
                    if (EventSearchVisibility::isDiscoverable($current)) {
                        $this->reindexEmbedding($current, 'event');
                    } else {
                        $this->deleteEmbedding($current, 'event');
                    }
                });
            } catch (\Throwable $exception) {
                $this->logSynchronizationFailure($operation, $tenantId, $eventId, $exception);
            }
        });
    }

    private function logSynchronizationFailure(
        string $operation,
        int $tenantId,
        int $eventId,
        \Throwable $exception,
    ): void {
        Log::error('EventObserver: failed to synchronize event search indexes', [
            'operation' => $operation,
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function identity(int $tenantId, int $eventId): Event
    {
        $event = new Event();
        $event->setAttribute('tenant_id', $tenantId);
        $event->setAttribute($event->getKeyName(), $eventId);

        return $event;
    }
}
