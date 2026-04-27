<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class AdminCaringCommunityControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function setCaringCommunityFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
        $features = [];
        if ($tenant && !empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }

        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById($this->testTenantId);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2?: array<string, mixed>}>
     */
    public static function disabledAdminRoutes(): array
    {
        return [
            'workflow summary' => ['GET', '/v2/admin/caring-community/workflow'],
            'workflow policy update' => ['PUT', '/v2/admin/caring-community/workflow/policy', [
                'review_sla_days' => 5,
            ]],
            'review assignment' => ['PUT', '/v2/admin/caring-community/workflow/reviews/999/assign', [
                'assigned_to' => null,
            ]],
            'review escalation' => ['PUT', '/v2/admin/caring-community/workflow/reviews/999/escalate', [
                'note' => 'Needs coordinator review.',
            ]],
            'role presets' => ['GET', '/v2/admin/caring-community/role-presets'],
            'role preset install' => ['POST', '/v2/admin/caring-community/role-presets/install', [
                'preset' => 'municipality_admin',
            ]],
            'member statement' => ['GET', '/v2/admin/caring-community/member-statements/999'],
            'support relationships' => ['GET', '/v2/admin/caring-community/support-relationships'],
            'support relationship create' => ['POST', '/v2/admin/caring-community/support-relationships', [
                'supporter_id' => 999,
                'recipient_id' => 998,
            ]],
            'support relationship update' => ['PUT', '/v2/admin/caring-community/support-relationships/999', [
                'status' => 'paused',
            ]],
            'support relationship hours' => ['POST', '/v2/admin/caring-community/support-relationships/999/hours', [
                'date' => '2026-04-20',
                'hours' => 1.5,
            ]],
        ];
    }

    /**
     * @dataProvider disabledAdminRoutes
     *
     * @param array<string, mixed> $payload
     */
    public function test_admin_caring_community_routes_return_403_when_feature_disabled(
        string $method,
        string $uri,
        array $payload = [],
    ): void {
        $this->setCaringCommunityFeature(false);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = match ($method) {
            'GET' => $this->apiGet($uri),
            'POST' => $this->apiPost($uri, $payload),
            'PUT' => $this->apiPut($uri, $payload),
            default => throw new \InvalidArgumentException("Unsupported method {$method}"),
        };

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }

    public function test_workflow_route_returns_401_for_unauthenticated_user(): void
    {
        $this->setCaringCommunityFeature(true);

        $response = $this->apiGet('/v2/admin/caring-community/workflow');

        $response->assertStatus(401);
    }

    public function test_member_statement_returns_kiss_support_and_wallet_context(): void
    {
        $this->setCaringCommunityFeature(true);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $member = User::factory()->forTenant($this->testTenantId)->create(['balance' => 7]);
        $owner = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);
        Sanctum::actingAs($admin);

        DB::table('tenant_settings')->updateOrInsert(
            [
                'tenant_id' => $this->testTenantId,
                'setting_key' => 'caring_community.workflow.default_hour_value_chf',
            ],
            [
                'setting_value' => '35',
                'setting_type' => 'integer',
                'category' => 'caring_community',
                'updated_at' => now(),
            ]
        );

        $orgId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $owner->id,
            'name' => 'KISS Zurich',
            'slug' => 'kiss-zurich-' . uniqid(),
            'status' => 'active',
            'balance' => 100,
            'created_at' => now(),
        ]);

        DB::table('vol_logs')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'user_id' => $member->id,
                'organization_id' => $orgId,
                'date_logged' => '2026-04-10',
                'hours' => 4.00,
                'description' => 'Weekly neighbour visit.',
                'status' => 'approved',
                'created_at' => '2026-04-10 09:00:00',
            ],
            [
                'tenant_id' => $this->testTenantId,
                'user_id' => $member->id,
                'organization_id' => $orgId,
                'date_logged' => '2026-04-12',
                'hours' => 1.50,
                'description' => 'Shopping accompaniment.',
                'status' => 'pending',
                'created_at' => '2026-04-12 09:00:00',
            ],
        ]);

        DB::table('transactions')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'sender_id' => $owner->id,
                'receiver_id' => $member->id,
                'amount' => 4,
                'description' => 'Volunteer auto-payment.',
                'transaction_type' => 'volunteer',
                'status' => 'completed',
                'created_at' => '2026-04-10 10:00:00',
                'updated_at' => '2026-04-10 10:00:00',
            ],
            [
                'tenant_id' => $this->testTenantId,
                'sender_id' => $member->id,
                'receiver_id' => $owner->id,
                'amount' => 1,
                'description' => 'Timebank exchange.',
                'transaction_type' => 'exchange',
                'status' => 'completed',
                'created_at' => '2026-04-15 10:00:00',
                'updated_at' => '2026-04-15 10:00:00',
            ],
        ]);

        $response = $this->apiGet("/v2/admin/caring-community/member-statements/{$member->id}?start_date=2026-04-01&end_date=2026-04-30");

        $response->assertStatus(200);
        $response->assertJsonPath('data.user.id', $member->id);
        $response->assertJsonPath('data.summary.approved_support_hours', 4);
        $response->assertJsonPath('data.summary.pending_support_hours', 1.5);
        $response->assertJsonPath('data.summary.wallet_hours_earned', 4);
        $response->assertJsonPath('data.summary.wallet_hours_spent', 1);
        $response->assertJsonPath('data.summary.estimated_social_value_chf', 140);
        $response->assertJsonPath('data.support_hours_by_organisation.0.organisation_name', 'KISS Zurich');
    }

    public function test_support_relationships_can_be_created_listed_and_paused(): void
    {
        $this->setCaringCommunityFeature(true);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $supporter = User::factory()->forTenant($this->testTenantId)->create();
        $recipient = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        $create = $this->apiPost('/v2/admin/caring-community/support-relationships', [
            'supporter_id' => $supporter->id,
            'recipient_id' => $recipient->id,
            'title' => 'Weekly companionship',
            'description' => 'Standing check-in and short local walk.',
            'frequency' => 'weekly',
            'expected_hours' => 2.5,
            'start_date' => '2026-04-20',
        ]);

        $create->assertStatus(201);
        $create->assertJsonPath('data.title', 'Weekly companionship');
        $create->assertJsonPath('data.frequency', 'weekly');
        $create->assertJsonPath('data.expected_hours', 2.5);
        $relationshipId = (int) $create->json('data.id');

        $list = $this->apiGet('/v2/admin/caring-community/support-relationships');
        $list->assertStatus(200);
        $list->assertJsonPath('data.stats.active_count', 1);
        $list->assertJsonPath('data.items.0.id', $relationshipId);

        $update = $this->apiPut("/v2/admin/caring-community/support-relationships/{$relationshipId}", [
            'status' => 'paused',
        ]);
        $update->assertStatus(200);
        $update->assertJsonPath('data.status', 'paused');

        $this->assertSame('paused', DB::table('caring_support_relationships')->where('id', $relationshipId)->value('status'));
    }

    public function test_support_relationship_hours_can_be_logged_without_partner_organisation(): void
    {
        $this->setCaringCommunityFeature(true);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $supporter = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);
        $recipient = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        $relationshipId = DB::table('caring_support_relationships')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'supporter_id' => $supporter->id,
            'recipient_id' => $recipient->id,
            'coordinator_id' => $admin->id,
            'title' => 'Weekly neighbour check-in',
            'frequency' => 'weekly',
            'expected_hours' => 1.5,
            'start_date' => '2026-04-01',
            'status' => 'active',
            'next_check_in_at' => '2026-04-08 09:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPost("/v2/admin/caring-community/support-relationships/{$relationshipId}/hours", [
            'date' => '2026-04-20',
            'hours' => 1.5,
            'description' => 'Visit and medication reminder.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.log.status', 'approved');
        $response->assertJsonPath('data.relationship.last_logged_at', fn ($value) => is_string($value) && $value !== '');

        $this->assertDatabaseHas('vol_logs', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $supporter->id,
            'organization_id' => null,
            'caring_support_relationship_id' => $relationshipId,
            'support_recipient_id' => $recipient->id,
            'date_logged' => '2026-04-20',
            'status' => 'approved',
        ]);

        $this->assertSame(
            '2026-04-27 09:00:00',
            DB::table('caring_support_relationships')->where('id', $relationshipId)->value('next_check_in_at'),
        );
    }
}
