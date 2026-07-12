<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class AccessibleEventSafetyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setEventsFeature(true);
    }

    public function test_organizer_saves_and_publishes_requirements_through_private_html_forms(): void
    {
        $owner = $this->member('Accessible Safety Owner');
        $eventId = $this->event((int) $owner->id);
        Sanctum::actingAs($owner, ['*']);
        $path = $this->safetyPath($eventId);

        $page = $this->get($path);
        $page->assertOk()
            ->assertSeeText(__('event_safety.govuk.title'))
            ->assertSeeText(__('event_safety.govuk.policy_title'));
        self::assertStringContainsString('private', (string) $page->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));

        $this->accessiblePost($path, [
            'action' => 'save_requirements',
            'idempotency_key' => 'accessible-safety-draft-1',
            'minimum_age' => '13',
            'guardian_consent_required' => '1',
            'minor_age_threshold' => '18',
            'code_of_conduct_required' => '1',
            'code_of_conduct_text' => 'Use the published accessible conduct policy.',
            'code_of_conduct_text_version' => 'accessible-conduct-v1',
        ])->assertRedirect("{$path}?status=safety-updated");

        $requirements = DB::table('event_safety_requirements')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->firstOrFail();
        self::assertSame('draft', (string) $requirements->status);
        $version = DB::table('event_safety_requirement_versions')
            ->where('tenant_id', $this->testTenantId)
            ->where('requirements_id', (int) $requirements->id)
            ->where('version_number', (int) $requirements->current_version)
            ->firstOrFail();

        $this->accessiblePost($path, [
            'action' => 'publish_requirements',
            'idempotency_key' => 'accessible-safety-publish-1',
            'expected_revision' => (string) $requirements->revision,
            'expected_version' => (string) $version->version_number,
        ])->assertRedirect("{$path}?status=safety-updated");

        self::assertSame('published', DB::table('event_safety_requirements')
            ->where('id', (int) $requirements->id)
            ->value('status'));
        $published = $this->get($path);
        $published->assertOk()
            ->assertSeeText('Use the published accessible conduct policy.')
            ->assertSeeText(__('event_safety.govuk.requirement_status.published'));
        self::assertStringContainsString('no-store', (string) $published->headers->get('Cache-Control'));
    }

    public function test_attendee_acknowledgement_and_guardian_request_never_disclose_capability_or_identity(): void
    {
        $owner = $this->member('Accessible Safety Owner Two');
        $start = CarbonImmutable::now('UTC')->addMonths(3)->startOfDay()->addHours(10);
        $minor = $this->member('Accessible Safety Minor', [
            'email' => 'accessible-safety-minor@example.test',
            'date_of_birth' => $start->subYears(16)->addDay()->toDateString(),
        ]);
        $eventId = $this->event((int) $owner->id, $start);
        $path = $this->safetyPath($eventId);

        Sanctum::actingAs($owner, ['*']);
        $this->accessiblePost($path, [
            'action' => 'save_requirements',
            'idempotency_key' => 'accessible-safety-attendee-draft',
            'guardian_consent_required' => '1',
            'minor_age_threshold' => '18',
            'code_of_conduct_required' => '1',
            'code_of_conduct_text' => 'Respect everyone at this accessible event.',
            'code_of_conduct_text_version' => 'accessible-attendee-v1',
        ])->assertRedirect("{$path}?status=safety-updated");
        $requirements = DB::table('event_safety_requirements')
            ->where('event_id', $eventId)
            ->firstOrFail();
        $version = DB::table('event_safety_requirement_versions')
            ->where('requirements_id', (int) $requirements->id)
            ->where('version_number', (int) $requirements->current_version)
            ->firstOrFail();
        $this->accessiblePost($path, [
            'action' => 'publish_requirements',
            'idempotency_key' => 'accessible-safety-attendee-publish',
            'expected_revision' => (string) $requirements->revision,
            'expected_version' => (string) $version->version_number,
        ])->assertRedirect("{$path}?status=safety-updated");

        Sanctum::actingAs($minor, ['*']);
        $attendeePage = $this->get($path);
        $attendeePage->assertOk()
            ->assertSeeText('Respect everyone at this accessible event.')
            ->assertSeeText(__('event_safety.govuk.attendee_title'))
            ->assertDontSeeText(__('event_safety.govuk.policy_title'));
        self::assertStringContainsString('no-store', (string) $attendeePage->headers->get('Cache-Control'));

        $this->accessiblePost($path, [
            'action' => 'acknowledge_code',
            'idempotency_key' => 'accessible-safety-acknowledge',
            'text_version' => (string) $version->code_of_conduct_text_version,
            'text_hash' => (string) $version->code_of_conduct_text_hash,
        ])->assertRedirect("{$path}?status=safety-updated");
        self::assertSame(1, DB::table('event_safety_code_acknowledgements')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->where('user_id', (int) $minor->id)
            ->where('requirements_id', (int) $requirements->id)
            ->where('requirements_version_id', (int) $version->id)
            ->where('requirements_version_number', (int) $version->version_number)
            ->where('action', 'acknowledged')
            ->where('text_version', (string) $version->code_of_conduct_text_version)
            ->where('text_hash', (string) $version->code_of_conduct_text_hash)
            ->count());

        $guardianName = 'Private Accessible Guardian';
        $guardianEmail = 'private-accessible-guardian@example.test';
        $guardianResponse = $this->accessiblePost($path, [
            'action' => 'request_guardian_consent',
            'idempotency_key' => 'accessible-safety-guardian-request',
            'guardian_name' => $guardianName,
            'guardian_email' => $guardianEmail,
            'relationship_code' => 'guardian',
        ]);
        $guardianResponse->assertRedirect("{$path}?status=safety-updated")
            ->assertDontSee($guardianName)
            ->assertDontSee($guardianEmail)
            ->assertDontSee('nxeg1_');

        $stored = DB::table('event_guardian_consents')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->where('minor_user_id', (int) $minor->id)
            ->firstOrFail();
        self::assertStringNotContainsString($guardianName, (string) $stored->guardian_identity_ciphertext);
        self::assertStringNotContainsString($guardianEmail, (string) $stored->guardian_email_ciphertext);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $stored->token_hash);

        $durableDelivery = json_encode([
            DB::table('event_domain_outbox')->where('event_id', $eventId)->get()->all(),
            DB::table('event_guardian_consent_delivery_envelopes')
                ->where('consent_id', (int) $stored->id)
                ->get()
                ->all(),
        ], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($guardianName, $durableDelivery);
        self::assertStringNotContainsString($guardianEmail, $durableDelivery);
        self::assertStringNotContainsString('nxeg1_', $durableDelivery);

        $refreshed = $this->get($path);
        $refreshed->assertOk()
            ->assertDontSee($guardianName)
            ->assertDontSee($guardianEmail)
            ->assertDontSee('nxeg1_');
        self::assertStringContainsString('no-store', (string) $refreshed->headers->get('Cache-Control'));
    }

    public function test_unauthorized_mutation_and_disabled_module_fail_without_safety_changes(): void
    {
        $owner = $this->member('Accessible Safety Owner Three');
        $outsider = $this->member('Accessible Safety Outsider');
        $eventId = $this->event((int) $owner->id);
        $path = $this->safetyPath($eventId);
        Sanctum::actingAs($outsider, ['*']);

        $this->accessiblePost($path, [
            'action' => 'save_requirements',
            'idempotency_key' => 'accessible-safety-unauthorized',
            'code_of_conduct_required' => '1',
            'code_of_conduct_text' => 'An outsider must not save this.',
            'code_of_conduct_text_version' => 'unauthorized-v1',
        ])->assertRedirect("{$path}?status=safety-failed");
        self::assertSame(0, DB::table('event_safety_requirements')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->count());

        $this->setEventsFeature(false);
        $this->get($path)->assertForbidden();
        $this->accessiblePost($path, [
            'action' => 'save_requirements',
            'idempotency_key' => 'accessible-safety-disabled',
        ])->assertForbidden();
        self::assertSame(0, DB::table('event_safety_requirements')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->count());
    }

    /** @param array<string,mixed> $data */
    private function accessiblePost(string $uri, array $data): TestResponse
    {
        $token = 'accessible-event-safety-token';
        $this->withSession(['_token' => $token]);

        return $this->post($uri, ['_token' => $token, ...$data]);
    }

    /** @param array<string,mixed> $overrides */
    private function member(string $name, array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'name' => $name,
            'first_name' => $name,
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
            'preferred_language' => 'en',
        ], $overrides));
    }

    private function event(
        int $ownerId,
        ?CarbonImmutable $start = null,
    ): int {
        $start ??= CarbonImmutable::now('UTC')->addMonths(2)->startOfHour();

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'title' => 'Accessible safety event',
            'description' => 'Accessible safety event fixture.',
            'location' => 'Accessible venue',
            'start_time' => $start,
            'end_time' => $start->addHours(2),
            'timezone' => 'UTC',
            'timezone_source' => 'test',
            'all_day' => false,
            'max_attendees' => 20,
            'is_online' => false,
            'allow_remote_attendance' => false,
            'federated_visibility' => 'none',
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 1,
            'calendar_sequence' => 1,
            'is_recurring_template' => false,
            'occurrence_key' => 'accessible-safety:' . bin2hex(random_bytes(12)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function safetyPath(int $eventId): string
    {
        return "/{$this->testTenantSlug}/accessible/events/{$eventId}/safety";
    }

    private function setEventsFeature(bool $enabled): void
    {
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => $enabled], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }
}
