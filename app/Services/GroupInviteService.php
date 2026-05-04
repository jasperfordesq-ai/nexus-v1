<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_invite_forbidden')];
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
    public function sendEmailInvites(int $groupId, int $inviterId, array $emails, string $message = ''): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!$this->canInvite($groupId, $inviterId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_invite_forbidden')];
            return null;
        }

        $group = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$group) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.group_not_found')];
            return null;
        }

        $inviter = DB::table('users')->where('id', $inviterId)->first();
        $inviterName = $inviter->name ?? __('emails.common.fallback_member_name');

        $results = [];
        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results[] = ['email' => $email, 'status' => 'invalid', 'message' => __('api.group_invite_invalid_email')];
                continue;
            }

            // Check if already a member
            $existingUser = DB::table('users')
                ->where('email', $email)
                ->where('tenant_id', $tenantId)
                ->select(['id', 'preferred_language'])
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

            // Send the actual invitation email — render subject, title, greeting,
            // body, and CTA in the recipient's preferred_language when we have
            // an existing user record. For brand-new invitees (no account yet)
            // this resolves to null and falls back to the caller's locale.
            try {
                LocaleContext::withLocale($existingUser ?? null, function () use ($email, $token, $group, $inviterName, $message, $groupId) {
                    $inviteUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/groups/invite/' . $token;
                    $subject   = __('emails_misc.group_invite.email_subject', ['group' => $group->name]);
                    $body      = __('emails_misc.group_invite.email_body', [
                        'inviter' => htmlspecialchars($inviterName, ENT_QUOTES, 'UTF-8'),
                        'group'   => htmlspecialchars($group->name ?? '', ENT_QUOTES, 'UTF-8'),
                    ]);

                    $builder = EmailTemplateBuilder::make()
                        ->title(__('emails_misc.group_invite.email_title'))
                        ->greeting(__('emails_misc.group_invite.email_greeting'))
                        ->paragraph($body);

                    if (!empty($message)) {
                        $builder->paragraph('<em>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</em>');
                    }

                    $html = $builder->button(__('emails_misc.group_invite.email_cta'), $inviteUrl)->render();

                    if (!Mailer::forCurrentTenant()->send($email, $subject, $html)) {
                        Log::warning('[GroupInviteService] invite email failed to send', ['email' => $email, 'group_id' => $groupId]);
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('[GroupInviteService] invite email error: ' . $e->getMessage(), ['email' => $email]);
            }

            $results[] = ['email' => $email, 'status' => 'sent'];
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
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.group_invite_not_found')];
            return null;
        }

        if ($invite->expires_at && now()->isAfter($invite->expires_at)) {
            DB::table('group_invites')->where('id', $invite->id)->update(['status' => self::STATUS_EXPIRED]);
            $this->errors[] = ['code' => 'EXPIRED', 'message' => __('api.group_invite_expired')];
            return null;
        }

        $group = DB::table('groups')
            ->where('id', $invite->group_id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$group) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.group_not_found')];
            return null;
        }

        // Check if already a member
        $membership = DB::table('group_members')
            ->where('group_id', $invite->group_id)
            ->where('user_id', $userId)
            ->first();

        if (($membership->status ?? null) === 'banned') {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_banned')];
            return null;
        }

        if (($membership->status ?? null) === 'active') {
            $this->errors[] = ['code' => 'ALREADY_MEMBER', 'message' => __('api.group_invite_already_member')];
            return null;
        }

        // Add to group
        DB::table('group_members')->updateOrInsert(
            ['group_id' => $invite->group_id, 'user_id' => $userId],
            ['tenant_id' => $tenantId, 'role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
        );

        // Update cached member count
        if (!$membership || ($membership->status ?? null) !== 'active') {
            DB::table('groups')
                ->where('id', $invite->group_id)
                ->where('tenant_id', $tenantId)
                ->increment('cached_member_count');
        }

        // Mark invite as accepted (only for email invites, links stay active)
        if ($invite->invite_type === 'email') {
            DB::table('group_invites')
                ->where('id', $invite->id)
                ->update(['status' => self::STATUS_ACCEPTED, 'accepted_by' => $userId, 'accepted_at' => now()]);
        }

        return [
            'group_id' => $invite->group_id,
            'group_name' => $group->name ?? '',
            'status' => 'joined',
        ];
    }

    /**
     * Get pending invites for a group (admin view).
     */
    public function getPendingInvites(int $groupId, int $userId): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!GroupService::canModify($groupId, $userId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_invite_forbidden')];
            return null;
        }

        return DB::table('group_invites as gi')
            ->leftJoin('users as u', function ($join) use ($tenantId) {
                $join->on('gi.invited_by', '=', 'u.id')
                    ->where('u.tenant_id', '=', $tenantId);
            })
            ->where('gi.group_id', $groupId)
            ->where('gi.tenant_id', $tenantId)
            ->where('gi.status', self::STATUS_PENDING)
            ->where('gi.expires_at', '>', now())
            ->select(
                'gi.id',
                'gi.invite_type',
                'gi.email',
                'gi.status',
                'gi.expires_at',
                'gi.invited_by',
                'gi.created_at',
                'u.name as inviter_name'
            )
            ->orderByDesc('gi.created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Revoke an invite.
     */
    public function revokeInvite(int $groupId, int $inviteId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!GroupService::canModify($groupId, $userId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_invite_forbidden')];
            return false;
        }

        $affected = DB::table('group_invites')
            ->where('id', $inviteId)
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_REVOKED, 'updated_at' => now()]);

        if ($affected === 0) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.group_invite_revoke_not_found')];
            return false;
        }

        return true;
    }

    private function canInvite(int $groupId, int $userId, int $tenantId): bool
    {
        return GroupService::canModify($groupId, $userId);
    }

    private function buildInviteUrl(string $token): string
    {
        $basePath = TenantContext::getBasePath();
        return rtrim($basePath, '/') . '/groups/invite/' . $token;
    }
}
