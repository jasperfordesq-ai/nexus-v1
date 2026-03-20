<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * RegistrationOrchestrationService — Thin delegate forwarding to \App\Services\Identity\RegistrationOrchestrationService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Identity\RegistrationOrchestrationService
 */
class RegistrationOrchestrationService
{

    public static function processRegistration(int $userId, int $tenantId): array
    {
        return \App\Services\Identity\RegistrationOrchestrationService::processRegistration($userId, $tenantId);
    }

    public static function initiateVerification(int $userId, int $tenantId): array
    {
        return \App\Services\Identity\RegistrationOrchestrationService::initiateVerification($userId, $tenantId);
    }

    public static function handleVerificationResult(int $sessionId, string $status, array $result): void
    {
        \App\Services\Identity\RegistrationOrchestrationService::handleVerificationResult($sessionId, $status, $result);
    }

    public static function applyPostVerificationAction(int $userId, int $tenantId, string $verificationStatus): void
    {
        \App\Services\Identity\RegistrationOrchestrationService::applyPostVerificationAction($userId, $tenantId, $verificationStatus);
    }

    public static function triggerFallback(int $userId, int $tenantId, string $reason): array
    {
        return \App\Services\Identity\RegistrationOrchestrationService::triggerFallback($userId, $tenantId, $reason);
    }

    public static function getRegistrationStatus(int $userId, int $tenantId): array
    {
        return \App\Services\Identity\RegistrationOrchestrationService::getRegistrationStatus($userId, $tenantId);
    }

    public static function adminReview(int $sessionId, int $adminId, string $decision): array
    {
        return \App\Services\Identity\RegistrationOrchestrationService::adminReview($sessionId, $adminId, $decision);
    }

    public static function sendVerificationReminders(): int
    {
        return \App\Services\Identity\RegistrationOrchestrationService::sendVerificationReminders();
    }

    public static function expireAbandonedSessions(): int
    {
        return \App\Services\Identity\RegistrationOrchestrationService::expireAbandonedSessions();
    }

    public static function purgeOldSessions(int $retentionDays = 180): int
    {
        return \App\Services\Identity\RegistrationOrchestrationService::purgeOldSessions($retentionDays);
    }
}
