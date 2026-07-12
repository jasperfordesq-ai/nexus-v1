<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\EventFederationAction;
use App\Enums\EventFederationTombstoneReason;
use App\Models\Event;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Creates independent per-partner event.federation.* delivery facts. */
final class EventFederationPublisher
{
    public function __construct(
        private readonly EventFederationPayloadBuilder $payloads,
        private readonly EventFederationDeliveryLedger $deliveries,
        private readonly FederationFeatureService $features,
    ) {}

    /** @return array{action:string,partners:int,delivery_ids:list<int>} */
    public function publish(
        Event $event,
        ?EventFederationTombstoneReason $forcedTombstone = null,
    ): array {
        $payload = $this->payloads->build($event, $forcedTombstone);

        return $this->enqueuePayload($payload);
    }

    /** @return array{action:string,partners:int,delivery_ids:list<int>} */
    public function publishDeletion(
        int $tenantId,
        int $eventId,
        int $aggregateVersion,
        int $calendarVersion,
        \DateTimeInterface $occurredAt,
    ): array {
        return $this->enqueuePayload($this->payloads->buildDeletion(
            $tenantId,
            $eventId,
            $aggregateVersion,
            $calendarVersion,
            $occurredAt,
        ));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{action:string,partners:int,delivery_ids:list<int>}
     */
    private function enqueuePayload(array $payload): array
    {
        $tenantId = (int) ($payload['source_tenant_id'] ?? 0);
        $eventId = (int) ($payload['external_id'] ?? 0);
        $action = EventFederationAction::tryFrom((string) ($payload['action'] ?? ''));
        if ($tenantId <= 0 || $eventId <= 0 || $action === null) {
            throw new InvalidArgumentException('event_federation_publish_scope_invalid');
        }

        if ($action === EventFederationAction::Upsert
            && (! TenantContext::hasFeature('events')
                || ! (bool) ($this->features->isOperationAllowed('events', $tenantId)['allowed'] ?? false))) {
            return ['action' => $action->value, 'partners' => 0, 'delivery_ids' => []];
        }

        $partnerIds = $this->partnerIds($tenantId, $eventId, $action);
        $deliveryIds = [];
        foreach ($partnerIds as $externalPartnerId) {
            $row = $this->deliveries->enqueue($tenantId, $eventId, $externalPartnerId, $payload);
            $deliveryIds[] = (int) $row['id'];
        }

        return [
            'action' => $action->value,
            'partners' => count($deliveryIds),
            'delivery_ids' => $deliveryIds,
        ];
    }

    /** @return list<int> */
    private function partnerIds(int $tenantId, int $eventId, EventFederationAction $action): array
    {
        $query = DB::table('federation_external_partners as partner')
            ->where('partner.tenant_id', $tenantId)
            ->where('partner.protocol_type', 'nexus');

        if ($action === EventFederationAction::Upsert) {
            $query->where('partner.status', 'active')->where('partner.allow_events', 1);
        } else {
            $query->whereExists(static function (Builder $evidence) use ($tenantId, $eventId): void {
                $evidence->selectRaw('1')
                    ->from('event_federation_deliveries as delivery')
                    ->whereColumn('delivery.external_partner_id', 'partner.id')
                    ->where('delivery.tenant_id', $tenantId)
                    ->where('delivery.event_id', $eventId);
            });
        }

        return $query->orderBy('partner.id')
            ->pluck('partner.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
