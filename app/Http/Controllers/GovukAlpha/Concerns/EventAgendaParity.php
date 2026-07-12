<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Enums\EventSessionStatus;
use App\Exceptions\EventSessionException;
use App\Models\User;
use App\Services\EventSessionService;
use App\Support\Events\EventSessionContractMapper;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/** HTML-first agenda viewing and management through the canonical service. */
trait EventAgendaParity
{
    public function eventsAgenda(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $actor = $this->eventsAgendaActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }

        try {
            $context = app(EventSessionService::class)->readAgenda($id, $actor, true);
            $agenda = EventSessionContractMapper::agenda(
                $context['event'],
                $context['sessions'],
                ['can_manage' => $context['can_manage']],
            );
        } catch (EventSessionException $exception) {
            return $this->eventsAgendaFailureResponse($exception, $tenantSlug, $id);
        }

        $response = $this->view('accessible-frontend::event-agenda', [
            'title' => __('govuk_alpha.events.agenda.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'eventId' => $id,
            'eventTitle' => (string) $context['event']->title,
            'eventStart' => $context['event']->getRawOriginal('start_time'),
            'eventEnd' => $context['event']->getRawOriginal('end_time'),
            'agenda' => $agenda,
            'status' => is_string($request->query('status'))
                ? trim((string) $request->query('status'))
                : null,
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->setVary('Cookie', false);

        return $response;
    }

    public function eventsUpdateAgenda(
        Request $request,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        $actor = $this->eventsAgendaActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }

        $action = is_string($request->input('action'))
            ? trim((string) $request->input('action'))
            : '';
        $idempotencyKey = $this->eventsAgendaIdempotencyKey($request);
        if ($idempotencyKey === null) {
            return $this->eventsAgendaValidationRedirect($tenantSlug, $id, $request);
        }

        try {
            $service = app(EventSessionService::class);
            $context = $service->readAgenda($id, $actor, true);
            if (! in_array($action, ['register', 'withdraw'], true) && ! $context['can_manage']) {
                throw new EventSessionException('event_agenda_authorization_denied');
            }
            $timeZone = trim((string) ($context['event']->getRawOriginal('timezone') ?? 'UTC')) ?: 'UTC';

            $status = match ($action) {
                'create' => $this->eventsAgendaCreate(
                    $service,
                    $request,
                    $id,
                    $actor,
                    $timeZone,
                    $idempotencyKey,
                ),
                'update' => $this->eventsAgendaUpdate(
                    $service,
                    $request,
                    $id,
                    $actor,
                    $timeZone,
                    $idempotencyKey,
                ),
                'cancel' => $this->eventsAgendaCancel(
                    $service,
                    $request,
                    $id,
                    $actor,
                    $idempotencyKey,
                ),
                'move_up', 'move_down' => $this->eventsAgendaReorder(
                    $service,
                    $request,
                    $id,
                    $actor,
                    $context,
                    $action,
                    $idempotencyKey,
                ),
                'register' => $this->eventsAgendaRegister(
                    $service,
                    $request,
                    $id,
                    $actor,
                    $idempotencyKey,
                ),
                'withdraw' => $this->eventsAgendaWithdraw(
                    $service,
                    $request,
                    $id,
                    $actor,
                    $idempotencyKey,
                ),
                default => throw new EventSessionException('event_agenda_action_invalid'),
            };
        } catch (EventSessionException $exception) {
            if (in_array($exception->reasonCode, [
                'event_agenda_event_not_found',
                'event_agenda_session_not_found',
            ], true)) {
                abort(404);
            }
            if (in_array($exception->reasonCode, [
                'event_agenda_authorization_denied',
                'event_agenda_actor_invalid',
            ], true)) {
                abort(403);
            }
            if (in_array($exception->reasonCode, [
                'event_agenda_schema_unavailable',
                'event_agenda_feature_disabled',
                'event_agenda_tenant_context_missing',
            ], true)) {
                abort(503);
            }

            $message = in_array($exception->reasonCode, [
                'event_agenda_version_conflict',
                'event_agenda_concurrent_write_failed',
                'event_agenda_idempotency_conflict',
                'event_agenda_room_conflict',
                'event_agenda_speaker_conflict',
                'event_agenda_registration_version_conflict',
                'event_agenda_registration_idempotency_conflict',
                'event_agenda_capacity_below_registrations',
            ], true)
                ? __('govuk_alpha.events.agenda.conflict_error')
                : match ($exception->reasonCode) {
                    'event_agenda_session_capacity_full' => __('event_agenda.session_full_error'),
                    'event_agenda_registration_eligibility_required' => __('event_agenda.eligibility_error'),
                    default => __('govuk_alpha.events.agenda.validation_error'),
                };

            return redirect()
                ->route('govuk-alpha.events.agenda', compact('tenantSlug', 'id'))
                ->withErrors(['agenda' => $message])
                ->withInput();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('govuk-alpha.events.agenda', compact('tenantSlug', 'id'))
                ->withErrors(['agenda' => __('govuk_alpha.events.agenda.save_error')]);
        }

        return redirect()->route('govuk-alpha.events.agenda', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $status,
        ]);
    }

