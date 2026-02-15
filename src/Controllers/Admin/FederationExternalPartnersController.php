<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Auth;
use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationExternalPartnerService;
use Nexus\Services\FederationExternalApiClient;
use Nexus\Services\FederationAuditService;

/**
 * FederationExternalPartnersController
 *
 * Admin interface for managing external federation partners (servers outside this installation).
 */
class FederationExternalPartnersController
{
    /**
     * List all external partners
     * GET /admin-legacy/federation/external-partners
     */
    public function index(): void
    {
        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();

        $partners = FederationExternalPartnerService::getAll($tenantId);

        View::render('admin/federation/external-partners', [
            'pageTitle' => 'External Federation Partners',
            'partners' => $partners,
            'basePath' => TenantContext::getBasePath()
        ]);
    }

    /**
     * Show create form
     * GET /admin-legacy/federation/external-partners/create
     */
    public function create(): void
    {
        Auth::requireAdmin();

        View::render('admin/federation/external-partners-create', [
            'pageTitle' => 'Add External Partner',
            'basePath' => TenantContext::getBasePath()
        ]);
    }

    /**
     * Store new external partner
     * POST /admin-legacy/federation/external-partners/store
     */
    public function store(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();

        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request. Please try again.';
            header('Location: /admin-legacy/federation/external-partners/create');
            exit;
        }

        // Validate required fields
        $name = trim($_POST['name'] ?? '');
        $baseUrl = trim($_POST['base_url'] ?? '');

        if (empty($name) || empty($baseUrl)) {
            $_SESSION['flash_error'] = 'Name and Base URL are required.';
            header('Location: /admin-legacy/federation/external-partners/create');
            exit;
        }

