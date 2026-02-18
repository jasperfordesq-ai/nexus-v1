<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ExchangeWorkflowService
 *
 * Manages the structured exchange workflow between members.
 * Supports broker approval, dual-party confirmation, and transaction creation.
 *
 * Workflow States:
 * 1. pending_provider - Waiting for provider to accept
 * 2. pending_broker - (optional) Waiting for broker approval
 * 3. accepted - Provider accepted, work can begin
 * 4. in_progress - Work is underway
 * 5. pending_confirmation - Work done, waiting for both parties to confirm hours
 * 6. completed - Both confirmed, transaction created
 * 7. disputed - Parties disagree on hours
 * 8. cancelled - Exchange cancelled
 * 9. expired - Request expired without response
 */
class ExchangeWorkflowService
{
    // Status constants
    public const STATUS_PENDING_PROVIDER = 'pending_provider';
    public const STATUS_PENDING_BROKER = 'pending_broker';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Valid status transitions
     */
    private const TRANSITIONS = [
        self::STATUS_PENDING_PROVIDER => [self::STATUS_PENDING_BROKER, self::STATUS_ACCEPTED, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_PENDING_BROKER => [self::STATUS_ACCEPTED, self::STATUS_CANCELLED],
        self::STATUS_ACCEPTED => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_PENDING_CONFIRMATION, self::STATUS_CANCELLED],
        self::STATUS_PENDING_CONFIRMATION => [self::STATUS_COMPLETED, self::STATUS_DISPUTED],
        self::STATUS_DISPUTED => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_EXPIRED => [],
    ];

    /**
     * Create a new exchange request
     *
     * @param int $requesterId User requesting the exchange
     * @param int $listingId Listing ID
     * @param array $data Request data (proposed_hours, message)
     * @return int|null Exchange ID or null on failure
     */
    public static function createRequest(int $requesterId, int $listingId, array $data): ?int
    {
        $tenantId = TenantContext::getId();

        // Get listing details
        $stmt = Database::query(
            "SELECT l.*, u.id as owner_id
             FROM listings l
             JOIN users u ON l.user_id = u.id
             WHERE l.id = ? AND l.tenant_id = ?",
            [$listingId, $tenantId]
        );
        $listing = $stmt->fetch();

        if (!$listing) {
            return null;
        }

        $providerId = (int) $listing['owner_id'];

        // Prevent requesting your own listing
        if ($requesterId === $providerId) {
            return null;
        }
        $proposedHours = max(0.25, min(24, (float) ($data['proposed_hours'] ?? $listing['hours'] ?? 1)));

        // Determine initial status
        $initialStatus = self::STATUS_PENDING_PROVIDER;

        // Check if broker approval is needed upfront
        $needsBrokerApproval = self::needsBrokerApproval($listingId, $proposedHours);

        Database::query(
            "INSERT INTO exchange_requests
             (tenant_id, listing_id, requester_id, provider_id, proposed_hours, requester_notes, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $tenantId,
                $listingId,
                $requesterId,
                $providerId,
                $proposedHours,
                $data['message'] ?? null,
                $initialStatus,
            ]
        );

        $exchangeId = Database::lastInsertId();

        // Log history
        self::logHistory($exchangeId, 'request_created', $requesterId, 'requester', null, $initialStatus);

        // Notify provider
        NotificationDispatcher::send($providerId, 'exchange_request_received', [
            'exchange_id' => $exchangeId,
            'requester_id' => $requesterId,
            'listing_id' => $listingId,
            'proposed_hours' => $proposedHours,
        ]);

        return $exchangeId;
    }

