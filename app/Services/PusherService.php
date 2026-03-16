<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * PusherService — Laravel DI wrapper for legacy \Nexus\Services\PusherService.
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
        return \Nexus\Services\PusherService::getInstance();
    }

    /**
     * Delegates to legacy PusherService::getConfig().
     */
    public function getConfig(): array
    {
        return \Nexus\Services\PusherService::getConfig();
    }

    /**
     * Delegates to legacy PusherService::getPublicKey().
     */
    public function getPublicKey(): string
    {
        return \Nexus\Services\PusherService::getPublicKey();
    }

    /**
     * Delegates to legacy PusherService::getCluster().
     */
    public function getCluster(): string
    {
        return \Nexus\Services\PusherService::getCluster();
    }

    /**
     * Delegates to legacy PusherService::isConfigured().
     */
    public function isConfigured(): bool
    {
        return \Nexus\Services\PusherService::isConfigured();
    }
}
