<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use App\Models\User;
use App\Services\EventInvitationCampaignService;
use App\Services\EventInvitationService;
use App\Services\EventRegistrationGuestService;
use App\Services\EventRegistrationSubmissionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventRegistrationFormFixtures;
use Tests\Laravel\TestCase;

/** HTML-first attendee and organiser parity for the private registration product. */
final class AccessibleEventRegistrationProductTest extends TestCase
{
    use BuildsEventRegistrationFormFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        foreach ([
            'HTTP_X_TENANT_ID',
            'HTTP_X_TENANT_SLUG',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
        ] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }
        $this->setEventsFeature(true);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_registration_product_requires_authentication_and_authenticated_html_is_private(): void
    {
        $owner = $this->eventUser();
        [$eventId] = $this->registrationEvent((int) $owner->id);
        $base = $this->base($eventId);

        $this->get($base)
            ->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");

        $this->signIn($owner);
        $response = $this->get($base);
        $response->assertOk()
            ->assertSeeText(__('event_registration.title'))
            ->assertSeeText(__('event_registration.settings.title'))
            ->assertSee('name="_token"', false)
            ->assertDontSee('display_name_ciphertext', false)
            ->assertDontSee('source_snapshot_ciphertext', false)
            ->assertDontSee('token_hash', false);
        $this->assertPrivateNoStore($response);
    }

