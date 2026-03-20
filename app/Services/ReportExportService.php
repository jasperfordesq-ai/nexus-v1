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
class ReportExportService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ReportExportService::export().
     */
    public function export(string $type, int $tenantId, array $filters = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy ReportExportService::sendCSVDownload().
     */
    public function sendCSVDownload(string $csv, string $filename): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy ReportExportService::getSupportedTypes().
     */
    public function getSupportedTypes(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy ReportExportService::exportPdf().
     */
    public function exportPdf(string $type, int $tenantId, array $filters = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy ReportExportService::sendPdfDownload().
     */
    public function sendPdfDownload(string $pdf, string $filename): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }
}
