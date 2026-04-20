<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\GroupMemberJoined;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies the group owner when a new member joins their group.
 */
class NotifyGroupMemberJoined implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(GroupMemberJoined $event): void
    {
        try {
            TenantContext::setById($event->tenantId);

            // Load the group and its owner_id
            $group = DB::table('groups')
                ->where('id', $event->groupId)
                ->where('tenant_id', $event->tenantId)
                ->select(['owner_id', 'name'])
                ->first();

            if (!$group || !$group->owner_id) {
                return;
            }

            $ownerId = (int) $group->owner_id;

            // Don't notify the owner if they joined their own group
            if ($ownerId === $event->userId) {
                return;
            }

            // Load the joining member's name
            $joiner = DB::table('users')
                ->where('id', $event->userId)
                ->where('tenant_id', $event->tenantId)
                ->select(['first_name', 'last_name', 'name'])
                ->first();

            if (!$joiner) {
                return;
            }

            $joinerName = trim(($joiner->first_name ?? '') . ' ' . ($joiner->last_name ?? ''))
                ?: ($joiner->name ?? __('emails.common.fallback_someone'));

            $groupName = $group->name ?? '';
            $content = __('notifications.group_new_member', ['name' => $joinerName, 'group' => $groupName]);
            $link = '/groups/' . $event->groupId;

            // dispatch() creates the in-app bell notification AND queues the email
            // (respects the owner's global notification frequency preference)
            \App\Services\NotificationDispatcher::dispatch(
                $ownerId,
                'global',
                null,
                'group_member_joined',
                $content,
                $link,
                null
            );
        } catch (\Throwable $e) {
            Log::error('NotifyGroupMemberJoined listener failed', [
                'group_id'  => $event->groupId,
                'user_id'   => $event->userId,
                'tenant_id' => $event->tenantId,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
        } finally {
            TenantContext::reset(); // Prevent context leaking to next queued job
        }
    }
}
