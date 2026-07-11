<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Safeguarding;

use App\Core\TenantContext;
use App\Events\MessageSent;
use App\Events\SafeguardingContactAttemptBlocked;
use App\Exceptions\SafeguardingPolicyException;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Services\ContextualMessageService;
use App\Services\GroupConversationService;
use App\Services\MarketplaceOrderService;
use App\Services\MessageService;
use App\Services\SafeguardingInteractionPolicy;
use App\Support\SafeguardingInteractionDecision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Laravel\TestCase;

class ContactPolicyBypassEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance('tenant.id', $this->testTenantId);
        Event::fake([MessageSent::class, SafeguardingContactAttemptBlocked::class]);
    }

    public function test_contextual_message_uses_direct_message_policy_before_persistence(): void
    {
        $sender = $this->member();
        $recipient = $this->member();

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('evaluateLocalContact')
            ->once()
            ->with($sender->id, $recipient->id, $this->testTenantId, 'direct_message')
            ->andReturn($this->denied());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $messageId = (new ContextualMessageService())->sendWithContext(
            $sender->id,
            $recipient->id,
            'Blocked contextual contact',
            'listing',
            123,
        );

        $this->assertNull($messageId);
        $this->assertDatabaseMissing('messages', [
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'Blocked contextual contact',
        ]);
        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_direct_message_definitive_locked_recheck_blocks_a_concurrent_policy_change(): void
    {
        $sender = $this->member();
        $recipient = $this->member();

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('evaluateLocalContact')
            ->once()
            ->with($sender->id, $recipient->id, $this->testTenantId, 'direct_message')
            ->andReturn($this->allowed());
        $policy->shouldReceive('evaluateLockedLocalContact')
            ->once()
            ->with($sender->id, $recipient->id, $this->testTenantId, 'direct_message')
            ->andReturn($this->denied());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $result = MessageService::send($sender->id, $recipient->id, [
            'body' => 'Must be rejected by the definitive recheck',
        ]);

        $this->assertSame([], $result);
        $this->assertSame('VETTING_REQUIRED', MessageService::getErrors()[0]['code'] ?? null);
        $this->assertDatabaseMissing('messages', [
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'Must be rejected by the definitive recheck',
        ]);
    }

    public function test_story_reply_propagates_denial_before_recipient_notification(): void
    {
        $sender = $this->member();
        $owner = $this->member(['preferred_language' => 'en']);
        Sanctum::actingAs($sender, ['*']);

        $storyId = DB::table('stories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $owner->id,
            'media_type' => 'text',
            'text_content' => 'Protected story',
            'audience' => 'everyone',
            'duration' => 5,
            'is_active' => 1,
            'view_count' => 0,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('evaluateLocalContact')
            ->once()
            ->with($sender->id, $owner->id, $this->testTenantId, 'direct_message')
            ->andReturn($this->denied());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost("/v2/stories/{$storyId}/reply", [
            'body' => 'Blocked story reply',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'VETTING_REQUIRED');
        $this->assertDatabaseMissing('messages', [
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $owner->id,
            'body' => 'Blocked story reply',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $owner->id,
            'type' => 'story_reply',
        ]);
    }

    public function test_group_creation_denial_creates_no_conversation_or_participants(): void
    {
        $creator = $this->member();
        $first = $this->member();
        $second = $this->member();
        $conversationCount = DB::table('conversations')->count();
        $participantCount = DB::table('conversation_participants')->count();

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('evaluateLocalContact')
            ->times(6)
            ->withArgs(function (int $senderId, int $recipientId, int $tenantId, string $channel) use ($creator, $first, $second): bool {
                $cohort = [$creator->id, $first->id, $second->id];

                return $senderId !== $recipientId
                    && in_array($senderId, $cohort, true)
                    && in_array($recipientId, $cohort, true)
                    && $tenantId === $this->testTenantId
                    && $channel === 'group_create';
            })
            ->andReturn($this->denied());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $result = GroupConversationService::createGroup(
            $creator->id,
            [$first->id, $second->id],
            'Blocked group',
        );

        $this->assertNull($result);
        $this->assertSame(
            'VETTING_REQUIRED',
            GroupConversationService::getErrors()[0]['code'] ?? null,
            json_encode(GroupConversationService::getErrors()),
        );
        $this->assertSame($conversationCount, DB::table('conversations')->count());
        $this->assertSame($participantCount, DB::table('conversation_participants')->count());
    }

    public function test_group_rejoin_policy_unavailable_leaves_participant_inactive(): void
    {
        $admin = $this->member();
        $member = $this->member();
        $target = $this->member();
        $conversation = $this->group($admin, [$member]);
        $leftParticipant = ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $target->id,
            'role' => 'member',
            'joined_at' => now()->subDay(),
            'left_at' => now(),
        ]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('evaluateLocalContact')
            ->twice()
            ->andReturn($this->denied(), $this->unavailable());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $result = GroupConversationService::addMember($conversation->id, $target->id, $admin->id);

        $this->assertNull($result);
        $this->assertSame('SAFEGUARDING_POLICY_UNAVAILABLE', GroupConversationService::getErrors()[0]['code'] ?? null);
        $this->assertNotNull($leftParticipant->fresh()?->left_at);
    }

    public function test_direct_marketplace_purchase_denial_writes_no_order_or_delivery_note(): void
    {
        $buyer = $this->member();
        $seller = $this->member();
        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Protected seller item',
            'description' => 'Safeguarding order regression',
            'price' => 10,
            'price_currency' => 'GBP',
            'price_type' => 'fixed',
            'quantity' => 1,
            'shipping_available' => 0,
            'local_pickup' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($buyer->id, $seller->id, $this->testTenantId, 'marketplace_order')
            ->andThrow(new SafeguardingPolicyException('VETTING_REQUIRED', 'Vetting required'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        try {
            MarketplaceOrderService::createDirectPurchase($buyer->id, (int) $listingId, [
                'delivery_notes' => 'This must never reach the protected seller',
            ]);
            $this->fail('Expected safeguarding denial');
        } catch (SafeguardingPolicyException $e) {
            $this->assertSame('VETTING_REQUIRED', $e->reasonCode);
        }

        $this->assertDatabaseMissing('marketplace_orders', [
            'tenant_id' => $this->testTenantId,
            'marketplace_listing_id' => $listingId,
            'buyer_id' => $buyer->id,
        ]);
        $this->assertSame('active', DB::table('marketplace_listings')->where('id', $listingId)->value('status'));
    }

    public function test_group_message_policy_unavailable_returns_503_without_message_write(): void
    {
        $sender = $this->member();
        $recipient = $this->member();
        $conversation = $this->group($sender, [$recipient]);
        Sanctum::actingAs($sender, ['*']);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('evaluateManyLocalContacts')
            ->once()
            ->with($sender->id, [$recipient->id], $this->testTenantId, 'group_message')
            ->andReturn($this->unavailable());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost("/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Must not persist',
        ]);

        $response->assertStatus(503);
        $response->assertJsonPath('errors.0.code', 'SAFEGUARDING_POLICY_UNAVAILABLE');
        $this->assertDatabaseMissing('messages', [
            'tenant_id' => $this->testTenantId,
            'conversation_id' => $conversation->id,
            'body' => 'Must not persist',
        ]);
    }

    public function test_accessible_attachment_attempt_is_denied_before_any_file_is_stored(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $sender = $this->member();
        $recipient = $this->member();
        Sanctum::actingAs($sender, ['*']);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('evaluateLocalContact')
            ->once()
            ->with($sender->id, $recipient->id, $this->testTenantId, 'direct_message')
            ->andReturn($this->denied());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $attachmentDirectory = base_path("httpdocs/uploads/{$this->testTenantId}/message_attachments");
        $before = $this->filesIn($attachmentDirectory);

        $response = $this->post(
            "/{$this->testTenantSlug}/accessible/messages/{$recipient->id}",
            [
                '_token' => csrf_token(),
                'body' => 'Blocked with attachment',
                'attachments' => [UploadedFile::fake()->create('private.pdf', 8, 'application/pdf')],
            ],
        );

        $response->assertRedirect();
        $this->assertStringContainsString('status=message-vetting-required', $response->headers->get('Location') ?? '');
        $this->assertSame($before, $this->filesIn($attachmentDirectory));
        $this->assertDatabaseMissing('messages', [
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'Blocked with attachment',
        ]);
    }

    public function test_accessible_conversation_hides_composer_when_contact_is_restricted(): void
    {
        $sender = $this->member();
        $recipient = $this->member();
        Sanctum::actingAs($sender, ['*']);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('evaluateLocalContact')
            ->once()
            ->with($sender->id, $recipient->id, $this->testTenantId, 'direct_message')
            ->andReturn($this->denied());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->get("/{$this->testTenantSlug}/accessible/messages/{$recipient->id}");

        $response->assertOk();
        $response->assertSee(__('safeguarding.errors.vetting_required_title'));
        $response->assertDontSee('name="attachments[]"', false);
        $response->assertDontSee('name="voice"', false);
        Event::assertNotDispatched(SafeguardingContactAttemptBlocked::class);
    }

    private function member(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        // Some console-mode model listeners deliberately clear their scoped
        // tenant after delivery. Re-establish the request tenant for the direct
        // service calls exercised by this feature test.
        TenantContext::setById($this->testTenantId);

        return $user;
    }

    /** @param list<User> $members */
    private function group(User $admin, array $members): Conversation
    {
        $conversationId = DB::table('conversations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'is_group' => true,
            'group_name' => 'Safeguarding test group',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('conversation_participants')->insert([
            'tenant_id' => $this->testTenantId,
            'conversation_id' => $conversationId,
            'user_id' => $admin->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        foreach ($members as $member) {
            DB::table('conversation_participants')->insert([
                'tenant_id' => $this->testTenantId,
                'conversation_id' => $conversationId,
                'user_id' => $member->id,
                'role' => 'member',
                'joined_at' => now(),
            ]);
        }

        return Conversation::withoutGlobalScopes()->findOrFail($conversationId);
    }

    private function denied(): SafeguardingInteractionDecision
    {
        return new SafeguardingInteractionDecision(
            status: SafeguardingInteractionDecision::DENY,
            code: 'VETTING_REQUIRED',
            recipientTenantId: $this->testTenantId,
            purposeCode: 'safeguarded_member_contact',
            scopeType: 'tenant',
            scopeIdentifier: '',
            policyVersion: 'test-v1',
            requiredAttestationCodes: ['dbs_enhanced'],
            requiredAttestationLabels: ['Enhanced DBS'],
            canRequestCoordinator: true,
        );
    }

    private function allowed(): SafeguardingInteractionDecision
    {
        return new SafeguardingInteractionDecision(
            status: SafeguardingInteractionDecision::ALLOW,
            code: 'ALLOWED',
            recipientTenantId: $this->testTenantId,
            purposeCode: 'safeguarded_member_contact',
            scopeType: 'tenant',
            scopeIdentifier: '',
        );
    }

    private function unavailable(): SafeguardingInteractionDecision
    {
        return new SafeguardingInteractionDecision(
            status: SafeguardingInteractionDecision::UNAVAILABLE,
            code: 'SAFEGUARDING_POLICY_UNAVAILABLE',
            recipientTenantId: $this->testTenantId,
            purposeCode: 'safeguarded_member_contact',
            scopeType: 'tenant',
            scopeIdentifier: '',
            canRequestCoordinator: true,
        );
    }

    /** @return list<string> */
    private function filesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (new \FilesystemIterator($directory) as $file) {
            if ($file->isFile()) {
                $files[] = $file->getFilename();
            }
        }
        sort($files);

        return $files;
    }
}
