<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Exceptions\EventTicketingException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Strict, non-executable ticket eligibility policy schema and evaluator. */
final class EventTicketEligibilityPolicy
{
    private const FIELDS = [
        'approved_member_required',
        'minimum_account_age_days',
        'required_group_ids',
    ];

    /**
     * @param mixed $policy
     * @return array{approved_member_required:bool,minimum_account_age_days:int,required_group_ids:list<int>}
     */
    public function normalize(int $tenantId, mixed $policy): array
    {
        if ($policy === null) {
            $policy = [];
        }
        if (! is_array($policy) || array_is_list($policy)) {
            throw new EventTicketingException('event_ticket_eligibility_policy_invalid');
        }
        if (array_diff(array_keys($policy), self::FIELDS) !== []) {
            throw new EventTicketingException('event_ticket_eligibility_policy_fields_unknown');
        }
        $approved = $policy['approved_member_required'] ?? true;
        if (! is_bool($approved)) {
            throw new EventTicketingException('event_ticket_eligibility_approval_invalid');
        }
        $age = $policy['minimum_account_age_days'] ?? 0;
        if (! is_int($age) || $age < 0 || $age > 3650) {
            throw new EventTicketingException('event_ticket_eligibility_account_age_invalid');
        }
        $rawGroups = $policy['required_group_ids'] ?? [];
        if (! is_array($rawGroups) || ! array_is_list($rawGroups) || count($rawGroups) > 20) {
            throw new EventTicketingException('event_ticket_eligibility_groups_invalid');
        }
        $groupIds = [];
        foreach ($rawGroups as $groupId) {
            if (! is_int($groupId) || $groupId <= 0) {
                throw new EventTicketingException('event_ticket_eligibility_group_invalid');
            }
            $groupIds[] = $groupId;
        }
        $groupIds = array_values(array_unique($groupIds));
        sort($groupIds);
        if ($groupIds !== []
            && DB::table('groups')
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $groupIds)
                ->where('status', 'active')
                ->count() !== count($groupIds)) {
            throw new EventTicketingException('event_ticket_eligibility_group_not_found');
        }

        return [
            'approved_member_required' => $approved,
            'minimum_account_age_days' => $age,
            'required_group_ids' => $groupIds,
        ];
    }

    /**
     * @param array{approved_member_required:bool,minimum_account_age_days:int,required_group_ids:list<int>} $policy
     * @return array{eligible:bool,reasons:list<string>}
     */
    public function evaluate(
        int $tenantId,
        User $member,
        array $policy,
        ?CarbonImmutable $asOf = null,
    ): array {
        $reasons = [];
        if ($policy['approved_member_required'] && ! (bool) $member->is_approved) {
            $reasons[] = 'event_ticket_eligibility_member_not_approved';
        }
        $asOf ??= CarbonImmutable::now('UTC');
        try {
            $createdAt = CarbonImmutable::instance($member->created_at)->utc();
        } catch (Throwable) {
            $reasons[] = 'event_ticket_eligibility_account_age_unknown';
            $createdAt = $asOf;
        }
        if ($createdAt->greaterThan($asOf->subDays($policy['minimum_account_age_days']))) {
            $reasons[] = 'event_ticket_eligibility_account_too_new';
        }
        if ($policy['required_group_ids'] !== []) {
            $membershipCount = DB::table('group_members as membership')
                ->join('groups as required_group', function ($join): void {
                    $join->on('required_group.tenant_id', '=', 'membership.tenant_id')
                        ->on('required_group.id', '=', 'membership.group_id');
                })
                ->where('membership.tenant_id', $tenantId)
                ->where('membership.user_id', (int) $member->id)
                ->where('membership.status', 'active')
                ->where('required_group.status', 'active')
                ->whereIn('membership.group_id', $policy['required_group_ids'])
                ->distinct()
                ->count('membership.group_id');
            if ($membershipCount !== count($policy['required_group_ids'])) {
                $reasons[] = 'event_ticket_eligibility_group_membership_required';
            }
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }
}
