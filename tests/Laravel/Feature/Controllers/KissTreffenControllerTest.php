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
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class KissTreffenControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function requireTreffenTable(): void
    {
        if (!Schema::hasTable('caring_kiss_treffen')) {
            $this->markTestSkipped('KISS Treffen table is not present in the test database.');
        }
    }

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

    private function createEvent(int $organizerId): int
    {
        return DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Monatlicher KISS Stamm',
            'description' => 'Monthly cooperative ritual meeting.',
            'location' => 'KISS office',
            'start_time' => now()->addDays(10),
            'end_time' => now()->addDays(10)->addHours(2),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_can_mark_event_as_kiss_treffen_and_member_can_see_quorum(): void
    {
        $this->requireTreffenTable();
        $this->setCaringCommunityFeature(true);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $eventId = $this->createEvent($admin->id);

        Sanctum::actingAs($admin);
        $created = $this->apiPut("/v2/admin/caring-community/kiss-treffen/{$eventId}", [
            'treffen_type' => 'monthly_stamm',
            'members_only' => true,
            'quorum_required' => 1,
            'fondation_header' => 'Fondation KISS meeting record',
        ]);

        $created->assertStatus(200);
        $created->assertJsonPath('data.treffen_type', 'monthly_stamm');
        $created->assertJsonPath('data.members_only', true);
        $created->assertJsonPath('data.quorum.required', 1);

        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $member->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($member);
        $shown = $this->apiGet("/v2/caring-community/kiss-treffen/{$eventId}");

        $shown->assertStatus(200);
        $shown->assertJsonPath('data.quorum.current', 1);
        $shown->assertJsonPath('data.quorum.met', true);
    }

    public function test_kiss_treffen_members_only_rsvp_rejects_pending_account_before_recording_rsvp(): void
    {
        $this->requireTreffenTable();
        $this->setCaringCommunityFeature(true);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $pending = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'pending',
            'is_approved' => false,
        ]);
        $approved = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $eventId = $this->createEvent($admin->id);

        DB::table('caring_kiss_treffen')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'treffen_type' => 'annual_general_assembly',
            'members_only' => true,
            'quorum_required' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($pending);
        $response = $this->apiPost("/v2/events/{$eventId}/rsvp", [
            'status' => 'going',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('event_rsvps', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $pending->id,
            'status' => 'going',
        ]);

        Sanctum::actingAs($approved);
        $approvedResponse = $this->apiPost("/v2/events/{$eventId}/rsvp", [
            'status' => 'going',
        ]);

        $approvedResponse->assertStatus(200);
        $this->assertDatabaseHas('event_rsvps', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $approved->id,
            'status' => 'going',
        ]);
    }
}
