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
}