    private function eventsAgendaCreate(
        EventSessionService $service,
        Request $request,
        int $eventId,
        User $actor,
        string $timeZone,
        string $idempotencyKey,
    ): string {
        $service->create(
            $eventId,
            $actor,
            $this->eventsAgendaPayload($request, $timeZone),
            $idempotencyKey,
        );

        return 'agenda-created';
    }

    private function eventsAgendaUpdate(
        EventSessionService $service,
        Request $request,
        int $eventId,
        User $actor,
        string $timeZone,
        string $idempotencyKey,
    ): string {
        $service->update(
            $eventId,
            $this->eventsAgendaPositiveInteger($request->input('session_id')),
            $actor,
            $this->eventsAgendaPayload($request, $timeZone),
            $this->eventsAgendaPositiveInteger($request->input('expected_version')),
            $idempotencyKey,
        );

        return 'agenda-updated';
    }

    private function eventsAgendaCancel(
        EventSessionService $service,
        Request $request,
        int $eventId,
        User $actor,
        string $idempotencyKey,
    ): string {
        $reason = is_string($request->input('reason'))
            ? trim((string) $request->input('reason'))
            : '';
        if ($reason === '') {
            throw new EventSessionException('event_agenda_cancellation_reason_invalid');
        }
        $service->cancel(
            $eventId,
            $this->eventsAgendaPositiveInteger($request->input('session_id')),
            $actor,
            $reason,
            $this->eventsAgendaPositiveInteger($request->input('expected_version')),
            $idempotencyKey,
        );

        return 'agenda-cancelled';
    }

    private function eventsAgendaRegister(
        EventSessionService $service,
        Request $request,
        int $eventId,
        User $actor,
        string $idempotencyKey,
    ): string {
        $service->registerSession(
            $eventId,
            $this->eventsAgendaPositiveInteger($request->input('session_id')),
            $actor,
            $this->eventsAgendaNonNegativeInteger($request->input('expected_version')),
            $idempotencyKey,
        );

        return 'agenda-session-registered';
    }

    private function eventsAgendaWithdraw(
        EventSessionService $service,
        Request $request,
        int $eventId,
        User $actor,
        string $idempotencyKey,
    ): string {
        $service->withdrawSession(
            $eventId,
            $this->eventsAgendaPositiveInteger($request->input('session_id')),
            $actor,
            $this->eventsAgendaNonNegativeInteger($request->input('expected_version')),
            $idempotencyKey,
        );

        return 'agenda-session-withdrawn';
    }

