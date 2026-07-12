<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\Event;
use App\Models\EventSafetyRequirement;
use App\Models\EventSafetyRequirementVersion;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/** Explicit privacy boundary for the independent Event Safety v1 contract. */
final class EventSafetyContractMapper
{
    public const CONTRACT_VERSION = 1;

    /**
     * @param array<string,mixed> $evidence
     * @param array<string,bool> $permissions
     * @param array<string,mixed> $rollout
     * @return array<string,mixed>
     */
    public static function project(
        Event $event,
        ?EventSafetyRequirement $requirements,
        ?EventSafetyRequirementVersion $version,
        ?EventSafetyEligibilityDecision $eligibility,
        array $evidence,
        array $permissions,
        array $rollout,
    ): array {
        return [
            'contract_version' => self::CONTRACT_VERSION,
            'event_id' => (int) $event->getKey(),
            'rollout' => [
                'mode' => (string) ($rollout['resolved_mode'] ?? 'off'),
                'source' => (string) ($rollout['source'] ?? 'global'),
                'configuration_valid' => (bool) ($rollout['configuration_valid'] ?? false),
                'enforcement_active' => ($rollout['resolved_mode'] ?? null) === 'enforce',
            ],
            'requirements' => self::requirements($requirements, $version),
            'eligibility' => self::eligibility($eligibility),
            'evidence' => [
                'code_of_conduct' => self::codeEvidence($evidence['code_of_conduct'] ?? null),
                'guardian_consent' => self::guardianEvidence($evidence['guardian_consent'] ?? null),
                'active_denial' => self::denialEvidence($evidence['active_denial'] ?? null),
            ],
            'permissions' => [
                'manage_requirements' => (bool) ($permissions['manage_requirements'] ?? false),
                'review_participation' => (bool) ($permissions['review_participation'] ?? false),
                'acknowledge_code_of_conduct' => (bool) ($permissions['acknowledge_code_of_conduct'] ?? false),
                'withdraw_code_of_conduct' => (bool) ($permissions['withdraw_code_of_conduct'] ?? false),
                'request_guardian_consent' => (bool) ($permissions['request_guardian_consent'] ?? false),
                'withdraw_guardian_consent' => (bool) ($permissions['withdraw_guardian_consent'] ?? false),
            ],
            'privacy' => [
                'guardian_identity_redacted' => true,
                'guardian_token_redacted' => true,
                'safeguarding_policy_evidence_redacted' => true,
                'free_text_review_notes_supported' => false,
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    private static function requirements(
        ?EventSafetyRequirement $requirements,
        ?EventSafetyRequirementVersion $version,
    ): ?array {
        if ($requirements === null || $version === null) {
            return null;
        }

        return [
            'status' => self::enum($requirements->getRawOriginal('status')),
            'revision' => max(1, (int) $requirements->revision),
            'current_version' => max(1, (int) $requirements->current_version),
            'published_version' => $requirements->published_version !== null
                ? max(1, (int) $requirements->published_version)
                : null,
            'version' => [
                'number' => max(1, (int) $version->version_number),
                'minimum_age' => $version->minimum_age !== null
                    ? (int) $version->minimum_age
                    : null,
                'guardian_consent_required' => (bool) $version->guardian_consent_required,
                'minor_age_threshold' => $version->minor_age_threshold !== null
                    ? (int) $version->minor_age_threshold
                    : null,
                'code_of_conduct' => [
                    'required' => (bool) $version->code_of_conduct_required,
                    'text' => (bool) $version->code_of_conduct_required
                        ? (string) $version->code_of_conduct_text
                        : null,
                    'text_version' => $version->code_of_conduct_text_version !== null
                        ? (string) $version->code_of_conduct_text_version
                        : null,
                    'text_hash' => $version->code_of_conduct_text_hash !== null
                        ? (string) $version->code_of_conduct_text_hash
                        : null,
                ],
                'published_at' => self::date($requirements->published_at),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private static function eligibility(?EventSafetyEligibilityDecision $decision): array
    {
        if ($decision === null) {
            return [
                'status' => 'not_evaluated',
                'reason_codes' => [],
                'required_actions' => [],
                'requirements_version' => null,
                'age_at_event' => null,
                'minor_at_event' => null,
            ];
        }

        return [
            'status' => $decision->status,
            'reason_codes' => array_values(array_filter(
                $decision->reasonCodes,
                static fn (mixed $reason): bool => is_string($reason) && $reason !== '',
            )),
            'required_actions' => array_values(array_filter(
                $decision->requiredActions,
                static fn (mixed $action): bool => is_string($action) && $action !== '',
            )),
            'requirements_version' => $decision->requirementsVersion,
            'age_at_event' => $decision->ageAtEvent,
            'minor_at_event' => $decision->minorAtEvent,
        ];
    }

    /** @return array<string,mixed> */
    private static function codeEvidence(mixed $evidence): array
    {
        if (! is_array($evidence)) {
            return [
                'status' => 'not_required',
                'acknowledgement_id' => null,
                'text_version' => null,
                'acknowledged_at' => null,
            ];
        }

        return [
            'status' => (string) ($evidence['status'] ?? 'required'),
            'acknowledgement_id' => self::positiveInt($evidence['acknowledgement_id'] ?? null),
            'text_version' => self::nullableString($evidence['text_version'] ?? null),
            'acknowledged_at' => self::date($evidence['acknowledged_at'] ?? null),
        ];
    }

    /** @return array<string,mixed> */
    private static function guardianEvidence(mixed $evidence): array
    {
        if (! is_array($evidence)) {
            return [
                'status' => 'not_required',
                'consent_id' => null,
                'consent_version' => null,
                'expires_at' => null,
                'granted_at' => null,
            ];
        }

        return [
            'status' => (string) ($evidence['status'] ?? 'required'),
            'consent_id' => self::positiveInt($evidence['consent_id'] ?? null),
            'consent_version' => self::positiveInt($evidence['consent_version'] ?? null),
            'expires_at' => self::date($evidence['expires_at'] ?? null),
            'granted_at' => self::date($evidence['granted_at'] ?? null),
        ];
    }

    /** @return array<string,mixed>|null */
    private static function denialEvidence(mixed $evidence): ?array
    {
        if (! is_array($evidence)) {
            return null;
        }

        return [
            'id' => self::positiveInt($evidence['id'] ?? null),
            'decision' => self::nullableString($evidence['decision'] ?? null),
            'reason_code' => self::nullableString($evidence['reason_code'] ?? null),
            'status' => self::nullableString($evidence['status'] ?? null),
            'decision_version' => self::positiveInt($evidence['decision_version'] ?? null),
            'effective_from' => self::date($evidence['effective_from'] ?? null),
            'effective_until' => self::date($evidence['effective_until'] ?? null),
        ];
    }

    private static function enum(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : trim((string) $value);
    }

    private static function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $date = $value instanceof DateTimeInterface
                ? CarbonImmutable::instance($value)
                : CarbonImmutable::parse((string) $value);

            return $date->utc()->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
