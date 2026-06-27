<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Reactions;

use App\Core\TenantContext;
use App\Models\User;
use App\Support\FeedItemTables;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: reacting to (or enumerating reactors of) a group discussion must
 * require group membership, like the discussion read/post endpoints.
 *
 * Group discussions are not feed_activity rows, so FeedItemTables::canView fell
 * through to its generic "no feed row → visible" branch and returned true for a
 * 'discussion' target — letting a NON-member react to and list reactors of a
 * PRIVATE group's discussions (confirmed live: a non-member POST /v2/reactions
 * {target_type:discussion} returned 200 and leaked the reactor list). canView
 * now gates 'discussion' on active membership.
 */
class DiscussionReactionAccessTest extends TestCase
{
    use DatabaseTransactions;

    private function user(): User
    {
        $u = User::factory()->forTenant($this->testTenantId)->create();
        DB::table('users')->where('id', $u->id)->update(['tenant_id' => $this->testTenantId]);

        return $u;
    }

    public function test_discussion_reaction_access_requires_group_membership(): void
    {
        $tid = $this->testTenantId;
        $owner    = $this->user();
        $member   = $this->user();
        $outsider = $this->user();

        $groupId = (int) DB::table('groups')->insertGetId([
            'tenant_id'  => $tid,
            'owner_id'   => $owner->id,
            'name'       => 'Private reaction-access group',
            'slug'       => 'private-rx-' . $owner->id,
            'visibility' => 'private',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([[$owner->id, 'owner'], [$member->id, 'member']] as [$uid, $role]) {
            DB::table('group_members')->insert([
                'tenant_id'  => $tid,
                'group_id'   => $groupId,
                'user_id'    => $uid,
                'status'     => 'active',
                'role'       => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $discId = (int) DB::table('group_discussions')->insertGetId([
            'tenant_id'  => $tid,
            'group_id'   => $groupId,
            'user_id'    => $owner->id,
            'title'      => 'Members only',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::runForTenant($tid, function () use ($discId, $owner, $member, $outsider) {
            $this->assertTrue(FeedItemTables::canView('discussion', $discId, (int) $owner->id), 'owner may react');
            $this->assertTrue(FeedItemTables::canView('discussion', $discId, (int) $member->id), 'active member may react');
            $this->assertFalse(FeedItemTables::canView('discussion', $discId, (int) $outsider->id), 'non-member must NOT react to a private group discussion');
            $this->assertFalse(FeedItemTables::canView('discussion', $discId, null), 'unauthenticated must NOT access');
        });
    }
}