    /**
     * @param array{event:\App\Models\Event,sessions:\Illuminate\Support\Collection<int,\App\Models\EventSession>,can_manage:bool} $context
     */
    private function eventsAgendaReorder(
        EventSessionService $service,
        Request $request,
        int $eventId,
        User $actor,
        array $context,
        string $action,
        string $idempotencyKey,
    ): string {
        $sessionId = $this->eventsAgendaPositiveInteger($request->input('session_id'));
        $expectedAgendaVersion = $this->eventsAgendaNonNegativeInteger(
            $request->input('expected_agenda_version'),
        );
        $ids = EventSessionContractMapper::agenda(
            $context['event'],
            $context['sessions'],
            ['can_manage' => true],
        )['sessions'];
        $orderedIds = [];
        foreach ($ids as $session) {
            if (($session['status'] ?? null) === EventSessionStatus::Scheduled->value) {
                $orderedIds[] = (int) $session['id'];
            }
        }
        $index = array_search($sessionId, $orderedIds, true);
        $target = is_int($index) ? $index + ($action === 'move_up' ? -1 : 1) : -1;
        if (! is_int($index) || $target < 0 || $target >= count($orderedIds)) {
            throw new EventSessionException('event_agenda_reorder_set_invalid');
        }
        $targetId = $orderedIds[$target];
        $orderedIds[$target] = $sessionId;
        $orderedIds[$index] = $targetId;

        $service->reorder(
            $eventId,
            $actor,
            $orderedIds,
            $expectedAgendaVersion,
            $idempotencyKey,
        );

        return 'agenda-reordered';
    }

    /** @return array<string,mixed> */
    private function eventsAgendaPayload(Request $request, string $timeZone): array
    {
        $title = is_string($request->input('title')) ? trim((string) $request->input('title')) : '';
        if ($title === '') {
            throw new EventSessionException('event_agenda_title_invalid');
        }

        return [
            'title' => $title,
            'description' => $this->eventsAgendaNullableText($request->input('description')),
            'session_type' => is_string($request->input('session_type'))
                ? trim((string) $request->input('session_type'))
                : '',
            'visibility' => is_string($request->input('visibility'))
                ? trim((string) $request->input('visibility'))
                : '',
            'start_at' => $this->eventsAgendaLocalDateTime($request->input('start_at'), $timeZone),
            'end_at' => $this->eventsAgendaLocalDateTime($request->input('end_at'), $timeZone),
            'timezone' => $timeZone,
            'track_name' => $this->eventsAgendaNullableText($request->input('track_name')),
            'room_name' => $this->eventsAgendaNullableText($request->input('room_name')),
            'capacity' => $this->eventsAgendaNullablePositiveInteger($request->input('capacity')),
            'speakers' => $this->eventsAgendaSpeakers($request),
            'resources' => $this->eventsAgendaResources($request),
        ];
    }

    /** @return list<array{type:string,title:string,url:string,visibility:string}> */
    private function eventsAgendaResources(Request $request): array
    {
        $types = $request->input('resource_type', []);
        $titles = $request->input('resource_title', []);
        $urls = $request->input('resource_url', []);
        $visibilities = $request->input('resource_visibility', []);
        if (! is_array($types)
            || ! is_array($titles)
            || ! is_array($urls)
            || ! is_array($visibilities)) {
            throw new EventSessionException('event_agenda_resources_invalid');
        }
        $count = max(count($types), count($titles), count($urls), count($visibilities));
        if ($count > 50) {
            throw new EventSessionException('event_agenda_resources_invalid');
        }

        $resources = [];
        for ($index = 0; $index < $count; $index++) {
            $type = $this->eventsAgendaNullableText($types[$index] ?? null);
            $title = $this->eventsAgendaNullableText($titles[$index] ?? null);
            $url = $this->eventsAgendaNullableText($urls[$index] ?? null);
            $visibility = $this->eventsAgendaNullableText($visibilities[$index] ?? null);
            if ($title === null && $url === null) {
                continue;
            }
            if ($type === null || $title === null || $url === null || $visibility === null) {
                throw new EventSessionException('event_agenda_resource_invalid');
            }
            $resources[] = compact('type', 'title', 'url', 'visibility');
        }

        return $resources;
    }

