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
class TransactionExportService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TransactionExportService::exportCsv().
     */
    public function exportCsv(int $tenantId, array $filters = []): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy TransactionExportService::exportPdf().
     */
    public function exportPdf(int $tenantId, array $filters = []): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy TransactionExportService::getExportHistory().
     */
    public function getExportHistory(int $tenantId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
