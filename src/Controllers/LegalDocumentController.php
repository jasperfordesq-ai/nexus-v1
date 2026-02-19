<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Controllers;

use Nexus\Core\Auth;
use Nexus\Core\TenantContext;
use Nexus\Core\View;
use Nexus\Core\SEO;
use Nexus\Services\LegalDocumentService;

/**
 * LegalDocumentController
 *
 * Handles public-facing legal document pages (Terms, Privacy, etc.)
 * with version display and acceptance tracking.
 *
 * @package Nexus\Controllers
 */
class LegalDocumentController
{
    /**
     * Display Terms of Service
     */
    public function terms(): void
    {
        $this->showDocument(LegalDocumentService::TYPE_TERMS, 'Terms of Service');
    }

    /**
     * Display Privacy Policy
     */
    public function privacy(): void
    {
        $this->showDocument(LegalDocumentService::TYPE_PRIVACY, 'Privacy Policy');
    }

    /**
     * Display Cookie Policy
     */
    public function cookies(): void
    {
        $this->showDocument(LegalDocumentService::TYPE_COOKIES, 'Cookie Policy');
    }

    /**
     * Display Accessibility Statement
     */
    public function accessibility(): void
    {
        $this->showDocument(LegalDocumentService::TYPE_ACCESSIBILITY, 'Accessibility Statement');
    }

    /**
     * Display Community Guidelines
     */
    public function communityGuidelines(): void
    {
        $this->showDocument(LegalDocumentService::TYPE_COMMUNITY_GUIDELINES, 'Community Guidelines');
    }

    /**
     * Display Acceptable Use Policy
     */
    public function acceptableUse(): void
    {
        $this->showDocument(LegalDocumentService::TYPE_ACCEPTABLE_USE, 'Acceptable Use Policy');
    }

    /**
     * Show a specific version of a document (for history/archive)
     */
    public function showVersion(int $versionId): void
    {
        $version = LegalDocumentService::getVersion($versionId);

        if (!$version) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // Verify tenant access
        if ($version['tenant_id'] !== TenantContext::getId()) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // Don't show drafts to public
        if ($version['is_draft'] && !Auth::isAdmin()) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        SEO::setTitle($version['title'] . ' - Version ' . $version['version_number']);

        View::render('legal/show-version', [
            'document' => $version,
            'version' => $version,
            'isArchived' => !$version['is_current'],
            'hideHero' => true
        ]);
    }

    /**
     * Show version history for terms
     */
    public function termsVersionHistory(): void
    {
        $this->versionHistory(LegalDocumentService::TYPE_TERMS);
    }

    /**
     * Show version history for privacy
     */
    public function privacyVersionHistory(): void
    {
        $this->versionHistory(LegalDocumentService::TYPE_PRIVACY);
    }

    /**
     * Show version history for a document (public archive)
     */
    public function versionHistory(string $type): void
    {
        $document = LegalDocumentService::getByType($type);

        if (!$document) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $versions = LegalDocumentService::getVersions($document['id']);

        // Filter out drafts for non-admins
        if (!Auth::isAdmin()) {
            $versions = array_filter($versions, fn($v) => !$v['is_draft']);
        }

        SEO::setTitle($document['title'] . ' - Version History');

        View::render('legal/version-history', [
            'document' => $document,
            'versions' => $versions,
            'hideHero' => true
        ]);
    }

