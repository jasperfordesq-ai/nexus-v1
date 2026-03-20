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

        $needsBroker = self::needsBrokerApproval($exchange->listing_id, (float) $exchange->proposed_hours);
        $newStatus = $needsBroker ? self::STATUS_PENDING_BROKER : self::STATUS_ACCEPTED;

        return self::updateStatus($exchangeId, $newStatus, $providerId, 'provider', 'Provider accepted request');
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

        return self::updateStatus($exchangeId, self::STATUS_CANCELLED, $providerId, 'provider', $reason ?: 'Provider declined');
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

        return self::updateStatus($exchangeId, self::STATUS_ACCEPTED, $brokerId, 'broker', $notes ?: 'Broker approved');
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

        return self::updateStatus($exchangeId, self::STATUS_CANCELLED, $brokerId, 'broker', $reason);
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
        return self::updateStatus($exchangeId, self::STATUS_IN_PROGRESS, $userId, $role, 'Work started');
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
        return self::updateStatus($exchangeId, self::STATUS_PENDING_CONFIRMATION, $userId, $role, 'Work completed, pending confirmation');
    }

    /**
     * Confirm completion with hours.
     */
    public function confirmCompletion(int $exchangeId, int $userId, float $hours): bool
    {
        $exchange = ExchangeRequest::find($exchangeId);
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

        // Adjust hours based on config
        $config = $this->configService->getConfig('exchange_workflow');
        $allowVariance = $config['allow_hour_adjustment'] ?? true;
        $maxVariance = $config['max_hour_variance_percent'] ?? 25;

        if (!$allowVariance) {
            $hours = (float) $exchange->proposed_hours;
        } else {
            $minHours = (float) $exchange->proposed_hours * (1 - $maxVariance / 100);
            $maxHours = (float) $exchange->proposed_hours * (1 + $maxVariance / 100);
            $hours = max($minHours, min($maxHours, $hours));
        }

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
            self::updateStatus($exchangeId, self::STATUS_PENDING_CONFIRMATION, $userId, $isRequester ? 'requester' : 'provider');
        }

        // Refresh and check if both confirmed
        $exchange->refresh();
        if ($exchange->requester_confirmed_at && $exchange->provider_confirmed_at) {
            return self::processConfirmations($exchangeId, $exchange);
        }

        return true;
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

        return self::updateStatus($exchangeId, self::STATUS_CANCELLED, $userId, $role, $reason ?: 'Cancelled');
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
                // Check vetting via DB — VettingService may not yet be converted
                $hasVetting = DB::table('user_vetting_records')
                    ->where('user_id', $providerId)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'valid')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
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
                    ->where('status', 'valid')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
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

        return DB::transaction(function () use ($exchangeId, $exchange, $finalHours) {
            $exchange->update(['final_hours' => $finalHours]);

            // Create transaction via DB
            $transactionId = self::createTransaction($exchangeId, $finalHours);
            if ($transactionId) {
                $exchange->update(['transaction_id' => $transactionId]);
            }

            self::updateStatus($exchangeId, self::STATUS_COMPLETED, null, 'system', "Completed with $finalHours hours");

            return true;
        });
    }

    private static function createTransaction(int $exchangeId, float $hours): ?int
    {
        $exchangeData = self::getExchange($exchangeId);
        if (!$exchangeData) {
            return null;
        }

        $tenantId = TenantContext::getId();

        try {
            // Deduct from requester, credit provider
            $requesterId = (int) $exchangeData['requester_id'];
            $providerId = (int) $exchangeData['provider_id'];

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
                'created_at' => now(),
            ]);

            return $transactionId;
        } catch (\Exception $e) {
            Log::error("Failed to create transaction for exchange $exchangeId: " . $e->getMessage());
            return null;
        }
    }

    private static function updateStatus(int $exchangeId, string $newStatus, ?int $actorId, string $actorRole, ?string $notes = null): bool
    {
        $exchange = ExchangeRequest::find($exchangeId);
        if (!$exchange) {
            return false;
        }

        $oldStatus = $exchange->status;

        $allowedTransitions = self::TRANSITIONS[$oldStatus] ?? [];
        if (!in_array($newStatus, $allowedTransitions, true)) {
            return false;
        }

        $exchange->update(['status' => $newStatus]);

        self::logHistory($exchangeId, 'status_changed', $actorId, $actorRole, $oldStatus, $newStatus, $notes);

        return true;
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

    private function needsBrokerApproval(int $listingId, float $proposedHours): bool
    {
        if (!$this->configService->isExchangeWorkflowEnabled()) {
            return false;
        }

        $config = $this->configService->getConfig('exchange_workflow');

        if (empty($config['require_broker_approval'])) {
            return false;
        }

        if (!empty($config['auto_approve_low_risk'])) {
            $tenantId = TenantContext::getId();

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
    }
}
