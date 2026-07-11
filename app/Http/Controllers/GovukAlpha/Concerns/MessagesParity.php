<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Services\GroupConversationService;
use App\Services\MessageService;
use App\Services\SearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Messages — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * This trait closes the biggest messages gap: GROUP messaging (create group,
 * invite/remove members, group conversation view, reply, emoji reactions). The
 * no-JS path mirrors the React CreateGroupModal + group ConversationPage and
 * calls the SAME service the React API controller (GroupConversationController)
 * calls — GroupConversationService — so no group/auth logic is reimplemented.
 */
trait MessagesParity
{
    /**
     * Fixed, no-JS-friendly emoji row for group reactions. The architecture
     * rules permit a fixed HTML emoji row (only the interactive PICKER is
     * excluded). These are the same six the React MessageBubble exposes.
     *
     * @var array<int, string>
     */
    private static array $messagesReactionEmojis = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    /**
     * GET /messages/groups — list the current member's group conversations.
     * Mirrors GroupConversationController::index() (GET /v2/conversations/groups).
     */
    public function messagesGroupsIndex(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $groups = [];
        $error = null;
        try {
            $groups = GroupConversationService::getUserGroups($userId);
        } catch (\Throwable $e) {
            report($e);
            $error = __('govuk_alpha.states.error_title');
        }

        return $this->view('accessible-frontend::messages-groups', [
            'title' => __('govuk_alpha_messages.groups.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'messages',
            'groups' => $groups,
            'currentUserId' => $userId,
            'directMessagingEnabled' => \App\Services\BrokerControlConfigService::isDirectMessagingEnabled(),
            'restriction' => app(\App\Services\BrokerMessageVisibilityService::class)->getUserRestrictionStatus($userId),
            'status' => $this->messagesAllowedStatus($request),
            'error' => $error,
        ]);
    }

    /**
     * GET /messages/groups/new — render the create-group form. An inline member
     * search (no-JS, GET ?q=) mirrors the React CreateGroupModal recipient picker.
     */
    public function messagesCreateGroupForm(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);
        abort_unless(TenantContext::hasFeature('connections'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $directMessagingEnabled = \App\Services\BrokerControlConfigService::isDirectMessagingEnabled();
        $restriction = app(\App\Services\BrokerMessageVisibilityService::class)->getUserRestrictionStatus($userId);

        $searchQuery = trim(self::asStr($request->query('q')));
        $searchResults = [];
        if ($searchQuery !== '') {
            $searchResults = $this->messagesGroupMemberSearch($searchQuery, $userId);
        }

        // Members chosen so far are carried in the URL as repeated members[] so the
        // picker survives a no-JS search round-trip. Resolve their display names.
        $selectedIds = $this->messagesNormaliseMemberIds($request->query('members', []), $userId);
        $selectedMembers = $this->messagesResolveMembers($selectedIds);

        return $this->view('accessible-frontend::messages-group-create', [
            'title' => __('govuk_alpha_messages.create.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'messages',
            'searchQuery' => $searchQuery,
            'searchResults' => $searchResults,
            'selectedMembers' => $selectedMembers,
            'selectedIds' => $selectedIds,
            'groupName' => trim(self::asStr($request->query('name'))),
            'directMessagingEnabled' => $directMessagingEnabled,
            'restriction' => $restriction,
            'status' => $this->messagesAllowedStatus($request),
            'currentUserId' => $userId,
        ]);
    }

    /**
     * POST /messages/groups — create a group conversation.
     * Mirrors GroupConversationController::store() (POST /v2/conversations/groups).
     */
    public function messagesStoreGroup(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);
        abort_unless(TenantContext::hasFeature('connections'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $name = trim(self::asStr($request->input('name')));
        $memberIds = $this->messagesNormaliseMemberIds($request->input('member_ids', []), $userId);

        $backToForm = fn (string $status): RedirectResponse => redirect()->route('govuk-alpha.messages.groups.create', array_filter([
            'tenantSlug' => $tenantSlug,
            'name' => $name !== '' ? $name : null,
            'members' => $memberIds,
            'status' => $status,
        ]));

        if (!\App\Services\BrokerControlConfigService::isDirectMessagingEnabled()) {
            return $backToForm('group-disabled');
        }

        $result = GroupConversationService::createGroup($userId, $memberIds, $name);

        if ($result === null) {
            $code = GroupConversationService::getErrors()[0]['code'] ?? 'VALIDATION_ERROR';
            return $backToForm($this->messagesSafeguardingStatus($code) ?? 'group-create-failed');
        }

        return redirect()->route('govuk-alpha.messages.groups.show', [
            'tenantSlug' => $tenantSlug,
            'conversationId' => (int) ($result['id'] ?? 0),
            'status' => 'group-created',
        ]);
    }

    /**
     * GET /messages/groups/{conversationId} — view a group conversation.
     * Mirrors GroupConversationController::messages() + participants().
     */
    public function messagesGroupShow(Request $request, string $tenantSlug, int $conversationId): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $result = GroupConversationService::getGroupMessages($conversationId, $userId, [
            'limit' => 50,
            'cursor' => self::asStr($request->query('cursor')) ?: null,
        ]);

        if ($result === null) {
            // FORBIDDEN (not a member) and NOT_FOUND both resolve to 404 on the
            // no-JS surface — we never reveal a group the viewer cannot see.
            abort(404);
        }

        $participants = GroupConversationService::getParticipants($conversationId, $userId);
        $viewerRole = 'member';
        if ($participants !== null) {
            foreach ($participants as $p) {
                if ((int) ($p['id'] ?? 0) === $userId) {
                    $viewerRole = self::asStr($p['role'] ?? 'member');
                    break;
                }
            }
        }

        // Reaction counts for the rendered messages (best-effort). The blade does
        // the ?q server-side search filtering on the full message list.
        $messages = array_reverse($result['items'] ?? []);
        $messageIds = array_map(static fn (array $m): int => (int) ($m['id'] ?? 0), $messages);
        $reactions = $this->messagesReactionCounts($messageIds, $userId);

        return $this->view('accessible-frontend::messages-group-conversation', [
            'title' => __('govuk_alpha_messages.conversation.title', [
                'name' => $result['conversation']['group_name'] ?? __('govuk_alpha_messages.groups.untitled'),
            ]),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'messages',
            'conversation' => $result['conversation'],
            'messages' => $messages,
            'meta' => ['has_more' => (bool) ($result['has_more'] ?? false), 'cursor' => $result['cursor'] ?? null],
            'participants' => $participants !== null ? $participants->all() : [],
            'viewerRole' => $viewerRole,
            'reactions' => $reactions,
            'reactionEmojis' => self::$messagesReactionEmojis,
            'currentUserId' => $userId,
            'directMessagingEnabled' => \App\Services\BrokerControlConfigService::isDirectMessagingEnabled(),
            'restriction' => app(\App\Services\BrokerMessageVisibilityService::class)->getUserRestrictionStatus($userId),
            'safeguarding' => $result['safeguarding'] ?? null,
            'searchQuery' => trim(self::asStr($request->query('q'))),
            'status' => $this->messagesAllowedStatus($request),
        ]);
    }

    /**
     * POST /messages/groups/{conversationId} — send a message to the group.
     * Mirrors GroupConversationController::sendMessage().
     */
    public function messagesStoreGroupMessage(Request $request, string $tenantSlug, int $conversationId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $back = fn (string $status): RedirectResponse => redirect()->route('govuk-alpha.messages.groups.show', [
            'tenantSlug' => $tenantSlug, 'conversationId' => $conversationId, 'status' => $status,
        ]);

        $body = trim(self::asStr($request->input('body')));
        if ($body === '') {
            return $back('group-message-empty');
        }
        if (mb_strlen($body) > 10000) {
            return $back('group-message-too-long');
        }
        if (!\App\Services\BrokerControlConfigService::isDirectMessagingEnabled()) {
            return $back('group-disabled');
        }

        $result = GroupConversationService::sendGroupMessage($conversationId, $userId, $body);

        if ($result === null) {
            $code = GroupConversationService::getErrors()[0]['code'] ?? 'VALIDATION_ERROR';
            return $back(
                $this->messagesSafeguardingStatus($code)
                    ?? ($code === 'NOT_FOUND' ? 'group-message-failed' : 'group-message-forbidden')
            );
        }

        return $back('group-message-sent');
    }

    /**
     * POST /messages/groups/{conversationId}/members — add a member (admin only).
     * Mirrors GroupConversationController::addParticipant().
     */
    public function messagesGroupAddMember(Request $request, string $tenantSlug, int $conversationId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $back = fn (string $status): RedirectResponse => redirect()->route('govuk-alpha.messages.groups.show', [
            'tenantSlug' => $tenantSlug, 'conversationId' => $conversationId, 'status' => $status,
        ]);

        $targetUserId = (int) $request->input('user_id');
        if ($targetUserId <= 0) {
            return $back('group-member-invalid');
        }

        $result = GroupConversationService::addMember($conversationId, $targetUserId, $userId);

        if ($result === null) {
            $code = GroupConversationService::getErrors()[0]['code'] ?? 'VALIDATION_ERROR';
            $safeguardingStatus = $this->messagesSafeguardingStatus($code);
            if ($safeguardingStatus !== null) {
                return $back($safeguardingStatus);
            }
            return $back(match ($code) {
                'FORBIDDEN'      => 'group-member-forbidden',
                'NOT_FOUND'      => 'group-member-not-found',
                'LIMIT_EXCEEDED' => 'group-member-limit',
                default          => 'group-member-failed',
            });
        }

        return $back('group-member-added');
    }

    /**
     * POST /messages/groups/{conversationId}/members/{targetUserId}/remove —
     * remove a member (admin) or leave the group (self).
     * Mirrors GroupConversationController::removeParticipant().
     */
    public function messagesGroupRemoveMember(string $tenantSlug, int $conversationId, int $targetUserId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $isSelfLeave = ($targetUserId === $userId);
        $success = GroupConversationService::removeMember($conversationId, $targetUserId, $userId);

        if ($isSelfLeave) {
            // After leaving, the viewer no longer has access — go back to the group list.
            return redirect()->route('govuk-alpha.messages.groups.index', [
                'tenantSlug' => $tenantSlug,
                'status' => $success ? 'group-left' : 'group-leave-failed',
            ]);
        }

        $status = 'group-member-removed';
        if (!$success) {
            $code = GroupConversationService::getErrors()[0]['code'] ?? 'VALIDATION_ERROR';
            $status = $code === 'FORBIDDEN' ? 'group-member-forbidden' : 'group-member-failed';
        }

        return redirect()->route('govuk-alpha.messages.groups.show', [
            'tenantSlug' => $tenantSlug, 'conversationId' => $conversationId, 'status' => $status,
        ]);
    }

    /**
     * POST /messages/groups/{conversationId}/m/{messageId}/react — toggle an
     * emoji reaction on a group message. Mirrors POST /v2/messages/{id}/reactions
     * by calling MessageService::toggleReaction (which permits group participants).
     */
    public function messagesToggleReaction(Request $request, string $tenantSlug, int $conversationId, int $messageId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $emoji = self::asStr($request->input('emoji'));
        $back = fn (string $status): RedirectResponse => redirect()->route('govuk-alpha.messages.groups.show', [
            'tenantSlug' => $tenantSlug, 'conversationId' => $conversationId, 'status' => $status,
        ])->withFragment('m-' . $messageId);

        if (!in_array($emoji, self::$messagesReactionEmojis, true)) {
            return $back('reaction-invalid');
        }

        $result = MessageService::toggleReaction($messageId, $userId, $emoji);

        if ($result === null) {
            $code = MessageService::getErrors()[0]['code'] ?? 'NOT_FOUND';
            return $back($code === 'FORBIDDEN' ? 'reaction-forbidden' : 'reaction-failed');
        }

        return $back($result ? 'reaction-added' : 'reaction-removed');
    }

    /**
     * POST /messages/{userId}/m/{messageId}/translate — translate one 1-to-1
     * message into the viewer's current UI language (no JS). Mirrors
     * MessagesController::translateTranscript() (POST /v2/messages/{id}/translate)
     * by calling the SAME backing service, TranscriptionService::translate, with
     * identical sender/receiver authorisation and transcript-or-body selection.
     *
     * The translated text is flashed to the session and rendered inline below the
     * original message when the conversation re-renders, anchored to that message.
     */
    public function messagesTranslateMessage(string $tenantSlug, int $userId, int $messageId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $viewerId = $this->currentUserId();
        if ($viewerId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $back = fn (string $status): RedirectResponse => redirect()->route('govuk-alpha.messages.show', [
            'tenantSlug' => $tenantSlug, 'userId' => $userId, 'status' => $status,
        ])->withFragment('m-' . $messageId);

        // Feature gate mirrors the React MessageBubble (hasFeature('message_translation')).
        if (!TenantContext::hasFeature('message_translation')) {
            return $back('translate-unavailable');
        }

        // Viewer must be the sender or receiver of the message (tenant-scoped).
        $message = DB::table('messages')
            ->where('id', $messageId)
            ->where('tenant_id', TenantContext::getId())
            ->where(function ($q) use ($viewerId) {
                $q->where('sender_id', $viewerId)->orWhere('receiver_id', $viewerId);
            })
            ->first();

        if ($message === null) {
            return $back('translate-failed');
        }

        // Prefer a voice transcript when present, else the text body (same order
        // the API uses). A deleted message has no translatable content.
        $sourceText = '';
        $fromLanguage = 'auto';
        if (!empty($message->transcript)) {
            $sourceText = (string) $message->transcript;
            $fromLanguage = self::asStr($message->transcript_language ?? '') ?: 'auto';
        } elseif (empty($message->is_deleted) && !empty($message->body)) {
            $sourceText = (string) $message->body;
        }

        if (trim($sourceText) === '') {
            return $back('translate-empty');
        }

        // Target the viewer's current UI language base (mirrors userLangBase).
        $target = explode('-', (string) app()->getLocale())[0] ?: 'en';

        $translated = null;
        try {
            $translated = \App\Services\TranscriptionService::translate($sourceText, $fromLanguage, $target);
        } catch (\Throwable $e) {
            report($e);
            $translated = null;
        }

        if ($translated === null || trim($translated) === '') {
            return $back('translate-failed');
        }

        // Flash the translation; the conversation blade reads it back and renders
        // it inline under the matching message (parity with the React bubble).
        session()->flash('messages_translation', [
            'id'     => $messageId,
            'text'   => $translated,
            'target' => $target,
        ]);

        return $back('translate-done');
    }

    // ----------------------------------------------------------------------
    //  Private helpers (module-prefixed; not exposed as routes)
    // ----------------------------------------------------------------------

    /**
     * Whitelist the ?status flash so a hand-crafted value cannot reach the view.
     */
    private function messagesAllowedStatus(Request $request): ?string
    {
        $allowed = [
            'group-created', 'group-disabled', 'group-create-failed',
            'group-message-sent', 'group-message-empty', 'group-message-too-long',
            'group-message-failed', 'group-message-forbidden',
            'group-member-added', 'group-member-removed', 'group-member-invalid',
            'group-member-forbidden', 'group-member-not-found', 'group-member-limit',
            'group-member-failed', 'group-left', 'group-leave-failed',
            'group-vetting-required', 'group-contact-restricted', 'group-policy-unavailable',
            'reaction-added', 'reaction-removed', 'reaction-invalid',
            'reaction-forbidden', 'reaction-failed',
        ];
        $status = self::asStr($request->query('status'));
        return in_array($status, $allowed, true) ? $status : null;
    }

    private function messagesSafeguardingStatus(string $code): ?string
    {
        return match ($code) {
            'VETTING_REQUIRED' => 'group-vetting-required',
            'SAFEGUARDING_CONTACT_RESTRICTED' => 'group-contact-restricted',
            'SAFEGUARDING_POLICY_UNAVAILABLE' => 'group-policy-unavailable',
            default => null,
        };
    }

    /**
     * Normalise a members payload (query array or comma list) to a unique list of
     * positive ints, excluding the current user.
     *
     * @param mixed $raw
     * @return array<int, int>
     */
    private function messagesNormaliseMemberIds(mixed $raw, int $selfId): array
    {
        if (is_string($raw)) {
            $raw = array_filter(explode(',', $raw), static fn ($v) => trim((string) $v) !== '');
        }
        if (!is_array($raw)) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $v): bool => $v > 0)));
        $ids = array_values(array_diff($ids, [$selfId]));
        // Hard cap mirrors the service (max 49 others); keep the URL bounded.
        return array_slice($ids, 0, 49);
    }

