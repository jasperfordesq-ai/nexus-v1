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
use App\Support\Events\EventLifecycleHistoryCursor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class AccessibleEventLifecycleHistoryTest extends TestCase
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

    public function test_owner_traverses_complete_private_history_with_govuk_semantics(): void
    {
        $owner = $this->member('Accessible History Owner');
        $event = $this->event($owner);
        $historyIds = $this->history((int) $event->id, (int) $owner->id, 3);
        rsort($historyIds, SORT_NUMERIC);
        Sanctum::actingAs($owner, ['*']);
        $path = "/{$this->testTenantSlug}/accessible/events/{$event->id}/lifecycle-history";

        $first = $this->get("{$path}?per_page=2")
            ->assertOk()
            ->assertSeeText(__('event_lifecycle_history.title'))
            ->assertSeeText(__('event_lifecycle_history.immutable_explanation'))
            ->assertSeeText(__('event_lifecycle_history.version', ['version' => 3]))
            ->assertSeeText(__('event_lifecycle_history.version', ['version' => 2]))
            ->assertDontSeeText(__('event_lifecycle_history.version', ['version' => 1]))
            ->assertSee('class="govuk-summary-card', false)
            ->assertSee('rel="next"', false)
            ->assertDontSee('DO_NOT_EXPOSE_PRIVATE_HISTORY_METADATA', false)
            ->assertDontSee('private-recipient@example.test', false);
        self::assertStringContainsString('no-store', (string) $first->headers->get('Cache-Control'));
        self::assertStringContainsString('Authorization', (string) $first->headers->get('Vary'));

        $cursor = EventLifecycleHistoryCursor::encode((int) $event->id, $historyIds[1]);
        $second = $this->get("{$path}?per_page=2&cursor=" . rawurlencode($cursor))
            ->assertOk()
            ->assertSeeText(__('event_lifecycle_history.version', ['version' => 1]))
            ->assertDontSeeText(__('event_lifecycle_history.version', ['version' => 3]))
            ->assertDontSee('rel="next"', false);
        self::assertSame(1, substr_count(
            (string) $second->getContent(),
            'class="govuk-summary-card govuk-!-margin-bottom-6"',
        ));

        $detail = $this->get("/{$this->testTenantSlug}/accessible/events/{$event->id}")
            ->assertOk()
            ->assertSeeText(__('event_lifecycle_history.link'))
            ->assertSee("/events/{$event->id}/lifecycle-history", false);
        self::assertStringNotContainsString('DO_NOT_EXPOSE_PRIVATE_HISTORY_METADATA', $detail->getContent());
    }

    public function test_accessible_history_denies_non_managers_and_rejects_malformed_queries(): void
    {
        $owner = $this->member('History Owner');
        $outsider = $this->member('History Outsider');
        $event = $this->event($owner);
        $this->history((int) $event->id, (int) $owner->id, 1);
        $path = "/{$this->testTenantSlug}/accessible/events/{$event->id}/lifecycle-history";

        Sanctum::actingAs($outsider, ['*']);
        $this->get($path)->assertForbidden();

        Sanctum::actingAs($owner, ['*']);
        $this->get("{$path}?cursor=invalid")->assertStatus(422);
        $this->get("{$path}?per_page=101")->assertStatus(422);
        $this->get("{$path}?per_page[]=20")->assertStatus(422);
    }

    private function member(string $name): User
    {
        [$first, $last] = array_pad(explode(' ', $name, 2), 2, 'Member');

        return User::factory()->forTenant($this->testTenantId)->create([
            'first_name' => $first,
            'last_name' => $last,
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function event(User $owner): Event
    {
        return Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => (int) $owner->id,
            'title' => 'Accessible lifecycle fixture',
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 3,
            'is_recurring_template' => false,
        ]);
    }

    /** @return list<int> */
    private function history(int $eventId, int $actorId, int $count): array
    {
        $ids = [];
        foreach (range(1, $count) as $version) {
            $ids[] = (int) DB::table('event_status_history')->insertGetId([
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
                'reason' => "Accessible reason {$version}",
                'metadata' => json_encode([
                    'axes_changed' => ['publication'],
                    'cascade' => ['reminders_cancelled' => 0],
                    'private_note' => 'DO_NOT_EXPOSE_PRIVATE_HISTORY_METADATA',
                    'recipient_email' => 'private-recipient@example.test',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now()->addSeconds($version),
            ]);
        }

        return $ids;
    }
}