        // Validate URL format
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $_SESSION['flash_error'] = 'Please enter a valid URL.';
            header('Location: /admin-legacy/federation/external-partners/create');
            exit;
        }

        $result = FederationExternalPartnerService::create([
            'name' => $name,
            'description' => trim($_POST['description'] ?? ''),
            'base_url' => $baseUrl,
            'api_path' => trim($_POST['api_path'] ?? '/api/v1/federation'),
            'api_key' => trim($_POST['api_key'] ?? ''),
            'auth_method' => $_POST['auth_method'] ?? 'api_key',
            'signing_secret' => trim($_POST['signing_secret'] ?? ''),
            'allow_member_search' => isset($_POST['allow_member_search']) ? 1 : 0,
            'allow_listing_search' => isset($_POST['allow_listing_search']) ? 1 : 0,
            'allow_messaging' => isset($_POST['allow_messaging']) ? 1 : 0,
            'allow_transactions' => isset($_POST['allow_transactions']) ? 1 : 0,
            'allow_events' => isset($_POST['allow_events']) ? 1 : 0,
            'allow_groups' => isset($_POST['allow_groups']) ? 1 : 0,
        ], $tenantId, $user['id']);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'External partner added successfully. Test the connection to verify it works.';
            session_write_close();
            header('Location: /admin-legacy/federation/external-partners/' . $result['id']);
        } else {
            $_SESSION['flash_error'] = $result['error'];
            session_write_close();
            header('Location: /admin-legacy/federation/external-partners/create');
        }
        exit;
    }

    /**
     * View/edit external partner details
     * GET /admin-legacy/federation/external-partners/{id}
     */
    public function show(int $id): void
    {
        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();

        $partner = FederationExternalPartnerService::getById($id, $tenantId);

        if (!$partner) {
            $_SESSION['flash_error'] = 'Partner not found.';
            header('Location: /admin-legacy/federation/external-partners');
            exit;
        }

        $logs = FederationExternalPartnerService::getLogs($id, 50);

        View::render('admin/federation/external-partners-show', [
            'pageTitle' => 'Partner: ' . $partner['name'],
            'partner' => $partner,
            'logs' => $logs,
            'basePath' => TenantContext::getBasePath()
        ]);
    }

    /**
     * Update external partner
     * POST /admin-legacy/federation/external-partners/{id}/update
     */
    public function update(int $id): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();

        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request. Please try again.';
            header('Location: /admin-legacy/federation/external-partners/' . $id);
            exit;
        }

        // Validate required fields
        $name = trim($_POST['name'] ?? '');
        $baseUrl = trim($_POST['base_url'] ?? '');

        if (empty($name) || empty($baseUrl)) {
            $_SESSION['flash_error'] = 'Name and Base URL are required.';
            header('Location: /admin-legacy/federation/external-partners/' . $id);
            exit;
        }

        $result = FederationExternalPartnerService::update($id, [
            'name' => $name,
            'description' => trim($_POST['description'] ?? ''),
            'base_url' => $baseUrl,
            'api_path' => trim($_POST['api_path'] ?? '/api/v1/federation'),
            'api_key' => trim($_POST['api_key'] ?? ''),
            'auth_method' => $_POST['auth_method'] ?? 'api_key',
            'signing_secret' => trim($_POST['signing_secret'] ?? ''),
            'allow_member_search' => isset($_POST['allow_member_search']) ? 1 : 0,
            'allow_listing_search' => isset($_POST['allow_listing_search']) ? 1 : 0,
            'allow_messaging' => isset($_POST['allow_messaging']) ? 1 : 0,
            'allow_transactions' => isset($_POST['allow_transactions']) ? 1 : 0,
            'allow_events' => isset($_POST['allow_events']) ? 1 : 0,
            'allow_groups' => isset($_POST['allow_groups']) ? 1 : 0,
        ], $tenantId, $user['id']);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Partner updated successfully.';
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }

        session_write_close();
        header('Location: /admin-legacy/federation/external-partners/' . $id);
        exit;
    }

    /**
     * Test connection to external partner
     * POST /admin-legacy/federation/external-partners/{id}/test
     */
    public function test(int $id): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();

        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request. Please try again.';
            header('Location: /admin-legacy/federation/external-partners/' . $id);
            exit;
        }

        $result = FederationExternalPartnerService::testConnection($id, $tenantId);

        if ($result['success']) {
            $partnerName = $result['data']['name'] ?? $result['data']['api'] ?? 'Partner';
            $version = $result['data']['version'] ?? 'unknown';
            $_SESSION['flash_success'] = "Connection successful! Connected to {$partnerName} (API v{$version})";
        } else {
            $_SESSION['flash_error'] = 'Connection failed: ' . $result['error'];
        }

        session_write_close();
        header('Location: /admin-legacy/federation/external-partners/' . $id);
        exit;
    }

    /**
     * Suspend external partner
     * POST /admin-legacy/federation/external-partners/{id}/suspend
     */
    public function suspend(int $id): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();

        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request.';
            header('Location: /admin-legacy/federation/external-partners');
            exit;
        }

        $result = FederationExternalPartnerService::updateStatus($id, 'suspended', $tenantId, $user['id']);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Partner suspended.';
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }

        session_write_close();
        header('Location: /admin-legacy/federation/external-partners/' . $id);
        exit;
    }

    /**
     * Activate external partner
     * POST /admin-legacy/federation/external-partners/{id}/activate
     */
    public function activate(int $id): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();

        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request.';
            header('Location: /admin-legacy/federation/external-partners');
            exit;
        }

        $result = FederationExternalPartnerService::updateStatus($id, 'active', $tenantId, $user['id']);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Partner activated.';
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }

        session_write_close();
        header('Location: /admin-legacy/federation/external-partners/' . $id);
        exit;
    }

    /**
     * Delete external partner
     * POST /admin-legacy/federation/external-partners/{id}/delete
     */
    public function delete(int $id): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();

        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request.';
            header('Location: /admin-legacy/federation/external-partners');
            exit;
        }

        $result = FederationExternalPartnerService::delete($id, $tenantId, $user['id']);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Partner deleted.';
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }

        session_write_close();
        header('Location: /admin-legacy/federation/external-partners');
        exit;
    }
}
