<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use App\Services\EventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class EventMutationSafetyTest extends TestCase
{
    use DatabaseTransactions;

    private function activeUser(array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    private function eventOwnedBy(int $organizerId): int
    {
        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Mutation safety event',
            'description' => 'Safety boundary fixture.',
            'start_time' => now()->subMinutes(5),
            'end_time' => now()->addHours(2),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string> */
    private function eventUploadFiles(): array
    {
        $root = dirname(__DIR__, 4) . '/httpdocs/uploads/tenants/' . $this->testTenantSlug;
        $files = [];
        foreach (['events', 'listings'] as $directory) {
            foreach (glob($root . '/' . $directory . '/*') ?: [] as $path) {
                if (is_file($path)) {
                    $files[] = $path;
                }
            }
        }
        sort($files);

        return $files;
    }

    public function test_unauthorized_image_request_writes_no_tenant_file(): void
    {
        $organizer = $this->activeUser();
        $otherMember = $this->activeUser();
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        Sanctum::actingAs($otherMember, ['*']);
        $before = $this->eventUploadFiles();

        $response = $this->post(
            "/api/v2/events/{$eventId}/image",
            ['image' => UploadedFile::fake()->image('cover.png', 40, 40)],
            $this->withTenantHeader()
        );
        $after = $this->eventUploadFiles();

        foreach (array_diff($after, $before) as $unexpectedFile) {
            @unlink($unexpectedFile);
        }

        $response->assertForbidden()->assertJsonPath('errors.0.code', 'FORBIDDEN');
        $this->assertSame($before, $after);
        $this->assertNull(DB::table('events')->where('id', $eventId)->value('cover_image'));
    }

    public function test_bulk_attendance_rejects_oversized_payload_before_writes(): void
    {
        $organizer = $this->activeUser();
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        Sanctum::actingAs($organizer, ['*']);

        $response = $this->apiPost("/v2/events/{$eventId}/attendance/bulk", [
            'user_ids' => range(1, EventService::MAX_BULK_ATTENDANCE + 1),
        ]);

        $response->assertStatus(422)->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
        $this->assertSame(0, DB::table('event_attendance')->where('event_id', $eventId)->count());
    }

    public function test_bulk_attendance_deduplicates_member_ids(): void
    {
        $organizer = $this->activeUser();
        $attendee = $this->activeUser();
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
        ]);
        Sanctum::actingAs($organizer, ['*']);

        $response = $this->apiPost("/v2/events/{$eventId}/attendance/bulk", [
            'user_ids' => [$attendee->id, (string) $attendee->id, $attendee->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.marked', 1)
            ->assertJsonPath('data.failed', 0);
        $this->assertSame(1, DB::table('event_attendance')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->count());
        $this->assertNull(DB::table('event_attendance')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->value('hours_credited'));
    }
}
