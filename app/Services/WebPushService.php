<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * WebPushService — Laravel DI wrapper for legacy \Nexus\Services\WebPushService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class WebPushService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy WebPushService::sendToUser().
     */
    public function sendToUser($userId, $title, $body, $link = null, $type = 'general', $options = [])
    {
        return \Nexus\Services\WebPushService::sendToUser($userId, $title, $body, $link, $type, $options);
    }

    /**
     * Static proxy for sendToUser — used by code that cannot inject an instance.
     */
    public static function sendToUserStatic($userId, $title, $body, $link = null, $type = 'general', $options = [])
    {
        return \Nexus\Services\WebPushService::sendToUser($userId, $title, $body, $link, $type, $options);
    }

    /**
     * Delegates to legacy WebPushService::sendToUsers().
     */
    public function sendToUsers($userIds, $title, $body, $link = null, $type = 'general', $options = [])
    {
        return \Nexus\Services\WebPushService::sendToUsers($userIds, $title, $body, $link, $type, $options);
    }
}
