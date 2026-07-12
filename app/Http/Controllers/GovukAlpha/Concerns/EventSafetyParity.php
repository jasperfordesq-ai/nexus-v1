<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Enums\EventParticipationDecision;
use App\Enums\EventParticipationDenialReason;
use App\Exceptions\EventSafetyException;
use App\Models\Event;
use App\Services\EventGuardianConsentService;
use App\Services\EventParticipationDenialService;
use App\Services\EventPeopleService;
use App\Services\EventSafetyAcknowledgementService;
use App\Services\EventSafetyProjectionService;
use App\Services\EventSafetyRequirementService;
use App\Services\EventService;
use App\Support\Events\EventPeopleQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** HTML-first Event Safety parity backed exclusively by the canonical services. */
trait EventSafetyParity
{
    public function eventsSafety(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', [
                'tenantSlug' => $tenantSlug,
                'status' => 'auth-required',
            ]);
        }

        $legacyEvent = EventService::getById($id, $userId);
        abort_if($legacyEvent === null, 404);
        $actor = $this->accessibleEventActor($userId);
        $safety = null;
        $reviews = null;
        $people = [];
        $loadError = false;
        try {
            $safety = app(EventSafetyProjectionService::class)->read($id, $actor);
            if (($safety['permissions']['review_participation'] ?? false) === true) {
                $reviews = app(EventSafetyProjectionService::class)->reviews(
                    $id,
                    $actor,
                    max(1, (int) $request->query('page', 1)),
                    25,
                );
                $event = Event::withoutGlobalScopes()
                    ->where('tenant_id', TenantContext::currentId())
                    ->whereKey($id)
                    ->first();
                if ($event instanceof Event) {
                    $peopleResult = app(EventPeopleService::class)->paginateForActor(
                        $event,
                        $actor,
                        new EventPeopleQuery(perPage: 100),
                    );
                    foreach ($peopleResult['items'] as $person) {
                        $member = is_array($person['member'] ?? null) ? $person['member'] : [];
                        $memberId = (int) ($member['id'] ?? 0);
                        if ($memberId > 0) {
                            $people[$memberId] = [
                                'id' => $memberId,
                                'display_name' => (string) ($member['display_name'] ?? ''),
                            ];
                        }
                    }
                    ksort($people);
                }
            }
        } catch (Throwable $exception) {
            $loadError = true;
            Log::notice('Accessible Event Safety projection unavailable', [
                'tenant_id' => TenantContext::currentId(),
                'event_id' => $id,
                'reason_code' => $exception instanceof EventSafetyException
                    ? $exception->reasonCode
                    : 'event_safety_projection_failed',
            ]);
        }

        return $this->view('accessible-frontend::event-safety', [
            'title' => __('govuk_alpha_events.safety.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => [
                'id' => $id,
                'title' => (string) ($legacyEvent['title'] ?? ''),
            ],
            'safety' => $safety,
            'reviews' => $reviews,
            'people' => array_values($people),
            'loadError' => $loadError,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function eventsUpdateSafety(
        Request $request,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', [
                'tenantSlug' => $tenantSlug,
                'status' => 'auth-required',
            ]);
        }
        abort_if(EventService::getById($id, $userId) === null, 404);

        $actor = $this->accessibleEventActor($userId);
        $action = self::asStr($request->input('action'));
        $status = 'safety-updated';
        try {
            $idempotencyKey = $this->eventsSafetyMutationKey($request);
            match ($action) {
                'save_requirements' => app(EventSafetyRequirementService::class)->saveDraft(
                    $id,
                    $actor,
                    [
                        'minimum_age' => $this->eventsSafetyNullableInteger($request->input('minimum_age')),
                        'guardian_consent_required' => $request->boolean('guardian_consent_required'),
                        'minor_age_threshold' => $this->eventsSafetyNullableInteger($request->input('minor_age_threshold')),
                        'code_of_conduct_required' => $request->boolean('code_of_conduct_required'),
                        'code_of_conduct_text' => self::asStr($request->input('code_of_conduct_text')),
                        'code_of_conduct_text_version' => self::asStr($request->input('code_of_conduct_text_version')),
                    ],
                    $this->eventsSafetyNullableInteger($request->input('expected_revision')),
                    $idempotencyKey,
                ),
                'publish_requirements' => app(EventSafetyRequirementService::class)->publish(
                    $id,
                    $actor,
                    $this->eventsSafetyPositiveInteger($request->input('expected_revision')),
                    $this->eventsSafetyPositiveInteger($request->input('expected_version')),
                    $idempotencyKey,
                ),
                'archive_requirements' => app(EventSafetyRequirementService::class)->archive(
                    $id,
                    $actor,
                    $this->eventsSafetyPositiveInteger($request->input('expected_revision')),
                    $this->eventsSafetyPositiveInteger($request->input('expected_version')),
                    $idempotencyKey,
                ),
                'acknowledge_code' => app(EventSafetyAcknowledgementService::class)->acknowledge(
                    $id,
                    $actor,
                    self::asStr($request->input('text_version')),
                    self::asStr($request->input('text_hash')),
                    $idempotencyKey,
                ),
                'withdraw_code' => app(EventSafetyAcknowledgementService::class)->withdraw(
                    $id,
                    $actor,
                    $this->eventsSafetyPositiveInteger($request->input('acknowledgement_id')),
                    $idempotencyKey,
                ),
                'request_guardian_consent' => app(EventGuardianConsentService::class)->requestWithDelivery(
                    $id,
                    $actor,
                    $actor,
                    [
                        'guardian_name' => self::asStr($request->input('guardian_name')),
                        'guardian_email' => self::asStr($request->input('guardian_email')),
                        'relationship_code' => self::asStr($request->input('relationship_code')),
                    ],
                    app()->getLocale(),
                    $idempotencyKey,
                ),
                'withdraw_guardian_consent' => app(EventGuardianConsentService::class)->withdraw(
                    $id,
                    $this->eventsSafetyPositiveInteger($request->input('consent_id')),
                    $actor,
                    $idempotencyKey,
                ),
                'record_review' => app(EventParticipationDenialService::class)->record(
                    $id,
                    $this->eventsSafetyPositiveInteger($request->input('user_id')),
                    $actor,
                    EventParticipationDecision::from(self::asStr($request->input('decision'))),
                    EventParticipationDenialReason::from(self::asStr($request->input('reason_code'))),
                    $this->eventsSafetyDate($request->input('effective_from')),
                    $request->input('effective_until') === null
                        || self::asStr($request->input('effective_until')) === ''
                            ? null
                            : $this->eventsSafetyDate($request->input('effective_until')),
                    $this->eventsSafetyNullableInteger($request->input('expected_version')),
                    $idempotencyKey,
                ),
                'withdraw_review' => app(EventParticipationDenialService::class)->withdraw(
                    $id,
                    $this->eventsSafetyPositiveInteger($request->input('denial_id')),
                    $actor,
                    $this->eventsSafetyPositiveInteger($request->input('expected_version')),
                    $idempotencyKey,
                ),
                default => throw new EventSafetyException('event_safety_action_invalid'),
            };
        } catch (Throwable $exception) {
            $status = 'safety-failed';
            Log::notice('Accessible Event Safety mutation rejected', [
                'tenant_id' => TenantContext::currentId(),
                'event_id' => $id,
                'action' => $action,
                'reason_code' => $exception instanceof EventSafetyException
                    ? $exception->reasonCode
                    : 'event_safety_validation_failed',
            ]);
        }

        return redirect()->route('govuk-alpha.events.safety', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $status,
        ]);
    }

    private function eventsSafetyMutationKey(Request $request): string
    {
        $key = trim(self::asStr($request->input('idempotency_key')));
        if ($key === '' || mb_strlen($key) > 191) {
            throw new EventSafetyException('event_safety_idempotency_key_invalid');
        }

        return $key;
    }

    private function eventsSafetyPositiveInteger(mixed $value): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed === false) {
            throw new EventSafetyException('event_safety_integer_invalid');
        }

        return (int) $parsed;
    }

    private function eventsSafetyNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($parsed === false) {
            throw new EventSafetyException('event_safety_integer_invalid');
        }

        return (int) $parsed;
    }

    private function eventsSafetyDate(mixed $value): CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            throw new EventSafetyException('event_safety_date_invalid');
        }
        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            throw new EventSafetyException('event_safety_date_invalid');
        }
    }
}
