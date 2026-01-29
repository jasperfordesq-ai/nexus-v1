<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin\Enterprise;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Services\Enterprise\GdprService;

/**
 * GDPR Consent Controller
 *
 * Handles consent type management and user consent records.
 */
class GdprConsentController extends BaseEnterpriseController
{
    private GdprService $gdprService;

    public function __construct()
    {
        parent::__construct();
        $this->gdprService = new GdprService();
    }

    /**
     * GET /admin/enterprise/gdpr/consents
     * Manage consent types with full statistics
     */
    public function index(): void
    {
        $tenantId = $this->getTenantId();

        // Get consent types with granted/denied counts
        $consentTypes = Database::query(
            "SELECT ct.*,
                    COALESCE(granted.cnt, 0) as granted_count,
                    COALESCE(denied.cnt, 0) as denied_count
             FROM consent_types ct
             LEFT JOIN (
                 SELECT consent_type, COUNT(*) as cnt
                 FROM user_consents
                 WHERE tenant_id = ? AND consent_given = 1
                 GROUP BY consent_type
             ) granted ON ct.slug = granted.consent_type
             LEFT JOIN (
                 SELECT consent_type, COUNT(*) as cnt
                 FROM user_consents
                 WHERE tenant_id = ? AND consent_given = 0
                 GROUP BY consent_type
             ) denied ON ct.slug = denied.consent_type
             WHERE ct.is_active = TRUE
             ORDER BY ct.display_order, ct.name",
            [$tenantId, $tenantId]
        )->fetchAll();

        // Calculate dashboard stats
        $totalConsents = Database::query(
            "SELECT COUNT(*) as cnt FROM user_consents WHERE tenant_id = ?",
            [$tenantId]
        )->fetch()['cnt'] ?? 0;

        $totalGranted = Database::query(
            "SELECT COUNT(*) as cnt FROM user_consents WHERE tenant_id = ? AND consent_given = 1",
            [$tenantId]
        )->fetch()['cnt'] ?? 0;

        $usersWithConsent = Database::query(
            "SELECT COUNT(DISTINCT user_id) as cnt FROM user_consents WHERE tenant_id = ? AND consent_given = 1",
            [$tenantId]
        )->fetch()['cnt'] ?? 0;

        $pendingReconsent = Database::query(
            "SELECT COUNT(DISTINCT uc.user_id) as cnt
             FROM user_consents uc
             JOIN consent_types ct ON uc.consent_type = ct.slug
             WHERE uc.tenant_id = ?
               AND ct.is_required = 1
               AND uc.consent_given = 1
               AND uc.consent_version != ct.current_version",
            [$tenantId]
        )->fetch()['cnt'] ?? 0;

        $stats = [
            'total_consents' => (int) $totalConsents,
            'consent_rate' => $totalConsents > 0 ? round(($totalGranted / $totalConsents) * 100, 1) : 0,
            'users_with_consent' => (int) $usersWithConsent,
            'pending_reconsent' => (int) $pendingReconsent
        ];

        // Get paginated consent records with filtering
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;
        $selectedType = $_GET['type'] ?? null;
        $filters = array_filter([
            'search' => $_GET['search'] ?? null,
            'status' => $_GET['status'] ?? null,
            'period' => $_GET['period'] ?? null,
            'type' => $selectedType
        ]);

        $whereClause = "uc.tenant_id = ?";
        $params = [$tenantId];

        if (!empty($filters['search'])) {
            $whereClause .= " AND (u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'granted') {
                $whereClause .= " AND uc.consent_given = 1";
            } elseif ($filters['status'] === 'denied') {
                $whereClause .= " AND uc.consent_given = 0 AND uc.withdrawn_at IS NULL";
            } elseif ($filters['status'] === 'withdrawn') {
                $whereClause .= " AND uc.withdrawn_at IS NOT NULL";
            }
        }
        if ($selectedType) {
            $whereClause .= " AND uc.consent_type = ?";
            $params[] = $selectedType;
        }

        $totalCount = Database::query(
            "SELECT COUNT(*) as cnt FROM user_consents uc
             LEFT JOIN users u ON uc.user_id = u.id
             WHERE {$whereClause}",
            $params
        )->fetch()['cnt'] ?? 0;

