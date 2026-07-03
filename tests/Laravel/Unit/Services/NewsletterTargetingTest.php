<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NewsletterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Laravel\TestCase;

/**
 * Regression tests for the newsletter group/geo targeting fix (2026-07-03).
 *
 * Root cause: newsletters.target_groups / target_counties / target_towns were
 * WRITE-ONLY — stored by the admin UI but never read at send time, so a
 * "send to group X" newsletter silently went to ALL members. These tests pin
 * the fix: extractTargeting(), filterUserIdsByTargeting() (via the recipient
 * resolvers), the sendNow() self-load, and the segment-without-id guard.
 */
class NewsletterTargetingTest extends TestCase
{
    use DatabaseTransactions;

    private int $isolatedTenantSeq = 0;

    private function useIsolatedTenant(): int
    {
        $this->isolatedTenantSeq++;
        $tenant = Tenant::factory()->create([
            'slug' => 'nl-targeting-' . uniqid('', true) . '-' . $this->isolatedTenantSeq,
            'domain' => null,
        ]);

        $this->withTenant((int) $tenant->id);

        return (int) $tenant->id;
    }

    /**
     * Create an approved, active member with a controlled location.
     */
    private function makeMember(int $tenantId, string $email, string $location = ''): User
    {
        $user = User::factory()->forTenant($tenantId)->create([
            'email' => $email,
            'location' => $location,
            'status' => 'active',
            'is_approved' => 1,
        ]);

        return $user;
    }

    /**
     * Create a real group row (group_members.group_id has a FK to groups.id).
     */
    private function makeGroup(int $tenantId, int $ownerId, string $name = 'Targeting Test Group'): int
    {
        return (int) DB::table('groups')->insertGetId([
            'tenant_id' => $tenantId,
            'owner_id' => $ownerId,
            'name' => $name,
            'slug' => 'targeting-test-' . uniqid('', true),
            'visibility' => 'public',
            'created_at' => now(),
        ]);
    }

    private function addToGroup(int $tenantId, int $groupId, int $userId, string $status = 'active'): void
    {
        DB::table('group_members')->insert([
            'tenant_id' => $tenantId,
            'group_id' => $groupId,
            'user_id' => $userId,
            'status' => $status,
            'role' => 'member',
            'joined_at' => now(),
            'created_at' => now(),
        ]);
    }

    // ================================================================
    // extractTargeting()
    // ================================================================

    public function test_extract_targeting_decodes_json_columns(): void
    {
        $targeting = NewsletterService::extractTargeting((object) [
            'target_groups' => '[3, "7", 0, null]',
            'target_counties' => '["Cork", " Kerry "]',
            'target_towns' => null,
        ]);

        $this->assertSame([3, 7], $targeting['groups']);
        $this->assertSame(['Cork', 'Kerry'], $targeting['counties']);
        $this->assertSame([], $targeting['towns']);
    }

    public function test_extract_targeting_handles_arrays_and_garbage(): void
    {
        $targeting = NewsletterService::extractTargeting([
            'target_groups' => [5],
            'target_counties' => 'not-json',
            'target_towns' => '',
        ]);

        $this->assertSame([5], $targeting['groups']);
        $this->assertSame([], $targeting['counties']);
        $this->assertSame([], $targeting['towns']);
    }

    // ================================================================
    // Recipient resolution — all_members audience
    // ================================================================

    public function test_recipient_count_applies_group_targeting(): void
    {
        $tenantId = $this->useIsolatedTenant();

        $inGroup = $this->makeMember($tenantId, 'in-group@targeting.test');
        $alsoInGroup = $this->makeMember($tenantId, 'also-in-group@targeting.test');
        $this->makeMember($tenantId, 'not-in-group@targeting.test');

        $groupId = $this->makeGroup($tenantId, (int) $inGroup->id);
        $this->addToGroup($tenantId, $groupId, (int) $inGroup->id);
        $this->addToGroup($tenantId, $groupId, (int) $alsoInGroup->id);

        $unfiltered = TenantContext::runForTenant($tenantId, fn () => NewsletterService::getRecipientCount('all_members'));
        $filtered = TenantContext::runForTenant($tenantId, fn () => NewsletterService::getRecipientCount('all_members', ['groups' => [$groupId]]));

        $this->assertSame(3, $unfiltered);
        $this->assertSame(2, $filtered);
    }