    /**
     * Provider accepts the exchange request
     *
     * @param int $exchangeId Exchange ID
     * @param int $providerId Provider user ID
     * @return bool Success
     */
    public static function acceptRequest(int $exchangeId, int $providerId): bool
    {
        $exchange = self::getExchange($exchangeId);

        if (!$exchange || (int) $exchange['provider_id'] !== $providerId) {
            return false;
        }

        if ($exchange['status'] !== self::STATUS_PENDING_PROVIDER) {
            return false;
        }

        // Determine next status
        $needsBrokerApproval = self::needsBrokerApproval($exchange['listing_id'], $exchange['proposed_hours']);
        $newStatus = $needsBrokerApproval ? self::STATUS_PENDING_BROKER : self::STATUS_ACCEPTED;

        $success = self::updateStatus($exchangeId, $newStatus, $providerId, 'provider', 'Provider accepted request');

        if ($success) {
            if ($newStatus === self::STATUS_PENDING_BROKER) {
                // Notify brokers that approval is needed
                NotificationDispatcher::notifyAdmins('exchange_pending_broker', [
                    'exchange_id' => $exchangeId,
                    'requester_name' => $exchange['requester_name'] ?? 'A member',
                    'provider_name' => $exchange['provider_name'] ?? 'Provider',
                    'listing_title' => $exchange['listing_title'] ?? 'Service',
                    'proposed_hours' => $exchange['proposed_hours'] ?? 0,
                ], "Exchange needs broker approval: {$exchange['requester_name']} ↔ {$exchange['provider_name']}");

                // Notify requester that approval is pending
                NotificationDispatcher::send($exchange['requester_id'], 'exchange_pending_broker', [
                    'exchange_id' => $exchangeId,
                ]);
            } else {
                // Notify requester that exchange is accepted (no broker needed)
                NotificationDispatcher::send($exchange['requester_id'], 'exchange_accepted', [
                    'exchange_id' => $exchangeId,
                ]);
            }
        }

        return $success;
    }

    /**
     * Provider declines the exchange request
     *
     * @param int $exchangeId Exchange ID
     * @param int $providerId Provider user ID
     * @param string $reason Decline reason
     * @return bool Success
     */
    public static function declineRequest(int $exchangeId, int $providerId, string $reason = ''): bool
    {
        $exchange = self::getExchange($exchangeId);

        if (!$exchange || (int) $exchange['provider_id'] !== $providerId) {
            return false;
        }

        if ($exchange['status'] !== self::STATUS_PENDING_PROVIDER) {
            return false;
        }

        $success = self::updateStatus($exchangeId, self::STATUS_CANCELLED, $providerId, 'provider', $reason ?: 'Provider declined');

        if ($success) {
            // Notify requester
            NotificationDispatcher::send($exchange['requester_id'], 'exchange_request_declined', [
                'exchange_id' => $exchangeId,
                'reason' => $reason,
            ]);
        }

        return $success;
    }

    /**
     * Broker approves the exchange
     *
     * @param int $exchangeId Exchange ID
     * @param int $brokerId Broker user ID
     * @param string $notes Approval notes
     * @return bool Success
     */
    public static function approveExchange(int $exchangeId, int $brokerId, string $notes = ''): bool
    {
        $exchange = self::getExchange($exchangeId);

        if (!$exchange || $exchange['status'] !== self::STATUS_PENDING_BROKER) {
            return false;
        }

        Database::query(
            "UPDATE exchange_requests SET broker_id = ?, broker_notes = ? WHERE id = ?",
            [$brokerId, $notes, $exchangeId]
        );

        $success = self::updateStatus($exchangeId, self::STATUS_ACCEPTED, $brokerId, 'broker', $notes ?: 'Broker approved');

        if ($success) {
            // Notify both parties
            NotificationDispatcher::send($exchange['requester_id'], 'exchange_approved', [
                'exchange_id' => $exchangeId,
            ]);
            NotificationDispatcher::send($exchange['provider_id'], 'exchange_approved', [
                'exchange_id' => $exchangeId,
            ]);
        }

        return $success;
    }

