<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\GroupConversationService;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Accessible-frontend MESSAGES parity — group messaging (create group, invite /
 * remove members, group conversation view, reply, emoji reactions). Each route /
 * feature gets at least one test (200/302 auth-gated, owner/admin 403, cross-tenant
 * 404, happy-path mutation persists). Method names are UNIQUE (test_messages_*).
 */
class MessagesParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();

        foreach ([
            'HTTP_X_TENANT_ID',
            'HTTP_X_TENANT_SLUG',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
        ] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        \Illuminate\Support\Facades\Cache::flush();
    }

    // ----------------------------------------------------------------------
    //  Auth gating
    // ----------------------------------------------------------------------

    public function test_messages_groups_index_requires_authentication(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/messages/groups");
        $response->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    public function test_messages_create_group_form_requires_authentication(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/messages/groups/new");
        $response->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    // ----------------------------------------------------------------------
    //  Group list + create form render
    // ----------------------------------------------------------------------

    public function test_messages_groups_index_renders_empty_state_for_member(): void
    {
        $this->messagesAuthedUser(['name' => 'Group Viewer']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/messages/groups");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha_messages.groups.title'));
        $response->assertSee(__('govuk_alpha_messages.groups.empty_title'));
        // Sub-nav links back to direct messages.
        $response->assertSee(route('govuk-alpha.messages.index', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_messages_groups_index_lists_a_group_the_user_belongs_to(): void
    {
        $owner = $this->messagesAuthedUser(['name' => 'Group Owner']);
        [$a, $b] = $this->messagesTwoMembers();
        $group = $this->messagesCreateGroup($owner->id, [$a->id, $b->id], 'Weekend Project');

        $response = $this->get("/{$this->testTenantSlug}/alpha/messages/groups");
        $response->assertOk();
        $response->assertSee('Weekend Project');
        $response->assertSee(route('govuk-alpha.messages.groups.show', [
            'tenantSlug' => $this->testTenantSlug,
            'conversationId' => $group['id'],
        ]), false);
    }

    public function test_messages_create_group_form_renders_and_searches_members(): void
    {
        $this->messagesAuthedUser(['name' => 'Group Creator']);
        $this->messagesDisableMeili();
        User::factory()->forTenant($this->testTenantId)->create([
            'first_name' => 'Findable', 'last_name' => 'Member',
            'status' => 'active', 'is_approved' => true, 'privacy_search' => 1,
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/messages/groups/new");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha_messages.create.title'));
        $response->assertSee('name="member_ids[]"', false);
        $response->assertSee(__('govuk_alpha_messages.create.create_button'));
    }

    // ----------------------------------------------------------------------
    //  Create group (POST)
    // ----------------------------------------------------------------------

    public function test_messages_store_group_creates_a_group_conversation(): void
    {
        $owner = $this->messagesAuthedUser(['name' => 'Maker']);
        [$a, $b] = $this->messagesTwoMembers();

        $response = $this->post("/{$this->testTenantSlug}/alpha/messages/groups", [
            'name' => 'Project Falcon',
            'member_ids' => [$a->id, $b->id],
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=group-created', $response->headers->get('Location'));
        $this->assertDatabaseHas('conversations', [
            'tenant_id' => $this->testTenantId,
            'is_group' => 1,
            'group_name' => 'Project Falcon',
            'created_by' => $owner->id,
        ]);
    }

    public function test_messages_store_group_rejects_when_too_few_members(): void
    {
        $this->messagesAuthedUser(['name' => 'Maker Solo']);
        [$a] = $this->messagesTwoMembers();

        $response = $this->post("/{$this->testTenantSlug}/alpha/messages/groups", [
            'name' => 'Too Small',
            'member_ids' => [$a->id],
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=group-create-failed', $response->headers->get('Location'));
        $this->assertDatabaseMissing('conversations', [
            'tenant_id' => $this->testTenantId,
            'group_name' => 'Too Small',
        ]);
    }

    // ----------------------------------------------------------------------
    //  Group conversation view
    // ----------------------------------------------------------------------

    public function test_messages_group_show_renders_for_a_participant(): void
    {
        $owner = $this->messagesAuthedUser(['name' => 'Convo Owner']);
        [$a, $b] = $this->messagesTwoMembers();
        $group = $this->messagesCreateGroup($owner->id, [$a->id, $b->id], 'Show Group');

        $response = $this->get("/{$this->testTenantSlug}/alpha/messages/groups/{$group['id']}");
        $response->assertOk();
        $response->assertSee('Show Group');
        $response->assertSee(__('govuk_alpha_messages.conversation.members_heading'));
        $response->assertSee(__('govuk_alpha_messages.conversation.send_button'));
    }

    public function test_messages_group_show_404_for_non_participant(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Other Owner', 'status' => 'active', 'is_approved' => true,
        ]);
        [$a, $b] = $this->messagesTwoMembers();
        $group = $this->messagesCreateGroup($owner->id, [$a->id, $b->id], 'Private Group');

        // A logged-in member who is NOT in the group.
        $this->messagesAuthedUser(['name' => 'Outsider']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/messages/groups/{$group['id']}");
        $response->assertNotFound();
    }

    public function test_messages_group_show_404_for_missing_conversation(): void
    {
        $this->messagesAuthedUser(['name' => 'Seeker']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/messages/groups/99999999");
        $response->assertNotFound();
    }

    // ----------------------------------------------------------------------
    //  Send group message
    // ----------------------------------------------------------------------

    public function test_messages_store_group_message_persists_and_redirects(): void
    {
        $owner = $this->messagesAuthedUser(['name' => 'Sender Owner']);
        [$a, $b] = $this->messagesTwoMembers();
        $group = $this->messagesCreateGroup($owner->id, [$a->id, $b->id], 'Chatty Group');

        $response = $this->post("/{$this->testTenantSlug}/alpha/messages/groups/{$group['id']}", [
            'body' => 'Hello to the whole group.',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=group-message-sent', $response->headers->get('Location'));
        $this->assertDatabaseHas('messages', [
            'tenant_id' => $this->testTenantId,
            'conversation_id' => $group['id'],
            'sender_id' => $owner->id,
            'body' => 'Hello to the whole group.',
        ]);
    }

    public function test_messages_store_group_message_rejects_empty_body(): void
    {
        $owner = $this->messagesAuthedUser(['name' => 'Empty Sender']);
        [$a, $b] = $this->messagesTwoMembers();
        $group = $this->messagesCreateGroup($owner->id, [$a->id, $b->id], 'Empty Group');

        $response = $this->post("/{$this->testTenantSlug}/alpha/messages/groups/{$group['id']}", [
            'body' => '   ',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=group-message-empty', $response->headers->get('Location'));
    }

    // ----------------------------------------------------------------------
    //  Add / remove members
    // ----------------------------------------------------------------------

    public function test_messages_group_admin_can_add_a_member(): void
    {
        $owner = $this->messagesAuthedUser(['name' => 'Admin Adder']);
        [$a, $b] = $this->messagesTwoMembers();
        $group = $this->messagesCreateGroup($owner->id, [$a->id, $b->id], 'Growing Group');
        $newcomer = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Newcomer', 'status' => 'active', 'is_approved' => true,
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/messages/groups/{$group['id']}/members", [
            'user_id' => $newcomer->id,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=group-member-added', $response->headers->get('Location'));
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $group['id'],
            'user_id' => $newcomer->id,
            'left_at' => null,
        ]);
    }

    public function test_messages_group_non_admin_cannot_add_a_member(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'True Admin', 'status' => 'active', 'is_approved' => true,
        ]);
        [$memberA, $memberB] = $this->messagesTwoMembers();
        $group = $this->messagesCreateGroup($owner->id, [$memberA->id, $memberB->id], 'Locked Group');

        // Log in as a plain member (memberA), who is not an admin.
        Sanctum::actingAs($memberA, ['*']);
        $newcomer = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Hopeful', 'status' => 'active', 'is_approved' => true,
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/messages/groups/{$group['id']}/members", [
            'user_id' => $newcomer->id,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=group-member-forbidden', $response->headers->get('Location'));
        $this->assertDatabaseMissing('conversation_participants', [
            'conversation_id' => $group['id'],
            'user_id' => $newcomer->id,
        ]);
    }

    public function test_messages_group_member_can_leave(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Stay Owner', 'status' => 'active', 'is_approved' => true,
        ]);
        [$leaver, $other] = $this->messagesTwoMembers();
        $group = $this->messagesCreateGroup($owner->id, [$leaver->id, $other->id], 'Leaver Group');

        Sanctum::actingAs($leaver, ['*']);

        $response = $this->post("/{$this->testTenantSlug}/alpha/messages/groups/{$group['id']}/members/{$leaver->id}/remove");

        $response->assertRedirect("/{$this->testTenantSlug}/alpha/messages/groups?status=group-left");
        $this->assertDatabaseMissing('conversation_participants', [
            'conversation_id' => $group['id'],
            'user_id' => $leaver->id,
            'left_at' => null,
        ]);
    }

    // ----------------------------------------------------------------------
    //  Reactions
    // ----------------------------------------------------------------------

    public function test_messages_group_member_can_react_to_a_message(): void
    {
        $owner = $this->messagesAuthedUser(['name' => 'Reactor Owner']);
        [$a, $b] = $this->messagesTwoMembers();
        $group = $this->messagesCreateGroup($owner->id, [$a->id, $b->id], 'React Group');

        $sent = GroupConversationService::sendGroupMessage($group['id'], $owner->id, 'React to me');
        $messageId = (int) ($sent['id'] ?? 0);
        $this->assertGreaterThan(0, $messageId);

        $response = $this->post("/{$this->testTenantSlug}/alpha/messages/groups/{$group['id']}/m/{$messageId}/react", [
            'emoji' => '👍',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=reaction-added', $response->headers->get('Location'));
        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $messageId,
            'user_id' => $owner->id,
            'emoji' => '👍',
        ]);
    }

    public function test_messages_group_reaction_rejects_invalid_emoji(): void
    {
        $owner = $this->messagesAuthedUser(['name' => 'Bad Reactor']);
        [$a, $b] = $this->messagesTwoMembers();
        $group = $this->messagesCreateGroup($owner->id, [$a->id, $b->id], 'Bad React Group');

        $sent = GroupConversationService::sendGroupMessage($group['id'], $owner->id, 'No bad reactions');
        $messageId = (int) ($sent['id'] ?? 0);

        $response = $this->post("/{$this->testTenantSlug}/alpha/messages/groups/{$group['id']}/m/{$messageId}/react", [
            'emoji' => 'not-an-emoji',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=reaction-invalid', $response->headers->get('Location'));
        $this->assertDatabaseMissing('message_reactions', [
            'message_id' => $messageId,
            'user_id' => $owner->id,
            'emoji' => 'not-an-emoji',
        ]);
    }

    // ----------------------------------------------------------------------
    //  In-conversation search
    // ----------------------------------------------------------------------

    public function test_messages_group_conversation_search_filters_messages(): void
    {
        $owner = $this->messagesAuthedUser(['name' => 'Search Owner']);
        [$a, $b] = $this->messagesTwoMembers();
        $group = $this->messagesCreateGroup($owner->id, [$a->id, $b->id], 'Search Group');

        GroupConversationService::sendGroupMessage($group['id'], $owner->id, 'apples are tasty');
        GroupConversationService::sendGroupMessage($group['id'], $owner->id, 'bananas are yellow');

        $response = $this->get("/{$this->testTenantSlug}/alpha/messages/groups/{$group['id']}?q=apples");
        $response->assertOk();
        // The matched term "apples" is wrapped in <mark> for highlighting, so the
        // raw phrase "apples are tasty" is split; assert the un-highlighted tail.
        $response->assertSee('are tasty');
        $response->assertDontSee('bananas are yellow');
    }

    // ----------------------------------------------------------------------
    //  Helpers (module-prefixed, replicating base-test helpers privately)
    // ----------------------------------------------------------------------

    private function messagesAuthedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function messagesTwoMembers(): array
    {
        $a = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Member A', 'status' => 'active', 'is_approved' => true,
        ]);
        $b = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Member B', 'status' => 'active', 'is_approved' => true,
        ]);

        return [$a, $b];
    }

    /**
     * Create a group conversation directly via the service (the same one the
     * controller calls), returning its formatted array.
     *
     * @param array<int, int> $memberIds
     * @return array<string, mixed>
     */
    private function messagesCreateGroup(int $creatorId, array $memberIds, string $name): array
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $group = GroupConversationService::createGroup($creatorId, $memberIds, $name);
        $this->assertNotNull($group, 'Failed to seed group conversation: ' . json_encode(GroupConversationService::getErrors()));

        return $group;
    }

    private function messagesDisableMeili(): void
    {
        $prop = new \ReflectionProperty(\App\Services\SearchService::class, 'available');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
    }
}
