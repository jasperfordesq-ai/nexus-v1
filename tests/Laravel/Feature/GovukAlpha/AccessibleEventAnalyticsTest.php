<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class AccessibleEventAnalyticsTest extends TestCase
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

    public function test_organizer_dashboard_and_localized_formula_safe_csv_are_private(): void
    {
        $owner = $this->user(['preferred_language' => 'de']);
        $event = $this->event($owner, '=1+1');
        Sanctum::actingAs($owner, ['*']);
        $base = "/{$this->testTenantSlug}/accessible/events/{$event->id}/analytics";

        $dashboard = $this->get($base);
        $dashboard->assertOk()
            ->assertSeeText('=1+1')
            ->assertSeeText(__('govuk_alpha.events.analytics.privacy_title'));
        self::assertStringContainsString('private', (string) $dashboard->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $dashboard->headers->get('Cache-Control'));

        $export = $this->get("{$base}/export.csv");
        $export->assertOk();
        self::assertStringContainsString('text/csv', (string) $export->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', (string) $export->headers->get('Cache-Control'));
        self::assertStringContainsString(
            'event-' . $event->id . '-analytics.csv',
            (string) $export->headers->get('Content-Disposition'),
        );
        $csv = $export->streamedContent();
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString('Kennzahl,Wert', $csv);
        self::assertStringContainsString("event_title,'=1+1,0", $csv);

        self::assertSame(2, DB::table('event_analytics_access_audits')
            ->where('event_id', $event->id)
            ->count());
    }

    public function test_non_organizer_analytics_is_hidden_without_creating_access_evidence(): void
    {
        $owner = $this->user();
        $outsider = $this->user();
        $event = $this->event($owner, 'Private organizer analytics');
        Sanctum::actingAs($outsider, ['*']);
        $base = "/{$this->testTenantSlug}/accessible/events/{$event->id}/analytics";

        $this->get($base)->assertNotFound();
        $this->get("{$base}/export.csv")->assertNotFound();
        self::assertSame(0, DB::table('event_analytics_access_audits')
            ->where('event_id', $event->id)
            ->count());
    }

    /** @param array<string,mixed> $overrides */
    private function user(array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
            'preferred_language' => 'en',
        ], $overrides));
    }

    private function event(User $owner, string $title): Event
    {
        return Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => (int) $owner->id,
            'title' => $title,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => false,
            'max_attendees' => 20,
        ]);
    }
}
