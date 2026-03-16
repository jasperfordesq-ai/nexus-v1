<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * PollExportService — Laravel DI wrapper for legacy \Nexus\Services\PollExportService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class PollExportService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy PollExportService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\PollExportService::getErrors();
    }

    /**
     * Delegates to legacy PollExportService::exportToCsv().
     */
    public function exportToCsv(int $pollId, int $userId): ?string
    {
        return \Nexus\Services\PollExportService::exportToCsv($pollId, $userId);
    }
}
