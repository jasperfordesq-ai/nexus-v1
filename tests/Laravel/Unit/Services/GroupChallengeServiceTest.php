<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\GroupAuditService;
use App\Services\GroupChallengeService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Laravel\TestCase;

final class GroupChallengeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $memberOne;
    private User $memberTwo;
    private User $nonMember;
    private int $activeGroupId;
    private int $archivedGroupId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->forTenant($this->testTenantId)->create(['xp' => 0]);
        $this->memberOne = User::factory()->forTenant($this->testTenantId)->create(['xp' => 0]);
        $this->memberTwo = User::factory()->forTenant($this->testTenantId)->create(['xp' => 0]);
        $this->nonMember = User::factory()->forTenant($this->testTenantId)->create(['xp' => 0]);
        TenantContext::setById($this->testTenantId);

        $this->activeGroupId = $this->insertGroup('active');
        $this->archivedGroupId = $this->insertGroup('archived');
        $this->insertMembership($this->activeGroupId, (int) $this->memberOne->id);
        $this->insertMembership($this->activeGroupId, (int) $this->memberTwo->id);
    }

    public function test_create_accepts_end_date_alias_and_returns_the_stable_dto(): void
    {
        $challenge = GroupChallengeService::create($this->activeGroupId, (int) $this->owner->id, [
            'title' => 'Publish useful updates',
            'description' => 'Share useful updates with everyone in the group.',
            'metric' => 'posts',
            'target_value' => 10,
            'reward_xp' => 25,
            'end_date' => now()->addWeek()->toDateString(),
        ]);

        self::assertSame([
            'id',
            'group_id',
            'title',
            'description',
            'metric',
            'target_value',
            'current_value',
            'reward_xp',
            'status',
            'progress_percentage',
            'starts_at',
            'ends_at',
            'completed_at',
            'creator',
            'created_at',
            'updated_at',
        ], array_keys($challenge));
        self::assertSame($this->activeGroupId, $challenge['group_id']);
        self::assertSame(0, $challenge['current_value']);
        self::assertSame(25, $challenge['reward_xp']);
        self::assertSame('active', $challenge['status']);
        self::assertSame((int) $this->owner->id, $challenge['creator']['id']);
        self::assertNotEmpty($challenge['starts_at']);
        self::assertNotEmpty($challenge['ends_at']);

        $listed = GroupChallengeService::getAll($this->activeGroupId);
        self::assertCount(1, $listed);
        self::assertSame(array_keys($challenge), array_keys($listed[0]));
        self::assertArrayNotHasKey('end_date', $listed[0]);
        self::assertSame(1, DB::table('group_audit_log')
            ->where('group_id', $this->activeGroupId)
            ->where('action', GroupAuditService::ACTION_CHALLENGE_CREATED)
            ->where('user_id', (int) $this->owner->id)
            ->count());
    }

    public function test_validation_rejects_unimplemented_metrics_unbounded_values_dates_and_arbitrary_rewards(): void
    {
        $valid = [
            'title' => 'Valid challenge',
            'description' => 'A sufficiently descriptive challenge body.',
            'metric' => 'posts',
            'target_value' => 10,
            'reward_xp' => 25,
            'ends_at' => now()->addWeek()->toIso8601String(),
        ];
        $cases = [
            'short title' => [['title' => 'ab'], GroupChallengeService::ERROR_TITLE_LENGTH],
            'long title' => [['title' => str_repeat('x', GroupChallengeService::TITLE_MAX + 1)], GroupChallengeService::ERROR_TITLE_LENGTH],
            'short description' => [['description' => 'short'], GroupChallengeService::ERROR_DESCRIPTION_LENGTH],
            'long description' => [['description' => str_repeat('x', GroupChallengeService::DESCRIPTION_MAX + 1)], GroupChallengeService::ERROR_DESCRIPTION_LENGTH],
            'events without hook' => [['metric' => 'events'], GroupChallengeService::ERROR_METRIC],
            'unknown metric' => [['metric' => 'likes'], GroupChallengeService::ERROR_METRIC],
            'zero target' => [['target_value' => 0], GroupChallengeService::ERROR_TARGET],
            'target above cap' => [['target_value' => GroupChallengeService::TARGET_MAX + 1], GroupChallengeService::ERROR_TARGET],
            'boolean target' => [['target_value' => true], GroupChallengeService::ERROR_TARGET],
            'fractional target' => [['target_value' => 1.5], GroupChallengeService::ERROR_TARGET],
            'arbitrary reward' => [['reward_xp' => 30], GroupChallengeService::ERROR_REWARD],
            'reward above cap' => [['reward_xp' => 101], GroupChallengeService::ERROR_REWARD],
            'boolean reward' => [['reward_xp' => false], GroupChallengeService::ERROR_REWARD],
            'fractional reward' => [['reward_xp' => 25.5], GroupChallengeService::ERROR_REWARD],
            'arbitrary badge' => [['reward_badge' => 'invented'], GroupChallengeService::ERROR_REWARD],
            'past end' => [['ends_at' => now()->subMinute()->toIso8601String()], GroupChallengeService::ERROR_DATES],
            'end before start' => [[
                'starts_at' => now()->addDays(2)->toIso8601String(),
                'ends_at' => now()->addDay()->toIso8601String(),
            ], GroupChallengeService::ERROR_DATES],
            'invalid date' => [['ends_at' => 'not-a-date'], GroupChallengeService::ERROR_DATES],
        ];

        foreach ($cases as $label => [$changes, $expectedError]) {
            try {
                GroupChallengeService::normalizeCreateData(array_replace($valid, $changes));
                self::fail("Expected validation failure: {$label}");
            } catch (InvalidArgumentException $e) {
                self::assertSame($expectedError, $e->getMessage(), $label);
            }
        }

        foreach (GroupChallengeService::REWARD_BANDS as $reward) {
            $normalized = GroupChallengeService::normalizeCreateData(array_replace($valid, ['reward_xp' => $reward]));
            self::assertSame($reward, $normalized['reward_xp']);
        }
        $defaultReward = GroupChallengeService::normalizeCreateData(array_diff_key($valid, ['reward_xp' => true]));
        self::assertSame(0, $defaultReward['reward_xp']);
        $formEncodedValues = GroupChallengeService::normalizeCreateData(array_replace($valid, [
            'target_value' => '10',
            'reward_xp' => '25',
        ]));
        self::assertSame(10, $formEncodedValues['target_value']);
        self::assertSame(25, $formEncodedValues['reward_xp']);
    }

    public function test_create_and_cancel_enforce_parent_lifecycle_admin_policy_and_idempotency(): void
    {
        $payload = [
            'title' => 'Protected challenge',
            'description' => 'Only an active group manager may create this.',
            'metric' => 'posts',
            'target_value' => 5,
            'ends_at' => now()->addWeek()->toIso8601String(),
        ];

        foreach ([
            [$this->activeGroupId, (int) $this->nonMember->id],
            [$this->archivedGroupId, (int) $this->owner->id],
        ] as [$groupId, $actorId]) {
            try {
                GroupChallengeService::create($groupId, $actorId, $payload);
                self::fail('Expected challenge create authorization failure.');
            } catch (AuthorizationException) {
                self::assertTrue(true);
            }
        }

        $challenge = GroupChallengeService::create($this->activeGroupId, (int) $this->owner->id, $payload);
        try {
            GroupChallengeService::delete($this->activeGroupId, $challenge['id'], (int) $this->nonMember->id);
                self::fail('Expected challenge cancellation authorization failure.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        $cancelled = GroupChallengeService::delete(
            $this->activeGroupId,
            $challenge['id'],
            (int) $this->owner->id,
        );
        self::assertNotNull($cancelled);
        self::assertTrue($cancelled['changed']);
        self::assertSame('cancelled', $cancelled['challenge']['status']);
        self::assertSame('cancelled', DB::table('group_challenges')->where('id', $challenge['id'])->value('status'));

        $repeated = GroupChallengeService::delete(
            $this->activeGroupId,
            $challenge['id'],
            (int) $this->owner->id,
        );
        self::assertNotNull($repeated);
        self::assertFalse($repeated['changed']);
        self::assertSame('cancelled', $repeated['challenge']['status']);
        self::assertContains($challenge['id'], array_column(GroupChallengeService::getAll($this->activeGroupId), 'id'));
        self::assertNotContains($challenge['id'], array_column(GroupChallengeService::getActive($this->activeGroupId), 'id'));
        self::assertSame(1, DB::table('group_audit_log')
            ->where('group_id', $this->activeGroupId)
            ->where('action', GroupAuditService::ACTION_CHALLENGE_CANCELLED)
            ->count());
    }

    public function test_completed_rewarded_challenge_is_immutable_and_preserves_economy_history(): void
    {
        $challengeId = $this->insertChallenge($this->activeGroupId, 'posts', 1, 25);
        GroupChallengeService::incrementProgress($this->activeGroupId, 'posts');

        $ledgerCount = DB::table('group_challenge_rewards')->where('challenge_id', $challengeId)->count();
        $xpLogCount = DB::table('user_xp_log')
            ->where('source_reference', 'group_challenge:' . $challengeId)
            ->count();
        $memberXp = DB::table('users')
            ->whereIn('id', [(int) $this->memberOne->id, (int) $this->memberTwo->id])
            ->sum('xp');

        try {
            GroupChallengeService::delete($this->activeGroupId, $challengeId, (int) $this->owner->id);
            self::fail('Expected completed challenge to be immutable.');
        } catch (DomainException $e) {
            self::assertSame(GroupChallengeService::ERROR_IMMUTABLE, $e->getMessage());
        }

        self::assertSame('completed', DB::table('group_challenges')->where('id', $challengeId)->value('status'));
        self::assertSame($ledgerCount, DB::table('group_challenge_rewards')->where('challenge_id', $challengeId)->count());
        self::assertSame($xpLogCount, DB::table('user_xp_log')
            ->where('source_reference', 'group_challenge:' . $challengeId)
            ->count());
        self::assertSame($memberXp, DB::table('users')
            ->whereIn('id', [(int) $this->memberOne->id, (int) $this->memberTwo->id])
            ->sum('xp'));
        self::assertSame(0, DB::table('group_audit_log')
            ->where('group_id', $this->activeGroupId)
            ->where('action', GroupAuditService::ACTION_CHALLENGE_CANCELLED)
            ->count());
    }

    public function test_repeated_completion_awards_each_active_member_exactly_once(): void
    {
        $challengeId = $this->insertChallenge($this->activeGroupId, 'posts', 2, 50);

        GroupChallengeService::incrementProgress($this->activeGroupId, 'posts');
        GroupChallengeService::incrementProgress($this->activeGroupId, 'posts');
        GroupChallengeService::incrementProgress($this->activeGroupId, 'posts');

        $challenge = DB::table('group_challenges')->where('id', $challengeId)->first();
        self::assertSame('completed', $challenge->status);
        self::assertSame(2, (int) $challenge->current_value);
        self::assertNotNull($challenge->completed_at);

        $memberIds = [(int) $this->memberOne->id, (int) $this->memberTwo->id];
        self::assertSame(2, DB::table('group_challenge_rewards')->where('challenge_id', $challengeId)->count());
        foreach ($memberIds as $memberId) {
            self::assertSame(50, (int) DB::table('users')->where('id', $memberId)->value('xp'));
            self::assertSame(1, DB::table('group_challenge_rewards')
                ->where('challenge_id', $challengeId)
                ->where('user_id', $memberId)
                ->count());
            self::assertSame(1, DB::table('user_xp_log')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $memberId)
                ->where('action', 'group_challenge')
                ->where('source_reference', 'group_challenge:' . $challengeId)
                ->count());
        }
        self::assertSame(1, DB::table('group_audit_log')
            ->where('group_id', $this->activeGroupId)
            ->where('action', GroupAuditService::ACTION_CHALLENGE_COMPLETED)
            ->count());
        self::assertSame(2, DB::table('group_audit_log')
            ->where('group_id', $this->activeGroupId)
            ->where('action', GroupAuditService::ACTION_CHALLENGE_REWARD_AWARDED)
            ->count());
    }

    public function test_zero_reward_completion_is_idempotent_without_ledger_rows(): void
    {
        $challengeId = $this->insertChallenge($this->activeGroupId, 'files', 1, 0);

        GroupChallengeService::incrementProgress($this->activeGroupId, 'files');
        GroupChallengeService::incrementProgress($this->activeGroupId, 'files');

        self::assertSame('completed', DB::table('group_challenges')->where('id', $challengeId)->value('status'));
        self::assertSame(0, DB::table('group_challenge_rewards')->where('challenge_id', $challengeId)->count());
    }

    public function test_legacy_arbitrary_reward_is_never_issued(): void
    {
        $challengeId = $this->insertChallenge($this->activeGroupId, 'posts', 1, 30);

        GroupChallengeService::incrementProgress($this->activeGroupId, 'posts');

        self::assertSame('completed', DB::table('group_challenges')->where('id', $challengeId)->value('status'));
        self::assertSame(0, DB::table('group_challenge_rewards')->where('challenge_id', $challengeId)->count());
        self::assertSame(0, (int) DB::table('users')->where('id', $this->memberOne->id)->value('xp'));
        self::assertSame(0, (int) DB::table('users')->where('id', $this->memberTwo->id)->value('xp'));
    }

    public function test_events_and_non_active_parent_groups_do_not_progress(): void
    {
        $legacyEventsId = $this->insertChallenge($this->activeGroupId, 'events', 2, 0);
        $archivedPostsId = $this->insertChallenge($this->archivedGroupId, 'posts', 2, 0);

        GroupChallengeService::incrementProgress($this->activeGroupId, 'events');
        GroupChallengeService::incrementProgress($this->archivedGroupId, 'posts');

        self::assertSame(0, (int) DB::table('group_challenges')->where('id', $legacyEventsId)->value('current_value'));
        self::assertSame(0, (int) DB::table('group_challenges')->where('id', $archivedPostsId)->value('current_value'));
        self::assertNotContains(
            $legacyEventsId,
            array_column(GroupChallengeService::getAll($this->activeGroupId), 'id'),
        );
    }

    private function insertGroup(string $status): int
    {
        return (int) DB::table('groups')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'owner_id' => $this->owner->id,
            'name' => 'Group challenge service ' . $status . ' ' . uniqid('', true),
            'description' => 'Group challenge service test fixture.',
            'visibility' => 'private',
            'status' => $status,
            'is_active' => $status === 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMembership(int $groupId, int $userId): void
    {
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'user_id' => $userId,
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertChallenge(int $groupId, string $metric, int $target, int $reward): int
    {
        return (int) DB::table('group_challenges')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'created_by' => $this->owner->id,
            'title' => 'Progress challenge ' . uniqid('', true),
            'description' => 'Challenge progress and reward fixture.',
            'metric' => $metric,
            'target_value' => $target,
            'current_value' => 0,
            'reward_xp' => $reward,
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
