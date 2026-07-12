<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Exceptions\EventRegistrationFoundationException;
use App\Models\EventRegistrationFormVersion;
use App\Models\User;
use App\Services\EventInvitationCampaignService;
use App\Services\EventInvitationService;
use App\Services\EventRegistrationFormService;
use App\Services\EventRegistrationGuestAttendanceService;
use App\Services\EventRegistrationGuestService;
use App\Services\EventRegistrationProductQueryService;
use App\Services\EventRegistrationRetentionService;
use App\Services\EventRegistrationSettingsService;
use App\Services\EventRegistrationSubmissionExportService;
use App\Services\EventRegistrationSubmissionService;
use App\Services\EventService;
use App\Support\Events\EventRegistrationFoundationSupport;
use App\Support\Events\EventRegistrationFormRuleSet;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** HTML-first registration forms, invitation campaigns, guests and retention. */
trait EventRegistrationParity
{
    public function eventsRegistrationProduct(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }

        try {
            return $this->eventsRegistrationPage(
                $request,
                $tenantSlug,
                $id,
                $actor,
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationFailure($exception, $tenantSlug, $id);
        }
    }

    public function eventsRegistrationSaveSettings(
        Request $request,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        $revision = $this->eventsRegistrationNonNegativeInteger($request->input('expected_revision'));
        $perMemberLimit = $this->eventsRegistrationPositiveInteger($request->input('per_member_limit'));
        $guestRetentionDays = $this->eventsRegistrationPositiveInteger($request->input('guest_retention_days'));
        $guestsEnabled = $request->boolean('guests_enabled');
        $maxGuests = $guestsEnabled
            ? $this->eventsRegistrationPositiveInteger($request->input('max_guests_per_registration'))
            : 0;
        $approvalMode = $request->input('approval_mode');
        $opensAt = $request->input('opens_at');
        $closesAt = $request->input('closes_at');
        $cancellationCutoff = $request->input('cancellation_cutoff_at');
        $dateInputsAreValid = collect([$opensAt, $closesAt, $cancellationCutoff])
            ->every(static fn (mixed $value): bool => $value === null || is_string($value));
        if ($key === null || $revision === null || $perMemberLimit === null
            || $guestRetentionDays === null || $maxGuests === null
            || ! $dateInputsAreValid || ! is_string($approvalMode)
            || ! in_array($approvalMode, ['auto', 'manual'], true)) {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')])
                ->withInput($request->except(['idempotency_key']));
        }

        try {
            $support = app(EventRegistrationFoundationSupport::class);
            $tenantId = $support->tenantId();
            $event = $support->concreteEvent($tenantId, $id);
            $timezone = $support->eventTimezone($event);
            app(EventRegistrationSettingsService::class)->save(
                $id,
                $actor,
                [
                    'approval_mode' => $approvalMode,
                    'opens_at' => $this->eventsRegistrationLocalInstant($opensAt, $timezone),
                    'closes_at' => $this->eventsRegistrationLocalInstant($closesAt, $timezone),
                    'cancellation_cutoff_at' => $this->eventsRegistrationLocalInstant(
                        $cancellationCutoff,
                        $timezone,
                    ),
                    'per_member_limit' => $perMemberLimit,
                    'guests_enabled' => $guestsEnabled,
                    'max_guests_per_registration' => $maxGuests,
                    'guest_retention_days' => $guestRetentionDays,
                ],
                $revision,
                $key,
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure(
                $exception,
                $tenantSlug,
                $id,
                request: $request,
            );
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'settings-saved');
    }

    public function eventsRegistrationPublishSettings(
        Request $request,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        $revision = $this->eventsRegistrationPositiveInteger($request->input('expected_revision'));
        if ($key === null || $revision === null) {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            app(EventRegistrationSettingsService::class)->publish($id, $actor, $revision, $key);
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'settings-published');
    }

    public function eventsRegistrationFormEditor(
        Request $request,
        string $tenantSlug,
        int $id,
        ?int $formId = null,
    ): Response|RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }

