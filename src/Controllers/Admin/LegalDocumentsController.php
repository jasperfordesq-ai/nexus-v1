<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin;

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Services\LegalDocumentService;

/**
 * LegalDocumentsController
 *
 * Admin interface for managing legal documents, versions, and viewing acceptance records.
 *
 * @package Nexus\Controllers\Admin
 */
class LegalDocumentsController
{
    /**
     * Check if user has admin access
     *
     * @param bool $jsonResponse Return JSON error instead of redirect
     */
    private function checkAdmin(bool $jsonResponse = false): void
    {
        if (!isset($_SESSION['user_id'])) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }
            header('HTTP/1.0 403 Forbidden');
            echo "Access Denied";
            exit;
        }
    }

    /**
     * List all legal documents
     */
    public function index(): void
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();
        $documents = LegalDocumentService::getAllForTenant($tenantId);
        $complianceStats = LegalDocumentService::getComplianceSummary($tenantId);

        $this->render('admin/legal-documents/index', [
            'pageTitle' => 'Legal Documents',
            'documents' => $documents,
            'complianceStats' => $complianceStats
        ]);
    }

    /**
     * Show a specific document with its versions
     */
    public function show(int $id): void
    {
        $this->checkAdmin();
        $document = LegalDocumentService::getById($id);

        if (!$document || $document['tenant_id'] !== TenantContext::getId()) {
            $this->notFound();
            return;
        }

        $versions = LegalDocumentService::getVersions($id);
        $stats = LegalDocumentService::getDocumentStats($id);

        $this->render('admin/legal-documents/show', [
            'pageTitle' => $document['title'],
            'document' => $document,
            'versions' => $versions,
            'stats' => $stats
        ]);
    }

    /**
     * Show form to create new document
     */
    public function create(): void
    {
        $this->checkAdmin();
        $this->render('admin/legal-documents/create', [
            'pageTitle' => 'Create Legal Document',
            'documentTypes' => $this->getDocumentTypes()
        ]);
    }

    /**
     * Store a new document
     */
    public function store(): void
    {
        $this->checkAdmin();
        Csrf::validate($_POST['csrf_token'] ?? '');

        $data = [
            'document_type' => $_POST['document_type'] ?? '',
            'title' => trim($_POST['title'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'requires_acceptance' => isset($_POST['requires_acceptance']) ? 1 : 0,
            'acceptance_required_for' => $_POST['acceptance_required_for'] ?? 'registration',
            'notify_on_update' => isset($_POST['notify_on_update']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        // Validation
        $errors = $this->validateDocument($data);
        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $data;
            header('Location: /admin-legacy/legal-documents/create');
            exit;
        }

        try {
            $documentId = LegalDocumentService::createDocument($data);

            $_SESSION['flash_success'] = 'Legal document created successfully. Now create the first version.';
            header("Location: /admin-legacy/legal-documents/{$documentId}/versions/create");
            exit;
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to create document: ' . $e->getMessage();
            header('Location: /admin-legacy/legal-documents/create');
            exit;
        }
    }

    /**
     * Show form to edit document settings
     */
    public function edit(int $id): void
    {
        $this->checkAdmin();
        $document = LegalDocumentService::getById($id);

        if (!$document || $document['tenant_id'] !== TenantContext::getId()) {
            $this->notFound();
            return;
        }

        $this->render('admin/legal-documents/edit', [
            'pageTitle' => 'Edit ' . $document['title'],
            'document' => $document
        ]);
    }

    /**
     * Update document settings
     */
    public function update(int $id): void
    {
        $this->checkAdmin();
        Csrf::validate($_POST['csrf_token'] ?? '');

        $document = LegalDocumentService::getById($id);
        if (!$document || $document['tenant_id'] !== TenantContext::getId()) {
            $this->notFound();
            return;
        }

        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'requires_acceptance' => isset($_POST['requires_acceptance']) ? 1 : 0,
            'acceptance_required_for' => $_POST['acceptance_required_for'] ?? 'registration',
            'notify_on_update' => isset($_POST['notify_on_update']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        try {
            LegalDocumentService::updateDocument($id, $data);

            $_SESSION['flash_success'] = 'Document settings updated successfully.';
            header("Location: /admin-legacy/legal-documents/{$id}");
            exit;
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to update document: ' . $e->getMessage();
            header("Location: /admin-legacy/legal-documents/{$id}/edit");
            exit;
        }
    }

    // =========================================================================
    // VERSION MANAGEMENT
    // =========================================================================

    /**
     * Show form to create new version
     */
    public function createVersion(int $documentId): void
    {
        $this->checkAdmin();
        $document = LegalDocumentService::getById($documentId);

        if (!$document || $document['tenant_id'] !== TenantContext::getId()) {
            $this->notFound();
            return;
        }

        // Get current version for reference
        $currentVersion = LegalDocumentService::getCurrentVersion($documentId);

        // Suggest next version number
        $suggestedVersion = $this->suggestNextVersion($documentId);

        $this->render('admin/legal-documents/versions/create', [
            'pageTitle' => 'Create New Version - ' . $document['title'],
            'document' => $document,
            'currentVersion' => $currentVersion,
            'suggestedVersion' => $suggestedVersion
        ]);
    }

    /**
     * Store a new version
     */
    public function storeVersion(int $documentId): void
    {
        $this->checkAdmin();
        Csrf::validate($_POST['csrf_token'] ?? '');

        $document = LegalDocumentService::getById($documentId);
        if (!$document || $document['tenant_id'] !== TenantContext::getId()) {
            $this->notFound();
            return;
        }

        $data = [
            'version_number' => trim($_POST['version_number'] ?? ''),
            'version_label' => trim($_POST['version_label'] ?? '') ?: null,
            'content' => $_POST['content'] ?? '',
            'summary_of_changes' => trim($_POST['summary_of_changes'] ?? '') ?: null,
            'effective_date' => $_POST['effective_date'] ?? date('Y-m-d'),
            'is_draft' => !isset($_POST['publish_immediately'])
        ];

        // Validation
        $errors = $this->validateVersion($data, $documentId);
        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $data;
            header("Location: /admin-legacy/legal-documents/{$documentId}/versions/create");
            exit;
        }

        try {
            $versionId = LegalDocumentService::createVersion($documentId, $data);

            // If publish immediately was checked, publish the version
            if (isset($_POST['publish_immediately'])) {
                LegalDocumentService::publishVersion($versionId);
                $_SESSION['flash_success'] = 'Version created and published successfully.';
            } else {
                $_SESSION['flash_success'] = 'Version created as draft. Publish when ready.';
            }

            header("Location: /admin-legacy/legal-documents/{$documentId}");
            exit;
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to create version: ' . $e->getMessage();
            header("Location: /admin-legacy/legal-documents/{$documentId}/versions/create");
            exit;
        }
    }

    /**
     * Show form to edit a version (drafts only)
     */
    public function editVersion(int $documentId, int $versionId): void
    {
        $this->checkAdmin();
        $document = LegalDocumentService::getById($documentId);
        $version = LegalDocumentService::getVersion($versionId);

        if (!$document || !$version || $document['tenant_id'] !== TenantContext::getId()) {
            $this->notFound();
            return;
        }

        if (!$version['is_draft']) {
            $_SESSION['flash_error'] = 'Published versions cannot be edited. Create a new version instead.';
            header("Location: /admin-legacy/legal-documents/{$documentId}");
            exit;
        }

        $this->render('admin/legal-documents/versions/edit', [
            'pageTitle' => 'Edit Version ' . $version['version_number'],
            'document' => $document,
            'version' => $version
        ]);
    }

    /**
     * Update a version (drafts only)
     */
    public function updateVersion(int $documentId, int $versionId): void
    {
        $this->checkAdmin();
        Csrf::validate($_POST['csrf_token'] ?? '');

        $version = LegalDocumentService::getVersion($versionId);
        if (!$version || $version['document_id'] !== $documentId) {
            $this->notFound();
            return;
        }

        if (!$version['is_draft']) {
            $_SESSION['flash_error'] = 'Published versions cannot be edited.';
            header("Location: /admin-legacy/legal-documents/{$documentId}");
            exit;
        }

        $data = [
            'version_number' => trim($_POST['version_number'] ?? ''),
            'version_label' => trim($_POST['version_label'] ?? '') ?: null,
            'content' => $_POST['content'] ?? '',
            'summary_of_changes' => trim($_POST['summary_of_changes'] ?? '') ?: null,
            'effective_date' => $_POST['effective_date'] ?? date('Y-m-d')
        ];

        try {
            LegalDocumentService::updateVersion($versionId, $data);

            $_SESSION['flash_success'] = 'Version updated successfully.';
            header("Location: /admin-legacy/legal-documents/{$documentId}");
            exit;
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to update version: ' . $e->getMessage();
            header("Location: /admin-legacy/legal-documents/{$documentId}/versions/{$versionId}/edit");
            exit;
        }
    }

    /**
     * View a specific version
     */
    public function showVersion(int $documentId, int $versionId): void
    {
        $this->checkAdmin();
        $document = LegalDocumentService::getById($documentId);
        $version = LegalDocumentService::getVersion($versionId);

        if (!$document || !$version || $document['tenant_id'] !== TenantContext::getId()) {
            $this->notFound();
            return;
        }

        $acceptanceCount = LegalDocumentService::countVersionAcceptances($versionId);

        $this->render('admin/legal-documents/versions/show', [
            'pageTitle' => 'Version ' . $version['version_number'] . ' - ' . $document['title'],
            'document' => $document,
            'version' => $version,
            'acceptanceCount' => $acceptanceCount
        ]);
    }

    /**
     * Publish a draft version
     */
    public function publishVersion(int $documentId, int $versionId): void
    {
        $this->checkAdmin();
        Csrf::validate($_POST['csrf_token'] ?? '');

        $version = LegalDocumentService::getVersion($versionId);
        if (!$version || $version['document_id'] !== $documentId) {
            $this->notFound();
            return;
        }

        try {
            LegalDocumentService::publishVersion($versionId);

            $_SESSION['flash_success'] = 'Version ' . $version['version_number'] . ' is now the current version.';
            header("Location: /admin-legacy/legal-documents/{$documentId}");
            exit;
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to publish version: ' . $e->getMessage();
            header("Location: /admin-legacy/legal-documents/{$documentId}");
            exit;
        }
    }

    /**
     * Delete a draft version
     */
    public function deleteVersion(int $documentId, int $versionId): void
    {
        $this->checkAdmin();
        Csrf::validate($_POST['csrf_token'] ?? '');

        $version = LegalDocumentService::getVersion($versionId);
        if (!$version || $version['document_id'] !== $documentId) {
            $this->notFound();
            return;
        }

        if (!$version['is_draft']) {
            $_SESSION['flash_error'] = 'Published versions cannot be deleted.';
            header("Location: /admin-legacy/legal-documents/{$documentId}");
            exit;
        }

        try {
            LegalDocumentService::deleteVersion($versionId);

            $_SESSION['flash_success'] = 'Draft version deleted.';
            header("Location: /admin-legacy/legal-documents/{$documentId}");
            exit;
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to delete version: ' . $e->getMessage();
            header("Location: /admin-legacy/legal-documents/{$documentId}");
            exit;
        }
    }

    /**
     * Send notification emails to users who haven't accepted current version
     */
    public function notifyUsers(int $documentId, int $versionId): void
    {
        $this->checkAdmin();
        Csrf::validate($_POST['csrf_token'] ?? '');

        $document = LegalDocumentService::getById($documentId);
        $version = LegalDocumentService::getVersion($versionId);

        if (!$document || !$version || $document['tenant_id'] !== TenantContext::getId()) {
            $this->notFound();
            return;
        }

        if ($version['is_draft']) {
            $_SESSION['flash_error'] = 'Cannot notify users about draft versions.';
            header("Location: " . TenantContext::getBasePath() . "/admin-legacy/legal-documents/{$documentId}/versions/{$versionId}");
            exit;
        }

        try {
            $sentCount = LegalDocumentService::notifyUsersOfUpdate($documentId, $versionId, true);

            if ($sentCount > 0) {
                $_SESSION['flash_success'] = "Sent notification emails to {$sentCount} user(s) who haven't accepted this version yet.";
            } else {
                $_SESSION['flash_info'] = 'All users have already accepted this version. No notifications sent.';
            }

            header("Location: " . TenantContext::getBasePath() . "/admin-legacy/legal-documents/{$documentId}/versions/{$versionId}");
            exit;
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to send notifications: ' . $e->getMessage();
            header("Location: " . TenantContext::getBasePath() . "/admin-legacy/legal-documents/{$documentId}/versions/{$versionId}");
            exit;
        }
    }

    /**
     * Compare two versions
     */
    public function compareVersions(int $documentId): void
    {
        $this->checkAdmin();
        $document = LegalDocumentService::getById($documentId);
        if (!$document || $document['tenant_id'] !== TenantContext::getId()) {
            $this->notFound();
            return;
        }

        $versionA = (int) ($_GET['a'] ?? 0);
        $versionB = (int) ($_GET['b'] ?? 0);

        if (!$versionA || !$versionB) {
            $versions = LegalDocumentService::getVersions($documentId);
            $this->render('admin/legal-documents/versions/compare-select', [
                'pageTitle' => 'Compare Versions - ' . $document['title'],
                'document' => $document,
                'versions' => $versions
            ]);
            return;
        }

        $verA = LegalDocumentService::getVersion($versionA);
        $verB = LegalDocumentService::getVersion($versionB);

        $this->render('admin/legal-documents/versions/compare', [
            'pageTitle' => 'Compare Versions - ' . $document['title'],
            'document' => $document,
            'versionA' => $verA,
            'versionB' => $verB
        ]);
    }

    // =========================================================================
    // ACCEPTANCE TRACKING
    // =========================================================================

    /**
     * View acceptance records for a version
     */
    public function acceptances(int $documentId, int $versionId): void
    {
        $this->checkAdmin();
        $document = LegalDocumentService::getById($documentId);
        $version = LegalDocumentService::getVersion($versionId);

        if (!$document || !$version || $document['tenant_id'] !== TenantContext::getId()) {
            $this->notFound();
            return;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $acceptances = LegalDocumentService::getVersionAcceptances($versionId, $limit, $offset);
        $totalCount = LegalDocumentService::countVersionAcceptances($versionId);

        $this->render('admin/legal-documents/acceptances', [
            'pageTitle' => 'Acceptances - Version ' . $version['version_number'],
            'document' => $document,
            'version' => $version,
            'acceptances' => $acceptances,
            'pagination' => [
                'current' => $page,
                'total' => ceil($totalCount / $limit),
                'count' => $totalCount
            ]
        ]);
    }

    /**
     * Export acceptance records
     */
    public function exportAcceptances(int $documentId): void
    {
        $this->checkAdmin();
        $document = LegalDocumentService::getById($documentId);
        if (!$document || $document['tenant_id'] !== TenantContext::getId()) {
            $this->notFound();
            return;
        }

        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        $records = LegalDocumentService::exportAcceptanceRecords($documentId, $startDate, $endDate);

        // Generate CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $document['slug'] . '-acceptances-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, [
            'Acceptance ID',
            'User ID',
            'User Name',
            'User Email',
            'Version',
            'Accepted At',
            'Method',
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
    }

    /**
     * Compliance dashboard
     */
    public function compliance(): void
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();
        $stats = LegalDocumentService::getComplianceSummary($tenantId);

        $this->render('admin/legal-documents/compliance', [
            'pageTitle' => 'Legal Compliance Dashboard',
            'stats' => $stats
        ]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function render(string $view, array $data = []): void
    {
        extract($data);
        require __DIR__ . "/../../../views/{$view}.php";
    }

    private function notFound(): void
    {
        http_response_code(404);
        require __DIR__ . '/../../../views/errors/404.php';
    }

    private function getDocumentTypes(): array
    {
        return [
            'terms' => 'Terms of Service',
            'privacy' => 'Privacy Policy',
            'cookies' => 'Cookie Policy',
            'accessibility' => 'Accessibility Statement',
            'community_guidelines' => 'Community Guidelines',
            'acceptable_use' => 'Acceptable Use Policy'
        ];
    }

    private function validateDocument(array $data): array
    {
        $errors = [];

        if (empty($data['document_type'])) {
            $errors['document_type'] = 'Document type is required.';
        }

        if (empty($data['title'])) {
            $errors['title'] = 'Title is required.';
        }

        if (empty($data['slug'])) {
            $errors['slug'] = 'URL slug is required.';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
            $errors['slug'] = 'Slug can only contain lowercase letters, numbers, and hyphens.';
        }

        return $errors;
    }

    private function validateVersion(array $data, int $documentId): array
    {
        $errors = [];

        if (empty($data['version_number'])) {
            $errors['version_number'] = 'Version number is required.';
        } elseif (!preg_match('/^\d+(\.\d+)*$/', $data['version_number'])) {
            $errors['version_number'] = 'Version number must be in format like 1.0 or 2.0.1';
        }

        if (empty($data['content'])) {
            $errors['content'] = 'Document content is required.';
        }

        if (empty($data['effective_date'])) {
            $errors['effective_date'] = 'Effective date is required.';
        }

        return $errors;
    }

    private function suggestNextVersion(int $documentId): string
    {
        $versions = LegalDocumentService::getVersions($documentId);

        if (empty($versions)) {
            return '1.0';
        }

        // Get the highest version number
        $highest = '0.0';
        foreach ($versions as $v) {
            if (version_compare($v['version_number'], $highest, '>')) {
                $highest = $v['version_number'];
            }
        }

        // Increment minor version
        $parts = explode('.', $highest);
        if (count($parts) >= 2) {
            $parts[1] = (int) $parts[1] + 1;
        } else {
            $parts[] = 1;
        }

        return implode('.', $parts);
    }
}
