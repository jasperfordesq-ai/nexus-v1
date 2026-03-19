<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * GamificationEmailService — Sends weekly progress digests and achievement notifications.
 *
 * Complex email template rendering — methods kept as TODO delegation to legacy
 * since they involve EmailTemplateBuilder and Mailer which are not yet in Laravel.
 */
class GamificationEmailService
{
    public function __construct()
    {
    }

    /**
     * Send weekly progress digests to users who actually have activity.
     *
     * // TODO: Convert to Eloquent — complex email template rendering with EmailTemplateBuilder
     */
    public function sendWeeklyDigests(): array
    {
        return \Nexus\Services\GamificationEmailService::sendWeeklyDigests();
    }

    /**
     * Generate a user's weekly digest data.
     *
     * // TODO: Convert to Eloquent — complex email template rendering with EmailTemplateBuilder
     */
    public function generateUserDigest(int $userId): array
    {
        return \Nexus\Services\GamificationEmailService::generateUserDigest($userId);
    }

    /**
     * Send a milestone achievement email.
     *
     * // TODO: Convert to Eloquent — complex email template rendering with EmailTemplateBuilder
     */
    public function sendMilestoneEmail(int $userId, string $type, array $data): bool
    {
        return \Nexus\Services\GamificationEmailService::sendMilestoneEmail($userId, $type, $data);
    }
}
