<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| All channels are tenant-scoped to prevent cross-tenant data leakage.
| The channel name format is: tenant.{tenantId}.resource.{resourceId}
|
*/

// Private user notification channel — user can only listen to their own
Broadcast::channel('tenant.{tenantId}.user.{userId}', function (User $user, int $tenantId, int $userId) {
    return $user->id === $userId && $user->tenant_id === $tenantId;
});

// Private conversation channel — both participants can listen
Broadcast::channel('tenant.{tenantId}.conversation.{conversationId}', function (User $user, int $tenantId, int $conversationId) {
    if ($user->tenant_id !== $tenantId) {
        return false;
    }
    return \Illuminate\Support\Facades\DB::table('messages')
        ->where('tenant_id', $tenantId)
        ->where('id', $conversationId)
        ->where(function ($q) use ($user) {
            $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
        })
        ->exists();
});

// Private group channel — group members only
Broadcast::channel('tenant.{tenantId}.group.{groupId}', function (User $user, int $tenantId, int $groupId) {
    if ($user->tenant_id !== $tenantId) {
        return false;
    }
    return \Illuminate\Support\Facades\DB::table('group_members')
        ->where('group_id', $groupId)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists();
});

// Presence channel for online members in a tenant
Broadcast::channel('tenant.{tenantId}.presence', function (User $user, int $tenantId) {
    if ($user->tenant_id !== $tenantId) {
        return false;
    }
    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
    ];
});