    /**
     * Broker rejects the exchange
     *
     * @param int $exchangeId Exchange ID
     * @param int $brokerId Broker user ID
     * @param string $reason Rejection reason
     * @return bool Success
     */
    public static function rejectExchange(int $exchangeId, int $brokerId, string $reason): bool
    {
        $exchange = self::getExchange($exchangeId);

        if (!$exchange || $exchange['status'] !== self::STATUS_PENDING_BROKER) {
            return false;
        }

        Database::query(
            "UPDATE exchange_requests SET broker_id = ?, broker_notes = ? WHERE id = ?",
            [$brokerId, $reason, $exchangeId]
        );

        $success = self::updateStatus($exchangeId, self::STATUS_CANCELLED, $brokerId, 'broker', $reason);

        if ($success) {
            // Notify both parties
            NotificationDispatcher::send($exchange['requester_id'], 'exchange_rejected', [
                'exchange_id' => $exchangeId,
                'reason' => $reason,
            ]);
            NotificationDispatcher::send($exchange['provider_id'], 'exchange_rejected', [
                'exchange_id' => $exchangeId,
                'reason' => $reason,
            ]);
        }

        return $success;
    }

    /**
     * Mark exchange as in progress
     *
     * @param int $exchangeId Exchange ID
     * @param int $userId User making the change
     * @return bool Success
     */
    public static function startProgress(int $exchangeId, int $userId): bool
    {
        $exchange = self::getExchange($exchangeId);

        if (!$exchange || $exchange['status'] !== self::STATUS_ACCEPTED) {
            return false;
        }

        $isRequester = (int) $exchange['requester_id'] === $userId;
        $role = $isRequester ? 'requester' : 'provider';
        $success = self::updateStatus($exchangeId, self::STATUS_IN_PROGRESS, $userId, $role, 'Work started');

        if ($success) {
            // Notify the other party that work has started
            $notifyUserId = $isRequester ? $exchange['provider_id'] : $exchange['requester_id'];
            NotificationDispatcher::send($notifyUserId, 'exchange_started', [
                'exchange_id' => $exchangeId,
                'started_by' => $role,
            ]);
        }

        return $success;
    }