    /**
     * API: Accept a document
     */
    public function accept(): void
    {
        header('Content-Type: application/json');

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $documentId = (int) ($input['document_id'] ?? 0);
        $versionId = (int) ($input['version_id'] ?? 0);

        if (!$documentId || !$versionId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing document_id or version_id']);
            return;
        }

        // Verify the version exists and belongs to current tenant
        $version = LegalDocumentService::getVersion($versionId);
        if (!$version || $version['tenant_id'] !== TenantContext::getId()) {
            http_response_code(404);
            echo json_encode(['error' => 'Document version not found']);
            return;
        }

        // Verify it's the current version
        $document = LegalDocumentService::getById($documentId);
        if (!$document || $document['current_version_id'] !== $versionId) {
            http_response_code(400);
            echo json_encode(['error' => 'This is not the current version']);
            return;
        }

        try {
            LegalDocumentService::recordAcceptanceFromRequest(
                Auth::id(),
                $documentId,
                $versionId,
                LegalDocumentService::ACCEPTANCE_SETTINGS
            );

            echo json_encode([
                'success' => true,
                'message' => 'Acceptance recorded',
                'accepted_at' => date('c')
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to record acceptance']);
        }
    }

    /**
     * API: Accept all required documents (for registration/login flow)
     */
    public function acceptAll(): void
    {
        header('Content-Type: application/json');

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        $pending = LegalDocumentService::getDocumentsRequiringAcceptance(Auth::id());

        if (empty($pending)) {
            echo json_encode([
                'success' => true,
                'message' => 'No documents require acceptance',
                'accepted' => []
            ]);
            return;
        }

        $accepted = [];
        $errors = [];

        foreach ($pending as $doc) {
            if (!$doc['current_version_id']) {
                continue;
            }

            try {
                LegalDocumentService::recordAcceptanceFromRequest(
                    Auth::id(),
                    $doc['document_id'],
                    $doc['current_version_id'],
                    LegalDocumentService::ACCEPTANCE_LOGIN_PROMPT
                );

                $accepted[] = [
                    'document_type' => $doc['document_type'],
                    'version' => $doc['current_version']
                ];
            } catch (\Exception $e) {
                $errors[] = $doc['document_type'];
            }
        }

        if (!empty($errors)) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to accept some documents',
                'failed' => $errors,
                'accepted' => $accepted
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'All documents accepted',
            'accepted' => $accepted
        ]);
    }

    /**
     * API: Get user's acceptance status
     */
    public function status(): void
    {
        header('Content-Type: application/json');

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        $status = LegalDocumentService::getUserAcceptanceStatus(Auth::id());

        echo json_encode([
            'success' => true,
            'documents' => $status,
            'has_pending' => LegalDocumentService::hasPendingAcceptances(Auth::id())
        ]);
    }

    /**
     * API: Get legal document content by type (public, no auth required)
     * GET /api/v2/legal/{type}
     */
    public function apiGetDocument(string $type): void
    {
        header('Content-Type: application/json');

        $validTypes = [
            LegalDocumentService::TYPE_TERMS,
            LegalDocumentService::TYPE_PRIVACY,
            LegalDocumentService::TYPE_COOKIES,
            LegalDocumentService::TYPE_ACCESSIBILITY,
            LegalDocumentService::TYPE_COMMUNITY_GUIDELINES,
            LegalDocumentService::TYPE_ACCEPTABLE_USE,
        ];

        if (!in_array($type, $validTypes, true)) {
            http_response_code(404);
            echo json_encode(['error' => 'Document type not found']);
            return;
        }

        $document = LegalDocumentService::getByType($type);

        if (!$document || !$document['content']) {
            // No custom document — React should show its default content
            echo json_encode(['data' => null]);
            return;
        }

        // Check how many published versions exist (for "View changes" link)
        $versions = LegalDocumentService::getVersions((int) $document['id']);
        $publishedCount = count(array_filter($versions, fn($v) => !$v['is_draft']));

        echo json_encode([
            'data' => [
                'id' => (int) $document['id'],
                'document_id' => (int) $document['id'],
                'type' => $document['document_type'],
                'title' => $document['title'],
                'content' => $document['content'],
                'version_number' => $document['version_number'],
                'effective_date' => $document['effective_date'],
                'summary_of_changes' => $document['summary_of_changes'] ?? null,
                'has_previous_versions' => $publishedCount > 1,
            ]
        ]);
    }