    /**
     * Resolve display names for selected member ids (tenant-scoped).
     *
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function messagesResolveMembers(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        try {
            return DB::table('users')
                ->where('tenant_id', TenantContext::getId())
                ->whereIn('id', $ids)
                ->select(
                    'id',
                    DB::raw("CASE WHEN profile_type = 'organisation' AND organization_name IS NOT NULL AND organization_name != '' THEN organization_name ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) END as name"),
                    'avatar_url'
                )
                ->get()
                ->map(static fn ($r): array => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /**
     * Tenant-scoped member search for the group recipient picker (excludes self,
     * honours the search opt-out). Mirrors the 1-to-1 messageUserSearch helper.
     *
     * @return array<int, array<string, mixed>>
     */
    private function messagesGroupMemberSearch(string $query, int $viewerId): array
    {
        try {
            $tenantId = TenantContext::getId();
            $ids = SearchService::searchUsersStatic($query, $tenantId);
            if (!is_array($ids) || empty($ids)) {
                return [];
            }
            $ids = array_slice(array_map('intval', $ids), 0, 10);

            return DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('id', '!=', $viewerId)
                ->where(function ($q) {
                    $q->where('privacy_search', 1)->orWhereNull('privacy_search');
                })
                ->whereIn('id', $ids)
                ->select(
                    'id',
                    DB::raw("CASE WHEN profile_type = 'organisation' AND organization_name IS NOT NULL AND organization_name != '' THEN organization_name ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) END as name"),
                    'location'
                )
                ->get()
                ->map(static fn ($r): array => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /**
     * Aggregate reaction counts per message and flag which the viewer reacted
     * with, from the message_reactions table (tenant-scoped via the message join).
     *
     * @param array<int, int> $messageIds
     * @return array<int, array{counts: array<string, int>, mine: array<int, string>}>
     */
    private function messagesReactionCounts(array $messageIds, int $viewerId): array
    {
        $messageIds = array_values(array_filter(array_map('intval', $messageIds), static fn (int $v): bool => $v > 0));
        if (empty($messageIds)) {
            return [];
        }

        $out = [];
        try {
            $rows = DB::table('message_reactions')
                ->whereIn('message_id', $messageIds)
                ->where('tenant_id', TenantContext::getId())
                ->select('message_id', 'emoji', 'user_id')
                ->get();

            foreach ($rows as $row) {
                $mid = (int) $row->message_id;
                $emoji = (string) $row->emoji;
                if (!isset($out[$mid])) {
                    $out[$mid] = ['counts' => [], 'mine' => []];
                }
                $out[$mid]['counts'][$emoji] = ($out[$mid]['counts'][$emoji] ?? 0) + 1;
                if ((int) $row->user_id === $viewerId) {
                    $out[$mid]['mine'][] = $emoji;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $out;
    }
}
