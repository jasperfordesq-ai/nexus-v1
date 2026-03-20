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
class DigestService
{
    public function __construct()
    {
    }

    /**
     * Send weekly digest emails to all eligible users.
     */
    public static function sendWeeklyDigests(): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Render the digest email template.
     */
    private static function renderTemplate(array $user, array $offers, array $requests, array $events): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }
}
