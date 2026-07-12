<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\Event;
use App\Models\Group;
use App\Models\Listing;
use App\Models\User;
use App\Services\SearchService;
use App\Support\Events\EventSearchVisibility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Laravel\TestCase;

final class EventSearchVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private ReflectionProperty $availability;

    private mixed $originalAvailability;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        $this->availability = new ReflectionProperty(SearchService::class, 'available');
        $this->availability->setAccessible(true);
        $this->originalAvailability = $this->availability->getValue();
        $this->availability->setValue(null, false);
        $this->organizer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->availability->setValue(null, $this->originalAvailability);
        parent::tearDown();
    }

    public function test_every_sql_search_surface_exposes_only_published_scheduled_or_postponed_occurrences(): void
    {
        $term = 'LifecycleSearch' . bin2hex(random_bytes(5));
        $visibleScheduled = $this->event($term . ' scheduled');
        $visiblePostponed = $this->event($term . ' postponed', [
            'status' => 'cancelled',
            'operational_status' => 'postponed',
        ]);
        foreach ([
            ['status' => 'draft', 'publication_status' => 'draft'],
            ['status' => 'draft', 'publication_status' => 'pending_review'],
            ['status' => 'cancelled', 'publication_status' => 'archived', 'operational_status' => 'cancelled'],
            ['status' => 'cancelled', 'operational_status' => 'cancelled'],
            ['status' => 'completed', 'operational_status' => 'completed'],
            ['is_recurring_template' => 1],
            // Corrupt legacy mirror: strict lifecycle compatibility fails closed.
            ['status' => 'active', 'operational_status' => 'postponed'],
        ] as $overrides) {
            $this->event($term . ' hidden ' . uniqid(), $overrides);
        }
        $this->event($term . ' other tenant', ['tenant_id' => 999]);

        $service = new SearchService(new User(), new Listing(), new Event(), new Group());
        $expected = [$visibleScheduled, $visiblePostponed];

        $classic = $service->search($term, 'events', 50);
        $this->assertEqualsCanonicalizing($expected, $this->ids($classic['events'] ?? []));

        $unified = $service->unifiedSearch($term, null, ['type' => 'events', 'limit' => 50]);
        $this->assertEqualsCanonicalizing($expected, $this->ids($unified['items']));

        $suggestions = $service->suggestions($term, 50);
        $this->assertEqualsCanonicalizing($expected, $this->ids($suggestions['events']));
    }

    public function test_stale_meilisearch_ids_are_revalidated_against_tenant_and_current_database_state(): void
    {
        $term = 'StaleSearch' . bin2hex(random_bytes(5));
        $scheduled = $this->event($term . ' scheduled');
        $postponed = $this->event($term . ' postponed', [
            'status' => 'cancelled',
            'operational_status' => 'postponed',
        ]);
        $draft = $this->event($term . ' draft', [
            'status' => 'draft',
            'publication_status' => 'draft',
        ]);
        $cancelled = $this->event($term . ' cancelled', [
            'status' => 'cancelled',
            'operational_status' => 'cancelled',
        ]);
        $template = $this->event($term . ' template', ['is_recurring_template' => 1]);
        $otherTenant = $this->event($term . ' other tenant', ['tenant_id' => 999]);

        $method = new ReflectionMethod(SearchService::class, 'visibleEventIds');
        $method->setAccessible(true);
        $actual = $method->invoke(null, [
            $otherTenant,
            $cancelled,
            $postponed,
            $template,
            $scheduled,
            $draft,
        ], $this->testTenantId);

        $this->assertSame([$postponed, $scheduled], $actual);
    }

    public function test_index_visibility_and_meilisearch_filter_fail_closed(): void
    {
        $base = [
            'id' => 42,
            'tenant_id' => $this->testTenantId,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => 0,
        ];

        $this->assertTrue(EventSearchVisibility::isDiscoverable($base));
        $this->assertTrue(EventSearchVisibility::isDiscoverable([
            ...$base,
            'status' => 'cancelled',
            'operational_status' => 'postponed',
        ]));
        $this->assertFalse(EventSearchVisibility::isDiscoverable([
            ...$base,
            'publication_status' => 'draft',
            'status' => 'draft',
        ]));
        $this->assertFalse(EventSearchVisibility::isDiscoverable([
            ...$base,
            'is_recurring_template' => 1,
        ]));
        $unknownTemplateIdentity = $base;
        unset($unknownTemplateIdentity['is_recurring_template']);
        $this->assertFalse(EventSearchVisibility::isDiscoverable($unknownTemplateIdentity));

        $documentMethod = new ReflectionMethod(SearchService::class, 'eventDocument');
        $documentMethod->setAccessible(true);
        $document = $documentMethod->invoke(null, $base);
        $this->assertSame('published', $document['publication_status'] ?? null);
        $this->assertSame('scheduled', $document['operational_status'] ?? null);
        $this->assertFalse($document['is_recurring_template'] ?? true);
        $this->assertNull($documentMethod->invoke(null, [
            ...$base,
            'status' => 'cancelled',
            'operational_status' => 'cancelled',
        ]));

        $filter = EventSearchVisibility::meilisearchFilter($this->testTenantId, 123456);
        $this->assertStringContainsString('tenant_id = ' . $this->testTenantId, $filter);
        $this->assertStringContainsString('publication_status = "published"', $filter);
        $this->assertStringContainsString('operational_status IN ["scheduled", "postponed"]', $filter);
        $this->assertStringContainsString('is_recurring_template = false', $filter);
        $this->assertStringContainsString('start_time >= 123456', $filter);
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private function ids(array $rows): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rows,
        ));
    }

    private function event(string $title, array $overrides = []): int
    {
        $tenantId = (int) ($overrides['tenant_id'] ?? $this->testTenantId);
        $userId = (int) $this->organizer->id;

        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'title' => $title,
            'description' => $title . ' description',
            'location' => 'Search boundary venue',
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 1,
            'is_recurring_template' => 0,
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