    public function test_feature_tenant_and_manager_boundaries_fail_closed(): void
    {
        $owner = $this->eventUser();
        $member = $this->eventUser();
        [$eventId, $start] = $this->registrationEvent((int) $owner->id);
        $this->registrationSettings($eventId, $owner, $start);
        $this->signIn($member);

        $this->get($this->base($eventId))->assertOk();
        $this->get($this->base($eventId) . '/forms/new')->assertForbidden();
        $this->accessiblePost($this->base($eventId) . '/settings', [
            'expected_revision' => '2',
            'idempotency_key' => 'accessible-registration-manager-denied',
            'approval_mode' => 'auto',
            'per_member_limit' => '1',
            'guest_retention_days' => '30',
        ])->assertForbidden();

        $this->setEventsFeature(false);
        $this->get($this->base($eventId))->assertForbidden();

        $this->setEventsFeature(true);
        $foreignTenantId = (int) DB::table('tenants')
            ->where('id', '<>', $this->testTenantId)
            ->orderBy('id')
            ->value('id');
        self::assertGreaterThan(0, $foreignTenantId);
        $foreignOwner = User::factory()->forTenant($foreignTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        [$foreignEventId] = $this->registrationEvent(
            (int) $foreignOwner->id,
            tenantId: $foreignTenantId,
        );
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $this->get($this->base($foreignEventId))->assertNotFound();
    }

    public function test_organiser_saves_timezone_aware_settings_then_publishes_them(): void
    {
        $owner = $this->eventUser();
        $start = CarbonImmutable::now('Europe/Dublin')->addMonth()->startOfHour();
        [$eventId] = $this->registrationEvent((int) $owner->id, $start, $start->addHours(2), 'Europe/Dublin');
        $this->signIn($owner);
        $base = $this->base($eventId);

        $this->accessiblePost("{$base}/settings", [
            'expected_revision' => '0',
            'idempotency_key' => 'accessible-registration-settings-save',
            'approval_mode' => 'manual',
            'opens_at' => $start->subDays(14)->format('Y-m-d\TH:i'),
            'closes_at' => $start->subHour()->format('Y-m-d\TH:i'),
            'cancellation_cutoff_at' => $start->subHours(2)->format('Y-m-d\TH:i'),
            'per_member_limit' => '2',
            'guests_enabled' => '1',
            'max_guests_per_registration' => '3',
            'guest_retention_days' => '45',
        ])->assertRedirect("{$base}?status=settings-saved");

        $settings = DB::table('event_registration_settings')->where('event_id', $eventId)->first();
        self::assertNotNull($settings);
        self::assertSame('draft', (string) $settings->status);
        self::assertSame('manual', (string) $settings->approval_mode);
        self::assertSame('Europe/Dublin', (string) $settings->event_timezone_snapshot);
        self::assertSame(1, (int) $settings->revision);
        self::assertSame(2, (int) $settings->per_member_limit);
        self::assertSame(3, (int) $settings->max_guests_per_registration);
        self::assertSame(45, (int) $settings->guest_retention_days);

        $this->accessiblePost("{$base}/settings/publish", [
            'expected_revision' => '1',
            'idempotency_key' => 'accessible-registration-settings-publish',
        ])->assertRedirect("{$base}?status=settings-published");
        self::assertSame('published', DB::table('event_registration_settings')
            ->where('event_id', $eventId)
            ->value('status'));
        self::assertSame(2, (int) DB::table('event_registration_settings')
            ->where('event_id', $eventId)
            ->value('revision'));
    }

    public function test_member_accepts_only_their_bound_invitation_without_exposing_the_secret(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2028-01-01T10:00:00Z'));
        $owner = $this->eventUser();
        $member = $this->eventUser(['preferred_language' => 'fr']);
        [$eventId, $start] = $this->registrationEvent(
            (int) $owner->id,
            CarbonImmutable::parse('2028-02-01T10:00:00Z'),
        );
        $this->registrationSettings($eventId, $owner, $start);
        $preview = (new EventInvitationCampaignService())->preview(
            $eventId,
            $owner,
            'member',
            ['member_id' => (int) $member->id],
            'accessible-registration-invitation-preview',
        );
        $issued = (new EventInvitationService())->issueCampaign(
            $eventId,
            (int) $preview['campaign']->id,
            $owner,
            [],
            1,
            'accessible-registration-invitation-issue',
            $start->subDay()->toIso8601String(),
        );
        $invitation = $issued['invitations'][0];
        $secret = (string) $invitation['secret'];
        $invitationId = (int) $invitation['invitation']->id;
        $this->signIn($member);
        $base = $this->base($eventId);

        $page = $this->get($base);
        $page->assertOk()
            ->assertSeeText(__('event_registration.accessible.accept_invitation'))
            ->assertDontSee($secret, false)
            ->assertDontSee('token_ciphertext', false);
        $this->assertPrivateNoStore($page);

        $this->accessiblePost("{$base}/invitations/{$invitationId}/accept", [
            'idempotency_key' => 'accessible-registration-invitation-accept',
        ])->assertRedirect("{$base}?status=invitation-accepted");
        $this->assertDatabaseHas('event_invitations', [
            'id' => $invitationId,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $eventId,
            'user_id' => (int) $member->id,
            'registration_state' => 'confirmed',
        ]);
    }

    public function test_attendee_submits_encrypted_answers_and_guest_details_without_cross_member_leakage(): void
    {
        $owner = $this->eventUser();
        $member = $this->eventUser(['preferred_language' => 'en']);
        $other = $this->eventUser();
        [$eventId, $start] = $this->registrationEvent((int) $owner->id);
        $settings = $this->registrationSettings($eventId, $owner, $start, true, 2, 30);
        $form = $this->publishedRegistrationForm($eventId, $owner, $settings);
        $registrationId = $this->canonicalRegistration($eventId, (int) $member->id);
        $otherRegistrationId = $this->canonicalRegistration($eventId, (int) $other->id);

        $submissions = new EventRegistrationSubmissionService();
        $otherDraft = $submissions->saveDraft(
            $eventId,
            $otherRegistrationId,
            (int) $form->id,
            $other,
            [
                'display_name' => 'Other private attendee',
                'dietary_needs' => 'TOP-SECRET-OTHER-ANSWER',
                'meal_choice' => 'Standard',
                'waiver' => true,
            ],
            null,
            'accessible-registration-other-draft',
        );
        $submissions->submit(
            $eventId,
            (int) $otherDraft['submission']->id,
            $other,
            (int) $otherDraft['submission']->revision,
            'accessible-registration-other-submit',
        );
        (new EventRegistrationGuestService())->capture(
            $eventId,
            $otherRegistrationId,
            $other,
            1,
            'Other Private Guest',
            'other-private-guest@example.test',
            null,
            true,
            'Guest privacy notice',
            'guest-notice-v1',
        );

        $this->signIn($member);
        $base = $this->base($eventId);
        $this->accessiblePost("{$base}/registrations/{$registrationId}/forms/{$form->id}/submit", [
            'idempotency_key' => 'accessible-registration-answer-submit',
            'answers' => [
                'display_name' => 'Member private answer',
                'dietary_needs' => 'Member confidential allergy',
                'meal_choice' => 'Plant-based',
                'waiver' => '1',
            ],
        ])->assertRedirect("{$base}?status=answers-submitted");
        $submission = DB::table('event_registration_form_submissions')
            ->where('registration_id', $registrationId)
            ->first();
        self::assertNotNull($submission);
        self::assertSame('submitted', (string) $submission->status);
        $answerRows = json_encode(DB::table('event_registration_form_answers')
            ->where('submission_id', $submission->id)
            ->get(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Member private answer', $answerRows);
        self::assertStringNotContainsString('Member confidential allergy', $answerRows);

        $this->accessiblePost("{$base}/registrations/{$registrationId}/guests", [
            'expected_registration_version' => '1',
            'display_name' => 'Own Private Guest',
            'email' => 'own-private-guest@example.test',
            'phone' => '+1 555 123 4567',
            'consent_accepted' => '1',
            'notification_consent' => '1',
        ])->assertRedirect("{$base}?status=guest-added");
        $guest = DB::table('event_registration_guests')
            ->where('registration_id', $registrationId)
            ->first();
        self::assertNotNull($guest);
        self::assertStringNotContainsString('Own Private Guest', (string) $guest->display_name_ciphertext);
        self::assertStringNotContainsString('own-private-guest@example.test', (string) $guest->email_ciphertext);

        $attendeePage = $this->get($base);
        $attendeePage->assertOk()
            ->assertSeeText('Own Private Guest')
            ->assertDontSee('Other Private Guest')
            ->assertDontSee('TOP-SECRET-OTHER-ANSWER')
            ->assertDontSee('other-private-guest@example.test')
            ->assertDontSee('answer_ciphertext', false)
            ->assertDontSee('identity_fingerprint', false);
        $this->assertPrivateNoStore($attendeePage);

        $this->signIn($owner);
        $review = $this->accessiblePost("{$base}/submissions/{$submission->id}/review", [
            'purpose' => 'Accessible attendee support review',
            'correlation_id' => 'accessible-registration-review-correlation',
            'include_sensitive' => '1',
        ]);
        $review->assertOk()
            ->assertSeeText('Member private answer')
            ->assertSeeText('Member confidential allergy')
            ->assertDontSee('TOP-SECRET-OTHER-ANSWER');
        $this->assertPrivateNoStore($review);
        self::assertGreaterThan(0, DB::table('event_registration_answer_access_audits')
            ->where('submission_id', $submission->id)
            ->count());
    }

    public function test_attendee_form_honours_required_ranges_and_conditional_visibility(): void
    {
        $owner = $this->eventUser();
        $member = $this->eventUser();
        [$eventId, $start] = $this->registrationEvent((int) $owner->id);
        $settings = $this->registrationSettings($eventId, $owner, $start);
        $form = $this->publishedRegistrationForm($eventId, $owner, $settings, [
            [
                'stable_key' => 'attendance_mode',
                'question_type' => 'single_choice',
                'prompt' => 'Attendance mode',
                'is_required' => true,
                'data_classification' => 'internal',
                'purpose' => 'Event planning',
                'retention_days' => 30,
                'choice_options' => ['online', 'onsite'],
            ],
            [
                'stable_key' => 'onsite_support',
                'question_type' => 'long_text',
                'prompt' => 'On-site support',
                'is_required' => true,
                'data_classification' => 'sensitive',
                'purpose' => 'Accessibility planning',
                'retention_days' => 30,
                'validation_rules' => ['min_length' => 3, 'max_length' => 10],
                'visibility_rules' => [
                    'match' => 'all',
                    'conditions' => [[
                        'question_key' => 'attendance_mode',
                        'operator' => 'equals',
                        'value' => 'onsite',
                    ]],
                ],
            ],
            [
                'stable_key' => 'topics',
                'question_type' => 'multiple_choice',
                'prompt' => 'Topics',
                'is_required' => false,
                'data_classification' => 'internal',
                'purpose' => 'Programme planning',
                'retention_days' => 30,
                'choice_options' => ['one', 'two', 'three'],
                'validation_rules' => ['min_selections' => 2, 'max_selections' => 3],
            ],
        ]);
        $registrationId = $this->canonicalRegistration($eventId, (int) $member->id);
        $this->signIn($member);
        $base = $this->base($eventId);

        $this->get($base)->assertOk()
            ->assertSee('data-alpha-registration-form', false)
            ->assertSee('data-alpha-registration-question="onsite_support"', false)
            ->assertSee('data-alpha-registration-visibility=', false)
            ->assertSee('minlength="3"', false)
            ->assertSee('maxlength="10"', false)
            ->assertSee('data-alpha-registration-min-selections-message=', false);

        $this->accessiblePost("{$base}/registrations/{$registrationId}/forms/{$form->id}/submit", [
            'idempotency_key' => 'accessible-registration-rules-invalid',
            'answers' => [
                'attendance_mode' => 'onsite',
                'onsite_support' => 'x',
                'topics' => ['one'],
            ],
        ])->assertRedirect($base);
        $this->get($base)->assertOk()
            ->assertSeeText(__('event_registration.accessible.validation_error'))
            ->assertSee('>x</textarea>', false);

        $this->accessiblePost("{$base}/registrations/{$registrationId}/forms/{$form->id}/submit", [
            'idempotency_key' => 'accessible-registration-rules-valid',
            'answers' => [
                'attendance_mode' => 'online',
                'onsite_support' => 'must-ignore',
            ],
        ])->assertRedirect("{$base}?status=answers-submitted");

        $submissionId = (int) DB::table('event_registration_form_submissions')
            ->where('registration_id', $registrationId)
            ->value('id');
        self::assertGreaterThan(0, $submissionId);
        $answeredKeys = DB::table('event_registration_form_answers as answer')
            ->join('event_registration_form_questions as question', 'question.id', '=', 'answer.question_id')
            ->where('answer.submission_id', $submissionId)
            ->pluck('question.stable_key')
            ->all();
        self::assertSame(['attendance_mode'], $answeredKeys);
    }

    public function test_accessible_registration_mutations_keep_auth_csrf_and_throttle_middleware(): void
    {
        $csrfMiddleware = array_values(array_filter([
            'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
            'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
        ], static fn (string $class): bool => class_exists($class)));
        $routes = collect(Route::getRoutes())
            ->filter(static fn ($route): bool => str_starts_with(
                (string) $route->getName(),
                'govuk-alpha.events.registration.',
            ));
        self::assertNotEmpty($routes);
        self::assertNotNull(Route::getRoutes()->getByName('govuk-alpha.events.registration.index'));

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            self::assertContains(RequireAccessibleAuthentication::class, $middleware);
            if (! in_array('POST', $route->methods(), true)) {
                continue;
            }
            self::assertTrue(
                in_array('web', $middleware, true)
                    || count(array_intersect($csrfMiddleware, $middleware)) > 0,
                sprintf('Route %s must retain CSRF middleware.', $route->getName()),
            );
            self::assertTrue(
                collect($middleware)->contains(static fn (string $name): bool => str_starts_with($name, 'throttle:')
                    || str_contains($name, 'ThrottleRequests:')),
                sprintf('Route %s must retain a mutation throttle.', $route->getName()),
            );
        }
    }

    private function base(int $eventId): string
    {
        return "/{$this->testTenantSlug}/accessible/events/{$eventId}/registration";
    }

    private function signIn(User $user): void
    {
        Sanctum::actingAs($user, ['*']);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function setEventsFeature(bool $enabled): void
    {
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => $enabled], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    /** @param array<string,mixed> $data */
    private function accessiblePost(string $uri, array $data): TestResponse
    {
        $token = 'accessible-event-registration-token';
        $this->withSession(['_token' => $token]);

        return $this->post($uri, ['_token' => $token, ...$data]);
    }

    private function assertPrivateNoStore(TestResponse $response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertSame('no-cache', $response->headers->get('Pragma'));
    }
}
