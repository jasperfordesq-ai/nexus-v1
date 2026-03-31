<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\ExchangeHistory;
use App\Models\ExchangeRequest;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExchangeWorkflowService
 *
 * Manages the structured exchange workflow between members.
 * Supports broker approval, dual-party confirmation, and transaction creation.
 *
 * All queries are tenant-scoped via TenantContext::getId() or HasTenantScope.
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

    /** Valid status transitions */
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

    private BrokerControlConfigService $configService;

    public function __construct(BrokerControlConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Initiate an exchange (legacy compat).
     */
    public static function initiate(int $tenantId, int $listingId, int $requesterId): ?int
    {
        return self::createRequest($requesterId, $listingId, []);
    }

    /**
     * Accept an exchange (legacy compat).
     */
    public static function accept(int $tenantId, int $exchangeId, int $userId): bool
    {
        return self::acceptRequest($exchangeId, $userId);
    }

    /**
     * Complete an exchange (legacy compat).
     */
    public static function complete(int $tenantId, int $exchangeId, int $userId, float $hours): bool
    {
        return self::confirmCompletion($exchangeId, $userId, $hours);
    }

    /**
     * Cancel an exchange (legacy compat).
     */
    public static function cancel(int $tenantId, int $exchangeId, int $userId, ?string $reason = null): bool
    {
        return self::cancelExchange($exchangeId, $userId, $reason ?? '');
    }

    /**
     * Create a new exchange request.
     *
     * @param int $requesterId User requesting the exchange
     * @param int $listingId Listing ID
     * @param array $data Request data (proposed_hours, message)
     * @return int|null Exchange ID or null
     */
    public static function createRequest(int $requesterId, int $listingId, array $data): ?int
    {
        $tenantId = TenantContext::getId();

        $listing = Listing::with('user')->find($listingId);
        if (!$listing) {
            return null;
        }

        $providerId = (int) $listing->user_id;

        if ($requesterId === $providerId) {
            return null;
        }

        $proposedHours = max(0.25, min(24, (float) ($data['proposed_hours'] ?? $listing->hours ?? 1)));
        $initialStatus = self::STATUS_PENDING_PROVIDER;

        $exchange = ExchangeRequest::create([
            'tenant_id' => $tenantId,
            'listing_id' => $listingId,
            'requester_id' => $requesterId,
            'provider_id' => $providerId,
            'proposed_hours' => $proposedHours,
            'requester_notes' => $data['message'] ?? null,
            'status' => $initialStatus,
        ]);

        self::logHistory($exchange->id, 'request_created', $requesterId, 'requester', null, $initialStatus);

        // Notify the listing owner (provider) about the new request
        try {
            NotificationDispatcher::send($providerId, 'exchange_request_received', [
                'exchange_id' => $exchange->id,
                'listing_title' => $listing->title ?? '',
                'proposed_hours' => $proposedHours,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Exchange #{$exchange->id}: notification failed after createRequest", [
                'error' => $e->getMessage(),
            ]);
        }

        return $exchange->id;
    }

    /**
     * Provider accepts the exchange request.
     */
    public static function acceptRequest(int $exchangeId, int $providerId): bool
    {
        $exchange = ExchangeRequest::find($exchangeId);
        if (!$exchange || (int) $exchange->provider_id !== $providerId) {
            return false;
        }
        if ($exchange->status !== self::STATUS_PENDING_PROVIDER) {
            return false;
        }

        $needsBroker = self::needsBrokerApproval(
            $exchange->listing_id,
            (float) $exchange->proposed_hours,
            (int) $exchange->requester_id,
            (int) $exchange->provider_id
        );
        $newStatus = $needsBroker ? self::STATUS_PENDING_BROKER : self::STATUS_ACCEPTED;

        $result = self::updateStatus($exchangeId, $newStatus, $providerId, 'provider', 'Provider accepted request');

        if ($result) {
            try {
                $requesterId = (int) $exchange->requester_id;
                $notificationData = [
                    'exchange_id' => $exchangeId,
                    'listing_title' => $exchange->listing->title ?? '',
                    'proposed_hours' => (float) $exchange->proposed_hours,
                ];

                if ($needsBroker) {
                    // Notify requester that exchange is pending broker approval
                    NotificationDispatcher::send($requesterId, 'exchange_pending_broker', $notificationData);
                    // Also notify admins/brokers
                    NotificationDispatcher::notifyAdmins('exchange_pending_broker', $notificationData);
                } else {
                    // Notify requester that exchange was accepted
                    NotificationDispatcher::send($requesterId, 'exchange_accepted', $notificationData);
                }
            } catch (\Throwable $e) {
                Log::warning("Exchange #{$exchangeId}: notification failed after acceptRequest", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Provider declines the exchange request.
     */
    public static function declineRequest(int $exchangeId, int $providerId, string $reason = ''): bool
    {
        $exchange = ExchangeRequest::find($exchangeId);
        if (!$exchange || (int) $exchange->provider_id !== $providerId) {
            return false;
        }
        if ($exchange->status !== self::STATUS_PENDING_PROVIDER) {
            return false;
        }

        $result = self::updateStatus($exchangeId, self::STATUS_CANCELLED, $providerId, 'provider', $reason ?: 'Provider declined');

        if ($result) {
            try {
                NotificationDispatcher::send((int) $exchange->requester_id, 'exchange_request_declined', [
                    'exchange_id' => $exchangeId,
                    'listing_title' => $exchange->listing->title ?? '',
                    'reason' => $reason,
                ]);
            } catch (\Throwable $e) {
                Log::warning("Exchange #{$exchangeId}: notification failed after declineRequest", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Broker approves the exchange.
     */
    public static function approveExchange(int $exchangeId, int $brokerId, string $notes = '', string $conditions = ''): bool
    {
        $exchange = ExchangeRequest::find($exchangeId);
        if (!$exchange || $exchange->status !== self::STATUS_PENDING_BROKER) {
            return false;
        }

        $exchange->update(['broker_id' => $brokerId, 'broker_notes' => $notes]);

        $result = self::updateStatus($exchangeId, self::STATUS_ACCEPTED, $brokerId, 'broker', $notes ?: 'Broker approved');

        if ($result) {
            try {
                $notificationData = [
                    'exchange_id' => $exchangeId,
                    'listing_title' => $exchange->listing->title ?? '',
                    'proposed_hours' => (float) $exchange->proposed_hours,
                ];
                NotificationDispatcher::send((int) $exchange->requester_id, 'exchange_approved', $notificationData);
                NotificationDispatcher::send((int) $exchange->provider_id, 'exchange_approved', $notificationData);
            } catch (\Throwable $e) {
                Log::warning("Exchange #{$exchangeId}: notification failed after approveExchange", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Broker rejects the exchange.
     */
    public static function rejectExchange(int $exchangeId, int $brokerId, string $reason): bool
    {
        $exchange = ExchangeRequest::find($exchangeId);
        if (!$exchange || $exchange->status !== self::STATUS_PENDING_BROKER) {
            return false;
        }

        $exchange->update(['broker_id' => $brokerId, 'broker_notes' => $reason]);

        $result = self::updateStatus($exchangeId, self::STATUS_CANCELLED, $brokerId, 'broker', $reason);

        if ($result) {
            try {
                $notificationData = [
                    'exchange_id' => $exchangeId,
                    'listing_title' => $exchange->listing->title ?? '',
                    'reason' => $reason,
                ];
                NotificationDispatcher::send((int) $exchange->requester_id, 'exchange_rejected', $notificationData);
                NotificationDispatcher::send((int) $exchange->provider_id, 'exchange_rejected', $notificationData);
            } catch (\Throwable $e) {
                Log::warning("Exchange #{$exchangeId}: notification failed after rejectExchange", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Mark exchange as in progress.
     */
    public static function startProgress(int $exchangeId, int $userId): bool
    {
        $exchange = ExchangeRequest::find($exchangeId);
        if (!$exchange || $exchange->status !== self::STATUS_ACCEPTED) {
            return false;
        }

        $role = ((int) $exchange->requester_id === $userId) ? 'requester' : 'provider';
        $result = self::updateStatus($exchangeId, self::STATUS_IN_PROGRESS, $userId, $role, 'Work started');

        if ($result) {
            try {
                $notificationData = [
                    'exchange_id' => $exchangeId,
                    'listing_title' => $exchange->listing->title ?? '',
                    'proposed_hours' => (float) $exchange->proposed_hours,
                ];
                // Notify the other party (not the one who started)
                $otherPartyId = ((int) $exchange->requester_id === $userId)
                    ? (int) $exchange->provider_id
                    : (int) $exchange->requester_id;
                NotificationDispatcher::send($otherPartyId, 'exchange_started', $notificationData);
            } catch (\Throwable $e) {
                Log::warning("Exchange #{$exchangeId}: notification failed after startProgress", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Mark exchange as ready for confirmation.
     */
    public static function markReadyForConfirmation(int $exchangeId, int $userId): bool
    {
        $exchange = ExchangeRequest::find($exchangeId);
        if (!$exchange || $exchange->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $role = ((int) $exchange->requester_id === $userId) ? 'requester' : 'provider';
        $result = self::updateStatus($exchangeId, self::STATUS_PENDING_CONFIRMATION, $userId, $role, 'Work completed, pending confirmation');

        if ($result) {
            try {
                $notificationData = [
                    'exchange_id' => $exchangeId,
                    'listing_title' => $exchange->listing->title ?? '',
                    'proposed_hours' => (float) $exchange->proposed_hours,
                ];
                // Notify the other party to confirm hours
                $otherPartyId = ((int) $exchange->requester_id === $userId)
                    ? (int) $exchange->provider_id
                    : (int) $exchange->requester_id;
                NotificationDispatcher::send($otherPartyId, 'exchange_ready_confirmation', $notificationData);
            } catch (\Throwable $e) {
                Log::warning("Exchange #{$exchangeId}: notification failed after markReadyForConfirmation", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Confirm completion with hours.
     */
    public static function confirmCompletion(int $exchangeId, int $userId, float $hours): bool
    {
        return DB::transaction(function () use ($exchangeId, $userId, $hours) {
            // Lock the exchange row to prevent concurrent confirmation races
            $exchange = ExchangeRequest::query()
                ->lockForUpdate()
                ->find($exchangeId);

            if (!$exchange) {
                return false;
            }

            if (!in_array($exchange->status, [self::STATUS_IN_PROGRESS, self::STATUS_PENDING_CONFIRMATION], true)) {
                return false;
            }

            $isRequester = (int) $exchange->requester_id === $userId;
            $isProvider = (int) $exchange->provider_id === $userId;

            if (!$isRequester && !$isProvider) {
                return false;
            }

            // Adjust hours — read configured variance percentage from broker config
            $config = BrokerControlConfigService::getConfig('exchange_workflow');
            $variancePercent = (int) ($config['max_hour_variance_percent'] ?? 25);
            $varianceFactor = $variancePercent / 100;
            $minHours = (float) $exchange->proposed_hours * (1 - $varianceFactor);
            $maxHours = (float) $exchange->proposed_hours * (1 + $varianceFactor);
            $hours = max($minHours, min($maxHours, $hours));

            if ($isRequester) {
                $exchange->update([
                    'requester_confirmed_at' => now(),
                    'requester_confirmed_hours' => $hours,
                ]);
                self::logHistory($exchangeId, 'requester_confirmed', $userId, 'requester', null, null, "Confirmed $hours hours");
            } else {
                $exchange->update([
                    'provider_confirmed_at' => now(),
                    'provider_confirmed_hours' => $hours,
                ]);
                self::logHistory($exchangeId, 'provider_confirmed', $userId, 'provider', null, null, "Confirmed $hours hours");
            }

            if ($exchange->status === self::STATUS_IN_PROGRESS) {
                // Use direct update since updateStatus() now acquires its own lock
                // and we already hold the lock — avoid nested lock
                $oldStatus = $exchange->status;
                $newStatus = self::STATUS_PENDING_CONFIRMATION;
                $allowedTransitions = self::TRANSITIONS[$oldStatus] ?? [];
                if (in_array($newStatus, $allowedTransitions, true)) {
                    DB::table('exchange_requests')
                        ->where('id', $exchangeId)
                        ->update(['status' => $newStatus]);
                    self::logHistory($exchangeId, 'status_changed', $userId, $isRequester ? 'requester' : 'provider', $oldStatus, $newStatus);
                    $exchange->status = $newStatus;
                }
            }

            // Refresh to get latest confirmation data (we already hold the lock)
            $exchange->refresh();
            if ($exchange->requester_confirmed_at && $exchange->provider_confirmed_at) {
                return self::processConfirmations($exchangeId, $exchange);
            }

            return true;
        });
    }

    /**
     * Cancel an exchange.
     */
    public static function cancelExchange(int $exchangeId, int $userId, string $reason = ''): bool
    {
        $exchange = ExchangeRequest::find($exchangeId);
        if (!$exchange) {
            return false;
        }

        $terminalStatuses = [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_EXPIRED];
        if (in_array($exchange->status, $terminalStatuses, true)) {
            return false;
        }

        $isRequester = (int) $exchange->requester_id === $userId;
        $isProvider = (int) $exchange->provider_id === $userId;
        $role = $isRequester ? 'requester' : ($isProvider ? 'provider' : 'broker');

        $result = self::updateStatus($exchangeId, self::STATUS_CANCELLED, $userId, $role, $reason ?: 'Cancelled');

        if ($result) {
            try {
                $notificationData = [
                    'exchange_id' => $exchangeId,
                    'listing_title' => $exchange->listing->title ?? '',
                    'reason' => $reason,
                ];
                // Notify the other party (not the one who cancelled)
                $otherPartyId = $isRequester
                    ? (int) $exchange->provider_id
                    : (int) $exchange->requester_id;
                NotificationDispatcher::send($otherPartyId, 'exchange_cancelled', $notificationData);
            } catch (\Throwable $e) {
                Log::warning("Exchange #{$exchangeId}: notification failed after cancelExchange", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Get exchange by ID.
     *
     * @param int $exchangeId Exchange ID
     * @return array|null Exchange data
     */
    public static function getExchange(int $exchangeId): ?array
    {
        $tenantId = TenantContext::getId();

        $row = DB::table('exchange_requests as e')
            ->join('listings as l', 'e.listing_id', '=', 'l.id')
            ->join('users as req', 'e.requester_id', '=', 'req.id')
            ->join('users as prov', 'e.provider_id', '=', 'prov.id')
            ->leftJoin('users as broker', 'e.broker_id', '=', 'broker.id')
            ->leftJoin('listing_risk_tags as rt', function ($join) {
                $join->on('e.listing_id', '=', 'rt.listing_id')
                    ->whereColumn('rt.tenant_id', '=', 'e.tenant_id');
            })
            ->where('e.id', $exchangeId)
            ->where('e.tenant_id', $tenantId)
            ->select([
                'e.*',
                'l.title as listing_title', 'l.type as listing_type',
                'req.name as requester_name', 'req.email as requester_email', 'req.avatar_url as requester_avatar',
                'prov.name as provider_name', 'prov.email as provider_email', 'prov.avatar_url as provider_avatar',
                'broker.name as broker_name',
                'rt.risk_level',
            ])
            ->first();

        if (!$row) {
            return null;
        }

        return (array) $row;
    }

    /**
     * Get active exchange for a user on a specific listing.
     */
    public static function getActiveExchangeForListing(int $userId, int $listingId): ?array
    {
        $terminalStatuses = [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_EXPIRED];

        $row = ExchangeRequest::where('listing_id', $listingId)
            ->where(function ($q) use ($userId) {
                $q->where('requester_id', $userId)->orWhere('provider_id', $userId);
            })
            ->whereNotIn('status', $terminalStatuses)
            ->orderByDesc('created_at')
            ->first(['id', 'status', 'proposed_hours', 'created_at', 'requester_id', 'provider_id']);

        return $row ? $row->toArray() : null;
    }

    /**
     * Get exchange history.
     *
     * @param int $exchangeId Exchange ID
     * @return array History entries
     */
    public static function getExchangeHistory(int $exchangeId): array
    {
        return ExchangeHistory::with('actor:id,name')
            ->where('exchange_id', $exchangeId)
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Get exchanges for a user with optional filters.
     *
     * @param int $userId User ID
     * @param array $filters Optional filters (status, limit, offset)
     * @return array Paginated result with 'items' and 'total'
     */
    public static function getExchangesForUser(int $userId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();

        $query = DB::table('exchange_requests as e')
            ->join('listings as l', 'e.listing_id', '=', 'l.id')
            ->join('users as req', 'e.requester_id', '=', 'req.id')
            ->join('users as prov', 'e.provider_id', '=', 'prov.id')
            ->where('e.tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('e.requester_id', $userId)->orWhere('e.provider_id', $userId);
            });

        if (!empty($filters['status'])) {
            $query->where('e.status', $filters['status']);
        }

        $total = $query->count();

        $limit = (int) ($filters['limit'] ?? 20);
        $offset = (int) ($filters['offset'] ?? 0);

        $rows = $query->orderByDesc('e.created_at')
            ->offset($offset)
            ->limit($limit)
            ->select([
                'e.*',
                'l.title as listing_title', 'l.type as listing_type',
                'req.name as requester_name',
                'prov.name as provider_name',
            ])
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return [
            'items' => $rows,
            'total' => $total,
        ];
    }

    /**
     * Get exchanges pending broker approval.
     *
     * @return array Paginated result with 'items'
     */
    public static function getPendingBrokerApprovals(): array
    {
        $tenantId = TenantContext::getId();

        $rows = DB::table('exchange_requests as e')
            ->join('listings as l', 'e.listing_id', '=', 'l.id')
            ->join('users as req', 'e.requester_id', '=', 'req.id')
            ->join('users as prov', 'e.provider_id', '=', 'prov.id')
            ->where('e.tenant_id', $tenantId)
            ->where('e.status', self::STATUS_PENDING_BROKER)
            ->orderByDesc('e.created_at')
            ->select([
                'e.*',
                'l.title as listing_title', 'l.type as listing_type',
                'req.name as requester_name',
                'prov.name as provider_name',
            ])
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return [
            'items' => $rows,
        ];
    }

    /**
     * Get exchange statistics for a time period.
     *
     * @param int $days Number of days to look back
     * @return array Statistics
     */
    public static function getStatistics(int $days = 30): array
    {
        $tenantId = TenantContext::getId();
        $since = now()->subDays($days)->toDateTimeString();

        try {
            $total = DB::table('exchange_requests')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $since)
                ->count();

            $completed = DB::table('exchange_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', self::STATUS_COMPLETED)
                ->where('created_at', '>=', $since)
                ->count();

            $pendingBroker = DB::table('exchange_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', self::STATUS_PENDING_BROKER)
                ->where('created_at', '>=', $since)
                ->count();

            $cancelled = DB::table('exchange_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', self::STATUS_CANCELLED)
                ->where('created_at', '>=', $since)
                ->count();

            $disputed = DB::table('exchange_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', self::STATUS_DISPUTED)
                ->where('created_at', '>=', $since)
                ->count();

            return [
                'total' => $total,
                'completed' => $completed,
                'pending_broker' => $pendingBroker,
                'cancelled' => $cancelled,
                'disputed' => $disputed,
                'days' => $days,
            ];
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'completed' => 0,
                'pending_broker' => 0,
                'cancelled' => 0,
                'disputed' => 0,
                'days' => $days,
            ];
        }
    }

    /**
     * Check compliance requirements for an exchange.
     *
     * @param int $listingId Listing ID
     * @param int $providerId Provider user ID
     * @return array Array of violation strings (empty = compliant)
     */
    public static function checkComplianceRequirements(int $listingId, int $providerId): array
    {
        $violations = [];
        $tenantId = TenantContext::getId();

        try {
            $riskTag = DB::table('listing_risk_tags')
                ->where('listing_id', $listingId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$riskTag) {
                return [];
            }

            if (!empty($riskTag->dbs_required)) {
                $hasVetting = DB::table('vetting_records')
                    ->where('user_id', $providerId)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'verified')
                    ->where(function ($q) {
                        $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now());
                    })
                    ->exists();

                if (!$hasVetting) {
                    $violations[] = 'Provider requires valid DBS/vetting check for this listing.';
                }
            }

            if (!empty($riskTag->insurance_required)) {
                $hasInsurance = DB::table('insurance_certificates')
                    ->where('user_id', $providerId)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'verified')
                    ->where(function ($q) {
                        $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now());
                    })
                    ->exists();

                if (!$hasInsurance) {
                    $violations[] = 'Provider requires valid insurance certificate for this listing.';
                }
            }
        } catch (\Exception $e) {
            // Tables may not exist — fail open
        }

        return $violations;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private static function processConfirmations(int $exchangeId, ExchangeRequest $exchange): bool
    {
        $requesterHours = (float) $exchange->requester_confirmed_hours;
        $providerHours = (float) $exchange->provider_confirmed_hours;

        if (abs($requesterHours - $providerHours) < 0.01) {
            return self::completeExchange($exchangeId, $requesterHours);
        }

        $varianceTolerance = 0.25;
        if (abs($requesterHours - $providerHours) <= $varianceTolerance) {
            $finalHours = ($requesterHours + $providerHours) / 2;
            return self::completeExchange($exchangeId, $finalHours);
        }

        // Dispute
        self::updateStatus($exchangeId, self::STATUS_DISPUTED, null, 'system',
            "Hours mismatch: requester=$requesterHours, provider=$providerHours");

        return true;
    }

    private static function completeExchange(int $exchangeId, float $finalHours): bool
    {
        $exchange = ExchangeRequest::find($exchangeId);
        if (!$exchange) {
            return false;
        }

        // Guard: only pending_confirmation or disputed exchanges can be completed
        $allowedStatuses = [self::STATUS_PENDING_CONFIRMATION, self::STATUS_DISPUTED];
        if (!in_array($exchange->status, $allowedStatuses, true)) {
            Log::warning("Exchange #$exchangeId: cannot complete from status '{$exchange->status}'");
            return false;
        }

        // Run the financial transaction first — notifications must NEVER be inside this block.
        // If notifications threw inside the transaction, the credit transfer would roll back.
        $transactionSucceeded = DB::transaction(function () use ($exchangeId, $exchange, $finalHours) {
            // Re-read with lock to prevent double-completion race condition
            $lockedExchange = DB::table('exchange_requests')
                ->where('id', $exchangeId)
                ->lockForUpdate()
                ->first();

            if (!$lockedExchange || $lockedExchange->status === self::STATUS_COMPLETED) {
                Log::warning("Exchange #$exchangeId: already completed (race condition prevented)");
                return false;
            }

            $exchange->update(['final_hours' => $finalHours]);

            // Create transaction via DB (includes balance check + row locking)
            $transactionId = self::createTransaction($exchangeId, $finalHours);
            if ($transactionId) {
                $exchange->update(['transaction_id' => $transactionId]);
            } else {
                // Transaction creation failed (e.g., insufficient balance) — abort
                throw new \RuntimeException("Exchange #$exchangeId: failed to create financial transaction");
            }

            self::updateStatus($exchangeId, self::STATUS_COMPLETED, null, 'system', "Completed with $finalHours hours");

            return true;
        });

        // Send notifications AFTER the transaction has committed — notification failures
        // must never affect the financial transaction or roll back credit transfers.
        if ($transactionSucceeded) {
            try {
                $notificationData = [
                    'exchange_id' => $exchangeId,
                    'hours' => $finalHours,
                    'listing_title' => $exchange->listing->title ?? '',
                ];
                $requesterId = (int) $exchange->requester_id;
                $providerId = (int) $exchange->provider_id;

                NotificationDispatcher::send($requesterId, 'exchange_completed', $notificationData);
                NotificationDispatcher::send($providerId, 'exchange_completed', $notificationData);
            } catch (\Throwable $e) {
                Log::warning("Exchange #{$exchangeId}: notification failed after completion", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $transactionSucceeded;
    }

    private static function createTransaction(int $exchangeId, float $hours): ?int
    {
        $exchangeData = self::getExchange($exchangeId);
        if (!$exchangeData) {
            return null;
        }

        $tenantId = TenantContext::getId();

        try {
            $requesterId = (int) $exchangeData['requester_id'];
            $providerId = (int) $exchangeData['provider_id'];

            // Lock sender row and check balance to prevent negative balances
            $sender = DB::table('users')
                ->where('id', $requesterId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (!$sender || (float) $sender->balance < $hours) {
                Log::warning("Exchange #{$exchangeId}: requester #{$requesterId} has insufficient balance (" . ($sender->balance ?? 0) . " < {$hours})");
                return null;
            }

            // Lock receiver row too for consistency
            DB::table('users')
                ->where('id', $providerId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            DB::table('users')->where('id', $requesterId)->where('tenant_id', $tenantId)
                ->decrement('balance', $hours);
            DB::table('users')->where('id', $providerId)->where('tenant_id', $tenantId)
                ->increment('balance', $hours);

            $transactionId = DB::table('transactions')->insertGetId([
                'tenant_id' => $tenantId,
                'sender_id' => $requesterId,
                'receiver_id' => $providerId,
                'amount' => $hours,
                'description' => "Exchange #$exchangeId for listing: " . ($exchangeData['listing_title'] ?? ''),
                'transaction_type' => 'exchange',
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $transactionId;
        } catch (\Exception $e) {
            Log::error("Failed to create transaction for exchange $exchangeId: " . $e->getMessage());
            return null;
        }
    }

    private static function updateStatus(int $exchangeId, string $newStatus, ?int $actorId, string $actorRole, ?string $notes = null): bool
    {
        return DB::transaction(function () use ($exchangeId, $newStatus, $actorId, $actorRole, $notes) {
            // Lock the row to prevent concurrent status transitions
            $exchange = DB::table('exchange_requests')
                ->where('id', $exchangeId)
                ->lockForUpdate()
                ->first();

            if (!$exchange) {
                return false;
            }

            $oldStatus = $exchange->status;

            $allowedTransitions = self::TRANSITIONS[$oldStatus] ?? [];
            if (!in_array($newStatus, $allowedTransitions, true)) {
                return false;
            }

            DB::table('exchange_requests')
                ->where('id', $exchangeId)
                ->update(['status' => $newStatus]);

            self::logHistory($exchangeId, 'status_changed', $actorId, $actorRole, $oldStatus, $newStatus, $notes);

            return true;
        });
    }

    private static function logHistory(
        int $exchangeId,
        string $action,
        ?int $actorId,
        string $actorRole,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?string $notes = null
    ): void {
        ExchangeHistory::create([
            'exchange_id' => $exchangeId,
            'action' => $action,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    /**
     * Check if an exchange needs broker approval (static).
     *
     * Checks TWO layers:
     * 1. User-level safeguarding: either party has requires_broker_approval in user_messaging_restrictions
     *    (set when a vulnerable person ticks safeguarding checkboxes during onboarding)
     * 2. Listing-level risk: listing risk tags, hours threshold, or global broker approval config
     */
    private static function needsBrokerApproval(int $listingId, float $proposedHours, ?int $requesterId = null, ?int $providerId = null): bool
    {
        $tenantId = TenantContext::getId();

        // LAYER 1: User-level safeguarding flags (always checked, even if exchange workflow is "off")
        // A vulnerable person's exchanges MUST go through broker regardless of tenant config
        try {
            $userIds = array_filter([$requesterId, $providerId]);
            if (!empty($userIds)) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $hasSafeguardingFlag = DB::selectOne(
                    "SELECT COUNT(*) as cnt FROM user_messaging_restrictions
                     WHERE tenant_id = ? AND user_id IN ({$placeholders})
                     AND requires_broker_approval = 1
                     AND (monitoring_expires_at IS NULL OR monitoring_expires_at > NOW())",
                    array_merge([$tenantId], $userIds)
                );
                if ($hasSafeguardingFlag && (int) $hasSafeguardingFlag->cnt > 0) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // Table may not exist — fail open for this check only
        }

        // LAYER 2: Listing-level and tenant-level config checks
        try {
            $configService = app(BrokerControlConfigService::class);
            if (!$configService->isExchangeWorkflowEnabled()) {
                return false;
            }

            $config = $configService->getConfig('exchange_workflow');

            if (empty($config['require_broker_approval'])) {
                return false;
            }

            if (!empty($config['auto_approve_low_risk'])) {
                $isHighRisk = DB::table('listing_risk_tags')
                    ->where('listing_id', $listingId)
                    ->where('tenant_id', $tenantId)
                    ->where('risk_level', 'high')
                    ->exists();

                if ($isHighRisk) {
                    return true;
                }

                $requiresApproval = DB::table('listing_risk_tags')
                    ->where('listing_id', $listingId)
                    ->where('tenant_id', $tenantId)
                    ->where('requires_approval', true)
                    ->exists();

                if ($requiresApproval) {
                    return true;
                }

                $maxHours = (float) ($config['max_hours_without_approval'] ?? 4);
                if ($proposedHours > $maxHours) {
                    return true;
                }

                return false;
            }

            return true;
        } catch (\Exception $e) {
            // If config service not available, default to no broker approval
            return false;
        }
    }
}
