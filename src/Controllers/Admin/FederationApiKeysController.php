<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Auth;
use Nexus\Core\Database;
use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationAuditService;

/**
 * FederationApiKeysController
 *
 * Admin interface for managing Federation API keys for external partner integrations.
 */
class FederationApiKeysController
{
    /**
     * List all API keys
     * GET /admin-legacy/federation/api-keys
     */
    public function index(): void
    {
        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();
        $db = Database::getInstance();

        // Get all API keys for this tenant
        $stmt = $db->prepare("
            SELECT
                fak.*,
                u.first_name,
                u.last_name,
                (SELECT COUNT(*) FROM federation_api_logs WHERE api_key_id = fak.id) as request_count_total
            FROM federation_api_keys fak
            LEFT JOIN users u ON u.id = fak.created_by
            WHERE fak.tenant_id = ?
            ORDER BY fak.created_at DESC
        ");
        $stmt->execute([$tenantId]);
        $apiKeys = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get recent API activity
        $stmt = $db->prepare("
            SELECT
                fal.*,
                fak.name as key_name
            FROM federation_api_logs fal
            JOIN federation_api_keys fak ON fak.id = fal.api_key_id
            WHERE fak.tenant_id = ?
            ORDER BY fal.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$tenantId]);
        $recentActivity = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        View::render('admin/federation/api-keys', [
            'pageTitle' => 'Federation API Keys',
            'apiKeys' => $apiKeys,
            'recentActivity' => $recentActivity,
            'basePath' => TenantContext::getBasePath()
        ]);
    }

    /**
     * Create new API key form
     * GET /admin-legacy/federation/api-keys/create
     */
    public function create(): void
    {
        Auth::requireAdmin();

        View::render('admin/federation/api-keys-create', [
            'pageTitle' => 'Create API Key',
            'basePath' => TenantContext::getBasePath()
        ]);
    }

