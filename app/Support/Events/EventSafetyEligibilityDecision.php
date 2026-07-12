<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

final readonly class EventSafetyEligibilityDecision
{
    public const ALLOW = 'allow';
    public const DENY = 'deny';
    public const UNAVAILABLE = 'unavailable';
    public const UNBOUND_GUEST_POLICY = 'event_safety_unbound_guest_denied';

    /**
     * @param list<string> $reasonCodes
     * @param list<string> $requiredActions
     * @param array<string,mixed> $safeguardingPolicy
     */
    public function __construct(
        public string $status,
        public int $eventId,
        public ?int $userId,
        public array $reasonCodes,
        public array $requiredActions,
        public ?int $requirementsVersion,
        public ?int $ageAtEvent,
        public ?bool $minorAtEvent,
        public array $safeguardingPolicy = [],
        public string $unboundGuestPolicy = self::UNBOUND_GUEST_POLICY,
    ) {
    }

    public function isAllowed(): bool
    {
        return $this->status === self::ALLOW;
    }

    public function isDenied(): bool
    {
        return $this->status === self::DENY;
    }

    public function isUnavailable(): bool
    {
        return $this->status === self::UNAVAILABLE;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'event_id' => $this->eventId,
            'user_id' => $this->userId,
            'reason_codes' => $this->reasonCodes,
            'required_actions' => $this->requiredActions,
            'requirements_version' => $this->requirementsVersion,
            'age_at_event' => $this->ageAtEvent,
            'minor_at_event' => $this->minorAtEvent,
            'safeguarding_policy' => $this->safeguardingPolicy,
            'unbound_guest_policy' => $this->unboundGuestPolicy,
        ];
    }
}
