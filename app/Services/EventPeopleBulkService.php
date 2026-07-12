<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\EventCapacityRegistrationState;
use App\Enums\EventPeopleBulkAction;
use App\Exceptions\EventAttendanceException;
use App\Exceptions\EventParticipationException;
use App\Exceptions\EventRegistrationException;
use App\Exceptions\EventWaitlistException;
use App\Exceptions\SafeguardingPolicyException;
use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Support\Events\EventPeopleBulkOperation;

/** Executes a bounded batch as independently transactional subject mutations. */
final class EventPeopleBulkService
{
    public const MAX_OPERATIONS = 100;

    public function __construct(
        private readonly EventRegistrationService $registrations,
        private readonly EventAttendanceService $attendance,
        private readonly EventPolicy $policy,
    ) {
    }

    /**
     * @param list<EventPeopleBulkOperation> $operations
     * @return array{
     *   requested:int,
     *   succeeded:int,
     *   failed:int,
     *   results:list<array<string,mixed>>
     * }
     */
    public function execute(Event $event, User $actor, array $operations): array
    {
        $tenantId = TenantContext::currentId();
        if ($tenantId === null
            || $tenantId <= 0
            || (int) $event->tenant_id !== $tenantId
            || (int) $actor->tenant_id !== $tenantId) {
            throw new EventRegistrationException('event_registration_authorization_denied');
        }
        if ($operations === [] || count($operations) > self::MAX_OPERATIONS) {
            throw new EventRegistrationException('event_registration_people_bulk_invalid');
        }
        $seenSubjects = [];
        foreach ($operations as $operation) {
            if (! $operation instanceof EventPeopleBulkOperation) {
                throw new EventRegistrationException('event_registration_people_bulk_invalid');
            }
            if (isset($seenSubjects[$operation->userId])) {
                throw new EventRegistrationException(
                    'event_registration_people_bulk_duplicate_subject',
                );
            }
            $seenSubjects[$operation->userId] = true;
        }

        $registrationActions = array_filter(
            $operations,
            static fn (EventPeopleBulkOperation $operation): bool =>
                $operation->action->attendanceAction() === null,
        );
        $attendanceActions = array_filter(
            $operations,
            static fn (EventPeopleBulkOperation $operation): bool =>
                $operation->action->attendanceAction() !== null,
        );
        if ($registrationActions !== [] && ! $this->policy->manageRegistration($actor, $event)) {
            throw new EventRegistrationException('event_registration_authorization_denied');
        }
        if ($attendanceActions !== [] && ! $this->policy->manageAttendance($actor, $event)) {
            throw new EventRegistrationException('event_registration_authorization_denied');
        }

        /** @var array<int,User> $members */
        $members = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_keys($seenSubjects))
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id')
            ->all();

        $results = [];
        $succeeded = 0;
        foreach ($operations as $index => $operation) {
            $base = [
                'index' => $index,
                'user_id' => $operation->userId,
                'action' => $operation->action->value,
                'expected_version' => $operation->expectedVersion,
            ];
            if (! isset($members[$operation->userId])) {
                $results[] = [
                    ...$base,
                    'success' => false,
                    'error' => 'event_registration_subject_not_found',
                ];

                continue;
            }

            try {
                $attendanceAction = $operation->action->attendanceAction();
                if ($attendanceAction !== null) {
                    $transition = $this->attendance->transition(
                        (int) $event->getKey(),
                        $operation->userId,
                        $attendanceAction,
                        $actor,
                        $operation->expectedVersion,
                        $operation->reason,
                        $operation->idempotencyKey,
                    );
                    $mutation = $transition->toArray();
                } else {
                    $target = match ($operation->action) {
                        EventPeopleBulkAction::Invite => EventCapacityRegistrationState::Invited,
                        EventPeopleBulkAction::Approve => EventCapacityRegistrationState::Confirmed,
                        EventPeopleBulkAction::Reject => EventCapacityRegistrationState::Declined,
                        EventPeopleBulkAction::Cancel => EventCapacityRegistrationState::Cancelled,
                        default => throw new EventRegistrationException(
                            'event_registration_people_bulk_invalid',
                        ),
                    };
                    $transition = $this->registrations->transition(
                        (int) $event->getKey(),
                        $operation->userId,
                        $target,
                        $actor,
                        $operation->idempotencyKey,
                        null,
                        null,
                        $operation->reason,
                        $operation->expectedVersion,
                    );
                    $mutation = [
                        'registration_id' => (int) $transition->registration->getKey(),
                        'state' => $transition->registration->registration_state->value,
                        'version' => (int) $transition->registration->registration_version,
                        'changed' => $transition->changed,
                        'idempotent_replay' => $transition->replayed,
                        'history_entry_id' => $transition->historyId,
                    ];
                }
                $results[] = [...$base, 'success' => true, 'mutation' => $mutation];
                $succeeded++;
            } catch (
                EventAttendanceException|
                EventRegistrationException|
                EventWaitlistException|
                EventParticipationException|
                SafeguardingPolicyException $exception
            ) {
                $results[] = [
                    ...$base,
                    'success' => false,
                    'error' => $exception->reasonCode,
                ];
            }
        }

        return [
            'requested' => count($operations),
            'succeeded' => $succeeded,
            'failed' => count($operations) - $succeeded,
            'results' => $results,
        ];
    }
}
