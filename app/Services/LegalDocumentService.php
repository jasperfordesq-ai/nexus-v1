<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * LegalDocumentService — Laravel DI-based service for legal document management.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\LegalDocumentService.
 * Manages versioned legal documents (ToS, Privacy, etc.) with acceptance tracking.
 */
class LegalDocumentService
{
    public const TYPE_TERMS = 'terms';
    public const TYPE_PRIVACY = 'privacy';
    public const TYPE_COOKIES = 'cookies';
    public const TYPE_COMMUNITY = 'community_guidelines';

    /**
     * Get a legal document by type for the current tenant.
     */
    public function getDocument(string $type): ?array
    {
        $record = DB::table('legal_documents as ld')
            ->leftJoin('legal_document_versions as ldv', 'ld.current_version_id', '=', 'ldv.id')
            ->where('ld.document_type', $type)
            ->where('ld.is_active', true)
            ->select('ld.*', 'ldv.version_number', 'ldv.content', 'ldv.effective_date', 'ldv.summary_of_changes')
            ->first();

        return $record ? (array) $record : null;
    }

    /**
     * Get all versions of a legal document.
     */
    public function getVersions(int $documentId): array
    {
        return DB::table('legal_document_versions')
            ->where('legal_document_id', $documentId)
            ->orderByDesc('version_number')
            ->get()
            ->map(fn ($v) => (array) $v)
            ->all();
    }

    /**
     * Record acceptance of all current legal documents for a user.
     */
    public function acceptAll(int $userId, string $method = 'registration'): int
    {
        $documents = DB::table('legal_documents')
            ->where('is_active', true)
            ->whereNotNull('current_version_id')
            ->get();

        $accepted = 0;
        foreach ($documents as $doc) {
            $exists = DB::table('legal_document_acceptances')
                ->where('user_id', $userId)
                ->where('document_version_id', $doc->current_version_id)
                ->exists();

            if (! $exists) {
                DB::table('legal_document_acceptances')->insert([
                    'user_id'             => $userId,
                    'legal_document_id'   => $doc->id,
                    'document_version_id' => $doc->current_version_id,
                    'acceptance_method'   => $method,
                    'ip_address'          => request()->ip(),
                    'accepted_at'         => now(),
                    'created_at'          => now(),
                ]);
                $accepted++;
            }
        }

        return $accepted;
    }

    /**
     * Check if a user has accepted the current version of a document type.
     */
    public function hasAccepted(int $userId, string $type): bool
    {
        $doc = DB::table('legal_documents')
            ->where('document_type', $type)
            ->where('is_active', true)
            ->first();

        if (! $doc || ! $doc->current_version_id) {
            return true;
        }

        return DB::table('legal_document_acceptances')
            ->where('user_id', $userId)
            ->where('document_version_id', $doc->current_version_id)
            ->exists();
    }

    // =========================================================================
    // Legacy delegation methods — used by AdminEnterpriseController and
    // AdminLegalDocController until full Eloquent migration is complete.
    // =========================================================================

    /**
     * Delegates to legacy LegalDocumentService::getAllForTenant().
     */
    public function getAllForTenant(int $tenantId): array
    {
        return \Nexus\Services\LegalDocumentService::getAllForTenant($tenantId);
    }

    /**
     * Delegates to legacy LegalDocumentService::createDocument().
     */
    public function createDocument(array $data): array
    {
        return \Nexus\Services\LegalDocumentService::createDocument($data);
    }

    /**
     * Delegates to legacy LegalDocumentService::updateDocument().
     */
    public function updateDocument(int $id, array $data): ?array
    {
        return \Nexus\Services\LegalDocumentService::updateDocument($id, $data);
    }

    /**
     * Delegates to legacy LegalDocumentService::compareVersions().
     */
    public function compareVersions(int $v1, int $v2): ?array
    {
        return \Nexus\Services\LegalDocumentService::compareVersions($v1, $v2);
    }

    /**
     * Delegates to legacy LegalDocumentService::createVersion().
     */
    public function createVersion(int $docId, array $data): int
    {
        return \Nexus\Services\LegalDocumentService::createVersion($docId, $data);
    }

    /**
     * Delegates to legacy LegalDocumentService::publishVersion().
     */
    public function publishVersion(int $vid): bool
    {
        return \Nexus\Services\LegalDocumentService::publishVersion($vid);
    }

