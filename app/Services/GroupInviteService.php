<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Core\TenantContext;

/**
 * GroupInviteService — Manages group invitations via email and shareable links.
 */
class GroupInviteService
{
    private array $errors = [];

    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REVOKED = 'revoked';

    const INVITE_EXPIRY_DAYS = 14;
    const MAX_PENDING_INVITES = 50;

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Generate a shareable invite link for a group.
     */
    public function createLink(int $groupId, int $inviterId, int $expiryDays = null): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!$this->canInvite($groupId, $inviterId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to invite members'];
            return null;
        }

        $token = Str::random(40);
        $expiresAt = now()->addDays($expiryDays ?? self::INVITE_EXPIRY_DAYS);

        $id = DB::table('group_invites')->insertGetId([
            'tenant_id' => $tenantId,
            'group_id' => $groupId,
            'invited_by' => $inviterId,
            'invite_type' => 'link',
            'token' => $token,
            'status' => self::STATUS_PENDING,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'token' => $token,
            'invite_url' => $this->buildInviteUrl($token),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Send email invitations to one or more email addresses.
     */
    public function sendEmailInvites(int $groupId, int $inviterId, array $emails, string $message = ''): array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!$this->canInvite($groupId, $inviterId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to invite members'];
            return [];
        }

        $group = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$group) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Group not found'];
            return [];
        }

        $inviter = DB::table('users')->where('id', $inviterId)->first();
        $inviterName = $inviter->name ?? 'A member';

        $results = [];
        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results[] = ['email' => $email, 'status' => 'invalid', 'message' => 'Invalid email'];
                continue;
            }

            // Check if already a member
            $existingUser = DB::table('users')
                ->where('email', $email)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($existingUser) {
                $isMember = DB::table('group_members')
                    ->where('group_id', $groupId)
                    ->where('user_id', $existingUser->id)
                    ->where('status', 'active')
                    ->exists();

                if ($isMember) {
                    $results[] = ['email' => $email, 'status' => 'already_member'];
                    continue;
                }
            }

            // Check for existing pending invite
            $existing = DB::table('group_invites')
                ->where('group_id', $groupId)
                ->where('tenant_id', $tenantId)
                ->where('email', $email)
                ->where('status', self::STATUS_PENDING)
                ->where('expires_at', '>', now())
                ->first();

            if ($existing) {
                $results[] = ['email' => $email, 'status' => 'already_invited'];
                continue;
            }

            $token = Str::random(40);
            $expiresAt = now()->addDays(self::INVITE_EXPIRY_DAYS);

            DB::table('group_invites')->insert([
                'tenant_id' => $tenantId,
                'group_id' => $groupId,
                'invited_by' => $inviterId,
                'invite_type' => 'email',
                'email' => $email,
                'token' => $token,
                'message' => $message ?: null,
                'status' => self::STATUS_PENDING,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $results[] = ['email' => $email, 'status' => 'sent', 'token' => $token];
        }

        return $results;
    }

    /**
     * Accept an invitation by token.
     */
    public function acceptInvite(string $token, int $userId): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $invite = DB::table('group_invites')
            ->where('token', $token)
            ->where('tenant_id', $tenantId)
            ->where('status', self::STATUS_PENDING)
            ->first();

        if (!$invite) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Invite not found or already used'];
            return null;
        }

        if ($invite->expires_at && now()->isAfter($invite->expires_at)) {
            DB::table('group_invites')->where('id', $invite->id)->update(['status' => self::STATUS_EXPIRED]);
            $this->errors[] = ['code' => 'EXPIRED', 'message' => 'This invite has expired'];
            return null;
        }

        // Check if already a member
        $isMember = DB::table('group_members')
            ->where('group_id', $invite->group_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if ($isMember) {
            $this->errors[] = ['code' => 'ALREADY_MEMBER', 'message' => 'You are already a member of this group'];
            return null;
        }

        // Add to group
        DB::table('group_members')->updateOrInsert(
            ['group_id' => $invite->group_id, 'user_id' => $userId],
            ['role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
        );

        // Update cached member count
        DB::table('groups')
            ->where('id', $invite->group_id)
            ->increment('cached_member_count');

        // Mark invite as accepted (only for email invites, links stay active)
        if ($invite->invite_type === 'email') {
            DB::table('group_invites')
                ->where('id', $invite->id)
                ->update(['status' => self::STATUS_ACCEPTED, 'accepted_by' => $userId, 'accepted_at' => now()]);
        }

        $group = DB::table('groups')->where('id', $invite->group_id)->first();

        return [
            'group_id' => $invite->group_id,
            'group_name' => $group->name ?? '',
            'status' => 'joined',
        ];
    }

    /**
     * Get pending invites for a group (admin view).
     */
    public function getPendingInvites(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_invites as gi')
            ->leftJoin('users as u', 'gi.invited_by', '=', 'u.id')
            ->where('gi.group_id', $groupId)
            ->where('gi.tenant_id', $tenantId)
            ->where('gi.status', self::STATUS_PENDING)
            ->where('gi.expires_at', '>', now())
            ->select('gi.*', 'u.name as inviter_name')
            ->orderByDesc('gi.created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Revoke an invite.
     */
    public function revokeInvite(int $inviteId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $affected = DB::table('group_invites')
            ->where('id', $inviteId)
            ->where('tenant_id', $tenantId)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_REVOKED, 'updated_at' => now()]);

        if ($affected === 0) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Invite not found or already processed'];
            return false;
        }

        return true;
    }

    private function canInvite(int $groupId, int $userId, int $tenantId): bool
    {
        return DB::table('group_members')
            ->join('groups', 'groups.id', '=', 'group_members.group_id')
            ->where('group_members.group_id', $groupId)
            ->where('group_members.user_id', $userId)
            ->where('group_members.status', 'active')
            ->where('groups.tenant_id', $tenantId)
            ->exists();
    }

    private function buildInviteUrl(string $token): string
    {
        $basePath = TenantContext::getBasePath();
        return rtrim($basePath, '/') . '/groups/invite/' . $token;
    }
}
