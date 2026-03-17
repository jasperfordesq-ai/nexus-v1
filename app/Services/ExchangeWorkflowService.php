<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ExchangeWorkflowService � Laravel DI wrapper for legacy \Nexus\Services\ExchangeWorkflowService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ExchangeWorkflowService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::initiate().
     */
    public function initiate(int $tenantId, int $listingId, int $requesterId): ?int
    {
        return \Nexus\Services\ExchangeWorkflowService::initiate($tenantId, $listingId, $requesterId);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::accept().
     */
    public function accept(int $tenantId, int $exchangeId, int $userId): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::accept($tenantId, $exchangeId, $userId);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::complete().
     */
    public function complete(int $tenantId, int $exchangeId, int $userId, float $hours): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::complete($tenantId, $exchangeId, $userId, $hours);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::cancel().
     */
    public function cancel(int $tenantId, int $exchangeId, int $userId, ?string $reason = null): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::cancel($tenantId, $exchangeId, $userId, $reason);
    }

    /**
     * Approve an exchange (broker action).
     */
    public function approveExchange(int $exchangeId, int $brokerId, string $notes = '', string $conditions = ''): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::approveExchange($exchangeId, $brokerId, $notes, $conditions);
    }

    /**
     * Reject an exchange (broker action).
     */
    public function rejectExchange(int $exchangeId, int $brokerId, string $reason): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::rejectExchange($exchangeId, $brokerId, $reason);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::createRequest().
     */
    public function createRequest(int $requesterId, int $listingId, array $data): ?int
    {
        return \Nexus\Services\ExchangeWorkflowService::createRequest($requesterId, $listingId, $data);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::acceptRequest().
     */
    public function acceptRequest(int $exchangeId, int $providerId): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::acceptRequest($exchangeId, $providerId);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::declineRequest().
     */
    public function declineRequest(int $exchangeId, int $providerId, string $reason = ''): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::declineRequest($exchangeId, $providerId, $reason);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::startProgress().
     */
    public function startProgress(int $exchangeId, int $userId): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::startProgress($exchangeId, $userId);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::markReadyForConfirmation().
     */
    public function markReadyForConfirmation(int $exchangeId, int $userId): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::markReadyForConfirmation($exchangeId, $userId);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::confirmCompletion().
     */
    public function confirmCompletion(int $exchangeId, int $userId, float $hours): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::confirmCompletion($exchangeId, $userId, $hours);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::cancelExchange().
     */
    public function cancelExchange(int $exchangeId, int $userId, string $reason = ''): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::cancelExchange($exchangeId, $userId, $reason);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::getExchange().
     */
    public function getExchange(int $exchangeId): ?array
    {
        return \Nexus\Services\ExchangeWorkflowService::getExchange($exchangeId);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::getActiveExchangeForListing().
     */
    public function getActiveExchangeForListing(int $userId, int $listingId): ?array
    {
        return \Nexus\Services\ExchangeWorkflowService::getActiveExchangeForListing($userId, $listingId);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::getExchangeHistory().
     */
    public function getExchangeHistory(int $exchangeId): array
    {
        return \Nexus\Services\ExchangeWorkflowService::getExchangeHistory($exchangeId);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::checkComplianceRequirements().
     */
    public function checkComplianceRequirements(int $listingId, int $providerId): array
    {
        return \Nexus\Services\ExchangeWorkflowService::checkComplianceRequirements($listingId, $providerId);
    }
}
