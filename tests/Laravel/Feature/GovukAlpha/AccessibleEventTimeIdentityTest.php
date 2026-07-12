<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\TenantSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class AccessibleEventTimeIdentityTest extends TestCase
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

    public function test_create_and_edit_preserve_wall_clock_timezone_and_inclusive_all_day_end(): void
    {
        app(TenantSettingsService::class)->set(
            $this->testTenantId,
            'general.timezone',
            'America/Los_Angeles',
        );
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($owner, ['*']);
        $base = "/{$this->testTenantSlug}/accessible";

        $this->get("{$base}/events/new")
            ->assertOk()
            ->assertSee('name="timezone"', false)
            ->assertSee('value="America/Los_Angeles"', false)
            ->assertSee('name="all_day"', false);

        $this->accessiblePost("{$base}/events/new", [
            'title' => 'Pacific time identity fixture',
            'description' => 'The accessible writer preserves the organizer wall clock.',
            'start_time' => '2030-01-15T09:30',
            'end_time' => '2030-01-15T11:00',
            'timezone' => 'America/Los_Angeles',
        ])->assertRedirect();

        $eventId = (int) DB::table('events')
            ->where('tenant_id', $this->testTenantId)
            ->where('title', 'Pacific time identity fixture')
            ->value('id');
        self::assertGreaterThan(0, $eventId);
        $created = DB::table('events')->where('id', $eventId)->firstOrFail();
        self::assertSame('America/Los_Angeles', $created->timezone);
        self::assertSame('2030-01-15 17:30:00', $created->start_time);

        $this->get("{$base}/events/{$eventId}/edit")
            ->assertOk()
            ->assertSee('value="2030-01-15T09:30"', false)
            ->assertSee('value="America/Los_Angeles"', false);

        $this->accessiblePost("{$base}/events/{$eventId}/edit", [
            'title' => 'Pacific time identity fixture',
            'description' => 'The accessible writer preserves the organizer wall clock.',
            'start_time' => '2030-02-10T09:45',
            'end_time' => '2030-02-12T22:00',
            'timezone' => 'Australia/Brisbane',
            'all_day' => '1',
        ])->assertRedirect("{$base}/events/{$eventId}?status=event-updated");

        $updated = DB::table('events')->where('id', $eventId)->firstOrFail();
        self::assertSame('Australia/Brisbane', $updated->timezone);
        self::assertSame(1, (int) $updated->all_day);
        self::assertSame('2030-02-09 14:00:00', $updated->start_time);
        self::assertSame('2030-02-12 14:00:00', $updated->end_time);

        $this->get("{$base}/events/{$eventId}/edit")
            ->assertOk()
            ->assertSee('value="2030-02-10T00:00"', false)
            ->assertSee('value="2030-02-12T00:00"', false)
            ->assertSee('value="Australia/Brisbane"', false)
            ->assertSee('id="all_day" name="all_day" type="checkbox" value="1" checked', false);
    }

    /** @param array<string,mixed> $data */
    private function accessiblePost(string $uri, array $data): TestResponse
    {
        $token = 'accessible-event-time-identity-token';

        return $this->withSession(['_token' => $token])->post($uri, [
            ...$data,
            '_token' => $token,
        ]);
    }
}
