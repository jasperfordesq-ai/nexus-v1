<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ReportExportService — Laravel DI wrapper for legacy \Nexus\Services\ReportExportService.
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
        return \Nexus\Services\ReportExportService::export($type, $tenantId, $filters);
    }

    /**
     * Delegates to legacy ReportExportService::sendCSVDownload().
     */
    public function sendCSVDownload(string $csv, string $filename): void
    {
        \Nexus\Services\ReportExportService::sendCSVDownload($csv, $filename);
    }

    /**
     * Delegates to legacy ReportExportService::getSupportedTypes().
     */
    public function getSupportedTypes(): array
    {
        return \Nexus\Services\ReportExportService::getSupportedTypes();
    }

    /**
     * Delegates to legacy ReportExportService::exportPdf().
     */
    public function exportPdf(string $type, int $tenantId, array $filters = []): array
    {
        return \Nexus\Services\ReportExportService::exportPdf($type, $tenantId, $filters);
    }

    /**
     * Delegates to legacy ReportExportService::sendPdfDownload().
     */
    public function sendPdfDownload(string $pdf, string $filename): void
    {
        \Nexus\Services\ReportExportService::sendPdfDownload($pdf, $filename);
    }
}
