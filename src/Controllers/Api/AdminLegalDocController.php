<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Core\Auth;
use Nexus\Services\LegalDocumentService;

/**
 * Admin Legal Document API Controller
 * Handles version management, compliance tracking, and notifications
 */
class AdminLegalDocController
{
    private function jsonResponse($data, $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    /**
     * Get all versions for a document
     * GET /api/v2/admin/legal-documents/{docId}/versions
     */
    public function getVersions(int $docId): void
    {
        try {
            $versions = LegalDocumentService::getVersions($docId);
            $this->jsonResponse(['success' => true, 'data' => $versions]);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] getVersions error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Failed to fetch versions'], 500);
        }
    }

    /**
     * Compare two versions
     * GET /api/v2/admin/legal-documents/{docId}/versions/compare?v1={id}&v2={id}
     */
    public function compareVersions(int $docId): void
    {
        $v1 = $_GET['v1'] ?? null;
        $v2 = $_GET['v2'] ?? null;

        if (!$v1 || !$v2) {
            $this->jsonResponse(['success' => false, 'error' => 'Both v1 and v2 parameters are required'], 400);
            return;
        }

        try {
            $version1 = LegalDocumentService::getVersion((int) $v1);
            $version2 = LegalDocumentService::getVersion((int) $v2);

            if (!$version1 || !$version2) {
                $this->jsonResponse(['success' => false, 'error' => 'One or both versions not found'], 404);
                return;
            }

            // Simple diff implementation using plain text
            // In production, you'd use a proper diff library
            $diff = $this->generateSimpleDiff($version1['content_plain'], $version2['content_plain']);

            $comparison = [
                'version1' => $version1,
                'version2' => $version2,
                'diff_html' => $diff,
                'changes_count' => substr_count($diff, '<del>') + substr_count($diff, '<ins>')
            ];

            $this->jsonResponse(['success' => true, 'data' => $comparison]);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] compareVersions error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Failed to compare versions'], 500);
        }
    }

    /**
     * Simple diff generator (basic implementation)
     * In production, use a proper diff library like sebastian/diff or jfcherng/php-diff
     */
    private function generateSimpleDiff(string $old, string $new): string
    {
        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);

        $html = '<div class="diff-container">';
        $html .= '<div class="diff-column"><h4>Version 1 (Older)</h4>';
        foreach ($oldLines as $line) {
            $html .= '<p>' . htmlspecialchars($line) . '</p>';
        }
        $html .= '</div>';

        $html .= '<div class="diff-column"><h4>Version 2 (Newer)</h4>';
        foreach ($newLines as $line) {
            $html .= '<p>' . htmlspecialchars($line) . '</p>';
        }
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<style>
            .diff-container { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
            .diff-column { border: 1px solid var(--color-border); padding: 1rem; border-radius: 0.5rem; }
            .diff-column h4 { margin-bottom: 0.5rem; font-weight: bold; }
            .diff-column p { margin: 0.25rem 0; font-size: 0.875rem; }
        </style>';

        return $html;
    }

    /**
     * Create a new version
     * POST /api/v2/admin/legal-documents/{docId}/versions
     */
    public function createVersion(int $docId): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        // Validation
        if (empty($input['version_number'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Version number is required'], 400);
            return;
        }

        if (empty($input['content'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Content is required'], 400);
            return;
        }

        if (empty($input['effective_date'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Effective date is required'], 400);
            return;
        }

        try {
            $versionId = LegalDocumentService::createVersion($docId, [
                'version_number' => $input['version_number'],
                'version_label' => $input['version_label'] ?? null,
                'content' => $input['content'],
                'summary_of_changes' => $input['summary_of_changes'] ?? null,
                'effective_date' => $input['effective_date'],
                'is_draft' => $input['is_draft'] ?? true,
            ]);

            $this->jsonResponse(['success' => true, 'data' => ['id' => $versionId]]);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] createVersion error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Failed to create version'], 500);
        }
    }

    /**
     * Publish a version
     * POST /api/v2/admin/legal-documents/versions/{versionId}/publish
     */
    public function publishVersion(int $versionId): void
    {
        try {
            $success = LegalDocumentService::publishVersion($versionId);

            if ($success) {
                $this->jsonResponse(['success' => true, 'data' => ['published' => true]]);
            } else {
                $this->jsonResponse(['success' => false, 'error' => 'Failed to publish version'], 500);
            }
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] publishVersion error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Failed to publish version'], 500);
        }
    }

    /**
     * Get compliance statistics
     * GET /api/v2/admin/legal-documents/compliance?doc_id={id}
     */
    public function getComplianceStats(): void
    {
        try {
            $docId = $_GET['doc_id'] ?? null;
            $tenantId = TenantContext::getId();

            // If doc_id provided, filter for specific document (not implemented in service yet)
            // For now, return all compliance stats for tenant
            $stats = LegalDocumentService::getComplianceSummary($tenantId);

            $this->jsonResponse(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] getComplianceStats error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Failed to fetch compliance stats'], 500);
        }
    }

    /**
     * Get acceptances for a version
     * GET /api/v2/admin/legal-documents/versions/{versionId}/acceptances?limit={n}&offset={n}
     */
    public function getAcceptances(int $versionId): void
    {
        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = (int) ($_GET['offset'] ?? 0);

        try {
            $acceptances = LegalDocumentService::getVersionAcceptances($versionId, $limit, $offset);
            $this->jsonResponse(['success' => true, 'data' => $acceptances]);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] getAcceptances error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Failed to fetch acceptances'], 500);
        }
    }

    /**
     * Export acceptances as CSV
     * GET /api/v2/admin/legal-documents/{docId}/acceptances/export?start_date={date}&end_date={date}
     */
    public function exportAcceptances(int $docId): void
    {
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        try {
            $records = LegalDocumentService::exportAcceptanceRecords($docId, $startDate, $endDate);

            // Generate CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="acceptances_' . $docId . '_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');

            // Headers
            fputcsv($output, [
                'Acceptance ID',
                'User ID',
                'User Name',
                'User Email',
                'Version Number',
                'Accepted At',
                'Acceptance Method',
                'IP Address'
            ]);

            // Data rows
            foreach ($records as $record) {
                fputcsv($output, [
                    $record['acceptance_id'],
                    $record['user_id'],
                    $record['user_name'],
                    $record['user_email'],
                    $record['version_number'],
                    $record['accepted_at'],
                    $record['acceptance_method'],
                    $record['ip_address'] ?? 'N/A'
                ]);
            }

            fclose($output);
            exit;
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] exportAcceptances error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Failed to export acceptances'], 500);
        }
    }

    /**
     * Send notification to users
     * POST /api/v2/admin/legal-documents/{docId}/versions/{versionId}/notify
     */
    public function notifyUsers(int $docId, int $versionId): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $target = $input['target'] ?? 'non_accepted';

        try {
            // If target is 'all', send to everyone
            $immediate = true; // Send immediately via email

            $count = LegalDocumentService::notifyUsersOfUpdate($docId, $versionId, $immediate);

            $this->jsonResponse(['success' => true, 'data' => ['notified' => true, 'count' => $count]]);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] notifyUsers error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Failed to send notifications'], 500);
        }
    }

    /**
     * Get count of users pending acceptance
     * GET /api/v2/admin/legal-documents/{docId}/versions/{versionId}/pending-count
     */
    public function getUsersPendingCount(int $docId, int $versionId): void
    {
        try {
            $count = LegalDocumentService::getUsersPendingAcceptanceCount($docId, $versionId);
            $this->jsonResponse(['success' => true, 'data' => ['count' => $count]]);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] getUsersPendingCount error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Failed to fetch count'], 500);
        }
    }
}