    /** @return list<array{user_id?:int,display_name?:string,role_label:?string}> */
    private function eventsAgendaSpeakers(Request $request): array
    {
        $memberIds = $request->input('speaker_member_id', []);
        $names = $request->input('speaker_name', []);
        $roles = $request->input('speaker_role', []);
        if (! is_array($memberIds) || ! is_array($names) || ! is_array($roles)) {
            throw new EventSessionException('event_agenda_speakers_invalid');
        }
        $count = max(count($memberIds), count($names), count($roles));
        if ($count > 50) {
            throw new EventSessionException('event_agenda_speakers_invalid');
        }

        $speakers = [];
        for ($index = 0; $index < $count; $index++) {
            $role = $this->eventsAgendaNullableText($roles[$index] ?? null);
            $rawMemberId = $memberIds[$index] ?? null;
            if ($rawMemberId !== null && $rawMemberId !== '') {
                $speakers[] = [
                    'user_id' => $this->eventsAgendaPositiveInteger($rawMemberId),
                    'role_label' => $role,
                ];
                continue;
            }
            $name = $this->eventsAgendaNullableText($names[$index] ?? null);
            if ($name !== null) {
                $speakers[] = ['display_name' => $name, 'role_label' => $role];
            }
        }

        return $speakers;
    }

    private function eventsAgendaLocalDateTime(mixed $value, string $timeZone): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new EventSessionException('event_agenda_time_invalid');
        }
        $value = trim($value);
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timeZone);
        } catch (Throwable) {
            throw new EventSessionException('event_agenda_time_invalid');
        }
        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d\TH:i') !== $value) {
            throw new EventSessionException('event_agenda_time_invalid');
        }

        return $date->utc()->toIso8601String();
    }

    private function eventsAgendaPositiveInteger(mixed $value): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed === false) {
            throw new EventSessionException('event_agenda_identifier_invalid');
        }

        return (int) $parsed;
    }

    private function eventsAgendaNonNegativeInteger(mixed $value): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($parsed === false) {
            throw new EventSessionException('event_agenda_expected_version_invalid');
        }

        return (int) $parsed;
    }

    private function eventsAgendaNullablePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $parsed = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 100000]],
        );
        if ($parsed === false) {
            throw new EventSessionException('event_agenda_capacity_invalid');
        }

        return (int) $parsed;
    }

    private function eventsAgendaNullableText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new EventSessionException('event_agenda_fields_invalid');
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function eventsAgendaIdempotencyKey(Request $request): ?string
    {
        $value = $request->input('idempotency_key');
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value !== '' && mb_strlen($value) <= 191 ? $value : null;
    }

    private function eventsAgendaActor(string $tenantSlug): User|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', compact('tenantSlug'));
        }

        return $this->accessibleEventActor($userId);
    }

    private function eventsAgendaFailureResponse(
        EventSessionException $exception,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        if (in_array($exception->reasonCode, [
            'event_agenda_event_not_found',
            'event_agenda_session_not_found',
        ], true)) {
            abort(404);
        }
        if (in_array($exception->reasonCode, [
            'event_agenda_authorization_denied',
            'event_agenda_actor_invalid',
        ], true)) {
            abort(403);
        }
        if (in_array($exception->reasonCode, [
            'event_agenda_schema_unavailable',
            'event_agenda_feature_disabled',
            'event_agenda_tenant_context_missing',
        ], true)) {
            abort(503);
        }

        return redirect()
            ->route('govuk-alpha.events.show', compact('tenantSlug', 'id'))
            ->withErrors(['agenda' => __('govuk_alpha.events.agenda.load_error')]);
    }

    private function eventsAgendaValidationRedirect(
        string $tenantSlug,
        int $id,
        Request $request,
    ): RedirectResponse {
        return redirect()
            ->route('govuk-alpha.events.agenda', compact('tenantSlug', 'id'))
            ->withErrors(['agenda' => __('govuk_alpha.events.agenda.validation_error')])
            ->withInput($request->except(['idempotency_key']));
    }

}
