<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Enums\EventFederationAction;
use App\Enums\EventFederationInboundDecision;
use App\Http\Resources\EventFederationStatusResource;
use App\Services\FederationExternalApiClient;
use App\Support\Events\EventFederationInboundResult;
use App\Support\Events\EventFederationReceiptContract;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class EventFederationPhaseBStaticTest extends TestCase
{
    public function test_receipt_is_explicit_versioned_and_contains_no_local_or_payload_evidence(): void
    {
        $receipt = EventFederationReceiptContract::fromResult(new EventFederationInboundResult(
            EventFederationInboundDecision::Accepted,
            991,
            EventFederationAction::Upsert,
            14,
            8,
            str_repeat('a', 64),
        ));

        EventFederationReceiptContract::assertValid($receipt);
        self::assertSame([
            'contract',
            'contract_version',
            'decision',
            'action',
            'event_aggregate_version',
            'event_calendar_version',
            'received_at',
        ], array_keys($receipt));
        $encoded = json_encode($receipt, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('991', $encoded);
        self::assertStringNotContainsString(str_repeat('a', 64), $encoded);
        self::assertStringNotContainsString('payload', $encoded);
    }

    public function test_receipt_must_match_the_delivered_version_vector(): void
    {
        $receipt = [
            'contract' => EventFederationReceiptContract::SCHEMA,
            'contract_version' => EventFederationReceiptContract::SCHEMA_VERSION,
            'decision' => 'replay',
            'action' => 'tombstone',
            'event_aggregate_version' => 7,
            'event_calendar_version' => 3,
            'received_at' => '2026-07-11T12:00:00+00:00',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('event_federation_receipt_delivery_mismatch');
        EventFederationReceiptContract::assertMatchesDelivery($receipt, [
            'action' => 'tombstone',
            'event_aggregate_version' => 8,
            'event_calendar_version' => 3,
        ]);
    }

    public function test_status_resource_drops_payload_claim_hash_credential_and_raw_error_material(): void
    {
        $resource = EventFederationStatusResource::fromSummary([
            'event_id' => 41,
            'federation_version' => 9,
            'visibility' => 'listed',
            'configured_partners' => 1,
            'recipient_partners' => 1,
            'health' => 'degraded',
            'counts' => ['dead_letter' => 1],
            'partners' => [[
                'partner_id' => 6,
                'partner_name' => 'Partner Six',
                'partner_status' => 'active',
                'events_enabled' => true,
                'action' => 'upsert',
                'delivery_status' => 'dead_letter',
                'attempts' => 5,
                'max_attempts' => 5,
                'aggregate_version' => 9,
                'calendar_version' => 2,
                'error_code' => 'REMOTE_HTTP_503',
                'payload' => ['registration_roster' => ['private@example.test']],
                'payload_hash' => str_repeat('b', 64),
                'claim_token' => 'private-claim',
                'last_error' => 'Bearer private-secret',
                'signing_secret' => 'private-signing-secret',
            ]],
        ]);

        $encoded = json_encode($resource, JSON_THROW_ON_ERROR);
        foreach ([
            'private@example.test',
            str_repeat('b', 64),
            'private-claim',
            'Bearer private-secret',
            'private-signing-secret',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $encoded);
        }
        self::assertSame('REMOTE_HTTP_503', $resource['partners'][0]['error_code']);
    }

    public function test_hmac_platform_identifier_uses_explicit_metadata_then_legacy_key_prefix(): void
    {
        $method = new ReflectionMethod(FederationExternalApiClient::class, 'resolveHmacPlatformId');

        self::assertSame('remote-assigned:nexus-a', $method->invoke(null, [
            'partner_metadata' => json_encode([
                'outbound_platform_id' => 'remote-assigned:nexus-a',
            ], JSON_THROW_ON_ERROR),
        ]));
        self::assertSame('fed_live', $method->invoke(null, [
            'api_key' => 'fed_live_abcdef012345',
        ]));
    }

    public function test_lifecycle_and_update_sources_increment_the_independent_federation_stream(): void
    {
        $lifecycle = $this->source('app/Services/EventLifecycleService.php');
        self::assertStringContainsString("'federation_version'", $lifecycle);
        self::assertStringContainsString('$this->federation->publish($event)', $lifecycle);

        $service = $this->source('app/Services/EventService.php');
        $start = strpos($service, '$federationVisibleFields = [');
        self::assertNotFalse($start);
        $end = strpos($service, "\n        ];", $start);
        self::assertNotFalse($end);
        $mutationBlock = substr($service, $start, $end - $start + 11);
        self::assertStringContainsString("'federated_visibility'", $mutationBlock);
        self::assertStringContainsString("'is_online'", $mutationBlock);
        self::assertStringNotContainsString("'online_link'", $mutationBlock);
        self::assertStringContainsString('$federationTouchedFields', $service);
        self::assertStringContainsString('app(EventFederationPublisher::class)->publish($event)', $service);
        self::assertStringNotContainsString('CommunityEventUpdated::dispatch', $service);
    }

    public function test_physical_delete_records_a_tombstone_before_after_commit_cleanup(): void
    {
        $observer = $this->source('app/Observers/EventObserver.php');
        $tombstone = strpos($observer, 'publishDeletion(');
        $afterCommit = strpos($observer, 'DB::afterCommit(', $tombstone ?: 0);

        self::assertNotFalse($tombstone);
        self::assertNotFalse($afterCommit);
        self::assertLessThan($afterCommit, $tombstone);
    }

    public function test_legacy_direct_event_push_is_unmapped_and_cannot_assemble_private_payloads(): void
    {
        $provider = $this->source('app/Providers/EventServiceProvider.php');
        self::assertStringNotContainsString('PushCommunityEventToFederatedPartners::class', $provider);

        $listener = $this->source('app/Listeners/PushCommunityEventToFederatedPartners.php');
        self::assertStringContainsString('$this->publisher->publish($eventModel)', $listener);
        foreach ([
            'FederationExternalApiClient::',
            'FederationExternalPartnerService::',
            "'description' =>",
            "'user_id' =>",
            'sendEvent(',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $listener);
        }
    }

    public function test_consumer_never_reads_notification_claim_or_delivery_tables(): void
    {
        $consumer = $this->source('app/Services/EventFederationDeliveryConsumer.php');
        self::assertStringContainsString('EventFederationDeliveryLedger', $consumer);
        self::assertStringNotContainsString('event_domain_outbox', $consumer);
        self::assertStringNotContainsString('event_notification_deliveries', $consumer);
        self::assertStringNotContainsString('EventNotificationOutbox', $consumer);
    }

    public function test_federation_worker_is_registered_with_cluster_safe_minute_schedule(): void
    {
        $schedule = $this->source('bootstrap/app.php');
        $start = strpos($schedule, "events:process-federation --limit=50");
        self::assertNotFalse($start);
        $block = substr($schedule, $start, 260);
        self::assertStringContainsString('->everyMinute()', $block);
        self::assertStringContainsString('->withoutOverlapping(10)', $block);
        self::assertStringContainsString('->onOneServer()', $block);
        self::assertStringContainsString("->name('events-process-federation')", $block);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . $path);
        self::assertIsString($source);

        return $source;
    }
}