    /**
     * Mark exchange as ready for confirmation
     *
     * @param int $exchangeId Exchange ID
     * @param int $userId User making the change
     * @return bool Success
     */
    public static function markReadyForConfirmation(int $exchangeId, int $userId): bool
    {
        $exchange = self::getExchange($exchangeId);

        if (!$exchange || $exchange['status'] !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $isRequester = (int) $exchange['requester_id'] === $userId;
        $role = $isRequester ? 'requester' : 'provider';
        $success = self::updateStatus($exchangeId, self::STATUS_PENDING_CONFIRMATION, $userId, $role, 'Work completed, pending confirmation');

        if ($success) {
            // Notify both parties to confirm hours
            NotificationDispatcher::send($exchange['requester_id'], 'exchange_ready_confirmation', [
                'exchange_id' => $exchangeId,
                'marked_by' => $role,
                'proposed_hours' => $exchange['proposed_hours'] ?? 0,
            ]);
            NotificationDispatcher::send($exchange['provider_id'], 'exchange_ready_confirmation', [
                'exchange_id' => $exchangeId,
                'marked_by' => $role,
                'proposed_hours' => $exchange['proposed_hours'] ?? 0,
            ]);
        }

        return $success;
    }

    /**
     * Confirm completion with hours
     *
     * @param int $exchangeId Exchange ID
     * @param int $userId User confirming
     * @param float $hours Confirmed hours
     * @return bool Success
     */
    public static function confirmCompletion(int $exchangeId, int $userId, float $hours): bool
    {
        $exchange = self::getExchange($exchangeId);

        if (!$exchange) {
            return false;
        }

        // Must be in pending_confirmation or in_progress status
        if (!in_array($exchange['status'], [self::STATUS_IN_PROGRESS, self::STATUS_PENDING_CONFIRMATION], true)) {
            return false;
        }

        $isRequester = (int) $exchange['requester_id'] === $userId;
        $isProvider = (int) $exchange['provider_id'] === $userId;

        if (!$isRequester && !$isProvider) {
            return false;
        }

        // Check hour variance if configured
        $config = BrokerControlConfigService::getConfig('exchange_workflow');
        $allowVariance = $config['allow_hour_adjustment'] ?? true;
        $maxVariance = $config['max_hour_variance_percent'] ?? 25;

        if (!$allowVariance) {
            $hours = $exchange['proposed_hours'];
        } else {
            // Limit hours to within variance
            $minHours = $exchange['proposed_hours'] * (1 - $maxVariance / 100);
            $maxHours = $exchange['proposed_hours'] * (1 + $maxVariance / 100);
            $hours = max($minHours, min($maxHours, $hours));
        }

        // Update confirmation
        if ($isRequester) {
            Database::query(
                "UPDATE exchange_requests
                 SET requester_confirmed_at = NOW(), requester_confirmed_hours = ?
                 WHERE id = ?",
                [$hours, $exchangeId]
            );
            self::logHistory($exchangeId, 'requester_confirmed', $userId, 'requester', null, null, "Confirmed $hours hours");
        } else {
            Database::query(
                "UPDATE exchange_requests
                 SET provider_confirmed_at = NOW(), provider_confirmed_hours = ?
                 WHERE id = ?",
                [$hours, $exchangeId]
            );
            self::logHistory($exchangeId, 'provider_confirmed', $userId, 'provider', null, null, "Confirmed $hours hours");
        }

        // Update status to pending confirmation if not already
        if ($exchange['status'] === self::STATUS_IN_PROGRESS) {
            self::updateStatus($exchangeId, self::STATUS_PENDING_CONFIRMATION, $userId, $isRequester ? 'requester' : 'provider');
        }

        // Check if both have confirmed
        $refreshed = self::getExchange($exchangeId);
        if ($refreshed['requester_confirmed_at'] && $refreshed['provider_confirmed_at']) {
            return self::processConfirmations($exchangeId, $refreshed);
        }

        return true;
    }

    /**
     * Process both confirmations and complete or dispute
     *
     * @param int $exchangeId Exchange ID
     * @param array $exchange Exchange data
     * @return bool Success
     */
    private static function processConfirmations(int $exchangeId, array $exchange): bool
    {
        $requesterHours = (float) $exchange['requester_confirmed_hours'];
        $providerHours = (float) $exchange['provider_confirmed_hours'];

        // If hours match, complete the exchange
        if (abs($requesterHours - $providerHours) < 0.01) {
            return self::completeExchange($exchangeId, $requesterHours);
        }

        // Hours don't match - check variance tolerance
        $config = BrokerControlConfigService::getConfig('exchange_workflow');
        $varianceTolerance = 0.25; // Default 15 minutes tolerance

        if (abs($requesterHours - $providerHours) <= $varianceTolerance) {
            // Use average
            $finalHours = ($requesterHours + $providerHours) / 2;
            return self::completeExchange($exchangeId, $finalHours);
        }

        // Dispute - hours differ too much
        self::updateStatus($exchangeId, self::STATUS_DISPUTED, null, 'system',
            "Hours mismatch: requester=$requesterHours, provider=$providerHours");

        // Notify broker
        NotificationDispatcher::notifyAdmins('exchange_disputed', [
            'exchange_id' => $exchangeId,
            'requester_hours' => $requesterHours,
            'provider_hours' => $providerHours,
        ], 'Exchange #' . $exchangeId . ' has conflicting hour confirmations');

        return true;
    }

    /**
     * Complete the exchange and create transaction
     *
     * @param int $exchangeId Exchange ID
     * @param float $finalHours Final agreed hours
     * @return bool Success
     */
    public static function completeExchange(int $exchangeId, float $finalHours): bool
    {
        $exchange = self::getExchange($exchangeId);

        if (!$exchange) {
            return false;
        }

        Database::beginTransaction();
        try {
            // Update exchange with final hours
            Database::query(
                "UPDATE exchange_requests SET final_hours = ? WHERE id = ?",
                [$finalHours, $exchangeId]
            );

            // Create transaction
            $transactionId = self::createTransaction($exchangeId, $finalHours);

            if ($transactionId) {
                Database::query(
                    "UPDATE exchange_requests SET transaction_id = ? WHERE id = ?",
                    [$transactionId, $exchangeId]
                );
            }

            self::updateStatus($exchangeId, self::STATUS_COMPLETED, null, 'system', "Completed with $finalHours hours");

            Database::commit();

            // Notify both parties
            NotificationDispatcher::send($exchange['requester_id'], 'exchange_completed', [
                'exchange_id' => $exchangeId,
                'hours' => $finalHours,
            ]);
            NotificationDispatcher::send($exchange['provider_id'], 'exchange_completed', [
                'exchange_id' => $exchangeId,
                'hours' => $finalHours,
            ]);

            return true;
        } catch (\Exception $e) {
            Database::rollback();
            error_log("Failed to complete exchange $exchangeId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel an exchange
     *
     * @param int $exchangeId Exchange ID
     * @param int $userId User cancelling
     * @param string $reason Cancellation reason
     * @return bool Success
     */
    public static function cancelExchange(int $exchangeId, int $userId, string $reason = ''): bool
    {
        $exchange = self::getExchange($exchangeId);

        if (!$exchange) {
            return false;
        }

        // Can only cancel if not completed
        if (in_array($exchange['status'], [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_EXPIRED], true)) {
            return false;
        }

        $isRequester = (int) $exchange['requester_id'] === $userId;
        $isProvider = (int) $exchange['provider_id'] === $userId;
        $role = $isRequester ? 'requester' : ($isProvider ? 'provider' : 'broker');

        $success = self::updateStatus($exchangeId, self::STATUS_CANCELLED, $userId, $role, $reason ?: 'Cancelled');

        if ($success) {
            // Notify the other party
            $notifyUserId = $isRequester ? $exchange['provider_id'] : $exchange['requester_id'];
            NotificationDispatcher::send($notifyUserId, 'exchange_cancelled', [
                'exchange_id' => $exchangeId,
                'cancelled_by' => $role,
                'reason' => $reason,
            ]);
        }

        return $success;
    }

    /**
     * Get exchange by ID
     *
     * @param int $exchangeId Exchange ID
     * @return array|null Exchange data or null
     */
    public static function getExchange(int $exchangeId): ?array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT e.*,
                    l.title as listing_title, l.type as listing_type,
                    req.name as requester_name, req.email as requester_email, req.avatar_url as requester_avatar,
                    prov.name as provider_name, prov.email as provider_email, prov.avatar_url as provider_avatar,
                    broker.name as broker_name,
                    rt.risk_level
             FROM exchange_requests e
             JOIN listings l ON e.listing_id = l.id
             JOIN users req ON e.requester_id = req.id
             JOIN users prov ON e.provider_id = prov.id
             LEFT JOIN users broker ON e.broker_id = broker.id
             LEFT JOIN listing_risk_tags rt ON e.listing_id = rt.listing_id AND rt.tenant_id = e.tenant_id
             WHERE e.id = ? AND e.tenant_id = ?",
            [$exchangeId, $tenantId]
        );

        $exchange = $stmt->fetch();
        return $exchange ?: null;
    }

    /**
     * Get exchanges for a user
     *
     * @param int $userId User ID
     * @param array $filters Filters (status, role)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array ['items' => [...], 'total' => int, 'pages' => int]
     */
    public static function getExchangesForUser(int $userId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $tenantId = TenantContext::getId();
        $offset = ($page - 1) * $perPage;

        $whereClause = "e.tenant_id = ? AND (e.requester_id = ? OR e.provider_id = ?)";
        $params = [$tenantId, $userId, $userId];

        if (!empty($filters['status'])) {
            // Handle 'active' as a special filter that includes multiple statuses
            if ($filters['status'] === 'active') {
                $activeStatuses = [
                    self::STATUS_PENDING_PROVIDER,
                    self::STATUS_PENDING_BROKER,
                    self::STATUS_ACCEPTED,
                    self::STATUS_IN_PROGRESS,
                    self::STATUS_PENDING_CONFIRMATION,
                ];
                $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
                $whereClause .= " AND e.status IN ($placeholders)";
                $params = array_merge($params, $activeStatuses);
            } else {
                $whereClause .= " AND e.status = ?";
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['role'])) {
            if ($filters['role'] === 'requester') {
                $whereClause = "e.tenant_id = ? AND e.requester_id = ?";
                $params = [$tenantId, $userId];
            } elseif ($filters['role'] === 'provider') {
                $whereClause = "e.tenant_id = ? AND e.provider_id = ?";
                $params = [$tenantId, $userId];
            }
            if (!empty($filters['status'])) {
                // Handle 'active' as a special filter that includes multiple statuses
                if ($filters['status'] === 'active') {
                    $activeStatuses = [
                        self::STATUS_PENDING_PROVIDER,
                        self::STATUS_PENDING_BROKER,
                        self::STATUS_ACCEPTED,
                        self::STATUS_IN_PROGRESS,
                        self::STATUS_PENDING_CONFIRMATION,
                    ];
                    $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
                    $whereClause .= " AND e.status IN ($placeholders)";
                    $params = array_merge($params, $activeStatuses);
                } else {
                    $whereClause .= " AND e.status = ?";
                    $params[] = $filters['status'];
                }
            }
        }

        // Get total
        $countStmt = Database::query(
            "SELECT COUNT(*) as total FROM exchange_requests e WHERE $whereClause",
            $params
        );
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        // Get items
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = Database::query(
            "SELECT e.*,
                    l.title as listing_title, l.type as listing_type,
                    req.name as requester_name, req.avatar_url as requester_avatar,
                    prov.name as provider_name, prov.avatar_url as provider_avatar
             FROM exchange_requests e
             JOIN listings l ON e.listing_id = l.id
             JOIN users req ON e.requester_id = req.id
             JOIN users prov ON e.provider_id = prov.id
             WHERE $whereClause
             ORDER BY e.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );

        return [
            'items' => $stmt->fetchAll() ?: [],
            'total' => $total,
            'pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get exchanges pending broker approval
     *
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array ['items' => [...], 'total' => int, 'pages' => int]
     */
    public static function getPendingBrokerApprovals(int $page = 1, int $perPage = 20): array
    {
        $tenantId = TenantContext::getId();
        $offset = ($page - 1) * $perPage;

        $countStmt = Database::query(
            "SELECT COUNT(*) as total FROM exchange_requests WHERE tenant_id = ? AND status = ?",
            [$tenantId, self::STATUS_PENDING_BROKER]
        );
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $stmt = Database::query(
            "SELECT e.*,
                    l.title as listing_title, l.type as listing_type,
                    req.name as requester_name, req.avatar_url as requester_avatar,
                    prov.name as provider_name, prov.avatar_url as provider_avatar,
                    rt.risk_level
             FROM exchange_requests e
             JOIN listings l ON e.listing_id = l.id
             JOIN users req ON e.requester_id = req.id
             JOIN users prov ON e.provider_id = prov.id
             LEFT JOIN listing_risk_tags rt ON e.listing_id = rt.listing_id AND rt.tenant_id = e.tenant_id
             WHERE e.tenant_id = ? AND e.status = ?
             ORDER BY e.created_at ASC
             LIMIT ? OFFSET ?",
            [$tenantId, self::STATUS_PENDING_BROKER, $perPage, $offset]
        );

        return [
            'items' => $stmt->fetchAll() ?: [],
            'total' => $total,
            'pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get exchanges by status
     *
     * @param string $status Status filter
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array ['items' => [...], 'total' => int, 'pages' => int]
     */
    public static function getExchangesByStatus(string $status, int $page = 1, int $perPage = 20): array
    {
        $tenantId = TenantContext::getId();
        $offset = ($page - 1) * $perPage;

        // Map UI status to database statuses
        $statusMap = [
            'pending' => [self::STATUS_PENDING_PROVIDER, self::STATUS_PENDING_BROKER],
            'active' => [self::STATUS_ACCEPTED, self::STATUS_IN_PROGRESS, self::STATUS_PENDING_CONFIRMATION],
            'completed' => [self::STATUS_COMPLETED],
            'cancelled' => [self::STATUS_CANCELLED, self::STATUS_EXPIRED, self::STATUS_DISPUTED],
        ];

        $statuses = $statusMap[$status] ?? [$status];
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));

        $params = array_merge([$tenantId], $statuses);

        $countStmt = Database::query(
            "SELECT COUNT(*) as total FROM exchange_requests WHERE tenant_id = ? AND status IN ($placeholders)",
            $params
        );
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $params[] = $perPage;
        $params[] = $offset;

        $stmt = Database::query(
            "SELECT e.*,
                    l.title as listing_title, l.type as listing_type,
                    req.name as requester_name, req.avatar_url as requester_avatar,
                    prov.name as provider_name, prov.avatar_url as provider_avatar,
                    rt.risk_level
             FROM exchange_requests e
             JOIN listings l ON e.listing_id = l.id
             JOIN users req ON e.requester_id = req.id
             JOIN users prov ON e.provider_id = prov.id
             LEFT JOIN listing_risk_tags rt ON e.listing_id = rt.listing_id AND rt.tenant_id = e.tenant_id
             WHERE e.tenant_id = ? AND e.status IN ($placeholders)
             ORDER BY e.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );

        return [
            'items' => $stmt->fetchAll() ?: [],
            'total' => $total,
            'pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get exchange history
     *
     * @param int $exchangeId Exchange ID
     * @return array History entries
     */
    public static function getExchangeHistory(int $exchangeId): array
    {
        $stmt = Database::query(
            "SELECT h.*, u.name as actor_name
             FROM exchange_history h
             LEFT JOIN users u ON h.actor_id = u.id
             WHERE h.exchange_id = ?
             ORDER BY h.created_at ASC",
            [$exchangeId]
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Log history entry
     *
     * @param int $exchangeId Exchange ID
     * @param string $action Action name
     * @param int|null $actorId Actor user ID
     * @param string $actorRole Actor role
     * @param string|null $oldStatus Previous status
     * @param string|null $newStatus New status
     * @param string|null $notes Notes
     */
    public static function logHistory(
        int $exchangeId,
        string $action,
        ?int $actorId,
        string $actorRole,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?string $notes = null
    ): void {
        Database::query(
            "INSERT INTO exchange_history
             (exchange_id, action, actor_id, actor_role, old_status, new_status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$exchangeId, $action, $actorId, $actorRole, $oldStatus, $newStatus, $notes]
        );
    }

    /**
     * Update exchange status
     *
     * @param int $exchangeId Exchange ID
     * @param string $newStatus New status
     * @param int|null $actorId Actor user ID
     * @param string $actorRole Actor role
     * @param string|null $notes Notes
     * @return bool Success
     */
    private static function updateStatus(
        int $exchangeId,
        string $newStatus,
        ?int $actorId,
        string $actorRole,
        ?string $notes = null
    ): bool {
        $exchange = self::getExchange($exchangeId);

        if (!$exchange) {
            return false;
        }

        $oldStatus = $exchange['status'];

        // Validate transition
        $allowedTransitions = self::TRANSITIONS[$oldStatus] ?? [];
        if (!in_array($newStatus, $allowedTransitions, true)) {
            return false;
        }

        $result = Database::query(
            "UPDATE exchange_requests SET status = ? WHERE id = ?",
            [$newStatus, $exchangeId]
        );

        if ($result) {
            self::logHistory($exchangeId, 'status_changed', $actorId, $actorRole, $oldStatus, $newStatus, $notes);
        }

        return $result !== false;
    }

    /**
     * Check if broker approval is needed for an exchange
     *
     * @param int $listingId Listing ID
     * @param float $proposedHours Proposed hours
     * @return bool True if broker approval needed
     */
    private static function needsBrokerApproval(int $listingId, float $proposedHours): bool
    {
        // Check if exchange workflow is enabled
        if (!BrokerControlConfigService::isExchangeWorkflowEnabled()) {
            return false;
        }

        // Check if broker approval is required globally
        if (!BrokerControlConfigService::requiresBrokerApproval()) {
            return false;
        }

        // Check auto-approve for low risk
        if (BrokerControlConfigService::canAutoApproveLowRisk()) {
            // Check if listing is high risk
            if (ListingRiskTagService::isHighRisk($listingId)) {
                return true;
            }

            // Check if listing explicitly requires approval
            if (ListingRiskTagService::requiresApproval($listingId)) {
                return true;
            }

            // Check if hours exceed threshold
            $maxHours = BrokerControlConfigService::getMaxHoursWithoutApproval();
            if ($proposedHours > $maxHours) {
                return true;
            }

            // Auto-approve low risk
            return false;
        }

        // Broker approval required for all
        return true;
    }

    /**
     * Create a transaction for completed exchange
     *
     * @param int $exchangeId Exchange ID
     * @param float $hours Hours to transfer
     * @return int|null Transaction ID or null
     */
    private static function createTransaction(int $exchangeId, float $hours): ?int
    {
        $exchange = self::getExchange($exchangeId);

        if (!$exchange) {
            return null;
        }

        // Use WalletService to create transaction
        try {
            $transactionId = WalletService::transfer(
                $exchange['requester_id'],
                $exchange['provider_id'],
                $hours,
                'exchange',
                "Exchange #$exchangeId for listing: {$exchange['listing_title']}"
            );

            return $transactionId;
        } catch (\Exception $e) {
            error_log("Failed to create transaction for exchange $exchangeId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get exchange statistics
     *
     * @param int $days Period in days
     * @return array Statistics
     */
    public static function getStatistics(int $days = 30): array
    {
        $tenantId = TenantContext::getId();
        $startDate = date('Y-m-d', strtotime("-$days days"));

        $stmt = Database::query(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending_broker' THEN 1 ELSE 0 END) as pending_broker,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) as disputed,
                SUM(CASE WHEN status IN ('pending_provider', 'pending_broker') THEN 1 ELSE 0 END) as pending_total,
                SUM(CASE WHEN status IN ('accepted', 'in_progress', 'pending_confirmation') THEN 1 ELSE 0 END) as active_total,
                SUM(final_hours) as total_hours
             FROM exchange_requests
             WHERE tenant_id = ? AND created_at >= ?",
            [$tenantId, $startDate]
        );

        return $stmt->fetch() ?: [];
    }

    /**
     * Expire old pending requests
     *
     * @return int Number of expired requests
     */
    public static function expireOldRequests(): int
    {
        $tenantId = TenantContext::getId();
        $expiryHours = BrokerControlConfigService::getExpiryHours();
        $expiryDate = date('Y-m-d H:i:s', strtotime("-$expiryHours hours"));

        $stmt = Database::query(
            "SELECT id FROM exchange_requests
             WHERE tenant_id = ? AND status = ? AND created_at < ?",
            [$tenantId, self::STATUS_PENDING_PROVIDER, $expiryDate]
        );

        $expired = 0;
        while ($row = $stmt->fetch()) {
            self::updateStatus($row['id'], self::STATUS_EXPIRED, null, 'system', 'Request expired');
            $expired++;
        }

        return $expired;
    }
}
