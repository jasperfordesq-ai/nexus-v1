<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FCMPushService::sendToUsers().
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FCMPushService::isConfigured().
     */
    public function isConfigured(): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FCMPushService::registerDevice().
     */
    public function registerDevice(int $userId, string $token, string $platform = 'android'): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FCMPushService::unregisterDevice().
     */
    public function unregisterDevice(string $token): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}
