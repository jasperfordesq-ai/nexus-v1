<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventSafetyCodeEvidenceAction;
use App\Enums\EventSafetyRequirementStatus;
use App\Exceptions\EventSafetyException;
use App\Models\EventSafetyCodeAcknowledgement;
use App\Models\EventSafetyRequirement;
use App\Models\EventSafetyRequirementVersion;
use App\Models\User;
use App\Support\Events\EventSafetyFoundationSupport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Append-only code-of-conduct acknowledgement, replacement, and withdrawal evidence. */
final class EventSafetyAcknowledgementService
{
    public function __construct(
        private readonly EventSafetyFoundationSupport $support = new EventSafetyFoundationSupport(),
    ) {
    }

    /** @return array{evidence:EventSafetyCodeAcknowledgement,changed:bool} */
    public function acknowledge(
        int $eventId,
        User|int $user,
        string $displayedTextVersion,
        string $displayedTextHash,
        string $idempotencyKey,
    ): array {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $idempotencyHash = $this->support->idempotencyHash($idempotencyKey);

        return DB::transaction(function () use (
            $tenantId,
            $eventId,
            $user,
            $displayedTextVersion,
            $displayedTextHash,
            $idempotencyHash,
        ): array {
            $this->support->concreteEvent($tenantId, $eventId, true);
            $persistedUser = $this->support->activeUser($tenantId, $user, true);
            $version = trim($displayedTextVersion);
            $hash = strtolower(trim($displayedTextHash));
            if ($version === '' || preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) {
                throw new EventSafetyException('event_safety_code_of_conduct_evidence_mismatch');
            }
            $requestHash = $this->support->requestHash([
                'action' => EventSafetyCodeEvidenceAction::Acknowledged->value,
                'event_id' => $eventId,
                'user_id' => (int) $persistedUser->id,
                'text_version' => $version,
                'text_hash' => $hash,
            ]);
            $replay = $this->replay(
                $tenantId,
                $idempotencyHash,
                EventSafetyCodeEvidenceAction::Acknowledged,
                $requestHash,
                true,
            );
            if ($replay !== null) {
                return ['evidence' => $replay, 'changed' => false];
            }
            $context = $this->publishedCodeContext($tenantId, $eventId, true);
            if (! hash_equals(
                (string) $context['version']->code_of_conduct_text_version,
                $version,
            )
                || ! hash_equals(
                    (string) $context['version']->code_of_conduct_text_hash,
                    $hash,
                )) {
                throw new EventSafetyException('event_safety_code_of_conduct_evidence_mismatch');
            }
            $active = $this->activeAcknowledgement($tenantId, $eventId, (int) $persistedUser->id, true);
            if ($active !== null
                && (int) $active->requirements_version_id === (int) $context['version']->id) {
                throw new EventSafetyException('event_safety_code_of_conduct_already_acknowledged');
            }
            $sequence = $this->nextSequence($tenantId, $eventId, (int) $persistedUser->id);
            $now = CarbonImmutable::now('UTC');
            if ($active !== null) {
                $replacementRequestHash = $this->support->requestHash([
                    'action' => EventSafetyCodeEvidenceAction::Replaced->value,
                    'event_id' => $eventId,
                    'user_id' => (int) $persistedUser->id,
                    'referenced_acknowledgement_id' => (int) $active->id,
                    'replacement_requirements_version_id' => (int) $context['version']->id,
                ]);
                $this->insertTerminalEvidence(
                    $tenantId,
                    $active,
                    $sequence,
                    EventSafetyCodeEvidenceAction::Replaced,
                    (int) $persistedUser->id,
                    hash('sha256', $idempotencyHash . '|replacement'),
                    $replacementRequestHash,
                    $now,
                );
                ++$sequence;
            }
            $evidenceId = (int) DB::table('event_safety_code_acknowledgements')->insertGetId([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'requirements_id' => (int) $context['requirements']->id,
                'requirements_version_id' => (int) $context['version']->id,
                'requirements_version_number' => (int) $context['version']->version_number,
                'user_id' => (int) $persistedUser->id,
                'evidence_sequence' => $sequence,
                'action' => EventSafetyCodeEvidenceAction::Acknowledged->value,
                'referenced_acknowledgement_id' => null,
                'text_version' => $version,
                'text_hash' => $hash,
                'acknowledged_at' => $now,
                'actor_user_id' => (int) $persistedUser->id,
                'idempotency_hash' => $idempotencyHash,
                'request_hash' => $requestHash,
                'recorded_at' => $now,
            ]);

            return [
                'evidence' => $this->evidenceModel($tenantId, $evidenceId),
                'changed' => true,
            ];
        }, 3);
    }

