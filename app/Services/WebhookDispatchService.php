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
class WebhookDispatchService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy WebhookDispatchService::dispatch().
     */
    public static function dispatch(string $eventType, array $payload): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy WebhookDispatchService::getWebhooks().
     */
    public static function getWebhooks(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy WebhookDispatchService::createWebhook().
     */
    public static function createWebhook(int $userId, array $data): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy WebhookDispatchService::updateWebhook().
     */
    public static function updateWebhook(int $id, array $data): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy WebhookDispatchService::deleteWebhook().
     */
    public static function deleteWebhook(int $id): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy WebhookDispatchService::testWebhook().
     */
    public static function testWebhook(int $id): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy WebhookDispatchService::getLogs().
     */
    public static function getLogs(int $id, array $filters = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
