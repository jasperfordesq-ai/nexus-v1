<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SafeguardingPolicyException;
use App\Models\MemberVettingAttestation;
use App\Models\SafeguardingVettingReviewRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Metadata-only community vetting decisions.
 *
 * Certificate evidence is deliberately outside this service's contract. All
 * scheme, purpose, scope, policy, actor, and timestamps are derived server-side.
 */
class MemberVettingAttestationService
{
    /** @var list<string> */
    public const REVOCATION_REASON_CODES = [
        'community_decision_withdrawn',
        'member_requested_correction',
        'policy_changed',
        'recorded_in_error',
    ];

    /** @var list<string> */
    public const REVIEW_RESOLUTION_CODES = [
        'no_change',
        'duplicate_request',
        'member_contacted',
    ];

    public function __construct(
        private readonly SafeguardingJurisdictionService $jurisdictions,
    ) {}

    /** @return array<string, mixed> */
    public function confirmForCurrentPolicy(
        int $tenantId,
        int $memberId,
        int $actorUserId,
        ?int $reviewRequestId = null,
    ): array {
        $this->assertMemberBelongsToTenant($memberId, $tenantId);
        $this->assertActorMayDecideForMember($actorUserId, $memberId, $tenantId);

        return DB::transaction(function () use ($tenantId, $memberId, $actorUserId, $reviewRequestId): array {
            $policy = $this->lockAndRequireAvailablePolicy($tenantId);
            $existing = $this->decisionScopeQuery($tenantId, $memberId, $policy)
                ->lockForUpdate()
                ->first();

            if ($existing !== null
                && $existing->decision === MemberVettingAttestation::DECISION_CONFIRMED
                && $existing->policy_version === $policy['policy_version']) {
                $this->resolvePendingReviews($tenantId, $memberId, $policy, $actorUserId, 'confirmed', $reviewRequestId);

                return $this->getById((int) $existing->id, $tenantId) ?? [];
            }

            $now = now();
            $decisionBefore = is_string($existing?->decision ?? null)
                ? (string) $existing->decision
                : null;
            $eventType = $existing === null ? 'confirmed' : 'reconfirmed';

            if ($existing === null) {
                $attestationId = DB::table('member_vetting_attestations')->insertGetId([
                    'tenant_id' => $tenantId,
                    'user_id' => $memberId,
                    'scheme_code' => $policy['scheme_code'],
                    'attestation_code' => $policy['attestation_code'],
                    'purpose_code' => $policy['purpose_code'],
                    'scope_type' => $policy['scope_type'],
                    'scope_identifier' => $policy['scope_identifier'],
                    'decision' => MemberVettingAttestation::DECISION_CONFIRMED,
                    'confirmed_by' => $actorUserId,
                    'confirmed_at' => $now,
                    'revoked_by' => null,
                    'revoked_at' => null,
                    'revocation_reason_code' => null,
                    'policy_version' => $policy['policy_version'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $attestationId = (int) $existing->id;
                DB::table('member_vetting_attestations')
                    ->where('id', $attestationId)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'decision' => MemberVettingAttestation::DECISION_CONFIRMED,
                        'confirmed_by' => $actorUserId,
                        'confirmed_at' => $now,
                        'revoked_by' => null,
                        'revoked_at' => null,
                        'revocation_reason_code' => null,
                        'policy_version' => $policy['policy_version'],
                        'updated_at' => $now,
                    ]);
            }

            $this->appendDecisionEvent(
                $attestationId,
                $tenantId,
                $memberId,
                $actorUserId,
                $policy,
                $eventType,
                $decisionBefore,
                MemberVettingAttestation::DECISION_CONFIRMED,
                null,
            );

            $this->resolvePendingReviews($tenantId, $memberId, $policy, $actorUserId, 'confirmed', $reviewRequestId);

            return $this->getById($attestationId, $tenantId) ?? [];
        });
    }

    /** @return array<string, mixed> */
    public function revokeForCurrentPolicy(
        int $tenantId,
        int $memberId,
        int $actorUserId,
        string $reasonCode = 'community_decision_withdrawn',
        ?int $reviewRequestId = null,
    ): array {
        if (! in_array($reasonCode, self::REVOCATION_REASON_CODES, true)) {
            throw new SafeguardingPolicyException('INVALID_VETTING_REVOCATION_REASON');
        }

        $this->assertMemberBelongsToTenant($memberId, $tenantId);
        $this->assertActorMayDecideForMember($actorUserId, $memberId, $tenantId);

        return DB::transaction(function () use ($tenantId, $memberId, $actorUserId, $reasonCode, $reviewRequestId): array {
            $policy = $this->lockAndRequireAvailablePolicy($tenantId);
            $existing = $this->decisionScopeQuery($tenantId, $memberId, $policy)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                throw new SafeguardingPolicyException('VETTING_CONFIRMATION_NOT_FOUND');
            }

            if ($existing->decision === MemberVettingAttestation::DECISION_REVOKED) {
                $this->resolvePendingReviews(
                    $tenantId,
                    $memberId,
                    $policy,
                    $actorUserId,
                    'confirmation_withdrawn',
                    $reviewRequestId,
                );

                return $this->getById((int) $existing->id, $tenantId) ?? [];
            }

            $now = now();
            DB::table('member_vetting_attestations')
                ->where('id', (int) $existing->id)
                ->where('tenant_id', $tenantId)
                ->update([
                    'decision' => MemberVettingAttestation::DECISION_REVOKED,
                    'revoked_by' => $actorUserId,
                    'revoked_at' => $now,
                    'revocation_reason_code' => $reasonCode,
                    'updated_at' => $now,
                ]);

            $this->appendDecisionEvent(
                (int) $existing->id,
                $tenantId,
                $memberId,
                $actorUserId,
                $policy,
                'revoked',
                MemberVettingAttestation::DECISION_CONFIRMED,
                MemberVettingAttestation::DECISION_REVOKED,
                $reasonCode,
            );

            $this->resolvePendingReviews(
                $tenantId,
                $memberId,
                $policy,
                $actorUserId,
                'confirmation_withdrawn',
                $reviewRequestId,
            );

            return $this->getById((int) $existing->id, $tenantId) ?? [];
        });
    }

