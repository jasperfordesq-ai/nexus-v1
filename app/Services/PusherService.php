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
class PusherService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy PusherService::getInstance().
     */
    public function getInstance(): ?Pusher
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy PusherService::getConfig().
     */
    public function getConfig(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy PusherService::getPublicKey().
     */
    public function getPublicKey(): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy PusherService::getCluster().
     */
    public function getCluster(): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy PusherService::isConfigured().
     */
    public function isConfigured(): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy PusherService::getUserChannel().
     */
    public function getUserChannel(int $userId): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy PusherService::getPresenceChannel().
     */
    public function getPresenceChannel(): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy PusherService::authPrivateChannel().
     */
    public function authPrivateChannel(string $channelName, string $socketId, int $userId): ?string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy PusherService::authPresenceChannel().
     */
    public function authPresenceChannel(string $channelName, string $socketId, int $userId, array $userInfo = []): ?string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
