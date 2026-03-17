<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Core\TenantContext;
use App\Services\LegalDocumentService;

/**
 * AdminLegalDocController -- Admin legal document version management and compliance.
 *
 * Converted from legacy delegation to direct service calls.
 * Note: The legacy controller uses v1 response format (success/error), which we preserve here
 * wrapped in the v2 envelope for consistency.
 */
class AdminLegalDocController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly LegalDocumentService $legalDocumentService,
    ) {}

    /** GET /api/v2/admin/legal-docs/{docId}/versions */
    public function getVersions(int $docId): JsonResponse
    {
        $this->requireAdmin();
        try {
            $versions = $this->legalDocumentService->getVersions($docId);
            return $this->respondWithData($versions);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] getVersions error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to fetch versions', null, 500);
        }
    }

    /** GET /api/v2/admin/legal-docs/{docId}/compare */
    public function compareVersions(int $docId): JsonResponse
    {
        $this->requireAdmin();
        $v1 = $this->query('v1');
        $v2 = $this->query('v2');

        if (!$v1 || !$v2) {
            return $this->respondWithError('VALIDATION_ERROR', 'Both v1 and v2 parameters are required', null, 400);
        }

        try {
            $comparison = $this->legalDocumentService->compareVersions((int) $v1, (int) $v2);

            if (!$comparison) {
                return $this->respondWithError('NOT_FOUND', 'One or both versions not found', null, 404);
            }

            if ((int) $comparison['version1']['document_id'] !== $docId || (int) $comparison['version2']['document_id'] !== $docId) {
                return $this->respondWithError('VALIDATION_ERROR', 'Versions do not belong to this document', null, 400);
            }

            return $this->respondWithData($comparison);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] compareVersions error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to compare versions', null, 500);
        }
    }

    /** POST /api/v2/admin/legal-docs/{docId}/versions */
    public function createVersion(int $docId): JsonResponse
    {
        $this->requireAdmin();
        $input = $this->getAllInput();

        if (empty($input['version_number'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'Version number is required', 'version_number', 400);
        }
        if (empty($input['content'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'Content is required', 'content', 400);
        }
        if (empty($input['effective_date'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'Effective date is required', 'effective_date', 400);
        }

        try {
            $versionId = $this->legalDocumentService->createVersion($docId, [
                'version_number' => $input['version_number'],
                'version_label' => $input['version_label'] ?? null,
                'content' => $input['content'],
                'summary_of_changes' => $input['summary_of_changes'] ?? null,
                'effective_date' => $input['effective_date'],
                'is_draft' => $input['is_draft'] ?? true,
            ]);

            return $this->respondWithData(['id' => $versionId], null, 201);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] createVersion error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to create version', null, 500);
        }
    }

    /** POST /api/v2/admin/legal-docs/versions/{vid}/publish */
    public function publishVersion(int $vid): JsonResponse
    {
        $this->requireAdmin();
        try {
            $success = $this->legalDocumentService->publishVersion($vid);

            if ($success) {
                return $this->respondWithData(['published' => true]);
            }
            return $this->respondWithError('SERVER_ERROR', 'Failed to publish version', null, 500);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] publishVersion error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to publish version', null, 500);
        }
    }

    /** GET /api/v2/admin/legal-docs/compliance-stats */
    public function getComplianceStats(): JsonResponse
    {
        $this->requireAdmin();
        try {
            $tenantId = TenantContext::getId();
            $stats = $this->legalDocumentService->getComplianceSummary($tenantId);
            return $this->respondWithData($stats);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] getComplianceStats error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to fetch compliance stats', null, 500);
        }
    }

    /** GET /api/v2/admin/legal-docs/versions/{vid}/acceptances */
    public function getAcceptances(int $vid): JsonResponse
    {
        $this->requireAdmin();
        $limit = $this->queryInt('limit', 50, 1, 200);
        $offset = $this->queryInt('offset', 0, 0);

        try {
            $acceptances = $this->legalDocumentService->getVersionAcceptances($vid, $limit, $offset);
            return $this->respondWithData($acceptances);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] getAcceptances error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to fetch acceptances', null, 500);
        }
    }

    /** GET /api/v2/admin/legal-docs/{docId}/export */
    public function exportAcceptances(int $docId)
    {
        $this->requireAdmin();
        $startDate = $this->query('start_date');
        $endDate = $this->query('end_date');

        try {
            $records = $this->legalDocumentService->exportAcceptanceRecords($docId, $startDate, $endDate);

            $filename = "acceptances_{$docId}_" . date('Y-m-d') . '.csv';

            return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($records) {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Acceptance ID', 'User ID', 'User Name', 'User Email', 'Version Number', 'Accepted At', 'Acceptance Method', 'IP Address']);
                foreach ($records as $record) {
                    fputcsv($output, [
                        $record['acceptance_id'], $record['user_id'], $record['user_name'], $record['user_email'],
                        $record['version_number'], $record['accepted_at'], $record['acceptance_method'], $record['ip_address'] ?? 'N/A',
                    ]);
                }
                fclose($output);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to export acceptances', null, 500);
        }
    }

    /** POST /api/v2/admin/legal-docs/{docId}/versions/{vid}/notify */
    public function notifyUsers(int $docId, int $vid): JsonResponse
    {
        $this->requireAdmin();
        $input = $this->getAllInput();
        $target = $input['target'] ?? 'non_accepted';

        try {
            $count = $this->legalDocumentService->notifyUsersOfUpdate($docId, $vid, true);
            return $this->respondWithData(['notified' => true, 'count' => $count]);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] notifyUsers error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to send notifications', null, 500);
        }
    }

    /** GET /api/v2/admin/legal-docs/{docId}/versions/{vid}/pending */
    public function getUsersPendingCount(int $docId, int $vid): JsonResponse
    {
        $this->requireAdmin();
        try {
            $count = $this->legalDocumentService->getUsersPendingAcceptanceCount($docId, $vid);
            return $this->respondWithData(['count' => $count]);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] getUsersPendingCount error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to fetch count', null, 500);
        }
    }

    /** PUT /api/v2/admin/legal-docs/{docId}/versions/{vid} */
    public function updateVersion(int $docId, int $vid): JsonResponse
    {
        $this->requireAdmin();
        $input = $this->getAllInput();

        try {
            $version = $this->legalDocumentService->getVersion($vid);

            if (!$version) {
                return $this->respondWithError('NOT_FOUND', 'Version not found', null, 404);
            }
            if ((int) $version['document_id'] !== $docId) {
                return $this->respondWithError('VALIDATION_ERROR', 'Version does not belong to this document', null, 400);
            }
            if (!$version['is_draft']) {
                return $this->respondWithError('VALIDATION_ERROR', 'Only draft versions can be edited', null, 400);
            }

            $success = $this->legalDocumentService->updateVersion($vid, [
                'version_number' => $input['version_number'] ?? $version['version_number'],
                'version_label' => $input['version_label'] ?? $version['version_label'],
                'content' => $input['content'] ?? $version['content'],
                'summary_of_changes' => $input['summary_of_changes'] ?? $version['summary_of_changes'],
                'effective_date' => $input['effective_date'] ?? $version['effective_date'],
            ]);

            if ($success) {
                return $this->respondWithData(['updated' => true]);
            }
            return $this->respondWithError('SERVER_ERROR', 'Failed to update version', null, 500);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] updateVersion error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to update version', null, 500);
        }
    }

    /** DELETE /api/v2/admin/legal-docs/{docId}/versions/{vid} */
    public function deleteVersion(int $docId, int $vid): JsonResponse
    {
        $this->requireAdmin();

        try {
            $version = $this->legalDocumentService->getVersion($vid);

            if (!$version) {
                return $this->respondWithError('NOT_FOUND', 'Version not found', null, 404);
            }
            if ((int) $version['document_id'] !== $docId) {
                return $this->respondWithError('VALIDATION_ERROR', 'Version does not belong to this document', null, 400);
            }
            if (!$version['is_draft']) {
                return $this->respondWithError('VALIDATION_ERROR', 'Only draft versions can be deleted', null, 400);
            }

            $success = $this->legalDocumentService->deleteVersion($vid);

            if ($success) {
                return $this->respondWithData(['deleted' => true]);
            }
            return $this->respondWithError('SERVER_ERROR', 'Failed to delete version', null, 500);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] deleteVersion error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to delete version', null, 500);
        }
    }

}
