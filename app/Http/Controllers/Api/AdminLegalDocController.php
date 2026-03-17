<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Core\TenantContext;
use Nexus\Services\LegalDocumentService;

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

    public function __construct() {}

    /** GET /api/v2/admin/legal-docs/{docId}/versions */
    public function getVersions(int $docId): JsonResponse
    {
        $this->requireAdmin();
        try {
            $versions = LegalDocumentService::getVersions($docId);
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
            $comparison = LegalDocumentService::compareVersions((int) $v1, (int) $v2);

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
            $versionId = LegalDocumentService::createVersion($docId, [
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
            $success = LegalDocumentService::publishVersion($vid);

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
            $stats = LegalDocumentService::getComplianceSummary($tenantId);
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
            $acceptances = LegalDocumentService::getVersionAcceptances($vid, $limit, $offset);
            return $this->respondWithData($acceptances);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] getAcceptances error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to fetch acceptances', null, 500);
        }
    }

    /** GET /api/v2/admin/legal-docs/{docId}/export -- keep as delegation (CSV download via php://output) */
    public function exportAcceptances(int $docId): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminLegalDocController::class, 'exportAcceptances', [$docId]);
    }

    /** POST /api/v2/admin/legal-docs/{docId}/versions/{vid}/notify */
    public function notifyUsers(int $docId, int $vid): JsonResponse
    {
        $this->requireAdmin();
        $input = $this->getAllInput();
        $target = $input['target'] ?? 'non_accepted';

        try {
            $count = LegalDocumentService::notifyUsersOfUpdate($docId, $vid, true);
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
            $count = LegalDocumentService::getUsersPendingAcceptanceCount($docId, $vid);
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
            $version = LegalDocumentService::getVersion($vid);

            if (!$version) {
                return $this->respondWithError('NOT_FOUND', 'Version not found', null, 404);
            }
            if ((int) $version['document_id'] !== $docId) {
                return $this->respondWithError('VALIDATION_ERROR', 'Version does not belong to this document', null, 400);
            }
            if (!$version['is_draft']) {
                return $this->respondWithError('VALIDATION_ERROR', 'Only draft versions can be edited', null, 400);
            }

            $success = LegalDocumentService::updateVersion($vid, [
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
            $version = LegalDocumentService::getVersion($vid);

            if (!$version) {
                return $this->respondWithError('NOT_FOUND', 'Version not found', null, 404);
            }
            if ((int) $version['document_id'] !== $docId) {
                return $this->respondWithError('VALIDATION_ERROR', 'Version does not belong to this document', null, 400);
            }
            if (!$version['is_draft']) {
                return $this->respondWithError('VALIDATION_ERROR', 'Only draft versions can be deleted', null, 400);
            }

            $success = LegalDocumentService::deleteVersion($vid);

            if ($success) {
                return $this->respondWithData(['deleted' => true]);
            }
            return $this->respondWithError('SERVER_ERROR', 'Failed to delete version', null, 500);
        } catch (\Exception $e) {
            error_log("[AdminLegalDocController] deleteVersion error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to delete version', null, 500);
        }
    }

    /**
     * Delegate to legacy controller for CSV export methods.
     */
    private function delegateLegacy(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
