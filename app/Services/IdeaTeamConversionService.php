<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Exceptions\SafeguardingPolicyException;
use App\I18n\LocaleContext;
use App\Models\IdeaTeamLink;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IdeaTeamConversionService — Converts winning/shortlisted ideas into groups (teams).
 *
 * When an idea is selected for implementation, this service creates a new group
 * and records the idea-to-group link in the idea_team_links table. It also sets
 * source_idea_id and source_challenge_id on the groups row for traceability.
 *
 * All queries are tenant-scoped.
 */
class IdeaTeamConversionService
{
    /** @var array<int, array{code: string, message: string, field?: string}> */
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function clearErrors(): void
    {
        $this->errors = [];
    }

    private function addError(string $code, string $message, ?string $field = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }
        $this->errors[] = $error;
    }

    /**
     * Convert an idea into a group/team.
     *
     * Creates a new group with the idea's title/description, links them via
     * idea_team_links, sets source columns on the group, and adds the
     * converting user + idea author as group members.
     *
     * @param int   $ideaId  The challenge_ideas.id to convert
     * @param int   $userId  The user performing the conversion
     * @param array $options Optional overrides: 'name', 'description', 'visibility'
     * @return array|null The created link data, or null on failure
     */
    public function convert(int $ideaId, int $userId, array $options = []): ?array
    {
        $this->clearErrors();

        $tenantId = TenantContext::getId();

        // Load idea with its challenge, verifying tenant ownership
        $idea = DB::table('challenge_ideas as ci')
            ->join('ideation_challenges as ic', 'ic.id', '=', 'ci.challenge_id')
            ->where('ci.id', $ideaId)
            ->where('ic.tenant_id', $tenantId)
            ->select('ci.id', 'ci.title', 'ci.description', 'ci.user_id', 'ci.challenge_id', 'ci.status')
            ->first();

        if (!$idea) {
            $this->addError('RESOURCE_NOT_FOUND', __('api.idea_not_found'));
            return null;
        }

        // Only the idea author, challenge creator, or an admin can convert
        $challenge = DB::table('ideation_challenges')
            ->where('id', $idea->challenge_id)
            ->where('tenant_id', $tenantId)
            ->first(['user_id']);

        $isAuthor = (int) $idea->user_id === $userId;
        $isChallengeOwner = $challenge && (int) $challenge->user_id === $userId;
        $isAdmin = $this->isAdmin($userId);

        if (!$isAuthor && !$isChallengeOwner && !$isAdmin) {
            $this->addError('RESOURCE_FORBIDDEN', __('api.idea_team_conversion_forbidden'));
            return null;
        }

        // Check if already converted
        $existingLink = IdeaTeamLink::where('idea_id', $ideaId)->first();
        if ($existingLink) {
            $this->addError('RESOURCE_CONFLICT', __('api.idea_team_conversion_conflict'));
            return null;
        }

        $groupName = trim($options['name'] ?? $idea->title);
        $groupDescription = trim($options['description'] ?? $idea->description ?? '');
        $visibility = $options['visibility'] ?? 'public';
        if (!in_array($visibility, ['public', 'private', 'secret'])) {
            $visibility = 'public';
        }

        try {
            return DB::transaction(function () use ($ideaId, $userId, $tenantId, $idea, $groupName, $groupDescription, $visibility) {
                if ((int) $idea->user_id !== $userId) {
                    $policy = app(SafeguardingInteractionPolicy::class);
                    $policy->assertLocalContactAllowed(
                        $userId,
                        (int) $idea->user_id,
                        (int) $tenantId,
                        'idea_team_conversion',
                    );
                    $policy->assertLocalContactAllowed(
                        (int) $idea->user_id,
                        $userId,
                        (int) $tenantId,
                        'idea_team_conversion',
                    );
                }

                // Route the conversion through the same creation limits,
                // approval policy, lifecycle mirror, audit, and events used by
                // the normal Groups API. This prevents background workflows
                // from creating lifecycle-less rows that bypass tenant policy.
                $group = GroupService::createFromIdea(
                    $userId,
                    [
                        'name' => $groupName,
                        'description' => $groupDescription,
                        'visibility' => $visibility,
                    ],
                    $ideaId,
                    (int) $idea->challenge_id,
                );
                if ($group === null) {
                    $this->errors = GroupService::getErrors();
                    if ($this->errors === []) {
                        $this->addError(
                            'GROUP_CREATE_FAILED',
                            __('api.idea_team_conversion_group_create_failed'),
                        );
                    }

                    return null;
                }

                $groupId = (int) $group->id;

                // Record the conversion link
                $link = IdeaTeamLink::create([
                    'idea_id'      => $ideaId,
                    'group_id'     => $groupId,
                    'challenge_id' => $idea->challenge_id,
                    'converted_by' => $userId,
                ]);

                // GroupService already adds the converter as canonical owner.
                // If the idea author is different, add them as an active member.
                if ((int) $idea->user_id !== $userId) {
                    DB::table('group_members')->insertOrIgnore([
                        'tenant_id'  => $tenantId,
                        'group_id'   => $groupId,
                        'user_id'    => $idea->user_id,
                        'role'       => 'member',
                        'status'     => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Update cached member count
                    DB::table('groups')
                        ->where('tenant_id', $tenantId)
                        ->where('id', $groupId)
                        ->update(['cached_member_count' => 2]);

                    // Notify the idea author that they've been added to the
                    // new group — render under their preferred_language since
                    // the converter and idea author often differ in locale.
                    try {
                        $ideaAuthor = User::find((int) $idea->user_id);
                        LocaleContext::withLocale($ideaAuthor, function () use ($idea, $groupName, $groupId) {
                            Notification::createNotification(
                                (int) $idea->user_id,
                                __('svc_notifications.idea_team.added_to_group', ['group' => $groupName]),
                                "/groups/{$groupId}",
                                'group_added'
                            );
                            \App\Services\NotificationDispatcher::fanOutPush((int) ($idea->user_id), 'group_added', __('svc_notifications.idea_team.added_to_group', ['group' => $groupName]), "/groups/{$groupId}");
                        });
                    } catch (\Throwable $e) {
                        Log::warning('IdeaTeamConversionService: failed to notify added user', [
                            'user_id' => $idea->user_id,
                            'group_id' => $groupId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                return [
                    'id'           => (int) $link->id,
                    'idea_id'      => $ideaId,
                    'group_id'     => (int) $groupId,
                    'challenge_id' => (int) $idea->challenge_id,
                    'converted_by' => $userId,
                    'converted_at' => $link->converted_at ?? now()->toDateTimeString(),
                    'group'        => [
                        'id'          => (int) $groupId,
                        'name'        => $groupName,
                        'description' => $groupDescription,
                        'visibility'  => $visibility,
                    ],
                ];
            });
        } catch (SafeguardingPolicyException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Idea-to-team conversion failed: ' . $e->getMessage(), [
                'idea_id' => $ideaId,
                'user_id' => $userId,
            ]);
            $this->addError('SERVER_INTERNAL_ERROR', __('api.idea_team_conversion_failed'));
            return null;
        }
    }

    /**
     * Get all idea-to-team links for a given challenge.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLinksForChallenge(int $challengeId, ?int $viewerId = null): array
    {
        $tenantId = TenantContext::getId();

        $query = DB::table('idea_team_links as itl')
            ->join('groups as g', 'g.id', '=', 'itl.group_id')
            ->join('challenge_ideas as ci', 'ci.id', '=', 'itl.idea_id')
            ->where('itl.challenge_id', $challengeId)
            ->where('itl.tenant_id', $tenantId)
            ->select(
                'itl.id',
                'itl.idea_id',
                'itl.group_id',
                'itl.challenge_id',
                'itl.converted_by',
                'itl.converted_at',
                'ci.title as idea_title',
                'g.name as group_name',
                'g.cached_member_count as group_member_count',
            );

        if ($viewerId !== null && !$this->canViewAllLinksForChallenge($challengeId, $tenantId, $viewerId)) {
            $query->where(function ($q) use ($viewerId): void {
                $q->where('ci.user_id', $viewerId)
                    ->orWhere('itl.converted_by', $viewerId)
                    ->orWhereExists(function ($sub) use ($viewerId): void {
                        $sub->selectRaw('1')
                            ->from('group_members as gm')
                            ->whereColumn('gm.group_id', 'itl.group_id')
                            ->where('gm.user_id', $viewerId)
                            ->where('gm.status', 'active');
                    });
            });
        }

        return $query->orderByDesc('itl.converted_at')
            ->get()
            ->map(fn ($row) => [
                'id'                 => (int) $row->id,
                'idea_id'            => (int) $row->idea_id,
                'idea_title'         => $row->idea_title,
                'group_id'           => (int) $row->group_id,
                'group_name'         => $row->group_name,
                'group_member_count' => (int) $row->group_member_count,
                'challenge_id'       => (int) $row->challenge_id,
                'converted_by'       => (int) $row->converted_by,
                'converted_at'       => $row->converted_at,
            ])
            ->all();
    }

    private function canViewAllLinksForChallenge(int $challengeId, int $tenantId, int $viewerId): bool
    {
        if ($this->isAdmin($viewerId)) {
            return true;
        }

        return DB::table('ideation_challenges')
            ->where('id', $challengeId)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $viewerId)
            ->exists();
    }

    /**
     * Get the team link for a specific idea.
     *
     * @return array<string, mixed>|null
     */
    public function getLinkForIdea(int $ideaId): ?array
    {
        $tenantId = TenantContext::getId();

        $row = DB::table('idea_team_links as itl')
            ->join('groups as g', 'g.id', '=', 'itl.group_id')
            ->where('itl.idea_id', $ideaId)
            ->where('itl.tenant_id', $tenantId)
            ->select(
                'itl.id',
                'itl.idea_id',
                'itl.group_id',
                'itl.challenge_id',
                'itl.converted_by',
                'itl.converted_at',
                'g.name as group_name',
                'g.cached_member_count as group_member_count',
            )
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'id'                 => (int) $row->id,
            'idea_id'            => (int) $row->idea_id,
            'group_id'           => (int) $row->group_id,
            'group_name'         => $row->group_name,
            'group_member_count' => (int) $row->group_member_count,
            'challenge_id'       => (int) $row->challenge_id,
            'converted_by'       => (int) $row->converted_by,
            'converted_at'       => $row->converted_at,
        ];
    }

    private function isAdmin(int $userId): bool
    {
        $tenantId = TenantContext::getId();
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first(['role']);

        return $user && in_array($user->role ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin']);
    }
}
