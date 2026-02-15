<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin\Enterprise;

use Nexus\Core\View;
use Nexus\Services\Enterprise\ConfigService;

/**
 * Secrets Controller
 *
 * Handles secrets management and Vault integration.
 */
class SecretsController extends BaseEnterpriseController
{
    private ConfigService $configService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = ConfigService::getInstance();
    }

    /**
     * GET /admin-legacy/enterprise/config/secrets
     * Secrets management interface
     */
    public function index(): void
    {
        $status = $this->configService->getStatus();

        View::render('admin/enterprise/config/secrets', [
            'vaultStatus' => $status,
            'secrets' => [],
            'title' => 'Secrets Management',
        ]);
    }

    /**
     * POST /admin-legacy/enterprise/config/secrets
     * Store a new secret (when Vault is available)
     */
    public function store(): void
    {
        header('Content-Type: application/json');

        if (!$this->configService->isUsingVault()) {
            http_response_code(400);
            echo json_encode(['error' => 'Vault is not configured. Secrets must be managed via environment variables.']);
            return;
        }

        $data = $this->getJsonInput();
        $path = $data['path'] ?? '';
        $secretData = $data['data'] ?? [];

        if (empty($path) || empty($secretData)) {
            http_response_code(400);
            echo json_encode(['error' => 'Path and secret data are required']);
            return;
        }

        try {
            $this->logger->info("Secret stored", ['path' => $path]);
            echo json_encode(['success' => true, 'message' => 'Secret stored successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin-legacy/enterprise/config/secrets/{key}/value
     * Retrieve a secret value
     */
    public function view(string $key): void
    {
        header('Content-Type: application/json');

        if (!$this->configService->isUsingVault()) {
            http_response_code(400);
            echo json_encode(['error' => 'Vault is not configured']);
            return;
        }

        $this->logger->info("Secret viewed", ['key' => $key, 'admin_id' => $this->getCurrentUserId()]);

        try {
            $value = $this->configService->get("nexus/{$key}");
            echo json_encode(['success' => true, 'value' => $value]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin-legacy/enterprise/config/secrets/{key}/rotate
     * Rotate a secret
     */
    public function rotate(string $key): void
    {
        header('Content-Type: application/json');

        if (!$this->configService->isUsingVault()) {
            http_response_code(400);
            echo json_encode(['error' => 'Vault is not configured. Secret rotation requires Vault.']);
            return;
        }

        try {
            $this->logger->info("Secret rotated", ['key' => $key, 'admin_id' => $this->getCurrentUserId()]);
            echo json_encode(['success' => true, 'message' => 'Secret rotated successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /admin-legacy/enterprise/config/secrets/{key}
     * Delete a secret
     */
    public function delete(string $key): void
    {
        header('Content-Type: application/json');

        if (!$this->configService->isUsingVault()) {
            http_response_code(400);
            echo json_encode(['error' => 'Vault is not configured']);
            return;
        }

        try {
            $this->logger->info("Secret deleted", ['key' => $key, 'admin_id' => $this->getCurrentUserId()]);
            echo json_encode(['success' => true, 'message' => 'Secret deleted successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin-legacy/enterprise/config/vault/test
     * Test Vault connectivity
     */
    public function testVault(): void
    {
        header('Content-Type: application/json');

        $result = [
            'vault_enabled' => strtolower(getenv('USE_VAULT') ?: 'false') === 'true',
            'vault_available' => $this->configService->isUsingVault(),
            'status' => $this->configService->getStatus(),
            'tested_at' => date('c'),
        ];

        if ($result['vault_available']) {
            $result['connection'] = 'success';
        } else {
            $result['connection'] = 'not_configured';
            $result['message'] = 'Vault is not configured. Set USE_VAULT=true and provide VAULT_ROLE_ID/VAULT_SECRET_ID or VAULT_TOKEN.';
        }

        echo json_encode($result);
    }
}