        try {
            $overview = app(EventRegistrationProductQueryService::class)
                ->organizerOverview($id, $actor);
            $form = $formId === null
                ? null
                : collect($overview['forms'])->first(
                    static fn (mixed $candidate): bool => $candidate instanceof EventRegistrationFormVersion
                        && (int) $candidate->id === $formId,
                );
            if ($formId !== null && ! $form instanceof EventRegistrationFormVersion) {
                abort(404);
            }
            if ($form instanceof EventRegistrationFormVersion
                && $form->status->value !== 'draft') {
                throw new EventRegistrationFoundationException('event_registration_published_form_immutable');
            }

            $questionRows = $form instanceof EventRegistrationFormVersion
                ? $form->questions->map(static fn ($question): array => $question->toArray())->all()
                : [];
            for ($index = 0; $index < 5; $index++) {
                $questionRows[] = [];
            }

            return $this->eventsRegistrationPrivateResponse($this->view(
                'accessible-frontend::event-registration-form-editor',
                [
                    'title' => __('event_registration.forms.editor.' . ($form === null ? 'create_title' : 'edit_title')),
                    'tenantSlug' => $tenantSlug,
                    'activeNav' => 'events',
                    'eventId' => $id,
                    'form' => $form,
                    'questionRows' => $questionRows,
                    'settingsRevision' => (int) $overview['settings']->revision,
                    'status' => is_string($request->query('status'))
                        ? trim((string) $request->query('status'))
                        : null,
                ],
            ));
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationFailure($exception, $tenantSlug, $id);
        }
    }

    public function eventsRegistrationSaveForm(
        Request $request,
        string $tenantSlug,
        int $id,
        ?int $formId = null,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        $settingsRevision = $this->eventsRegistrationPositiveInteger(
            $request->input('expected_settings_revision'),
        );
        $formRevision = $formId === null ? null : $this->eventsRegistrationPositiveInteger(
            $request->input('expected_form_revision'),
        );
        $name = is_string($request->input('name')) ? trim((string) $request->input('name')) : '';
        $description = is_string($request->input('description'))
            ? trim((string) $request->input('description'))
            : null;
        $questions = $this->eventsRegistrationQuestions($request->input('questions'));
        if ($key === null || $settingsRevision === null || $name === '' || $questions === []
            || ($formId !== null && $formRevision === null)) {
            return $this->eventsRegistrationFormRedirect($tenantSlug, $id, $formId)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')])
                ->withInput($request->except(['idempotency_key']));
        }

        try {
            $forms = app(EventRegistrationFormService::class);
            if ($formId === null) {
                $forms->createDraft(
                    $id,
                    $actor,
                    $name,
                    $description,
                    $questions,
                    $settingsRevision,
                    $key,
                );
            } else {
                $forms->updateDraft(
                    $id,
                    $formId,
                    $actor,
                    compact('name', 'description', 'questions'),
                    (int) $formRevision,
                    $settingsRevision,
                    $key,
                );
            }
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure(
                $exception,
                $tenantSlug,
                $id,
                $this->eventsRegistrationFormRoute($formId),
                $formId === null ? [] : ['formId' => $formId],
                $request,
            );
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'form-saved');
    }

    public function eventsRegistrationPublishForm(
        Request $request,
        string $tenantSlug,
        int $id,
        int $formId,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        $formRevision = $this->eventsRegistrationPositiveInteger($request->input('expected_form_revision'));
        $settingsRevision = $this->eventsRegistrationPositiveInteger($request->input('expected_settings_revision'));
        if ($key === null || $formRevision === null || $settingsRevision === null) {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            app(EventRegistrationFormService::class)->publish(
                $id,
                $formId,
                $actor,
                $formRevision,
                $settingsRevision,
                $key,
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'form-published');
    }

    public function eventsRegistrationForkForm(
        Request $request,
        string $tenantSlug,
        int $id,
        int $formId,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        $settingsRevision = $this->eventsRegistrationPositiveInteger($request->input('expected_settings_revision'));
        if ($key === null || $settingsRevision === null) {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            app(EventRegistrationFormService::class)->forkPublished(
                $id,
                $formId,
                $actor,
                $settingsRevision,
                $key,
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'form-forked');
    }

    public function eventsRegistrationSubmitAnswers(
        Request $request,
        string $tenantSlug,
        int $id,
        int $registrationId,
        int $formId,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        if ($key === null) {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }

        try {
            $state = app(EventRegistrationProductQueryService::class)->attendeeState($id, $actor);
            $registration = collect($state['registrations'])->first(
                static fn (mixed $row): bool => (int) data_get($row, 'id') === $registrationId,
            );
            $form = $state['form'];
            if ($registration === null
                || ! $form instanceof EventRegistrationFormVersion
                || (int) $form->id !== $formId) {
                throw new EventRegistrationFoundationException('event_registration_submission_identity_mismatch');
            }
            $answers = $this->eventsRegistrationAnswers($request, $form);
            $existing = collect($state['submissions'])->first(
                static fn (mixed $row): bool => (int) data_get($row, 'registration_id') === $registrationId
                    && (int) data_get($row, 'form_version_id') === $formId
                    && (int) data_get($row, 'effective_slot') === 1
                    && (string) data_get($row, 'status') === 'draft',
            );
            $submissionKey = $key . ':draft';
            $saved = app(EventRegistrationSubmissionService::class)->saveDraft(
                $id,
                $registrationId,
                $formId,
                $actor,
                $answers,
                $existing === null ? null : (int) data_get($existing, 'revision'),
                $submissionKey,
            );
            app(EventRegistrationSubmissionService::class)->submit(
                $id,
                (int) $saved['submission']->id,
                $actor,
                (int) $saved['submission']->revision,
                $key . ':submit',
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id, request: $request);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'answers-submitted');
    }

    public function eventsRegistrationReviewAnswers(
        Request $request,
        string $tenantSlug,
        int $id,
        int $submissionId,
    ): Response|RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $purpose = is_string($request->input('purpose')) ? trim((string) $request->input('purpose')) : '';
        $correlation = is_string($request->input('correlation_id'))
            ? trim((string) $request->input('correlation_id'))
            : '';
        if ($purpose === '' || $correlation === '') {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            $answers = app(EventRegistrationSubmissionService::class)->readAnswers(
                $id,
                $submissionId,
                $actor,
                $purpose,
                $correlation,
                'read',
                $request->boolean('include_sensitive'),
            );
            $overview = app(EventRegistrationProductQueryService::class)->organizerOverview($id, $actor);
            $questions = [];
            foreach ($overview['forms'] as $form) {
                foreach ($form->questions as $question) {
                    $questions[(int) $question->id] = (string) $question->prompt;
                }
            }
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsRegistrationPrivateResponse($this->view(
            'accessible-frontend::event-registration-answers',
            [
                'title' => __('event_registration.accessible.review_submission', ['id' => $submissionId]),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'events',
                'eventId' => $id,
                'submissionId' => $submissionId,
                'answers' => $answers,
                'questions' => $questions,
            ],
        ));
    }

    public function eventsRegistrationExportAnswers(
        Request $request,
        string $tenantSlug,
        int $id,
    ): StreamedResponse|RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $purpose = is_string($request->input('purpose')) ? trim((string) $request->input('purpose')) : '';
        $correlation = is_string($request->input('correlation_id'))
            ? trim((string) $request->input('correlation_id'))
            : '';
        if ($purpose === '' || $correlation === '') {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            $csv = app(EventRegistrationSubmissionExportService::class)->export(
                $id,
                $actor,
                $purpose,
                $correlation,
                $request->boolean('include_sensitive'),
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationFailure($exception, $tenantSlug, $id);
        }

        return response()->streamDownload(static function () use ($csv): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $csv['headers'], ',', '"', '\\');
            foreach ($csv['rows'] as $row) {
                fputcsv($stream, $row, ',', '"', '\\');
            }
            fclose($stream);
        }, "event-registration-{$id}.csv", [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function eventsRegistrationPreviewCampaign(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        $type = is_string($request->input('campaign_type'))
            ? trim((string) $request->input('campaign_type'))
            : '';
        $rawSource = is_string($request->input('source')) ? (string) $request->input('source') : '';
        $source = $this->eventsRegistrationCampaignSource($type, $rawSource);
        if ($key === null || $source === null) {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')])
                ->withInput($request->except(['idempotency_key']));
        }
        try {
            $result = app(EventInvitationCampaignService::class)->preview(
                $id,
                $actor,
                $type,
                $source,
                $key,
                is_string($request->input('default_locale'))
                    ? (string) $request->input('default_locale')
                    : null,
            );

            return $this->eventsRegistrationPage(
                $request,
                $tenantSlug,
                $id,
                $actor,
                $result['campaign'],
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id, request: $request);
        }
    }

    public function eventsRegistrationIssueCampaign(
        Request $request,
        string $tenantSlug,
        int $id,
        int $campaignId,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        $revision = $this->eventsRegistrationPositiveInteger($request->input('expected_revision'));
        $expiry = is_string($request->input('expires_at')) ? trim((string) $request->input('expires_at')) : '';
        if ($key === null || $revision === null || $expiry === '') {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            app(EventInvitationService::class)->issueCampaign(
                $id,
                $campaignId,
                $actor,
                [],
                $revision,
                $key,
                $expiry,
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'campaign-issued');
    }

    public function eventsRegistrationScheduleCampaign(
        Request $request,
        string $tenantSlug,
        int $id,
        int $campaignId,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        $revision = $this->eventsRegistrationPositiveInteger($request->input('expected_revision'));
        $scheduled = is_string($request->input('scheduled_for'))
            ? trim((string) $request->input('scheduled_for'))
            : '';
        if ($key === null || $revision === null || $scheduled === '') {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            app(EventInvitationCampaignService::class)->schedule(
                $id,
                $campaignId,
                $actor,
                $scheduled,
                $revision,
                $key,
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'campaign-scheduled');
    }

    public function eventsRegistrationCancelCampaign(
        Request $request,
        string $tenantSlug,
        int $id,
        int $campaignId,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        $revision = $this->eventsRegistrationPositiveInteger($request->input('expected_revision'));
        $reason = is_string($request->input('reason')) ? trim((string) $request->input('reason')) : '';
        if ($key === null || $revision === null || $reason === '') {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            app(EventInvitationCampaignService::class)->cancel(
                $id,
                $campaignId,
                $actor,
                $revision,
                $reason,
                $key,
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'campaign-cancelled');
    }

    public function eventsRegistrationAcceptInvitation(
        Request $request,
        string $tenantSlug,
        int $id,
        int $invitationId,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        if ($key === null) {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            app(EventInvitationService::class)->acceptMemberById($id, $invitationId, $actor, $key);
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'invitation-accepted');
    }

    public function eventsRegistrationCaptureGuest(
        Request $request,
        string $tenantSlug,
        int $id,
        int $registrationId,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $registrationVersion = $this->eventsRegistrationPositiveInteger(
            $request->input('expected_registration_version'),
        );
        if ($registrationVersion === null) {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        $notificationConsent = $request->boolean('notification_consent');
        try {
            app(EventRegistrationGuestService::class)->capture(
                $id,
                $registrationId,
                $actor,
                $registrationVersion,
                is_string($request->input('display_name')) ? (string) $request->input('display_name') : '',
                is_string($request->input('email')) ? (string) $request->input('email') : null,
                is_string($request->input('phone')) ? (string) $request->input('phone') : null,
                $request->boolean('consent_accepted'),
                __('event_registration.accessible.privacy_consent_text'),
                '2026-07-12',
                (string) ($actor->preferred_language ?? 'en'),
                $notificationConsent,
                $notificationConsent
                    ? __('event_registration.accessible.notification_consent_text')
                    : null,
                $notificationConsent ? '2026-07-12' : null,
                $this->eventsRegistrationPositiveInteger($request->input('ticket_entitlement_id')),
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id, request: $request);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'guest-added');
    }

    public function eventsRegistrationCancelGuest(
        Request $request,
        string $tenantSlug,
        int $id,
        int $guestId,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $revision = $this->eventsRegistrationPositiveInteger($request->input('expected_revision'));
        $reason = is_string($request->input('reason')) ? trim((string) $request->input('reason')) : '';
        if ($revision === null || $reason === '' || ! $request->boolean('confirm_destructive')) {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            app(EventRegistrationGuestService::class)->cancel($id, $guestId, $actor, $revision, $reason);
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id, request: $request);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'guest-cancelled');
    }

    public function eventsRegistrationGuestAttendance(
        Request $request,
        string $tenantSlug,
        int $id,
        int $guestId,
        string $action,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        $version = filter_var($request->input('expected_version'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);
        if ($key === null || $version === false) {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            app(EventRegistrationGuestAttendanceService::class)->transition(
                $id,
                $guestId,
                $actor,
                $action,
                (int) $version,
                $key,
                is_string($request->input('reason')) ? (string) $request->input('reason') : null,
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'attendance-updated');
    }

    public function eventsRegistrationRetentionPreview(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        $asOf = is_string($request->input('as_of')) ? trim((string) $request->input('as_of')) : '';
        if ($key === null || $asOf === '') {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            $result = app(EventRegistrationRetentionService::class)->dryRun($id, $actor, $asOf, $key);

            return $this->eventsRegistrationPage(
                $request,
                $tenantSlug,
                $id,
                $actor,
                retentionRun: $result['run'],
            );
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id, request: $request);
        }
    }

    public function eventsRegistrationRetentionApply(
        Request $request,
        string $tenantSlug,
        int $id,
        int $runId,
    ): RedirectResponse {
        $actor = $this->eventsRegistrationActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsRegistrationIdempotencyKey($request);
        if ($key === null || ! $request->boolean('confirm_destructive')) {
            return $this->eventsRegistrationIndexRedirect($tenantSlug, $id)
                ->withErrors(['registration' => __('event_registration.accessible.validation_error')]);
        }
        try {
            app(EventRegistrationRetentionService::class)->apply($id, $runId, $actor, $key);
        } catch (EventRegistrationFoundationException $exception) {
            return $this->eventsRegistrationMutationFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $id, 'retention-applied');
    }

    private function eventsRegistrationPage(
        Request $request,
        string $tenantSlug,
        int $eventId,
        User $actor,
        mixed $campaignPreview = null,
        mixed $retentionRun = null,
    ): Response {
        $queries = app(EventRegistrationProductQueryService::class);
        $attendee = $queries->attendeeState($eventId, $actor);
        $organizer = null;
        try {
            $organizer = $queries->organizerOverview($eventId, $actor);
        } catch (EventRegistrationFoundationException $exception) {
            if (! str_contains($exception->getMessage(), 'denied')) {
                throw $exception;
            }
        }
        $event = EventService::getById($eventId, (int) $actor->id);
        if ($event === null) {
            throw new EventRegistrationFoundationException('event_registration_event_not_found');
        }

        return $this->eventsRegistrationPrivateResponse($this->view(
            'accessible-frontend::event-registration',
            [
                'title' => __('event_registration.title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'events',
                'event' => $event,
                'eventId' => $eventId,
                'attendee' => $attendee,
                'organizer' => $organizer,
                'campaignPreview' => $campaignPreview,
                'retentionRun' => $retentionRun,
                'status' => is_string($request->query('status'))
                    ? trim((string) $request->query('status'))
                    : null,
            ],
        ));
    }

    private function eventsRegistrationActor(string $tenantSlug): User|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', compact('tenantSlug'));
        }

        return $this->accessibleEventActor($userId);
    }

    /** @return list<array<string,mixed>> */
    private function eventsRegistrationQuestions(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $questions = [];
        foreach (array_slice($value, 0, 100) as $index => $row) {
            if (! is_array($row) || ! filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
                continue;
            }
            $type = is_string($row['question_type'] ?? null) ? trim($row['question_type']) : '';
            $question = [
                'stable_key' => is_string($row['stable_key'] ?? null)
                    ? trim($row['stable_key'])
                    : 'question_' . ($index + 1),
                'question_type' => $type,
                'prompt' => is_string($row['prompt'] ?? null) ? trim($row['prompt']) : '',
                'help_text' => is_string($row['help_text'] ?? null) ? trim($row['help_text']) : null,
                'is_required' => filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOL),
                'data_classification' => is_string($row['data_classification'] ?? null)
                    ? trim($row['data_classification'])
                    : 'internal',
                'purpose' => is_string($row['purpose'] ?? null) ? trim($row['purpose']) : '',
                'retention_days' => $this->eventsRegistrationPositiveInteger($row['retention_days'] ?? null),
                'choice_options' => null,
                'validation_rules' => null,
                'visibility_rules' => null,
                'displayed_text' => null,
                'displayed_text_version' => null,
            ];
            if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
                $choices = preg_split('/\R/u', is_string($row['choices'] ?? null) ? $row['choices'] : '') ?: [];
                $question['choice_options'] = array_values(array_filter(
                    array_map('trim', $choices),
                    static fn (string $choice): bool => $choice !== '',
                ));
            }
            $validation = [];
            foreach (['min_length', 'max_length'] as $field) {
                $parsed = filter_var($row[$field] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0],
                ]);
                if ($parsed !== false) {
                    $validation[$field] = (int) $parsed;
                }
            }
            $question['validation_rules'] = $validation === [] ? null : $validation;
            $conditionKey = is_string($row['condition_key'] ?? null)
                ? trim($row['condition_key'])
                : '';
            if ($conditionKey !== '') {
                $question['visibility_rules'] = [
                    'match' => 'all',
                    'conditions' => [[
                        'question_key' => $conditionKey,
                        'operator' => is_string($row['condition_operator'] ?? null)
                            ? trim($row['condition_operator'])
                            : 'equals',
                        'value' => is_string($row['condition_value'] ?? null)
                            ? trim($row['condition_value'])
                            : '',
                    ]],
                ];
            }
            if (in_array($type, ['consent', 'waiver'], true)) {
                $question['displayed_text'] = is_string($row['displayed_text'] ?? null)
                    ? trim($row['displayed_text'])
                    : '';
                $question['displayed_text_version'] = is_string($row['displayed_text_version'] ?? null)
                    ? trim($row['displayed_text_version'])
                    : '';
            }
            $questions[] = $question;
        }

        return $questions;
    }

    /** @return array<string,mixed> */
    private function eventsRegistrationAnswers(Request $request, EventRegistrationFormVersion $form): array
    {
        $input = $request->input('answers', []);
        $input = is_array($input) ? $input : [];
        $answers = [];
        $visibleAnswers = [];
        $rules = app(EventRegistrationFormRuleSet::class);
        foreach ($form->questions as $question) {
            $key = (string) $question->stable_key;
            $type = $question->question_type->value;
            $visibilityRules = is_array($question->visibility_rules) ? $question->visibility_rules : null;
            if (! $rules->isVisible($visibilityRules, $visibleAnswers)) {
                continue;
            }
            if (in_array($type, ['consent', 'waiver'], true)) {
                if (array_key_exists($key, $input)) {
                    $answers[$key] = filter_var($input[$key], FILTER_VALIDATE_BOOL);
                }
            } elseif ($type === 'multiple_choice' && array_key_exists($key, $input)) {
                $answers[$key] = is_array($input[$key]) ? array_values($input[$key]) : [];
            } elseif (array_key_exists($key, $input) && is_string($input[$key])) {
                $answers[$key] = $input[$key];
            }
            if (array_key_exists($key, $answers)) {
                $visibleAnswers[$key] = $answers[$key];
            }
        }

        return $answers;
    }

    /** @return array<string,mixed>|null */
    private function eventsRegistrationCampaignSource(string $type, string $raw): ?array
    {
        $values = preg_split('/[\s,;]+/u', trim($raw)) ?: [];
        $values = array_values(array_filter(array_map('trim', $values)));

        return match ($type) {
            'member' => ['member_ids' => array_values(array_filter(array_map(
                fn (string $value): ?int => $this->eventsRegistrationPositiveInteger($value),
                $values,
            )))],
            'email' => ['emails' => $values],
            'group' => ($groupId = $this->eventsRegistrationPositiveInteger($values[0] ?? null)) === null
                ? null
                : ['group_id' => $groupId],
            'audience' => ['criteria' => $values === []
                ? ['all_active' => true]
                : ['all_active' => true, 'roles' => $values]],
            'csv' => trim($raw) === '' ? null : ['csv' => $raw],
            default => null,
        };
    }

    private function eventsRegistrationIdempotencyKey(Request $request): ?string
    {
        $key = $request->input('idempotency_key');
        if (! is_string($key)) {
            return null;
        }
        $key = trim($key);

        return $key !== '' && mb_strlen($key) <= 191 ? $key : null;
    }

    private function eventsRegistrationPositiveInteger(mixed $value): ?int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $parsed === false ? null : (int) $parsed;
    }

    private function eventsRegistrationNonNegativeInteger(mixed $value): ?int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return $parsed === false ? null : (int) $parsed;
    }

    private function eventsRegistrationLocalInstant(mixed $value, string $timezone): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $instant = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone);
        if ($instant === false || $instant->format('Y-m-d\TH:i') !== $value) {
            throw new EventRegistrationFoundationException('event_registration_local_instant_invalid');
        }

        return $instant->format('Y-m-d\TH:i:sP');
    }

    private function eventsRegistrationFailure(
        EventRegistrationFoundationException $exception,
        string $tenantSlug,
        int $eventId,
    ): RedirectResponse {
        $reason = $exception->getMessage();
        if (str_contains($reason, 'not_found')) {
            abort(404);
        }
        if (str_contains($reason, 'denied') || str_contains($reason, 'identity_mismatch')) {
            abort(403);
        }
        if (str_contains($reason, 'schema_unavailable') || str_contains($reason, 'tenant_context')) {
            abort(503);
        }

        return $this->eventsRegistrationIndexRedirect($tenantSlug, $eventId)
            ->withErrors(['registration' => __('event_registration.messages.review_error')]);
    }

    /** @param array<string,int> $parameters */
    private function eventsRegistrationMutationFailure(
        EventRegistrationFoundationException $exception,
        string $tenantSlug,
        int $eventId,
        string $route = 'govuk-alpha.events.registration.index',
        array $parameters = [],
        ?Request $request = null,
    ): RedirectResponse {
        $reason = $exception->getMessage();
        if (str_contains($reason, 'not_found')) {
            abort(404);
        }
        if (str_contains($reason, 'denied') || str_contains($reason, 'identity_mismatch')) {
            abort(403);
        }
        if (str_contains($reason, 'schema_unavailable') || str_contains($reason, 'tenant_context')) {
            abort(503);
        }
        $redirect = redirect()->route($route, array_merge([
            'tenantSlug' => $tenantSlug,
            'id' => $eventId,
        ], $parameters))->withErrors([
            'registration' => __('event_registration.accessible.validation_error'),
        ]);

        return $request === null
            ? $redirect
            : $redirect->withInput($request->except(['idempotency_key']));
    }

    private function eventsRegistrationIndexRedirect(
        string $tenantSlug,
        int $eventId,
        ?string $status = null,
    ): RedirectResponse {
        $parameters = ['tenantSlug' => $tenantSlug, 'id' => $eventId];
        if ($status !== null) {
            $parameters['status'] = $status;
        }

        return redirect()->route('govuk-alpha.events.registration.index', $parameters);
    }

    private function eventsRegistrationFormRedirect(
        string $tenantSlug,
        int $eventId,
        ?int $formId,
    ): RedirectResponse {
        $parameters = ['tenantSlug' => $tenantSlug, 'id' => $eventId];
        if ($formId !== null) {
            $parameters['formId'] = $formId;
        }

        return redirect()->route($this->eventsRegistrationFormRoute($formId), $parameters);
    }

    private function eventsRegistrationFormRoute(?int $formId): string
    {
        return $formId === null
            ? 'govuk-alpha.events.registration.forms.new'
            : 'govuk-alpha.events.registration.forms.edit';
    }

    private function eventsRegistrationPrivateResponse(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->setVary('Cookie', false);

        return $response;
    }
}
