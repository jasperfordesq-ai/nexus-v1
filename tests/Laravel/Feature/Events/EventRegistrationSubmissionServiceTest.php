<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Exceptions\EventRegistrationFoundationException;
use App\Models\EventRegistrationFormAnswer;
use App\Models\EventRegistrationFormSubmission;
use App\Services\EventRegistrationSubmissionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventRegistrationFormFixtures;
use Tests\Laravel\TestCase;

final class EventRegistrationSubmissionServiceTest extends TestCase
{
    use DatabaseTransactions;
    use BuildsEventRegistrationFormFixtures;

    public function test_answers_are_validated_encrypted_hidden_and_every_read_or_export_is_audited(): void
    {
        $owner = $this->eventUser();
        $member = $this->eventUser();
        [$eventId, $start] = $this->registrationEvent((int) $owner->id);
        $settings = $this->registrationSettings($eventId, $owner, $start);
        $form = $this->publishedRegistrationForm($eventId, $owner, $settings);
        $registrationId = $this->canonicalRegistration($eventId, (int) $member->id);
        $registrationBefore = DB::table('event_registrations')->find($registrationId);
        $service = new EventRegistrationSubmissionService();
        $answers = [
            'display_name' => 'Private attendee name',
            'dietary_needs' => 'Severe peanut allergy',
            'meal_choice' => 'Plant-based',
            'waiver' => true,
        ];
        $draft = $service->saveDraft(
            $eventId,
            $registrationId,
            (int) $form->id,
            $member,
            $answers,
            null,
            'submission-save-encrypted',
        );
        $replay = $service->saveDraft(
            $eventId,
            $registrationId,
            (int) $form->id,
            $member,
            $answers,
            null,
            'submission-save-encrypted',
        );
        self::assertTrue($draft['changed']);
        self::assertFalse($replay['changed']);
        self::assertFalse($draft['submission']->relationLoaded('answers'));
        self::assertArrayNotHasKey('answers', $draft['submission']->toArray());

        $storedAnswers = DB::table('event_registration_form_answers')
            ->where('submission_id', $draft['submission']->id)
            ->get();
        self::assertCount(4, $storedAnswers);
        $serializedRows = json_encode($storedAnswers, JSON_THROW_ON_ERROR);
        foreach (array_filter($answers, 'is_string') as $plaintext) {
            self::assertStringNotContainsString($plaintext, $serializedRows);
        }
        $answerModel = EventRegistrationFormAnswer::withoutGlobalScopes()
            ->where('submission_id', $draft['submission']->id)
            ->firstOrFail();
        self::assertArrayNotHasKey('answer_ciphertext', $answerModel->toArray());
        self::assertArrayNotHasKey('displayed_text_hash', $answerModel->toArray());

        $submitted = $service->submit(
            $eventId,
            (int) $draft['submission']->id,
            $member,
            1,
            'submission-submit',
        );
        self::assertSame('submitted', $submitted['submission']->status->value);
        $submittedAnswerId = (int) DB::table('event_registration_form_answers')
            ->where('submission_id', $draft['submission']->id)
            ->value('id');
        $this->assertImmutableQuery(
            fn () => DB::table('event_registration_form_answers')
                ->where('id', $submittedAnswerId)
                ->update(['answer_ciphertext' => 'plaintext-tamper']),
            'event_registration_submitted_answer_immutable',
        );
        $read = $service->readAnswers(
            $eventId,
            (int) $draft['submission']->id,
            $owner,
            'Operational attendee support',
            'answer-read-correlation',
        );
        $export = $service->readAnswers(
            $eventId,
            (int) $draft['submission']->id,
            $owner,
            'Authorised event export',
            'answer-export-correlation',
            'export',
        );
        self::assertSame($answers['dietary_needs'], $read['dietary_needs']['value']);
        self::assertSame($answers['waiver'], $export['waiver']['value']);
        self::assertSame(8, DB::table('event_registration_answer_access_audits')->count());
        self::assertSame(4, DB::table('event_registration_answer_access_audits')->where('action', 'read')->count());
        self::assertSame(4, DB::table('event_registration_answer_access_audits')->where('action', 'export')->count());
        $auditId = (int) DB::table('event_registration_answer_access_audits')->value('id');
        $this->assertImmutableQuery(
            fn () => DB::table('event_registration_answer_access_audits')
                ->where('id', $auditId)
                ->update(['purpose' => 'tampered']),
            'ev_reg_answer_access_audit_immutable',
        );

        $registrationAfter = DB::table('event_registrations')->find($registrationId);
        self::assertSame((int) $registrationBefore->registration_version, (int) $registrationAfter->registration_version);
        self::assertSame(1, (int) $registrationAfter->party_size);
        self::assertSame((string) $registrationBefore->registration_state, (string) $registrationAfter->registration_state);
    }

    public function test_required_choice_type_consent_and_optimistic_rules_fail_closed(): void
    {
        $owner = $this->eventUser();
        $member = $this->eventUser();
        [$eventId, $start] = $this->registrationEvent((int) $owner->id);
        $settings = $this->registrationSettings($eventId, $owner, $start);
        $form = $this->publishedRegistrationForm($eventId, $owner, $settings);
        $registrationId = $this->canonicalRegistration($eventId, (int) $member->id);
        $service = new EventRegistrationSubmissionService();

        $this->assertReason(
            'event_registration_choice_answer_invalid',
            fn () => $service->saveDraft(
                $eventId,
                $registrationId,
                (int) $form->id,
                $member,
                ['meal_choice' => 'Unlisted'],
                null,
                'submission-invalid-choice',
            ),
        );
        $this->assertReason(
            'event_registration_text_answer_invalid',
            fn () => $service->saveDraft(
                $eventId,
                $registrationId,
                (int) $form->id,
                $member,
                ['display_name' => ['not-a-string']],
                null,
                'submission-invalid-type',
            ),
        );
        $draft = $service->saveDraft(
            $eventId,
            $registrationId,
            (int) $form->id,
            $member,
            [
                'display_name' => 'Attendee',
                'meal_choice' => 'Standard',
                'waiver' => false,
            ],
            null,
            'submission-declined-waiver',
        );
        $this->assertReason(
            'event_registration_required_answer_invalid',
            fn () => $service->submit(
                $eventId,
                (int) $draft['submission']->id,
                $member,
                1,
                'submission-submit-without-consent',
            ),
        );
        $updated = $service->saveDraft(
            $eventId,
            $registrationId,
            (int) $form->id,
            $member,
            [
                'display_name' => 'Attendee',
                'meal_choice' => 'Standard',
                'waiver' => true,
            ],
            1,
            'submission-consent-corrected',
        );
        self::assertSame(2, (int) $updated['submission']->revision);
        $this->assertReason(
            'event_registration_submission_revision_conflict',
            fn () => $service->saveDraft(
                $eventId,
                $registrationId,
                (int) $form->id,
                $member,
                ['display_name' => 'Stale'],
                1,
                'submission-stale-revision',
            ),
        );
    }

    /** @param callable():mixed $operation */
    private function assertReason(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail("Expected {$reason}.");
        } catch (EventRegistrationFoundationException $exception) {
            self::assertSame($reason, $exception->getMessage());
        }
    }

    /** @param callable():mixed $operation */
    private function assertImmutableQuery(callable $operation, string $needle): void
    {
        try {
            $operation();
            self::fail("Expected {$needle}.");
        } catch (QueryException $exception) {
            self::assertStringContainsString($needle, $exception->getMessage());
        }
    }
}