    /**
     * API: Get version history for a legal document type (public)
     * GET /api/v2/legal/{type}/versions
     */
    public function apiGetVersions(string $type): void
    {
        header('Content-Type: application/json');

        $validTypes = [
            LegalDocumentService::TYPE_TERMS,
            LegalDocumentService::TYPE_PRIVACY,
            LegalDocumentService::TYPE_COOKIES,
            LegalDocumentService::TYPE_ACCESSIBILITY,
            LegalDocumentService::TYPE_COMMUNITY_GUIDELINES,
            LegalDocumentService::TYPE_ACCEPTABLE_USE,
        ];

        if (!in_array($type, $validTypes, true)) {
            http_response_code(404);
            echo json_encode(['error' => 'Document type not found']);
            return;
        }

        $document = LegalDocumentService::getByType($type);

        if (!$document) {
            echo json_encode(['data' => ['title' => '', 'versions' => []]]);
            return;
        }

        $versions = LegalDocumentService::getVersions((int) $document['id']);

        // Only show published versions to the public
        $published = [];
        foreach ($versions as $v) {
            if ($v['is_draft']) {
                continue;
            }
            $published[] = [
                'id' => (int) $v['id'],
                'version_number' => $v['version_number'],
                'version_label' => $v['version_label'] ?? null,
                'effective_date' => $v['effective_date'],
                'published_at' => $v['published_at'],
                'is_current' => (bool) $v['is_current'],
                'summary_of_changes' => $v['summary_of_changes'] ?? null,
            ];
        }

        echo json_encode([
            'data' => [
                'title' => $document['title'],
                'type' => $document['document_type'],
                'versions' => $published,
            ]
        ]);
    }

    /**
     * API: Get a specific version's content (public)
     * GET /api/v2/legal/version/{versionId}
     */
    public function apiGetVersion(int $versionId): void
    {
        header('Content-Type: application/json');

        $version = LegalDocumentService::getVersion($versionId);

        if (!$version) {
            http_response_code(404);
            echo json_encode(['error' => 'Version not found']);
            return;
        }

        // Verify tenant access
        if ((int) $version['tenant_id'] !== TenantContext::getId()) {
            http_response_code(404);
            echo json_encode(['error' => 'Version not found']);
            return;
        }

        // Don't show drafts
        if ($version['is_draft']) {
            http_response_code(404);
            echo json_encode(['error' => 'Version not found']);
            return;
        }

        echo json_encode([
            'data' => [
                'id' => (int) $version['id'],
                'document_type' => $version['document_type'],
                'title' => $version['title'],
                'version_number' => $version['version_number'],
                'version_label' => $version['version_label'] ?? null,
                'content' => $version['content'],
                'effective_date' => $version['effective_date'],
                'published_at' => $version['published_at'],
                'is_current' => (bool) $version['is_current'],
                'summary_of_changes' => $version['summary_of_changes'] ?? null,
            ]
        ]);
    }

    /**
     * Common method to show a legal document
     */
    private function showDocument(string $type, string $fallbackTitle): void
    {
        $document = LegalDocumentService::getByType($type);

        // If no versioned document exists, fall back to legacy file-based system
        if (!$document || !$document['content']) {
            $this->showLegacyDocument($type, $fallbackTitle);
            return;
        }

        SEO::setTitle($document['title']);
        SEO::setDescription("Read our {$document['title']} - Last updated " . date('F j, Y', strtotime($document['effective_date'])));

        // Get current user's acceptance status
        $acceptanceStatus = null;
        if (Auth::check()) {
            $acceptanceStatus = LegalDocumentService::hasAcceptedCurrent(Auth::id(), $type)
                ? 'current'
                : 'pending';
        }

        View::render('legal/show', [
            'document' => $document,
            'documentType' => $type,
            'acceptanceStatus' => $acceptanceStatus,
            'hideHero' => true
        ]);
    }

    /**
     * Fall back to legacy file-based documents
     * This ensures backward compatibility during migration
     */
    private function showLegacyDocument(string $type, string $fallbackTitle): void
    {
        SEO::setTitle($fallbackTitle);

        // Check for tenant-specific override first
        $tenant = TenantContext::get();
        $tenantSlug = $tenant['slug'] ?? '';
        $layout = layout();

        // Try tenant-specific file
        $tenantFile = __DIR__ . "/../../views/tenants/{$tenantSlug}/{$layout}/pages/{$type}.php";
        if (file_exists($tenantFile)) {
            require $tenantFile;
            return;
        }

        // Try layout-specific file
        $layoutFile = __DIR__ . "/../../views/{$layout}/pages/{$type}.php";
        if (file_exists($layoutFile)) {
            require $layoutFile;
            return;
        }

        // Try generic pages file
        $genericFile = __DIR__ . "/../../views/pages/{$type}.php";
        if (file_exists($genericFile)) {
            require $genericFile;
            return;
        }

        // 404 if nothing found
        http_response_code(404);
        View::render('errors/404');
    }
}