    /**
     * Store new API key
     * POST /admin-legacy/federation/api-keys/store
     */
    public function store(): void
    {
        // Ensure session is started before we set session variables
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();

        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request. Please try again.';
            header('Location: /admin-legacy/federation/api-keys/create');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $permissions = $_POST['permissions'] ?? [];
        $rateLimit = (int)($_POST['rate_limit'] ?? 1000);
        $expiresIn = $_POST['expires_in'] ?? '';
        $platformId = trim($_POST['platform_id'] ?? '');
        $authMethod = $_POST['auth_method'] ?? 'api_key';

        // Validate
        if (empty($name)) {
            $_SESSION['flash_error'] = 'API key name is required.';
            header('Location: /admin-legacy/federation/api-keys/create');
            exit;
        }

        // Generate secure API key
        $rawKey = 'fed_' . bin2hex(random_bytes(32)); // 64 hex chars + prefix
        $keyHash = hash('sha256', $rawKey);
        $keyPrefix = substr($rawKey, 0, 12); // Show first 12 chars for identification

        // Generate signing secret if HMAC auth is enabled
        $signingSecret = null;
        $signingEnabled = false;
        if ($authMethod === 'hmac') {
            $signingSecret = bin2hex(random_bytes(32)); // 64 hex char secret
            $signingEnabled = true;
        }

        // Calculate expiry
        $expiresAt = null;
        if ($expiresIn === '30d') {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        } elseif ($expiresIn === '90d') {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));
        } elseif ($expiresIn === '1y') {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 year'));
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO federation_api_keys
            (tenant_id, name, key_hash, key_prefix, signing_secret, signing_enabled, platform_id, permissions, rate_limit, expires_at, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $tenantId,
            $name,
            $keyHash,
            $keyPrefix,
            $signingSecret,
            $signingEnabled ? 1 : 0,
            $platformId ?: null,
            json_encode($permissions),
            $rateLimit,
            $expiresAt,
            $user['id']
        ]);

        // Log the action
        FederationAuditService::log(
            'api_key_created',
            $tenantId,
            null,
            $user['id'],
            ['key_name' => $name, 'key_prefix' => $keyPrefix, 'hmac_enabled' => $signingEnabled]
        );

        // Show the key once (won't be shown again)
        $_SESSION['new_api_key'] = $rawKey;
        if ($signingSecret) {
            $_SESSION['new_signing_secret'] = $signingSecret;
        }
        $_SESSION['flash_success'] = 'API key created successfully. Copy the credentials now - they won\'t be shown again!';

        // Ensure session data is written before redirect
        session_write_close();

        header('Location: /admin-legacy/federation/api-keys');
        exit;
    }

    /**
     * View API key details
     * GET /admin-legacy/federation/api-keys/{id}
     */
    public function show(int $id): void
    {
        Auth::requireAdmin();
        $tenantId = TenantContext::getId();
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT fak.*, u.first_name, u.last_name
            FROM federation_api_keys fak
            LEFT JOIN users u ON u.id = fak.created_by
            WHERE fak.id = ? AND fak.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        $apiKey = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$apiKey) {
            $_SESSION['flash_error'] = 'API key not found.';
            header('Location: /admin-legacy/federation/api-keys');
            exit;
        }

        // Get usage stats
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_requests,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                MAX(created_at) as last_request
            FROM federation_api_logs
            WHERE api_key_id = ?
        ");
        $stmt->execute([$id]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Get recent logs
        $stmt = $db->prepare("
            SELECT * FROM federation_api_logs
            WHERE api_key_id = ?
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$id]);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        View::render('admin/federation/api-keys-show', [
            'pageTitle' => 'API Key: ' . $apiKey['name'],
            'apiKey' => $apiKey,
            'stats' => $stats,
            'logs' => $logs,
            'basePath' => TenantContext::getBasePath()
        ]);
    }

    /**
     * Suspend API key
     * POST /admin-legacy/federation/api-keys/{id}/suspend
     */
    public function suspend(int $id): void
    {
        $user = Auth::requireAdmin();

        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request. Please try again.';
            header('Location: /admin-legacy/federation/api-keys');
            exit;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getInstance();

        // Verify ownership
        $stmt = $db->prepare("SELECT name FROM federation_api_keys WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $apiKey = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$apiKey) {
            $_SESSION['flash_error'] = 'API key not found.';
            header('Location: /admin-legacy/federation/api-keys');
            exit;
        }

        $stmt = $db->prepare("UPDATE federation_api_keys SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$id]);

        FederationAuditService::log(
            'api_key_suspended',
            $tenantId,
            null,
            $user['id'],
            ['key_name' => $apiKey['name']]
        );

        $_SESSION['flash_success'] = 'API key suspended.';
        header('Location: /admin-legacy/federation/api-keys');
        exit;
    }

    /**
     * Reactivate API key
     * POST /admin-legacy/federation/api-keys/{id}/activate
     */
    public function activate(int $id): void
    {
        $user = Auth::requireAdmin();

        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request. Please try again.';
            header('Location: /admin-legacy/federation/api-keys');
            exit;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT name FROM federation_api_keys WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $apiKey = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$apiKey) {
            $_SESSION['flash_error'] = 'API key not found.';
            header('Location: /admin-legacy/federation/api-keys');
            exit;
        }

        $stmt = $db->prepare("UPDATE federation_api_keys SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);

        FederationAuditService::log(
            'api_key_activated',
            $tenantId,
            null,
            $user['id'],
            ['key_name' => $apiKey['name']]
        );

        $_SESSION['flash_success'] = 'API key reactivated.';
        header('Location: /admin-legacy/federation/api-keys');
        exit;
    }

    /**
     * Revoke (permanently delete) API key
     * POST /admin-legacy/federation/api-keys/{id}/revoke
     */
    public function revoke(int $id): void
    {
        $user = Auth::requireAdmin();

        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request. Please try again.';
            header('Location: /admin-legacy/federation/api-keys');
            exit;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT name FROM federation_api_keys WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $apiKey = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$apiKey) {
            $_SESSION['flash_error'] = 'API key not found.';
            header('Location: /admin-legacy/federation/api-keys');
            exit;
        }

        // Mark as revoked (keeps audit trail)
        $stmt = $db->prepare("UPDATE federation_api_keys SET status = 'revoked' WHERE id = ?");
        $stmt->execute([$id]);

        FederationAuditService::log(
            'api_key_revoked',
            $tenantId,
            null,
            $user['id'],
            ['key_name' => $apiKey['name']]
        );

        $_SESSION['flash_success'] = 'API key permanently revoked.';
        header('Location: /admin-legacy/federation/api-keys');
        exit;
    }

    /**
     * Regenerate API key
     * POST /admin-legacy/federation/api-keys/{id}/regenerate
     */
    public function regenerate(int $id): void
    {
        // Ensure session is started before we set session variables
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = Auth::requireAdmin();

        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request. Please try again.';
            header('Location: /admin-legacy/federation/api-keys');
            exit;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT name FROM federation_api_keys WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $apiKey = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$apiKey) {
            $_SESSION['flash_error'] = 'API key not found.';
            header('Location: /admin-legacy/federation/api-keys');
            exit;
        }

        // Generate new key
        $rawKey = 'fed_' . bin2hex(random_bytes(32));
        $keyHash = hash('sha256', $rawKey);
        $keyPrefix = substr($rawKey, 0, 12);

        $stmt = $db->prepare("
            UPDATE federation_api_keys
            SET key_hash = ?, key_prefix = ?, request_count = 0
            WHERE id = ?
        ");
        $stmt->execute([$keyHash, $keyPrefix, $id]);

        FederationAuditService::log(
            'api_key_regenerated',
            $tenantId,
            null,
            $user['id'],
            ['key_name' => $apiKey['name']]
        );

        $_SESSION['new_api_key'] = $rawKey;
        $_SESSION['flash_success'] = 'API key regenerated. Copy it now - it won\'t be shown again!';

        // Ensure session data is written before redirect
        session_write_close();

        header('Location: /admin-legacy/federation/api-keys');
        exit;
    }
}