    public function test_recipient_count_ignores_non_active_group_memberships(): void
    {
        $tenantId = $this->useIsolatedTenant();

        $active = $this->makeMember($tenantId, 'active-member@targeting.test');
        $pending = $this->makeMember($tenantId, 'pending-member@targeting.test');

        $groupId = $this->makeGroup($tenantId, (int) $active->id);
        $this->addToGroup($tenantId, $groupId, (int) $active->id, 'active');
        $this->addToGroup($tenantId, $groupId, (int) $pending->id, 'pending');

        $filtered = TenantContext::runForTenant($tenantId, fn () => NewsletterService::getRecipientCount('all_members', ['groups' => [$groupId]]));

        $this->assertSame(1, $filtered);
    }

    public function test_recipient_count_applies_county_and_town_targeting(): void
    {
        $tenantId = $this->useIsolatedTenant();

        $this->makeMember($tenantId, 'cork@targeting.test', 'Skibbereen, Co. Cork');
        $this->makeMember($tenantId, 'dublin@targeting.test', 'Dublin');
        $this->makeMember($tenantId, 'nowhere@targeting.test', '');

        $byCounty = TenantContext::runForTenant($tenantId, fn () => NewsletterService::getRecipientCount('all_members', ['counties' => ['Cork']]));
        $byTown = TenantContext::runForTenant($tenantId, fn () => NewsletterService::getRecipientCount('all_members', ['towns' => ['Skibbereen']]));

        $this->assertSame(1, $byCounty);
        $this->assertSame(1, $byTown);
    }

    public function test_targeting_facets_combine_with_or(): void
    {
        $tenantId = $this->useIsolatedTenant();

        $groupOnly = $this->makeMember($tenantId, 'group-only@targeting.test', 'Dublin');
        $this->makeMember($tenantId, 'county-only@targeting.test', 'Bantry, Co. Cork');
        $this->makeMember($tenantId, 'neither@targeting.test', 'Galway');

        $groupId = $this->makeGroup($tenantId, (int) $groupOnly->id);
        $this->addToGroup($tenantId, $groupId, (int) $groupOnly->id);

        $count = TenantContext::runForTenant($tenantId, fn () => NewsletterService::getRecipientCount('all_members', [
            'groups' => [$groupId],
            'counties' => ['Cork'],
        ]));

        // Union semantics: in the group OR in the county.
        $this->assertSame(2, $count);
    }

    public function test_targeting_matching_nobody_yields_zero_not_everyone(): void
    {
        $tenantId = $this->useIsolatedTenant();

        $this->makeMember($tenantId, 'somebody@targeting.test');

        $count = TenantContext::runForTenant($tenantId, fn () => NewsletterService::getRecipientCount('all_members', ['groups' => [999999999]]));

        // Before the fix this returned every member; targeting must narrow, never widen.
        $this->assertSame(0, $count);
    }

    // ================================================================
    // Recipient resolution — subscribers_only audience
    // ================================================================