    /**
     * Delegates to legacy LegalDocumentService::getComplianceSummary().
     */
    public function getComplianceSummary(int $tenantId): array
    {
        return \Nexus\Services\LegalDocumentService::getComplianceSummary($tenantId);
    }

    /**
     * Delegates to legacy LegalDocumentService::getVersionAcceptances().
     */
    public function getVersionAcceptances(int $vid, int $limit = 50, int $offset = 0): array
    {
        return \Nexus\Services\LegalDocumentService::getVersionAcceptances($vid, $limit, $offset);
    }

    /**
     * Delegates to legacy LegalDocumentService::exportAcceptanceRecords().
     */
    public function exportAcceptanceRecords(int $docId, ?string $startDate = null, ?string $endDate = null): array
    {
        return \Nexus\Services\LegalDocumentService::exportAcceptanceRecords($docId, $startDate, $endDate);
    }

    /**
     * Delegates to legacy LegalDocumentService::notifyUsersOfUpdate().
     */
    public function notifyUsersOfUpdate(int $docId, int $vid, bool $sendEmail = true): int
    {
        return \Nexus\Services\LegalDocumentService::notifyUsersOfUpdate($docId, $vid, $sendEmail);
    }

    /**
     * Delegates to legacy LegalDocumentService::getUsersPendingAcceptanceCount().
     */
    public function getUsersPendingAcceptanceCount(int $docId, int $vid): int
    {
        return \Nexus\Services\LegalDocumentService::getUsersPendingAcceptanceCount($docId, $vid);
    }

    /**
     * Delegates to legacy LegalDocumentService::getVersion().
     */
    public function getVersion(int $vid): ?array
    {
        return \Nexus\Services\LegalDocumentService::getVersion($vid);
    }

    /**
     * Delegates to legacy LegalDocumentService::updateVersion().
     */
    public function updateVersion(int $vid, array $data): bool
    {
        return \Nexus\Services\LegalDocumentService::updateVersion($vid, $data);
    }

    /**
     * Delegates to legacy LegalDocumentService::deleteVersion().
     */
    public function deleteVersion(int $vid): bool
    {
        return \Nexus\Services\LegalDocumentService::deleteVersion($vid);
    }

    /**
     * Delegates to legacy LegalDocumentService::getByType().
     */
    public function getByType(string $type): ?array
    {
        return \Nexus\Services\LegalDocumentService::getByType($type);
    }

    /**
     * Delegates to legacy LegalDocumentService::getById().
     */
    public function legacyGetById(int $id): ?array
    {
        return \Nexus\Services\LegalDocumentService::getById($id);
    }

    /**
     * Delegates to legacy LegalDocumentService::recordAcceptanceFromRequest().
     */
    public function recordAcceptanceFromRequest(int $userId, int $documentId, int $versionId, string $method): void
    {
        \Nexus\Services\LegalDocumentService::recordAcceptanceFromRequest($userId, $documentId, $versionId, $method);
    }

    /**
     * Delegates to legacy LegalDocumentService::getUserAcceptanceStatus().
     */
    public function getUserAcceptanceStatus(int $userId): array
    {
        return \Nexus\Services\LegalDocumentService::getUserAcceptanceStatus($userId);
    }

    /**
     * Delegates to legacy LegalDocumentService::hasPendingAcceptances().
     */
    public function hasPendingAcceptances(int $userId): bool
    {
        return \Nexus\Services\LegalDocumentService::hasPendingAcceptances($userId);
    }

    /**
     * Get legacy versions by document ID (delegates to legacy).
     */
    public function legacyGetVersions(int $documentId): array
    {
        return \Nexus\Services\LegalDocumentService::getVersions($documentId);
    }

    /**
     * Get a current document by slug and tenant ID.
     */
    public function getCurrentDocument(string $slug, int $tenantId): ?array
    {
        $record = DB::table('legal_documents as ld')
            ->leftJoin('legal_document_versions as ldv', 'ld.current_version_id', '=', 'ldv.id')
            ->where('ld.slug', $slug)
            ->where('ld.tenant_id', $tenantId)
            ->where('ld.is_active', true)
            ->select('ld.*', 'ldv.version_number', 'ldv.content', 'ldv.effective_date', 'ldv.summary_of_changes')
            ->first();

        return $record ? (array) $record : null;
    }
}