    /** @return array<string, mixed> */
    public function requestReview(int $tenantId, int $memberId): array
    {
        $policy = $this->requireAvailablePolicy($tenantId);
        $this->assertMemberBelongsToTenant($memberId, $tenantId);

        return DB::transaction(function () use ($tenantId, $memberId, $policy): array {
            $query = DB::table('safeguarding_vetting_review_requests')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $memberId)
                ->where('purpose_code', $policy['purpose_code'])
                ->where('scope_type', $policy['scope_type'])
                ->where('scope_identifier', $policy['scope_identifier']);

            $existing = $query->lockForUpdate()->first();
            $now = now();

            $values = [
                'jurisdiction' => $policy['jurisdiction'],
                'scheme_code' => $policy['scheme_code'],
                'attestation_code' => $policy['attestation_code'],
                'policy_version' => $policy['policy_version'],
                'status' => SafeguardingVettingReviewRequest::STATUS_PENDING,
                'request_source' => SafeguardingVettingReviewRequest::SOURCE_MEMBER_REQUEST,
                'requested_by' => $memberId,
                'requested_at' => $now,
                'handled_by' => null,
                'handled_at' => null,
                'resolution_code' => null,
                'updated_at' => $now,
            ];

            if ($existing === null) {
                $reviewId = DB::table('safeguarding_vetting_review_requests')->insertGetId(array_merge($values, [
                    'tenant_id' => $tenantId,
                    'user_id' => $memberId,
                    'purpose_code' => $policy['purpose_code'],
                    'scope_type' => $policy['scope_type'],
                    'scope_identifier' => $policy['scope_identifier'],
                    'created_at' => $now,
                ]));
            } else {
                $reviewId = (int) $existing->id;
                $isCurrentPending = $existing->status === SafeguardingVettingReviewRequest::STATUS_PENDING
                    && $existing->jurisdiction === $policy['jurisdiction']
                    && $existing->scheme_code === $policy['scheme_code']
                    && $existing->attestation_code === $policy['attestation_code']
                    && $existing->policy_version === $policy['policy_version'];
                if (! $isCurrentPending) {
                    DB::table('safeguarding_vetting_review_requests')
                        ->where('id', $reviewId)
                        ->where('tenant_id', $tenantId)
                        ->update($values);
                }
            }

            return $this->getReviewById($reviewId, $tenantId) ?? [];
        });
    }

    /** @return array<string, mixed> */
    public function resolveReview(
        int $tenantId,
        int $reviewRequestId,
        int $actorUserId,
        string $resolutionCode,
    ): array {
        if (! in_array($resolutionCode, self::REVIEW_RESOLUTION_CODES, true)) {
            throw new SafeguardingPolicyException('INVALID_VETTING_REVIEW_RESOLUTION');
        }

        return DB::transaction(function () use ($tenantId, $reviewRequestId, $actorUserId, $resolutionCode): array {
            $review = DB::table('safeguarding_vetting_review_requests')
                ->where('id', $reviewRequestId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if ($review === null) {
                throw new SafeguardingPolicyException('VETTING_REVIEW_REQUEST_NOT_FOUND');
            }

            if ($review->status !== SafeguardingVettingReviewRequest::STATUS_PENDING) {
                return $this->getReviewById($reviewRequestId, $tenantId) ?? [];
            }

            DB::table('safeguarding_vetting_review_requests')
                ->where('id', $reviewRequestId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status' => SafeguardingVettingReviewRequest::STATUS_COMPLETED,
                    'handled_by' => $actorUserId,
                    'handled_at' => now(),
                    'resolution_code' => $resolutionCode,
                    'updated_at' => now(),
                ]);

            return $this->getReviewById($reviewRequestId, $tenantId) ?? [];
        });
    }

    /**
     * Live authorization read. Database failures intentionally propagate so the
     * central interaction policy can return `unavailable` rather than permission.
     */
    public function hasConfirmedAttestation(
        int $tenantId,
        int $memberId,
        string $schemeCode,
        string $attestationCode,
        string $purposeCode,
        string $scopeType = SafeguardingJurisdictionService::SCOPE_TENANT,
        string $scopeIdentifier = '',
        ?string $policyVersion = null,
    ): bool {
        $query = DB::table('member_vetting_attestations')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $memberId)
            ->where('scheme_code', $schemeCode)
            ->where('attestation_code', $attestationCode)
            ->where('purpose_code', $purposeCode)
            ->where('scope_type', $scopeType)
            ->where('scope_identifier', $scopeIdentifier)
            ->where('decision', MemberVettingAttestation::DECISION_CONFIRMED)
            ->whereNotNull('confirmed_by')
            ->whereNotNull('confirmed_at')
            ->whereNull('revoked_at');

        if ($policyVersion !== null) {
            $query->where('policy_version', $policyVersion);
        }

        return $query->exists();
    }

    /**
     * Lock every tenant-scoped attestation row that could affect a definitive
     * interaction decision. The tenant policy mutex must be acquired first.
     */
    public function lockMemberAttestationsForUpdate(int $tenantId, int $memberId): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Vetting attestation locks require an active database transaction.');
        }

        DB::table('member_vetting_attestations')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $memberId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /** @return array<string, mixed>|null */
    public function getMemberStatus(int $tenantId, int $memberId): ?array
    {
        $policy = $this->jurisdictions->getPolicy($tenantId);
        if (! $policy['configured'] || ! $policy['contact_policy_available'] || $policy['attestation_code'] === null) {
            return [
                'policy' => $policy,
                'decision' => 'not_confirmed',
                'review_status' => null,
                'confirmed_at' => null,
            ];
        }

        $attestation = $this->currentPolicyDecisionQuery($tenantId, $memberId, $policy)->first();
        $review = DB::table('safeguarding_vetting_review_requests')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $memberId)
            ->where('scheme_code', $policy['scheme_code'])
            ->where('attestation_code', $policy['attestation_code'])
            ->where('purpose_code', $policy['purpose_code'])
            ->where('scope_type', $policy['scope_type'])
            ->where('scope_identifier', $policy['scope_identifier'])
            ->where('policy_version', $policy['policy_version'])
            ->first();

        return [
            'policy' => $policy,
            'decision' => $attestation?->decision ?? 'not_confirmed',
            'review_status' => $review?->status,
            'confirmed_at' => $attestation?->confirmed_at,
            'revoked_at' => $attestation?->revoked_at,
        ];
    }

    /** @return array{data: list<array<string, mixed>>, pagination: array<string, int>} */
    public function listMembers(int $tenantId, array $filters = []): array
    {
        $policy = $this->jurisdictions->getPolicy($tenantId);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 25)));
        $search = trim((string) ($filters['search'] ?? ''));
        $decisionFilter = trim((string) ($filters['status'] ?? 'all'));

        $query = DB::table('users as u')
            ->where('u.tenant_id', $tenantId)
            ->whereNotIn('u.status', ['deleted', 'deactivated']);

        if ($policy['attestation_code'] !== null) {
            $query->leftJoin('member_vetting_attestations as a', function ($join) use ($tenantId, $policy): void {
                $join->on('a.user_id', '=', 'u.id')
                    ->where('a.tenant_id', '=', $tenantId)
                    ->where('a.scheme_code', '=', $policy['scheme_code'])
                    ->where('a.attestation_code', '=', $policy['attestation_code'])
                    ->where('a.purpose_code', '=', $policy['purpose_code'])
                    ->where('a.scope_type', '=', $policy['scope_type'])
                    ->where('a.scope_identifier', '=', $policy['scope_identifier'])
                    ->where('a.policy_version', '=', $policy['policy_version']);
            });
        } else {
            $query->leftJoin('member_vetting_attestations as a', function ($join): void {
                $join->on('a.user_id', '=', 'u.id')->whereRaw('1 = 0');
            });
        }

        if ($policy['scheme_code'] !== null && $policy['attestation_code'] !== null && $policy['policy_version'] !== null) {
            $query->leftJoin('safeguarding_vetting_review_requests as r', function ($join) use ($tenantId, $policy): void {
                $join->on('r.user_id', '=', 'u.id')
                    ->where('r.tenant_id', '=', $tenantId)
                    ->where('r.scheme_code', '=', $policy['scheme_code'])
                    ->where('r.attestation_code', '=', $policy['attestation_code'])
                    ->where('r.purpose_code', '=', $policy['purpose_code'])
                    ->where('r.scope_type', '=', $policy['scope_type'])
                    ->where('r.scope_identifier', '=', $policy['scope_identifier'])
                    ->where('r.policy_version', '=', $policy['policy_version']);
            });
        } else {
            $query->leftJoin('safeguarding_vetting_review_requests as r', function ($join): void {
                $join->on('r.user_id', '=', 'u.id')->whereRaw('1 = 0');
            });
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($searchQuery) use ($like): void {
                $searchQuery->where('u.first_name', 'like', $like)
                    ->orWhere('u.last_name', 'like', $like)
                    ->orWhere('u.email', 'like', $like);
            });
        }

        match ($decisionFilter) {
            'confirmed' => $query->where('a.decision', MemberVettingAttestation::DECISION_CONFIRMED),
            'revoked' => $query->where('a.decision', MemberVettingAttestation::DECISION_REVOKED),
            'review_requested' => $query->where('r.status', SafeguardingVettingReviewRequest::STATUS_PENDING),
            'not_confirmed' => $query->where(function ($statusQuery): void {
                $statusQuery->whereNull('a.id')
                    ->orWhere('a.decision', '!=', MemberVettingAttestation::DECISION_CONFIRMED);
            }),
            default => null,
        };

        $total = (clone $query)->count('u.id');
        $rows = $query
            ->select([
                'u.id as user_id',
                'u.first_name',
                'u.last_name',
                'u.email',
                'u.avatar_url',
                'a.id as attestation_id',
                'a.decision',
                'a.confirmed_by',
                'a.confirmed_at',
                'a.revoked_by',
                'a.revoked_at',
                'a.revocation_reason_code',
                'a.policy_version',
                'r.id as review_request_id',
                'r.status as review_status',
                'r.requested_at',
            ])
            ->orderByRaw("CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('u.first_name')
            ->orderBy('u.last_name')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        $data = $rows->map(fn (object $row): array => [
            'user_id' => (int) $row->user_id,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'email' => $row->email,
            'avatar_url' => $row->avatar_url,
            'attestation_id' => $row->attestation_id !== null ? (int) $row->attestation_id : null,
            'decision' => $row->decision ?? 'not_confirmed',
            'confirmed_by' => $row->confirmed_by !== null ? (int) $row->confirmed_by : null,
            'confirmed_at' => $row->confirmed_at,
            'revoked_by' => $row->revoked_by !== null ? (int) $row->revoked_by : null,
            'revoked_at' => $row->revoked_at,
            'revocation_reason_code' => $row->revocation_reason_code,
            'policy_version' => $row->policy_version,
            'review_request_id' => $row->review_request_id !== null ? (int) $row->review_request_id : null,
            'review_status' => $row->review_status,
            'requested_at' => $row->requested_at,
            'policy' => $policy,
        ])->values()->all();

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /** @return array<string, int|array<string, mixed>> */
    public function stats(int $tenantId): array
    {
        $policy = $this->jurisdictions->getPolicy($tenantId);
        $totalMembers = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['deleted', 'deactivated'])
            ->count();

        $confirmed = 0;
        $revoked = 0;
        if ($policy['attestation_code'] !== null) {
            $base = DB::table('member_vetting_attestations')
                ->where('tenant_id', $tenantId)
                ->where('scheme_code', $policy['scheme_code'])
                ->where('attestation_code', $policy['attestation_code'])
                ->where('purpose_code', $policy['purpose_code'])
                ->where('scope_type', $policy['scope_type'])
                ->where('scope_identifier', $policy['scope_identifier'])
                ->where('policy_version', $policy['policy_version']);
            $confirmed = (clone $base)->where('decision', MemberVettingAttestation::DECISION_CONFIRMED)->count();
            $revoked = (clone $base)->where('decision', MemberVettingAttestation::DECISION_REVOKED)->count();
        }

        $reviewRequested = 0;
        if ($policy['scheme_code'] !== null && $policy['attestation_code'] !== null && $policy['policy_version'] !== null) {
            $reviewRequested = DB::table('safeguarding_vetting_review_requests')
                ->where('tenant_id', $tenantId)
                ->where('scheme_code', $policy['scheme_code'])
                ->where('attestation_code', $policy['attestation_code'])
                ->where('purpose_code', $policy['purpose_code'])
                ->where('scope_type', $policy['scope_type'])
                ->where('scope_identifier', $policy['scope_identifier'])
                ->where('policy_version', $policy['policy_version'])
                ->where('status', SafeguardingVettingReviewRequest::STATUS_PENDING)
                ->count();
        }

        return [
            'total_members' => $totalMembers,
            'confirmed' => $confirmed,
            'revoked' => $revoked,
            'not_confirmed' => max(0, $totalMembers - $confirmed),
            'review_requested' => $reviewRequested,
            'policy' => $policy,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function getUserRecords(int $memberId, int $tenantId): array
    {
        $this->assertMemberBelongsToTenant($memberId, $tenantId);

        return DB::table('member_vetting_attestations as a')
            ->leftJoin('users as confirmer', function ($join) use ($tenantId): void {
                $join->on('confirmer.id', '=', 'a.confirmed_by')
                    ->where('confirmer.tenant_id', '=', $tenantId);
            })
            ->where('a.tenant_id', $tenantId)
            ->where('a.user_id', $memberId)
            ->select([
                'a.id', 'a.user_id', 'a.scheme_code', 'a.attestation_code', 'a.purpose_code',
                'a.scope_type', 'a.scope_identifier', 'a.decision', 'a.confirmed_at',
                'a.revoked_at', 'a.revocation_reason_code', 'a.policy_version',
                'confirmer.first_name as confirmer_first_name',
                'confirmer.last_name as confirmer_last_name',
            ])
            ->orderByDesc('a.updated_at')
            ->get()
            ->map(fn (object $row): array => $this->serializeAttestationRow($row))
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function getById(int $attestationId, int $tenantId): ?array
    {
        $row = DB::table('member_vetting_attestations as a')
            ->join('users as member', function ($join) use ($tenantId): void {
                $join->on('member.id', '=', 'a.user_id')
                    ->where('member.tenant_id', '=', $tenantId);
            })
            ->leftJoin('users as confirmer', function ($join) use ($tenantId): void {
                $join->on('confirmer.id', '=', 'a.confirmed_by')
                    ->where('confirmer.tenant_id', '=', $tenantId);
            })
            ->where('a.id', $attestationId)
            ->where('a.tenant_id', $tenantId)
            ->select([
                'a.*',
                'member.first_name',
                'member.last_name',
                'member.email',
                'member.avatar_url',
                'confirmer.first_name as confirmer_first_name',
                'confirmer.last_name as confirmer_last_name',
            ])
            ->first();

        return $row !== null ? $this->serializeAttestationRow($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function getReviewById(int $reviewId, int $tenantId): ?array
    {
        $row = DB::table('safeguarding_vetting_review_requests')
            ->where('id', $reviewId)
            ->where('tenant_id', $tenantId)
            ->first();

        return $row !== null ? (array) $row : null;
    }

    /** @param array<string, mixed> $policy */
    private function decisionScopeQuery(int $tenantId, int $memberId, array $policy): Builder
    {
        return DB::table('member_vetting_attestations')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $memberId)
            ->where('scheme_code', $policy['scheme_code'])
            ->where('attestation_code', $policy['attestation_code'])
            ->where('purpose_code', $policy['purpose_code'])
            ->where('scope_type', $policy['scope_type'])
            ->where('scope_identifier', $policy['scope_identifier']);
    }

    /** @param array<string, mixed> $policy */
    private function currentPolicyDecisionQuery(int $tenantId, int $memberId, array $policy): Builder
    {
        return $this->decisionScopeQuery($tenantId, $memberId, $policy)
            ->where('policy_version', $policy['policy_version']);
    }

    /** @return array<string, mixed> */
    private function requireAvailablePolicy(int $tenantId): array
    {
        return $this->requireAvailablePolicyState($this->jurisdictions->getPolicy($tenantId));
    }

    /**
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    private function requireAvailablePolicyState(array $policy): array
    {
        if (! $policy['configured']) {
            throw new SafeguardingPolicyException('SAFEGUARDING_JURISDICTION_REQUIRED');
        }
        if (! $policy['contact_policy_available']) {
            throw new SafeguardingPolicyException('SAFEGUARDING_POLICY_UNAVAILABLE');
        }
        if ($policy['scheme_code'] === null || $policy['attestation_code'] === null || $policy['policy_version'] === null) {
            throw new SafeguardingPolicyException('SAFEGUARDING_POLICY_UNAVAILABLE');
        }

        return $policy;
    }

    /**
     * Serialize decisions with jurisdiction/policy rotation. Reading a cached
     * policy before the transaction could otherwise confirm an obsolete version
     * after a concurrent rotation had already committed.
     *
     * @return array<string, mixed>
     */
    private function lockAndRequireAvailablePolicy(int $tenantId): array
    {
        return $this->requireAvailablePolicyState(
            $this->jurisdictions->lockPolicyForUpdate($tenantId),
        );
    }

    private function assertMemberBelongsToTenant(int $memberId, int $tenantId): void
    {
        $exists = DB::table('users')
            ->where('id', $memberId)
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['deleted', 'deactivated'])
            ->exists();

        if (! $exists) {
            throw new SafeguardingPolicyException('MEMBER_NOT_FOUND');
        }
    }

    private function assertActorMayDecideForMember(int $actorUserId, int $memberId, int $tenantId): void
    {
        if ($actorUserId === $memberId) {
            throw new SafeguardingPolicyException('VETTING_SELF_CONFIRMATION_FORBIDDEN');
        }

        $actorExists = DB::table('users')
            ->where('id', $actorUserId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists();
        if (! $actorExists) {
            throw new SafeguardingPolicyException('VETTING_DECISION_ACTOR_NOT_FOUND');
        }
    }

    /** @param array<string, mixed> $policy */
    private function appendDecisionEvent(
        int $attestationId,
        int $tenantId,
        int $memberId,
        int $actorUserId,
        array $policy,
        string $eventType,
        ?string $decisionBefore,
        string $decisionAfter,
        ?string $reasonCode,
    ): void {
        DB::table('member_vetting_attestation_events')->insert([
            'attestation_id' => $attestationId,
            'tenant_id' => $tenantId,
            'user_id' => $memberId,
            'scheme_code' => $policy['scheme_code'],
            'attestation_code' => $policy['attestation_code'],
            'purpose_code' => $policy['purpose_code'],
            'scope_type' => $policy['scope_type'],
            'scope_identifier' => $policy['scope_identifier'],
            'event_type' => $eventType,
            'decision_before' => $decisionBefore,
            'decision_after' => $decisionAfter,
            'reason_code' => $reasonCode,
            'actor_user_id' => $actorUserId,
            'policy_version' => $policy['policy_version'],
            'created_at' => now(),
        ]);
    }

    private function resolvePendingReviews(
        int $tenantId,
        int $memberId,
        array $policy,
        int $actorUserId,
        string $resolutionCode,
        ?int $reviewRequestId,
    ): void {
        $query = DB::table('safeguarding_vetting_review_requests')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $memberId)
            ->where('scheme_code', $policy['scheme_code'])
            ->where('attestation_code', $policy['attestation_code'])
            ->where('purpose_code', $policy['purpose_code'])
            ->where('scope_type', $policy['scope_type'])
            ->where('scope_identifier', $policy['scope_identifier'])
            ->where('policy_version', $policy['policy_version'])
            ->where('status', SafeguardingVettingReviewRequest::STATUS_PENDING);

        if ($reviewRequestId !== null) {
            $query->where('id', $reviewRequestId);
        }

        $query->update([
            'status' => SafeguardingVettingReviewRequest::STATUS_COMPLETED,
            'handled_by' => $actorUserId,
            'handled_at' => now(),
            'resolution_code' => $resolutionCode,
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function serializeAttestationRow(object $row): array
    {
        $data = (array) $row;
        $first = trim((string) ($data['confirmer_first_name'] ?? ''));
        $last = trim((string) ($data['confirmer_last_name'] ?? ''));
        $data['confirmed_by_name'] = trim($first . ' ' . $last) ?: null;
        unset($data['confirmer_first_name'], $data['confirmer_last_name']);

        return $data;
    }
}