    public function test_unregistered_subscribers_excluded_when_targeting_active(): void
    {
        $tenantId = $this->useIsolatedTenant();

        $member = $this->makeMember($tenantId, 'member-subscriber@targeting.test');
        $groupId = $this->makeGroup($tenantId, (int) $member->id);
        $this->addToGroup($tenantId, $groupId, (int) $member->id);

        DB::table('newsletter_subscribers')->insert([
            [
                'tenant_id' => $tenantId,
                'email' => 'member-subscriber@targeting.test',
                'user_id' => $member->id,
                'status' => 'active',
                'confirmation_token' => Str::random(64),
                'unsubscribe_token' => Str::random(64),
                'confirmed_at' => now(),
                'source' => 'manual',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenantId,
                'email' => 'unregistered@targeting.test',
                'user_id' => null,
                'status' => 'active',
                'confirmation_token' => Str::random(64),
                'unsubscribe_token' => Str::random(64),
                'confirmed_at' => now(),
                'source' => 'manual',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $unfiltered = TenantContext::runForTenant($tenantId, fn () => NewsletterService::getRecipientCount('subscribers_only'));
        $filtered = TenantContext::runForTenant($tenantId, fn () => NewsletterService::getRecipientCount('subscribers_only', ['groups' => [$groupId]]));

        $this->assertSame(2, $unfiltered);
        // Unregistered subscribers cannot belong to a group — excluded while targeting.
        $this->assertSame(1, $filtered);
    }

    // ================================================================
    // Segment recipients honor targeting too
    // ================================================================

    public function test_segment_recipients_apply_group_targeting(): void
    {
        $tenantId = $this->useIsolatedTenant();

        $inGroup = $this->makeMember($tenantId, 'seg-in-group@targeting.test');
        $outGroup = $this->makeMember($tenantId, 'seg-out-group@targeting.test');
        $groupId = $this->makeGroup($tenantId, (int) $inGroup->id);
        $this->addToGroup($tenantId, $groupId, (int) $inGroup->id);

        // Segment rule matching both users (empty bio); opt-in required by queryUsersByRules().
        DB::table('users')->whereIn('id', [$inGroup->id, $outGroup->id])->update([
            'bio' => '',
            'newsletter_opt_in' => 1,
        ]);

        $segmentId = DB::table('newsletter_segments')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Targeting test segment',
            'description' => '',
            'is_active' => 1,
            'match_type' => 'all',
            'rules' => json_encode([['field' => 'bio', 'operator' => 'is_empty', 'value' => '']]),
            'subscriber_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unfiltered = TenantContext::runForTenant($tenantId, fn () => NewsletterService::getSegmentRecipients($segmentId));
        $filtered = TenantContext::runForTenant($tenantId, fn () => NewsletterService::getSegmentRecipients($segmentId, ['groups' => [$groupId]]));

        $this->assertCount(2, $unfiltered);
        $this->assertCount(1, $filtered);
        $this->assertSame('seg-in-group@targeting.test', $filtered[0]['email']);
    }

    // ================================================================
    // sendNow() — self-loads stored targeting + segment guard
    // ================================================================

    public function test_send_now_reads_stored_target_groups_from_newsletter_row(): void
    {
        $tenantId = $this->useIsolatedTenant();

        $admin = User::factory()->forTenant($tenantId)->admin()->create();
        $this->makeMember($tenantId, 'everyone-else@targeting.test');

        // Stored targeting matches NOBODY. Before the fix sendNow() ignored the
        // column entirely and would have queued every member; with the fix the
        // empty recipient set is detected before anything is queued or sent.
        $newsletterId = DB::table('newsletters')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Group-targeted newsletter',
            'subject' => 'Group-targeted newsletter',
            'content' => '<p>Hello</p>',
            'status' => 'draft',
            'target_audience' => 'all_members',
            'target_groups' => json_encode([999999999]),
            'created_by' => $admin->id,
            'created_at' => now(),
        ]);

        $this->expectExceptionMessage('No eligible recipients found');

        TenantContext::runForTenant($tenantId, fn () => NewsletterService::sendNow($newsletterId, 'all_members'));
    }

    public function test_send_now_throws_for_segment_audience_without_segment(): void
    {
        $tenantId = $this->useIsolatedTenant();

        $admin = User::factory()->forTenant($tenantId)->admin()->create();
        $this->makeMember($tenantId, 'silent-fallthrough@targeting.test');

        $newsletterId = DB::table('newsletters')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Segment newsletter without segment',
            'subject' => 'Segment newsletter without segment',
            'content' => '<p>Hello</p>',
            'status' => 'draft',
            'target_audience' => 'segment',
            'segment_id' => null,
            'created_by' => $admin->id,
            'created_at' => now(),
        ]);

        // Must refuse — never silently fall through to all_members.
        $this->expectExceptionMessage('targets a segment but no segment is selected');

        TenantContext::runForTenant($tenantId, fn () => NewsletterService::sendNow($newsletterId, 'segment'));
    }

    public function test_send_now_without_stored_targeting_still_resolves_recipients(): void
    {
        $tenantId = $this->useIsolatedTenant();

        $admin = User::factory()->forTenant($tenantId)->admin()->create();

        $newsletterId = DB::table('newsletters')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Untargeted newsletter',
            'subject' => 'Untargeted newsletter',
            'content' => '<p>Hello</p>',
            'status' => 'draft',
            'target_audience' => 'subscribers_only',
            'created_by' => $admin->id,
            'created_at' => now(),
        ]);

        // No subscribers exist → 'No eligible recipients found' proves the code
        // path runs cleanly (no crash in extractTargeting on NULL columns).
        $this->expectExceptionMessage('No eligible recipients found');

        TenantContext::runForTenant($tenantId, fn () => NewsletterService::sendNow($newsletterId, 'subscribers_only'));
    }
}