        $consents = Database::query(
            "SELECT uc.*, u.email, CONCAT(u.first_name, ' ', u.last_name) as username,
                    ct.name as consent_type_name, uc.consent_given as granted
             FROM user_consents uc
             LEFT JOIN users u ON uc.user_id = u.id
             LEFT JOIN consent_types ct ON uc.consent_type = ct.slug
             WHERE {$whereClause}
             ORDER BY uc.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        $selectedTypeName = null;
        if ($selectedType) {
            foreach ($consentTypes as $ct) {
                if ($ct['slug'] === $selectedType) {
                    $selectedTypeName = $ct['name'];
                    break;
                }
            }
        }

        View::render('admin/enterprise/gdpr/consents', [
            'consentTypes' => $consentTypes,
            'stats' => $stats,
            'consents' => $consents,
            'selectedType' => $selectedType,
            'selectedTypeName' => $selectedTypeName,
            'filters' => $filters,
            'totalCount' => (int) $totalCount,
            'totalPages' => (int) ceil($totalCount / $perPage),
            'pageCurrent' => $page,
            'offset' => $offset,
            'title' => 'Consent Management',
        ]);
    }

    /**
     * GET /admin/enterprise/gdpr/consents/{id}
     * View consent type details and user consents
     */
    public function show(int $id): void
    {
        $consentType = Database::query(
            "SELECT * FROM consent_types WHERE id = ?",
            [$id]
        )->fetch();

        if (!$consentType) {
            $_SESSION['flash_error'] = 'Consent type not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/enterprise/gdpr/consents');
            exit;
        }

        $consents = Database::query(
            "SELECT uc.*, u.email, u.first_name, u.last_name
             FROM user_consents uc
             JOIN users u ON uc.user_id = u.id
             WHERE uc.consent_type = ? AND uc.tenant_id = ?
             ORDER BY uc.given_at DESC
             LIMIT 100",
            [$consentType['slug'], $this->getTenantId()]
        )->fetchAll();

