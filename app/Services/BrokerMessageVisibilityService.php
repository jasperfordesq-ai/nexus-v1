<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\BrokerMessageCopy;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BrokerMessageVisibilityService
 *
 * Manages broker visibility into member messages for compliance and safeguarding.
 * Copies messages to a broker review queue based on configurable criteria.
 *
 * All queries are tenant-scoped via TenantContext::getId() or HasTenantScope.
 */
class BrokerMessageVisibilityService
{
    public const REASON_FIRST_CONTACT = 'first_contact';
    public const REASON_HIGH_RISK_LISTING = 'high_risk_listing';
    public const REASON_NEW_MEMBER = 'new_member';
    public const REASON_FLAGGED_USER = 'flagged_user';
    public const REASON_MONITORING = 'random_sample';

    private BrokerControlConfigService $configService;

    public function __construct(BrokerControlConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Check if a message should be copied for broker review.
     *
     * @param int $senderId Sender user ID
     * @param int $receiverId Receiver user ID
     * @param int|null $listingId Related listing ID
     * @return string|null Copy reason or null
     */
    public function shouldCopyMessage(int $senderId, int $receiverId, ?int $listingId = null): ?string
    {
        if (!$this->configService->isBrokerVisibilityEnabled()) {
            return null;
        }

        if ($this->isUserUnderMonitoring($senderId)) {
            return self::REASON_FLAGGED_USER;
        }

        if ($this->configService->isFirstContactMonitoringEnabled()) {
            if ($this->isFirstContact($senderId, $receiverId)) {
                return self::REASON_FIRST_CONTACT;
            }
        }

        // Check new member
        $config = $this->configService->getConfig('broker_visibility');
        if (!empty($config['copy_new_member_messages'])) {
            $messagingConfig = $this->configService->getConfig('messaging');
            $monitoringDays = (int) ($messagingConfig['new_member_monitoring_days'] ?? 30);
            if ($monitoringDays > 0 && $this->isNewMember($senderId, $monitoringDays)) {
                return self::REASON_NEW_MEMBER;
            }
        }

        // Check high-risk listing
        if ($listingId && !empty($config['copy_high_risk_listing_messages'])) {
            $isHighRisk = DB::table('listing_risk_tags')
                ->where('listing_id', $listingId)
                ->where('tenant_id', TenantContext::getId())
                ->where('risk_level', 'high')
                ->exists();
            if ($isHighRisk) {
                return self::REASON_HIGH_RISK_LISTING;
            }
        }

        // Random sampling
        $sampleRate = (int) ($config['random_sample_percentage'] ?? 0);
        if ($sampleRate > 0 && rand(1, 100) <= $sampleRate) {
            return self::REASON_MONITORING;
        }

        return null;
    }

    /**
     * Copy a message for broker review.
     *
     * @param int $messageId Original message ID
     * @param string $reason Copy reason
     * @return int|null Copy ID or null
     */
    public function copyMessageForBroker(int $messageId, string $reason): ?int
    {
        $tenantId = TenantContext::getId();

        $message = Message::find($messageId);
        if (!$message) {
            return null;
        }

        // Check if already copied
        if (BrokerMessageCopy::where('original_message_id', $messageId)->exists()) {
            return null;
        }

        $ids = [(int) $message->sender_id, (int) $message->receiver_id];
        sort($ids);
        $conversationKey = md5(implode('-', $ids));

        $copy = BrokerMessageCopy::create([
            'tenant_id' => $tenantId,
            'original_message_id' => $message->id,
            'conversation_key' => $conversationKey,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'message_body' => $message->content ?? $message->body ?? '',
            'sent_at' => $message->created_at,
            'copy_reason' => $reason,
            'related_listing_id' => $message->listing_id ?? null,
        ]);

        // Notify admin brokers
        try {
            $sender = User::find($message->sender_id);
            $senderDisplayName = $sender ? trim($sender->name ?? '') : 'A user';
            if (empty($senderDisplayName)) {
                $senderDisplayName = 'A user';
            }

            $adminIds = $this->getTenantBrokerAdminIds();
            foreach ($adminIds as $adminId) {
                if ($adminId === (int) $message->sender_id) {
                    continue;
                }
                Notification::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $adminId,
                    'message' => "New message for review from {$senderDisplayName}",
                    'link' => '/admin/broker-controls/messages',
                    'type' => 'broker_review',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[BrokerMessageVisibilityService] Admin notification error: ' . $e->getMessage());
        }

        // Record first contact if applicable
        if ($reason === self::REASON_FIRST_CONTACT) {
            $this->recordFirstContact($message->sender_id, $message->receiver_id, $messageId);
        }

        return $copy->id;
    }

    /**
     * Get unreviewed messages for broker.
     *
     * @param int $limit Max number of messages
     * @param int $offset Offset for pagination
     * @return array
     */
    public function getUnreviewedMessages(int $limit = 50, int $offset = 0): array
    {
        return BrokerMessageCopy::with(['sender:id,name,avatar_url', 'receiver:id,name,avatar_url'])
            ->whereNull('reviewed_at')
            ->orderByDesc('flagged')
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get messages by filter.
     *
     * @param string $filter Filter type (unreviewed, flagged, reviewed, all)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array{items: array, total: int, pages: int}
     */
    public function getMessages(string $filter = 'unreviewed', int $page = 1, int $perPage = 50): array
    {
        $query = BrokerMessageCopy::with(['sender:id,name,avatar_url', 'receiver:id,name,avatar_url', 'reviewer:id,name']);

        switch ($filter) {
            case 'unreviewed':
                $query->whereNull('reviewed_at');
                break;
            case 'flagged':
                $query->where('flagged', true);
                break;
            case 'reviewed':
                $query->whereNotNull('reviewed_at');
                break;
        }

        $total = $query->count();

        $items = $query
            ->orderByDesc('flagged')
            ->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Mark a message copy as reviewed.
     *
     * @param int $copyId Message copy ID
     * @param int $brokerId Reviewer user ID
     * @return bool
     */
    public function markAsReviewed(int $copyId, int $brokerId): bool
    {
        $copy = BrokerMessageCopy::find($copyId);
        if (!$copy) {
            return false;
        }

        $copy->update([
            'reviewed_by' => $brokerId,
            'reviewed_at' => now(),
        ]);

        return true;
    }

    /**
     * Check if user has messaging disabled.
     *
     * @param int $userId User ID
     * @return bool
     */
    public function isMessagingDisabledForUser(int $userId): bool
    {
        $tenantId = TenantContext::getId();

        $restriction = DB::table('user_messaging_restrictions')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$restriction || !$restriction->messaging_disabled) {
            return false;
        }

        // If monitoring has expired, auto-clear
        if ($restriction->under_monitoring && $restriction->monitoring_expires_at
            && strtotime($restriction->monitoring_expires_at) <= time()) {
            $this->clearExpiredMonitoring($userId, $tenantId);
            return false;
        }

        return true;
    }

    /**
     * Get the messaging restriction status for a user.
     *
     * @param int $userId User ID
     * @return array{messaging_disabled: bool, under_monitoring: bool, restriction_reason: string|null}
     */
    public function getUserRestrictionStatus(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $row = DB::table('user_messaging_restrictions')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$row) {
            return [
                'messaging_disabled' => false,
                'under_monitoring' => false,
                'restriction_reason' => null,
            ];
        }

        $underMonitoring = (bool) ($row->under_monitoring ?? 0);
        $messagingDisabled = (bool) ($row->messaging_disabled ?? 0);

        if ($underMonitoring && $row->monitoring_expires_at && strtotime($row->monitoring_expires_at) <= time()) {
            $this->clearExpiredMonitoring($userId, $tenantId);
            $underMonitoring = false;
            $messagingDisabled = false;
        }

        return [
            'messaging_disabled' => $messagingDisabled,
            'under_monitoring' => $underMonitoring,
            'restriction_reason' => $row->monitoring_reason ?? $row->restriction_reason ?? null,
        ];
    }

    /**
     * Count unreviewed messages.
     *
     * @return int
     */
    public function countUnreviewed(): int
    {
        return BrokerMessageCopy::whereNull('reviewed_at')->count();
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function isUserUnderMonitoring(int $userId): bool
    {
        $tenantId = TenantContext::getId();

        $restriction = DB::table('user_messaging_restrictions')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$restriction || !$restriction->under_monitoring) {
            return false;
        }

        if ($restriction->monitoring_expires_at && strtotime($restriction->monitoring_expires_at) <= time()) {
            $this->clearExpiredMonitoring($userId, $tenantId);
            return false;
        }

        return true;
    }

    private function isFirstContact(int $senderId, int $receiverId): bool
    {
        $tenantId = TenantContext::getId();

        return !DB::table('user_first_contacts')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($senderId, $receiverId) {
                $q->where(function ($inner) use ($senderId, $receiverId) {
                    $inner->where('user1_id', $senderId)->where('user2_id', $receiverId);
                })->orWhere(function ($inner) use ($senderId, $receiverId) {
                    $inner->where('user1_id', $receiverId)->where('user2_id', $senderId);
                });
            })
            ->exists();
    }

    private function isNewMember(int $userId, int $days = 30): bool
    {
        return User::where('id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->exists();
    }

    private function recordFirstContact(int $userA, int $userB, int $messageId): void
    {
        $tenantId = TenantContext::getId();

        if ($userA > $userB) {
            [$userA, $userB] = [$userB, $userA];
        }

        DB::table('user_first_contacts')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'user1_id' => $userA,
            'user2_id' => $userB,
            'first_message_id' => $messageId,
            'first_contact_at' => now(),
        ]);
    }

    private function clearExpiredMonitoring(int $userId, int $tenantId): void
    {
        try {
            DB::table('user_messaging_restrictions')
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'under_monitoring' => 0,
                    'messaging_disabled' => 0,
                    'monitoring_reason' => DB::raw("CONCAT(COALESCE(monitoring_reason, ''), ' [auto-expired]')"),
                    'monitoring_expires_at' => null,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[BrokerMessageVisibilityService] Failed to clear expired monitoring: ' . $e->getMessage());
        }
    }

    private function getTenantBrokerAdminIds(): array
    {
        return User::whereIn('role', ['admin', 'tenant_admin', 'super_admin'])
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }
}
