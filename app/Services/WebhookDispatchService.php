<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * WebhookDispatchService — Laravel DI wrapper for legacy \Nexus\Services\WebhookDispatchService.
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
    public function dispatch(string $eventType, array $payload): void
    {
        \Nexus\Services\WebhookDispatchService::dispatch($eventType, $payload);
    }

    /**
     * Delegates to legacy WebhookDispatchService::getWebhooks().
     */
    public function getWebhooks(): array
    {
        return \Nexus\Services\WebhookDispatchService::getWebhooks();
    }

    /**
     * Delegates to legacy WebhookDispatchService::createWebhook().
     */
    public function createWebhook(int $userId, array $data): array
    {
        return \Nexus\Services\WebhookDispatchService::createWebhook($userId, $data);
    }

    /**
     * Delegates to legacy WebhookDispatchService::updateWebhook().
     */
    public function updateWebhook(int $id, array $data): bool
    {
        return \Nexus\Services\WebhookDispatchService::updateWebhook($id, $data);
    }

    /**
     * Delegates to legacy WebhookDispatchService::deleteWebhook().
     */
    public function deleteWebhook(int $id): bool
    {
        return \Nexus\Services\WebhookDispatchService::deleteWebhook($id);
    }
}