        View::render('admin/enterprise/gdpr/consent-detail', [
            'consentType' => $consentType,
            'consents' => $consents,
            'title' => 'Consent: ' . $consentType['name'],
        ]);
    }

    /**
     * POST /admin/enterprise/gdpr/consents/types
     * Create or update a consent type
     */
    public function storeType(): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();

        $required = ['slug', 'name', 'current_text'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Field '{$field}' is required"]);
                return;
            }
        }

        try {
            $id = $data['id'] ?? null;

            if ($id) {
                Database::query(
                    "UPDATE consent_types SET slug = ?, name = ?, description = ?, category = ?, is_required = ?, current_version = ?, current_text = ?, legal_basis = ?, retention_days = ?, is_active = ?, display_order = ? WHERE id = ?",
                    [
                        $data['slug'],
                        $data['name'],
                        $data['description'] ?? null,
                        $data['category'] ?? 'general',
                        $data['is_required'] ?? false,
                        $data['current_version'] ?? '1.0',
                        $data['current_text'],
                        $data['legal_basis'] ?? 'consent',
                        $data['retention_days'] ?? null,
                        $data['is_active'] ?? true,
                        $data['display_order'] ?? 0,
                        $id,
                    ]
                );
            } else {
                Database::query(
                    "INSERT INTO consent_types (slug, name, description, category, is_required, current_version, current_text, legal_basis, retention_days, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $data['slug'],
                        $data['name'],
                        $data['description'] ?? null,
                        $data['category'] ?? 'general',
                        $data['is_required'] ?? false,
                        $data['current_version'] ?? '1.0',
                        $data['current_text'],
                        $data['legal_basis'] ?? 'consent',
                        $data['retention_days'] ?? null,
                        $data['is_active'] ?? true,
                        $data['display_order'] ?? 0,
                    ]
                );
                $id = Database::query("SELECT LAST_INSERT_ID() as id")->fetch()['id'];
            }

            $this->logger->info("Consent type saved", ['id' => $id, 'slug' => $data['slug']]);

            echo json_encode(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/gdpr/consents/backfill
     * Backfill consent records for existing users
     */
    public function backfill(): void
    {
        Csrf::verifyOrDie();

        $consentType = $_POST['consent_type'] ?? '';

        if (empty($consentType)) {
            $_SESSION['flash_error'] = 'Please select a consent type to backfill.';
            header('Location: ' . TenantContext::getBasePath() . '/admin/enterprise/gdpr/consents');
            exit;
        }

        try {
            $consentTypes = $this->gdprService->getConsentTypes();

            $selectedType = null;
            foreach ($consentTypes as $ct) {
                if ($ct['slug'] === $consentType) {
                    $selectedType = $ct;
                    break;
                }
            }

            if (!$selectedType) {
                throw new \Exception('Invalid consent type: ' . $consentType);
            }

            $count = $this->gdprService->backfillConsentsForExistingUsers(
                $selectedType['slug'],
                $selectedType['current_version'],
                $selectedType['current_text']
            );

            $this->logger->info("Consent backfill completed", [
                'consent_type' => $consentType,
                'users_affected' => $count,
                'admin_id' => $this->getCurrentUserId()
            ]);

            if ($count > 0) {
                $_SESSION['flash_success'] = "Successfully created consent records for {$count} users. They will be prompted to accept on their next login.";
            } else {
                $_SESSION['flash_info'] = "No users found without consent records for this type. All users already have records.";
            }

        } catch (\Exception $e) {
            $this->logger->error("Consent backfill failed", ['error' => $e->getMessage()]);
            $_SESSION['flash_error'] = 'Backfill failed: ' . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/enterprise/gdpr/consents');
        exit;
    }

    /**
     * GET /admin/enterprise/gdpr/consents/export
     * Export all consent records
     */
    public function export(): void
    {
        $format = $_GET['format'] ?? 'csv';
        $tenantId = $this->getTenantId();

        $consents = Database::query(
            "SELECT uc.*, ct.name as consent_name, u.email, u.first_name, u.last_name
             FROM user_consents uc
             LEFT JOIN consent_types ct ON uc.consent_type = ct.slug
             LEFT JOIN users u ON uc.user_id = u.id
             WHERE uc.tenant_id = ?
             ORDER BY uc.given_at DESC",
            [$tenantId]
        )->fetchAll();

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="consent_records_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['User ID', 'Email', 'Name', 'Consent Type', 'Given', 'Given At', 'Withdrawn At', 'IP Address']);

            foreach ($consents as $consent) {
                fputcsv($output, [
                    $consent['user_id'],
                    $consent['email'],
                    trim($consent['first_name'] . ' ' . $consent['last_name']),
                    $consent['consent_name'] ?? $consent['consent_type'],
                    $consent['consent_given'] ? 'Yes' : 'No',
                    $consent['given_at'],
                    $consent['withdrawn_at'],
                    $consent['ip_address'],
                ]);
            }

            fclose($output);
        } else {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="consent_records_' . date('Y-m-d') . '.json"');
            echo json_encode($consents, JSON_PRETTY_PRINT);
        }
    }

    /**
     * POST /admin/enterprise/gdpr/consents/tenant-version
     * Update tenant-specific consent version
     */
    public function updateTenantVersion(): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();

        if (empty($data['consent_slug']) || empty($data['version'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Consent type and version are required']);
            return;
        }

        $tenantId = $this->getTenantId();
        $slug = $data['consent_slug'];
        $version = $data['version'];

        if (!preg_match('/^\d+\.\d+$/', $version)) {
            http_response_code(400);
            echo json_encode(['error' => 'Version must be in format X.Y (e.g., 2.0)']);
            return;
        }

        try {
            $gdprService = new GdprService($tenantId);
            $gdprService->setTenantConsentVersion($slug, $version);

            $affectedUsers = Database::query(
                "SELECT COUNT(DISTINCT uc.user_id) as cnt
                 FROM user_consents uc
                 WHERE uc.tenant_id = ? AND uc.consent_type = ?
                   AND uc.consent_given = 1
                   AND uc.consent_version < ?",
                [$tenantId, $slug, $version]
            )->fetch();

            echo json_encode([
                'success' => true,
                'message' => "Consent version updated to {$version}",
                'affected_users' => $affectedUsers['cnt'] ?? 0
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/enterprise/gdpr/consents/tenant-versions
     * Get tenant's consent version overrides
     */
    public function getTenantVersions(): void
    {
        header('Content-Type: application/json');

        $tenantId = $this->getTenantId();

        $versions = Database::query(
            "SELECT ct.slug, ct.name, ct.is_required,
                    ct.current_version AS global_version,
                    tco.current_version AS tenant_version,
                    COALESCE(tco.current_version, ct.current_version) AS effective_version,
                    tco.updated_at AS last_updated
             FROM consent_types ct
             LEFT JOIN tenant_consent_overrides tco
                    ON ct.slug = tco.consent_type_slug
                   AND tco.tenant_id = ?
                   AND tco.is_active = 1
             WHERE ct.is_active = TRUE
               AND ct.slug IN ('terms_of_service', 'privacy_policy')
             ORDER BY ct.display_order, ct.name",
            [$tenantId]
        )->fetchAll();

        echo json_encode(['versions' => $versions]);
    }

    /**
     * DELETE /admin/enterprise/gdpr/consents/tenant-version/{slug}
     * Remove tenant override (revert to global version)
     */
    public function removeTenantVersion(string $slug): void
    {
        header('Content-Type: application/json');

        $tenantId = $this->getTenantId();

        try {
            $gdprService = new GdprService($tenantId);
            $gdprService->removeTenantConsentOverride($slug);

            echo json_encode([
                'success' => true,
                'message' => 'Reverted to global version'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
