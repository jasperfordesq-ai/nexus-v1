<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class EventTemplateControllerTest extends TestCase
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

    public function test_preview_capture_list_history_and_materialization_use_explicit_contracts(): void
    {
        $owner = $this->user();
        $sourceEventId = $this->sourceEvent((int) $owner->id);
        Sanctum::actingAs($owner, ['*']);

        $this->apiPost("/v2/events/{$sourceEventId}/template-preview", [])
            ->assertOk()
            ->assertJsonPath('data.kind', 'capture')
            ->assertJsonPath('data.source_event_id', $sourceEventId)
            ->assertJsonPath('data.configuration.title', 'API template source')
            ->assertJsonPath('data.configuration.federated_visibility', 'none')
            ->assertJsonMissingPath('data.configuration.online_link')
            ->assertJsonMissingPath('data.payload')
            ->assertJsonMissingPath('data.tenant_id');

        $captured = $this->apiPost(
            "/v2/events/{$sourceEventId}/templates",
            [],
            ['Idempotency-Key' => 'template-api-capture-1'],
        );
        $captured->assertCreated()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.idempotent_replay', false)
            ->assertJsonPath('data.template.current_version', 1)
            ->assertJsonPath('data.template.version.snapshot.immutable', true)
            ->assertJsonMissingPath('data.template.tenant_id')
            ->assertJsonMissingPath('data.template.created_by_user_id')
            ->assertJsonMissingPath('data.template.version.capture_idempotency_hash');
        $templateId = (int) $captured->json('data.template.id');

        $this->apiPost(
            "/v2/events/{$sourceEventId}/templates",
            [],
            ['Idempotency-Key' => 'template-api-capture-1'],
        )->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.idempotent_replay', true)
            ->assertJsonPath('data.template.id', $templateId);

        $list = $this->apiGet('/v2/event-templates?status=active&per_page=20');
        $list->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $templateId)
            ->assertJsonPath('data.0.capabilities.materialize', true)
            ->assertJsonPath('meta.has_more', false);
        self::assertStringContainsString(
            'no-store',
            (string) $list->headers->get('Cache-Control'),
        );

        $start = CarbonImmutable::now('UTC')->addMonths(2)->startOfHour();
        $input = [
            'template_version' => 1,
            'start_time' => $start->toIso8601String(),
            'end_time' => $start->addHours(3)->toIso8601String(),
            'overrides' => [
                'title' => 'Fresh API draft',
                'location' => 'New venue',
            ],
        ];
        $this->apiPost(
            "/v2/event-templates/{$templateId}/materialization-preview",
            $input,
        )->assertOk()
            ->assertJsonPath('data.kind', 'materialization')
            ->assertJsonPath('data.configuration.title', 'Fresh API draft')
            ->assertJsonPath('data.will_create.publication_status', 'draft')
            ->assertJsonPath('data.will_create.publish', false)
            ->assertJsonPath('data.will_create.register', false)
            ->assertJsonPath('data.will_create.notify', false)
            ->assertJsonPath('data.will_create.federate', false);

        $materialized = $this->apiPost(
            "/v2/event-templates/{$templateId}/materializations",
            $input,
            ['Idempotency-Key' => 'template-api-materialize-1'],
        );
        $materialized->assertCreated()
            ->assertJsonPath('data.created_event.title', 'Fresh API draft')
            ->assertJsonPath('data.created_event.publication_status', 'draft')
            ->assertJsonPath('data.workflow.fresh_draft', true)
            ->assertJsonPath('data.workflow.registrations_copied', false)
            ->assertJsonPath('data.workflow.notifications_sent', false)
            ->assertJsonPath('data.workflow.federated', false)
            ->assertJsonMissingPath('data.created_event.user_id')
            ->assertJsonMissingPath('data.provenance.idempotency_hash');
        $createdEventId = (int) $materialized->json('data.created_event.id');

        $this->apiPost(
            "/v2/event-templates/{$templateId}/materializations",
            $input,
            ['Idempotency-Key' => 'template-api-materialize-1'],
        )->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.idempotent_replay', true)
            ->assertJsonPath('data.created_event.id', $createdEventId);

        $this->apiGet("/v2/event-templates/{$templateId}/history")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'materialized')
            ->assertJsonPath('data.0.immutable', true)
            ->assertJsonMissingPath('data.0.actor_user_id')
            ->assertJsonMissingPath('data.0.request_hash');

        self::assertSame('draft', DB::table('events')->where('id', $createdEventId)->value('publication_status'));
        self::assertSame('none', DB::table('events')->where('id', $createdEventId)->value('federated_visibility'));
        self::assertSame(0, DB::table('event_registrations')->where('event_id', $createdEventId)->count());
        self::assertSame(0, DB::table('event_attendance')->where('event_id', $createdEventId)->count());
        self::assertSame(1, DB::table('event_template_materializations')->where('created_event_id', $createdEventId)->count());
    }

    public function test_authorization_tenant_validation_and_idempotency_conflicts_fail_closed(): void
    {
        $owner = $this->user();
        $member = $this->user();
        $sourceEventId = $this->sourceEvent((int) $owner->id);

        Sanctum::actingAs($member, ['*']);
        $this->apiPost("/v2/events/{$sourceEventId}/template-preview", [])
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'EVENT_TEMPLATE_FORBIDDEN');
        $this->apiGet('/v2/event-templates')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($owner, ['*']);
        $this->apiPost("/v2/events/{$sourceEventId}/templates", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'idempotency_key');
        $this->apiPost(
            "/v2/events/{$sourceEventId}/templates",
            ['idempotency_key' => 'body-key'],
            ['Idempotency-Key' => 'different-header-key'],
        )->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'idempotency_key');

        $captured = $this->apiPost(
            "/v2/events/{$sourceEventId}/templates",
            [],
            ['Idempotency-Key' => 'template-api-conflict-capture'],
        )->assertCreated();
        $templateId = (int) $captured->json('data.template.id');
        $start = CarbonImmutable::now('UTC')->addMonths(3)->startOfHour();

        $this->apiPost(
            "/v2/event-templates/{$templateId}/materialization-preview",
            [
                'template_version' => 1,
                'start_time' => $start->toIso8601String(),
                'end_time' => $start->addHour()->toIso8601String(),
                'overrides' => ['online_link' => 'https://private.example.invalid'],
            ],
        )->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'overrides');

        $this->apiPost(
            "/v2/event-templates/{$templateId}/revisions",
            ['expected_version' => 99],
            ['Idempotency-Key' => 'template-api-stale-revision'],
        )->assertConflict()
            ->assertJsonPath('errors.0.code', 'EVENT_TEMPLATE_CONFLICT');
        self::assertSame(1, DB::table('event_template_versions')->where('template_id', $templateId)->count());
    }

    private function user(array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    private function sourceEvent(int $ownerId): int
    {
        $start = CarbonImmutable::now('UTC')->addMonth()->startOfHour();

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'title' => 'API template source',
            'description' => 'Reusable configuration for API coverage.',
            'location' => 'Original venue',
            'latitude' => null,
            'longitude' => null,
            'start_time' => $start,
            'end_time' => $start->addHours(2),
            'timezone' => 'UTC',
            'timezone_source' => 'test',
            'all_day' => false,
            'category_id' => null,
            'group_id' => null,
            'max_attendees' => 40,
            'is_online' => false,
            'allow_remote_attendance' => false,
            'online_link' => 'https://private.example.invalid/meeting',
            'video_url' => null,
            'federated_visibility' => 'none',
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 1,
            'calendar_sequence' => 1,
            'is_recurring_template' => false,
            'occurrence_key' => 'template-api:' . bin2hex(random_bytes(12)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
