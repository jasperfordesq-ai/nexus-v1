<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * DigestService — Laravel DI wrapper for legacy \Nexus\Services\DigestService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class DigestService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy DigestService::sendWeeklyDigests().
     */
    public function sendWeeklyDigests()
    {
        return \Nexus\Services\DigestService::sendWeeklyDigests();
    }
}