    /** @return array{evidence:EventSafetyCodeAcknowledgement,changed:bool} */
    public function withdraw(
        int $eventId,
        User|int $user,
        int $acknowledgementId,
        string $idempotencyKey,
    ): array {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $idempotencyHash = $this->support->idempotencyHash($idempotencyKey);

        return DB::transaction(function () use (
            $tenantId,
            $eventId,
            $user,
            $acknowledgementId,
            $idempotencyHash,
        ): array {
            $this->support->concreteEvent($tenantId, $eventId, true);
            $persistedUser = $this->support->activeUser($tenantId, $user, true);
            $acknowledgement = EventSafetyCodeAcknowledgement::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('user_id', (int) $persistedUser->id)
                ->whereKey($acknowledgementId)
                ->where('action', EventSafetyCodeEvidenceAction::Acknowledged->value)
                ->lockForUpdate()
                ->first();
            if ($acknowledgement === null) {
                throw new EventSafetyException('event_safety_code_of_conduct_acknowledgement_not_found');
            }
            $requestHash = $this->support->requestHash([
                'action' => EventSafetyCodeEvidenceAction::Withdrawn->value,
                'event_id' => $eventId,
                'user_id' => (int) $persistedUser->id,
                'referenced_acknowledgement_id' => $acknowledgementId,
            ]);
            $replay = $this->replay(
                $tenantId,
                $idempotencyHash,
                EventSafetyCodeEvidenceAction::Withdrawn,
                $requestHash,
                true,
            );
            if ($replay !== null) {
                return ['evidence' => $replay, 'changed' => false];
            }
            if ($this->hasTerminalEvidence($tenantId, $acknowledgementId)) {
                throw new EventSafetyException('event_safety_code_of_conduct_acknowledgement_inactive');
            }
            $sequence = $this->nextSequence($tenantId, $eventId, (int) $persistedUser->id);
            $now = CarbonImmutable::now('UTC');
            $evidenceId = $this->insertTerminalEvidence(
                $tenantId,
                $acknowledgement,
                $sequence,
                EventSafetyCodeEvidenceAction::Withdrawn,
                (int) $persistedUser->id,
                $idempotencyHash,
                $requestHash,
                $now,
            );

            return [
                'evidence' => $this->evidenceModel($tenantId, $evidenceId),
                'changed' => true,
            ];
        }, 3);
    }

    /** @return array{requirements:EventSafetyRequirement,version:EventSafetyRequirementVersion} */
    private function publishedCodeContext(int $tenantId, int $eventId, bool $lock): array
    {
        $query = EventSafetyRequirement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', EventSafetyRequirementStatus::Published->value);
        if ($lock) {
            $query->lockForUpdate();
        }
        $requirements = $query->first();
        if ($requirements === null || $requirements->published_version === null) {
            throw new EventSafetyException('event_safety_requirements_not_published');
        }
        $version = EventSafetyRequirementVersion::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('requirements_id', (int) $requirements->id)
            ->where('version_number', (int) $requirements->published_version)
            ->first();
        if ($version === null || ! (bool) $version->code_of_conduct_required) {
            throw new EventSafetyException('event_safety_code_of_conduct_not_required');
        }

        return ['requirements' => $requirements, 'version' => $version];
    }

