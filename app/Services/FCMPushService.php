<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FCMPushService — Laravel DI wrapper for legacy \Nexus\Services\FCMPushService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FCMPushService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FCMPushService::sendToUser().
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        return \Nexus\Services\FCMPushService::sendToUser($userId, $title, $body, $data);
    }

    /**
     * Delegates to legacy FCMPushService::sendToUsers().
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        return \Nexus\Services\FCMPushService::sendToUsers($userIds, $title, $body, $data);
    }

    /**
     * Delegates to legacy FCMPushService::isConfigured().
     */
    public function isConfigured(): bool
    {
        return \Nexus\Services\FCMPushService::isConfigured();
    }

    /**
     * Delegates to legacy FCMPushService::registerDevice().
     */
    public function registerDevice(int $userId, string $token, string $platform = 'android'): bool
    {
        return \Nexus\Services\FCMPushService::registerDevice($userId, $token, $platform);
    }

    /**
     * Delegates to legacy FCMPushService::unregisterDevice().
     */
    public function unregisterDevice(string $token): bool
    {
        return \Nexus\Services\FCMPushService::unregisterDevice($token);
    }
}
