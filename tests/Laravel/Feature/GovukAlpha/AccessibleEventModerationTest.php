<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class AccessibleEventModerationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => true], JSON_THROW_ON_ERROR),
        ]);
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'moderation.enabled'],
            ['setting_value' => '1', 'updated_at' => now()],
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'moderation.require_event'],
            ['setting_value' => '1', 'updated_at' => now()],
        );
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_queue_requires_authentication_and_events_feature(): void
    {
        $path = $this->queuePath();
        $this->get($path)->assertRedirect(
            "/{$this->testTenantSlug}/accessible/login?status=auth-required",
        );

        $this->actingAsAdmin();
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => false], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $this->get($path)->assertForbidden();
    }

    public function test_only_true_tenant_admins_can_open_or_discover_queue(): void
    {
        $queuePath = $this->queuePath();
        foreach (['member', 'broker', 'coordinator'] as $role) {
            $user = User::factory()->forTenant($this->testTenantId)->create([
                'role' => $role,
                'is_admin' => $role === 'member' ? 0 : 1,
                'status' => 'active',
                'is_approved' => true,
            ]);
            Sanctum::actingAs($user, ['*']);

            $this->get($queuePath)->assertForbidden();
            $this->get("/{$this->testTenantSlug}/accessible/events")
                ->assertOk()
                ->assertDontSee($queuePath, false);
        }

        $this->actingAsAdmin();
        $this->get("/{$this->testTenantSlug}/accessible/events")
            ->assertOk()
            ->assertSee($queuePath, false)
            ->assertSeeText(__('govuk_alpha_events.moderation.queue_link'));
        $this->get($queuePath)->assertOk();
    }

    public function test_queue_is_tenant_scoped_and_exposes_only_allowlisted_review_facts(): void
    {
        $owner = $this->member('Visible organiser');
        $eventId = $this->pendingEvent($this->testTenantId, (int) $owner->id, [
            'title' => 'Visible moderation event',
            'description' => 'Visible review description',
            'location' => 'Visible community hall',
            'is_online' => 1,
            'online_link' => 'https://secret.example.test/private-meeting-link',
            'video_url' => 'https://secret.example.test/private-video',
            'latitude' => '53.12345678',
            'longitude' => '-6.12345678',
            'accessibility_assistance_contact' => 'private-helper@example.test',
            'lifecycle_reason' => 'PRIVATE_INTERNAL_LIFECYCLE_REASON',
            'moderation_reason' => 'PRIVATE_OLD_MODERATION_REASON',
        ]);
        $this->queueEvent($this->testTenantId, $eventId, (int) $owner->id);

        $foreignTenant = Tenant::factory()->create();
        $foreignOwner = User::factory()->forTenant((int) $foreignTenant->id)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $foreignEventId = $this->pendingEvent((int) $foreignTenant->id, (int) $foreignOwner->id, [
            'title' => 'FOREIGN_TENANT_EVENT_TITLE',
        ]);
        $this->queueEvent((int) $foreignTenant->id, $foreignEventId, (int) $foreignOwner->id);

        $this->actingAsAdmin();
        $response = $this->get($this->queuePath())
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSeeText('Visible moderation event')
            ->assertSeeText('Visible review description')
            ->assertSeeText('Visible community hall')
            ->assertSeeText('Visible organiser')
            ->assertDontSeeText('FOREIGN_TENANT_EVENT_TITLE')
            ->assertDontSee('https://secret.example.test/private-meeting-link', false)
            ->assertDontSee('https://secret.example.test/private-video', false)
            ->assertDontSee('53.12345678', false)
            ->assertDontSee('-6.12345678', false)
            ->assertDontSee('private-helper@example.test', false)
            ->assertDontSee('PRIVATE_INTERNAL_LIFECYCLE_REASON', false)
            ->assertDontSee('PRIVATE_OLD_MODERATION_REASON', false);
        $this->assertPrivateNoStore($response);
        self::assertStringContainsString('Authorization', (string) $response->headers->get('Vary'));

        $this->get("/{$this->testTenantSlug}/accessible/events/moderation/{$foreignEventId}/approve")
            ->assertNotFound();
    }

    public function test_queue_has_bounded_navigation_without_a_silent_result_cap(): void
    {
        $owner = $this->member('Pagination organiser');
        for ($index = 1; $index <= 21; $index++) {
            $title = sprintf('Moderation-page-row-%02d-ZZZ', $index);
            $eventId = $this->pendingEvent($this->testTenantId, (int) $owner->id, ['title' => $title]);
            $this->queueEvent(
                $this->testTenantId,
                $eventId,
                (int) $owner->id,
                now()->addSeconds($index),
            );
        }
        $this->actingAsAdmin();

        $first = $this->get($this->queuePath())
            ->assertOk()
            ->assertSeeText('Moderation-page-row-01-ZZZ')
            ->assertSeeText('Moderation-page-row-20-ZZZ')
            ->assertDontSeeText('Moderation-page-row-21-ZZZ')
            ->assertSee($this->queuePath() . '?page=2', false);
        self::assertSame(20, substr_count((string) $first->getContent(), '<article class="nexus-alpha-card">'));

        $second = $this->get($this->queuePath() . '?page=2')
            ->assertOk()
            ->assertSeeText('Moderation-page-row-21-ZZZ')
            ->assertDontSeeText('Moderation-page-row-01-ZZZ')
            ->assertSee($this->queuePath() . '?page=1', false);
        self::assertSame(1, substr_count((string) $second->getContent(), '<article class="nexus-alpha-card">'));

        $this->get($this->queuePath() . '?page[]=2')->assertRedirect($this->queuePath());
        $this->get($this->queuePath() . '?page=999')->assertRedirect($this->queuePath() . '?page=2');
    }

    public function test_approve_form_uses_canonical_series_history_outbox_and_queue_closure(): void
    {
        $owner = $this->member('Series organiser');
        $admin = $this->actingAsAdmin();
        $templateId = $this->pendingEvent($this->testTenantId, (int) $owner->id, [
            'title' => 'Moderated repeating series',
            'is_recurring_template' => 1,
        ]);
        $occurrenceId = $this->pendingEvent($this->testTenantId, (int) $owner->id, [
            'title' => 'Moderated repeating occurrence',
            'parent_event_id' => $templateId,
            'occurrence_key' => 'accessible-moderation:' . bin2hex(random_bytes(8)),
        ]);
        $this->queueEvent($this->testTenantId, $templateId, (int) $owner->id);
        $confirmationPath = "/{$this->testTenantSlug}/accessible/events/moderation/{$templateId}/approve";

        $confirmation = $this->get($confirmationPath)
            ->assertOk()
            ->assertSee('method="post"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="confirmation"', false);
        $this->assertPrivateNoStore($confirmation);
        self::assertSame(0, DB::table('event_status_history')->whereIn('event_id', [$templateId, $occurrenceId])->count());

        $approved = $this->accessiblePost($confirmationPath, ['confirmation' => 'approve'])
            ->assertRedirect($this->queuePath() . '?status=approved');
        $this->assertPrivateNoStore($approved);

        self::assertSame(
            ['published', 'published'],
            DB::table('events')->whereIn('id', [$templateId, $occurrenceId])->orderBy('id')->pluck('publication_status')->all(),
        );
        self::assertSame(2, DB::table('event_status_history')->whereIn('event_id', [$templateId, $occurrenceId])->count());
        self::assertSame(2, DB::table('event_domain_outbox')
            ->whereIn('event_id', [$templateId, $occurrenceId])
            ->where('action', 'event.lifecycle.transitioned')
            ->count());
        $this->assertDatabaseHas('content_moderation_queue', [
            'tenant_id' => $this->testTenantId,
            'content_type' => 'event',
            'content_id' => $templateId,
            'status' => 'approved',
            'reviewer_id' => $admin->id,
        ]);

        $childPayload = json_decode((string) DB::table('event_domain_outbox')
            ->where('event_id', $occurrenceId)
            ->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) $childPayload['metadata']['notifications_suppressed']);
        self::assertSame($templateId, (int) $childPayload['metadata']['series']['root_event_id']);
    }

    public function test_reject_requires_reason_and_confirmation_then_uses_canonical_workflow(): void
    {
        $owner = $this->member('Rejected organiser');
        $admin = $this->actingAsAdmin();
        $eventId = $this->pendingEvent($this->testTenantId, (int) $owner->id, [
            'title' => 'Event needing more detail',
        ]);
        $this->queueEvent($this->testTenantId, $eventId, (int) $owner->id);
        $path = "/{$this->testTenantSlug}/accessible/events/moderation/{$eventId}/reject";

        $invalid = $this->accessiblePost($path, ['confirmation' => 'reject'])
            ->assertUnprocessable()
            ->assertSeeText(__('govuk_alpha_events.moderation.reason_required'));
        $this->assertPrivateNoStore($invalid);
        $this->accessiblePost($path, ['reason' => 'Add the venue access details.'])
            ->assertUnprocessable()
            ->assertSeeText(__('govuk_alpha_events.moderation.confirmation_required'));
        self::assertSame('pending_review', DB::table('events')->where('id', $eventId)->value('publication_status'));
        self::assertSame(0, DB::table('event_status_history')->where('event_id', $eventId)->count());

        $reason = 'Add the venue access details before publishing.';
        $this->accessiblePost($path, ['reason' => $reason, 'confirmation' => 'reject'])
            ->assertRedirect($this->queuePath() . '?status=rejected');

        self::assertSame('draft', DB::table('events')->where('id', $eventId)->value('publication_status'));
        $this->assertDatabaseHas('event_status_history', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'actor_user_id' => $admin->id,
            'from_publication_status' => 'pending_review',
            'to_publication_status' => 'draft',
            'reason' => $reason,
        ]);
        $this->assertDatabaseHas('content_moderation_queue', [
            'tenant_id' => $this->testTenantId,
            'content_type' => 'event',
            'content_id' => $eventId,
            'status' => 'rejected',
            'reviewer_id' => $admin->id,
            'rejection_reason' => $reason,
        ]);
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'event.lifecycle.transitioned')
            ->count());
        $payload = json_decode((string) DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($reason, $payload['reason']);
    }

    public function test_decision_routes_are_csrf_backed_post_actions_with_non_mutating_get_confirmations(): void
    {
        $owner = $this->member('Route semantics organiser');
        $this->actingAsAdmin();
        $eventId = $this->pendingEvent($this->testTenantId, (int) $owner->id);
        $this->queueEvent($this->testTenantId, $eventId, (int) $owner->id);

        foreach (['approve', 'reject'] as $decision) {
            $postRoute = app('router')->getRoutes()->getByName("govuk-alpha.events.moderation.{$decision}");
            $getRoute = app('router')->getRoutes()->getByName("govuk-alpha.events.moderation.{$decision}.confirm");
            self::assertNotNull($postRoute);
            self::assertNotNull($getRoute);
            self::assertSame(['POST'], $postRoute->methods());
            self::assertSame(['GET', 'HEAD'], $getRoute->methods());
            self::assertContains('web', $postRoute->gatherMiddleware());
            self::assertContains(\App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication::class, $postRoute->gatherMiddleware());

            $this->get("/{$this->testTenantSlug}/accessible/events/moderation/{$eventId}/{$decision}")
                ->assertOk()
                ->assertSee('name="_token"', false)
                ->assertSee("/events/moderation/{$eventId}/{$decision}", false);
            self::assertSame('pending_review', DB::table('events')->where('id', $eventId)->value('publication_status'));
        }
    }

    private function queuePath(): string
    {
        return "/{$this->testTenantSlug}/accessible/events/moderation";
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->forTenant($this->testTenantId)->create([
            'role' => 'tenant_admin',
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function member(string $name): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'name' => $name,
            'first_name' => $name,
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function pendingEvent(int $tenantId, int $ownerId, array $overrides = []): int
    {
        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'title' => 'Accessible pending event',
            'description' => 'Event details awaiting an admin decision.',
            'location' => 'Community venue',
            'start_time' => now()->addWeek(),
            'end_time' => now()->addWeek()->addHours(2),
            'timezone' => 'Europe/Dublin',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'is_online' => 0,
            'status' => 'draft',
            'publication_status' => 'pending_review',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'is_recurring_template' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function queueEvent(
        int $tenantId,
        int $eventId,
        int $ownerId,
        mixed $createdAt = null,
    ): void {
        DB::table('content_moderation_queue')->insert([
            'tenant_id' => $tenantId,
            'content_type' => 'event',
            'content_id' => $eventId,
            'author_id' => $ownerId,
            'title' => 'Pending Event review',
            'status' => 'pending',
            'auto_flagged' => 0,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function accessiblePost(string $uri, array $data): \Illuminate\Testing\TestResponse
    {
        $token = 'accessible-event-moderation-csrf-token';
        $this->withSession(['_token' => $token]);

        return $this->post($uri, array_merge(['_token' => $token], $data));
    }

    private function assertPrivateNoStore(\Illuminate\Testing\TestResponse $response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
    }
}
