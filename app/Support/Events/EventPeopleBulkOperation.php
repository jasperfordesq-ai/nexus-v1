<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Enums\EventPeopleBulkAction;
use App\Exceptions\EventRegistrationException;

/** One independently idempotent, optimistic Event People bulk operation. */
final readonly class EventPeopleBulkOperation
{
    public function __construct(
        public int $userId,
        public EventPeopleBulkAction $action,
        public int $expectedVersion,
        public string $idempotencyKey,
        public ?string $reason = null,
    ) {
        if ($userId <= 0 || $expectedVersion < 0) {
            throw new EventRegistrationException('event_registration_people_bulk_invalid');
        }
        if (trim($idempotencyKey) === '' || mb_strlen(trim($idempotencyKey)) > 191) {
            throw new EventRegistrationException('event_registration_idempotency_key_invalid');
        }
        if ($reason !== null && mb_strlen(trim($reason)) > 4000) {
            throw new EventRegistrationException('event_registration_reason_too_long');
        }
        if (in_array($action, [
            EventPeopleBulkAction::Reject,
            EventPeopleBulkAction::Cancel,
            EventPeopleBulkAction::UndoAttendance,
        ], true) && ($reason === null || trim($reason) === '')) {
            throw new EventRegistrationException('event_registration_reason_required');
        }
    }

    /** @param array<string,mixed> $input */
    public static function fromArray(array $input): self
    {
        $userId = self::integer($input['user_id'] ?? null);
        $expectedVersion = self::integer($input['expected_version'] ?? null);
        $action = is_string($input['action'] ?? null)
            ? EventPeopleBulkAction::tryFrom(strtolower(trim($input['action'])))
            : null;
        $idempotencyKey = is_string($input['idempotency_key'] ?? null)
            ? trim($input['idempotency_key'])
            : '';
        $reason = is_string($input['reason'] ?? null)
            ? trim($input['reason'])
            : null;
        if ($action === null || (($input['reason'] ?? null) !== null && ! is_string($input['reason']))) {
            throw new EventRegistrationException('event_registration_people_bulk_invalid');
        }

        return new self(
            $userId,
            $action,
            $expectedVersion,
            $idempotencyKey,
            $reason === '' ? null : $reason,
        );
    }

    private static function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1) {
            return (int) $value;
        }

        throw new EventRegistrationException('event_registration_people_bulk_invalid');
    }
}
