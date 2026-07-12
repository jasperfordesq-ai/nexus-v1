<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class EventLifecycleHistoryControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => true], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_manager_can_traverse_complete_immutable_history_without_private_metadata(): void
    {
        $owner = $this->member($this->testTenantId, [
            'first_name' => 'Morgan',
            'last_name' => 'Owner',
            'email' => 'owner-private@example.test',
        ]);
        $eventId = $this->event($this->testTenantId, (int) $owner->id, 'Lifecycle fixture');
        $this->history($eventId, (int) $owner->id, 45);

        Sanctum::actingAs($owner, ['*']);
        $cursor = null;
        $ids = [];
        do {
            $query = $cursor === null
                ? '?per_page=20'
                : '?per_page=20&cursor=' . rawurlencode($cursor);
            $response = $this->apiGet("/v2/events/{$eventId}/lifecycle-history{$query}")
                ->assertOk()
                ->assertJsonPath('meta.per_page', 20);
            self::assertStringContainsString(
                'no-store',
                (string) $response->headers->get('Cache-Control'),
            );

            $items = $response->json('data');
            self::assertIsArray($items);
            foreach ($items as $item) {
                $ids[] = (int) $item['id'];
                self::assertTrue($item['immutable']);
                self::assertSame('Morgan Owner', $item['actor']['display_name']);
                self::assertSame(['publication'], $item['evidence']['axes_changed']);
                self::assertSame(1, $item['evidence']['cascade']['reminders_cancelled']);
                self::assertArrayNotHasKey('secret_note', $item['evidence']);
                self::assertArrayNotHasKey('affected_recipient_user_ids', $item['evidence']);
                self::assertArrayNotHasKey('email', $item['actor']);
            }

            $cursor = $response->json('meta.next_cursor');
            $hasMore = (bool) $response->json('meta.has_more');
            self::assertSame($hasMore, is_string($cursor) && $cursor !== '');
        } while ($hasMore);

        self::assertCount(45, $ids);
        self::assertCount(45, array_unique($ids));
        $sorted = $ids;
        rsort($sorted, SORT_NUMERIC);
        self::assertSame($sorted, $ids);

        $payload = $this->apiGet("/v2/events/{$eventId}/lifecycle-history?per_page=100")
            ->assertOk()
            ->getContent();
        foreach ([
            'TOP SECRET',
            'affected_recipient_user_ids',
            'owner-private@example.test',
            'tenant_id',
            'actor_user_id',
            'from_legacy_status',
            'to_legacy_status',
        ] as $privateValue) {
            self::assertStringNotContainsString($privateValue, $payload);
        }
    }

    public function test_query_rejects_non_manager_foreign_tenant_and_invalid_pagination(): void
    {
        $owner = $this->member($this->testTenantId);
        $member = $this->member($this->testTenantId);
        $eventId = $this->event($this->testTenantId, (int) $owner->id, 'Protected history');
        $this->history($eventId, (int) $owner->id, 1);

        Sanctum::actingAs($member, ['*']);
        $this->apiGet("/v2/events/{$eventId}/lifecycle-history")
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'EVENT_LIFECYCLE_HISTORY_FORBIDDEN');

        Sanctum::actingAs($owner, ['*']);
        $invalidCursor = $this->apiGet("/v2/events/{$eventId}/lifecycle-history?cursor=not-a-cursor")
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'EVENT_LIFECYCLE_HISTORY_VALIDATION_FAILED')
            ->assertJsonPath('errors.0.field', 'cursor');
        self::assertStringContainsString(
            'no-store',
            (string) $invalidCursor->headers->get('Cache-Control'),
        );
        self::assertStringContainsString(
            'Authorization',
            (string) $invalidCursor->headers->get('Vary'),
        );
        $this->apiGet("/v2/events/{$eventId}/lifecycle-history?per_page=101")
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'per_page');

        $foreignOwner = $this->member(999);
        $foreignEventId = $this->event(999, (int) $foreignOwner->id, 'Foreign history');
        $this->apiGet("/v2/events/{$foreignEventId}/lifecycle-history")
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'EVENT_LIFECYCLE_HISTORY_NOT_FOUND');
    }

    /** @param array<string,mixed> $overrides */
    private function member(int $tenantId, array $overrides = []): User
    {
        return User::factory()->forTenant($tenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    private function event(int $tenantId, int $ownerId, string $title): int
    {
        $start = now()->addMonth();

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'title' => $title,
            'description' => 'Lifecycle history coverage.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'timezone' => 'UTC',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'occurrence_key' => 'lifecycle-history:' . bin2hex(random_bytes(8)),
            'is_recurring_template' => 0,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 45,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function history(int $eventId, int $actorId, int $count): void
    {
        $rows = [];
        foreach (range(1, $count) as $version) {
            $rows[] = [
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'actor_user_id' => $actorId,
                'lifecycle_version' => $version,
                'from_publication_status' => 'draft',
                'to_publication_status' => 'published',
                'from_operational_status' => 'scheduled',
                'to_operational_status' => 'scheduled',
                'from_legacy_status' => 'draft',
                'to_legacy_status' => 'active',
                'reason' => $version === 45 ? 'Approved after review' : null,
                'metadata' => json_encode([
                    'schema_version' => 1,
                    'source' => 'event_lifecycle_service',
                    'axes_changed' => ['publication', 'private_future_axis'],
                    'cascade' => [
                        'reminders_cancelled' => 1,
                        'registrations_cancelled' => 0,
                        'private_count' => 999,
                    ],
                    'secret_note' => 'TOP SECRET',
                    'affected_recipient_user_ids' => [123, 456],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now()->subMinutes($count - $version),
            ];
        }
        if ($rows !== []) {
            DB::table('event_status_history')->insert($rows);
        }
    }
}