    private function activeAcknowledgement(
        int $tenantId,
        int $eventId,
        int $userId,
        bool $lock,
    ): ?EventSafetyCodeAcknowledgement {
        $query = EventSafetyCodeAcknowledgement::withoutGlobalScopes()
            ->from('event_safety_code_acknowledgements as acknowledged')
            ->where('acknowledged.tenant_id', $tenantId)
            ->where('acknowledged.event_id', $eventId)
            ->where('acknowledged.user_id', $userId)
            ->where('acknowledged.action', EventSafetyCodeEvidenceAction::Acknowledged->value)
            ->whereNotExists(static function ($terminal): void {
                $terminal->selectRaw('1')
                    ->from('event_safety_code_acknowledgements as terminal')
                    ->whereColumn(
                        'terminal.referenced_acknowledgement_id',
                        'acknowledged.id',
                    )
                    ->whereIn('terminal.action', [
                        EventSafetyCodeEvidenceAction::Withdrawn->value,
                        EventSafetyCodeEvidenceAction::Replaced->value,
                    ]);
            })
            ->orderByDesc('acknowledged.evidence_sequence');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function hasTerminalEvidence(int $tenantId, int $acknowledgementId): bool
    {
        return EventSafetyCodeAcknowledgement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('referenced_acknowledgement_id', $acknowledgementId)
            ->whereIn('action', [
                EventSafetyCodeEvidenceAction::Withdrawn->value,
                EventSafetyCodeEvidenceAction::Replaced->value,
            ])
            ->exists();
    }

    private function nextSequence(int $tenantId, int $eventId, int $userId): int
    {
        return ((int) EventSafetyCodeAcknowledgement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->max('evidence_sequence')) + 1;
    }

    private function insertTerminalEvidence(
        int $tenantId,
        EventSafetyCodeAcknowledgement $acknowledgement,
        int $sequence,
        EventSafetyCodeEvidenceAction $action,
        int $actorId,
        string $idempotencyHash,
        string $requestHash,
        CarbonImmutable $now,
    ): int {
        return (int) DB::table('event_safety_code_acknowledgements')->insertGetId([
            'tenant_id' => $tenantId,
            'event_id' => (int) $acknowledgement->event_id,
            'requirements_id' => (int) $acknowledgement->requirements_id,
            'requirements_version_id' => (int) $acknowledgement->requirements_version_id,
            'requirements_version_number' => (int) $acknowledgement->requirements_version_number,
            'user_id' => (int) $acknowledgement->user_id,
            'evidence_sequence' => $sequence,
            'action' => $action->value,
            'referenced_acknowledgement_id' => (int) $acknowledgement->id,
            'text_version' => (string) $acknowledgement->text_version,
            'text_hash' => (string) $acknowledgement->text_hash,
            'acknowledged_at' => $acknowledgement->acknowledged_at,
            'actor_user_id' => $actorId,
            'idempotency_hash' => $idempotencyHash,
            'request_hash' => $requestHash,
            'recorded_at' => $now,
        ]);
    }

    private function replay(
        int $tenantId,
        string $idempotencyHash,
        EventSafetyCodeEvidenceAction $action,
        string $requestHash,
        bool $lock,
    ): ?EventSafetyCodeAcknowledgement {
        $query = EventSafetyCodeAcknowledgement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_hash', $idempotencyHash);
        if ($lock) {
            $query->lockForUpdate();
        }
        $evidence = $query->first();
        if ($evidence === null) {
            return null;
        }
        if ((string) $evidence->getRawOriginal('action') !== $action->value
            || ! hash_equals((string) $evidence->request_hash, $requestHash)) {
            throw new EventSafetyException('event_safety_idempotency_conflict');
        }

        return $evidence;
    }

    private function evidenceModel(int $tenantId, int $id): EventSafetyCodeAcknowledgement
    {
        return EventSafetyCodeAcknowledgement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    private function assertSchema(): void
    {
        foreach ([
            'event_safety_requirements',
            'event_safety_requirement_versions',
            'event_safety_code_acknowledgements',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new EventSafetyException('event_safety_schema_unavailable');
            }
        }
    }
}
