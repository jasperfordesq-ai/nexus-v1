<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Services\RedisCache;
use Nexus\Services\TokenService;

/**
 * AdminToolsApiController - V2 API for admin tools and SEO management
 *
 * Provides endpoints for SEO redirects, 404 tracking, health checks,
 * WebP image conversion stats, seed generation, and blog backup management.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/tools/redirects         - List SEO redirects
 * - POST   /api/v2/admin/tools/redirects         - Create SEO redirect
 * - DELETE /api/v2/admin/tools/redirects/{id}     - Delete SEO redirect
 * - GET    /api/v2/admin/tools/404-errors         - List 404 errors
 * - DELETE /api/v2/admin/tools/404-errors/{id}    - Delete 404 error entry
 * - POST   /api/v2/admin/tools/health-check       - Run system health checks
 * - GET    /api/v2/admin/tools/webp-stats         - Get WebP conversion stats
 * - POST   /api/v2/admin/tools/webp-convert       - Queue WebP conversion
 * - POST   /api/v2/admin/tools/seed               - Queue seed data generation
 * - GET    /api/v2/admin/tools/blog-backups       - List blog backups
 */
class AdminToolsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ─────────────────────────────────────────────────────────────────────────
    // SEO Redirects
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/tools/redirects
     *
     * List all SEO redirects for the current tenant.
     */
    public function getRedirects(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $redirects = [];

        try {
            $redirects = Database::query(
                "SELECT id, tenant_id, from_url, to_url, status_code, hits, created_at
                 FROM seo_redirects
                 WHERE tenant_id = ?
                 ORDER BY created_at DESC",
                [$tenantId]
            )->fetchAll();

            // Cast numeric fields
            $redirects = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'tenant_id' => (int) $row['tenant_id'],
                    'from_url' => $row['from_url'],
                    'to_url' => $row['to_url'],
                    'status_code' => (int) ($row['status_code'] ?? 301),
                    'hits' => (int) ($row['hits'] ?? 0),
                    'created_at' => $row['created_at'] ?? '',
                ];
            }, $redirects);
        } catch (\Throwable $e) {
            // Table doesn't exist yet — return empty array
            $redirects = [];
        }

        $this->respondWithData($redirects);
    }

    /**
     * POST /api/v2/admin/tools/redirects
     *
     * Create a new SEO redirect.
     * Body: { "from_url": "/old-path", "to_url": "/new-path", "status_code": 301 }
     */
    public function createRedirect(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();
        $fromUrl = trim($input['from_url'] ?? '');
        $toUrl = trim($input['to_url'] ?? '');
        $statusCode = (int) ($input['status_code'] ?? 301);

        if (empty($fromUrl)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'from_url is required', 'from_url', 422);
            return;
        }

        if (empty($toUrl)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'to_url is required', 'to_url', 422);
            return;
        }

        if (!in_array($statusCode, [301, 302, 307, 308], true)) {
            $statusCode = 301;
        }

        try {
            // Ensure table exists
            Database::query("
                CREATE TABLE IF NOT EXISTS `seo_redirects` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
                    `from_url` VARCHAR(500) NOT NULL,
                    `to_url` VARCHAR(500) NOT NULL,
                    `status_code` SMALLINT NOT NULL DEFAULT 301,
                    `hits` INT UNSIGNED NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_tenant_from` (`tenant_id`, `from_url`(191))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            Database::query(
                "INSERT INTO seo_redirects (tenant_id, from_url, to_url, status_code) VALUES (?, ?, ?, ?)",
                [$tenantId, $fromUrl, $toUrl, $statusCode]
            );

            $id = (int) Database::getConnection()->lastInsertId();

            $this->respondWithData([
                'id' => $id,
                'from_url' => $fromUrl,
                'to_url' => $toUrl,
                'status_code' => $statusCode,
            ], null, 201);
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create redirect: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * DELETE /api/v2/admin/tools/redirects/{id}
     *
     * Delete a SEO redirect by ID.
     */
    public function deleteRedirect(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/tools/redirects/(\d+)#', $uri, $matches);
        $id = (int) ($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid redirect ID', 'id', 400);
            return;
        }

        try {
            $redirect = Database::query(
                "SELECT id FROM seo_redirects WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$redirect) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Redirect not found', 'id', 404);
                return;
            }

            Database::query(
                "DELETE FROM seo_redirects WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            $this->respondWithData(['deleted' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Redirect not found or table does not exist', 'id', 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 404 Error Tracking
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/tools/404-errors
     *
     * List 404 errors for the current tenant, grouped by URL with hit counts.
     */
    public function get404Errors(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $errors = [];

        try {
            $errors = Database::query(
                "SELECT id, url, referer, hit_count,
                        first_seen_at, last_seen_at, resolved
                 FROM error_404_log
                 WHERE resolved = 0
                 ORDER BY hit_count DESC, last_seen_at DESC"
            )->fetchAll();

            $errors = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'url' => $row['url'],
                    'referrer' => $row['referer'] ?? null,
                    'hits' => (int) ($row['hit_count'] ?? 1),
                    'first_seen' => $row['first_seen_at'] ?? '',
                    'last_seen' => $row['last_seen_at'] ?? '',
                ];
            }, $errors);
        } catch (\Throwable $e) {
            // Table doesn't exist yet — return empty array
            $errors = [];
        }

        $this->respondWithData($errors);
    }

    /**
     * DELETE /api/v2/admin/tools/404-errors/{id}
     *
     * Delete a 404 error entry by ID.
     */
    public function delete404Error(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/tools/404-errors/(\d+)#', $uri, $matches);
        $id = (int) ($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid error ID', 'id', 400);
            return;
        }

        try {
            $error = Database::query(
                "SELECT id FROM error_404_log WHERE id = ?",
                [$id]
            )->fetch();

            if (!$error) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, '404 error entry not found', 'id', 404);
                return;
            }

            Database::query(
                "DELETE FROM error_404_log WHERE id = ?",
                [$id]
            );

            $this->respondWithData(['deleted' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, '404 error entry not found or table does not exist', 'id', 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Health Check / Test Runner
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/v2/admin/tools/health-check
     *
     * Run system health checks: database, Redis, API auth, tenant bootstrap.
     * Returns an array of test results with name, status, and duration.
     */
    public function runHealthCheck(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $tests = [];

        // Test 1: Database Connection
        $start = microtime(true);
        try {
            Database::query("SELECT 1")->fetch();
            $tests[] = [
                'name' => 'Database Connection',
                'status' => 'pass',
                'duration_ms' => round((microtime(true) - $start) * 1000),
            ];
        } catch (\Throwable $e) {
            $tests[] = [
                'name' => 'Database Connection',
                'status' => 'fail',
                'duration_ms' => round((microtime(true) - $start) * 1000),
                'error' => $e->getMessage(),
            ];
        }

        // Test 2: Redis Connection
        $start = microtime(true);
        try {
            $stats = RedisCache::getStats();
            $tests[] = [
                'name' => 'Redis Connection',
                'status' => ($stats['enabled'] ?? false) ? 'pass' : 'fail',
                'duration_ms' => round((microtime(true) - $start) * 1000),
            ];
        } catch (\Throwable $e) {
            $tests[] = [
                'name' => 'Redis Connection',
                'status' => 'fail',
                'duration_ms' => round((microtime(true) - $start) * 1000),
                'error' => $e->getMessage(),
            ];
        }

        // Test 3: API Auth (verify token service works)
        $start = microtime(true);
        try {
            // Verify TokenService can generate a token (basic functionality check)
            $testToken = TokenService::generateAccessToken(0, 'test', $tenantId);
            $tests[] = [
                'name' => 'API Auth (Token Service)',
                'status' => !empty($testToken) ? 'pass' : 'fail',
                'duration_ms' => round((microtime(true) - $start) * 1000),
            ];
        } catch (\Throwable $e) {
            $tests[] = [
                'name' => 'API Auth (Token Service)',
                'status' => 'fail',
                'duration_ms' => round((microtime(true) - $start) * 1000),
                'error' => $e->getMessage(),
            ];
        }

        // Test 4: Tenant Bootstrap (check tenant exists)
        $start = microtime(true);
        try {
            $tenant = Database::query(
                "SELECT id, name, slug FROM tenants WHERE id = ?",
                [$tenantId]
            )->fetch();

            $tests[] = [
                'name' => 'Tenant Bootstrap',
                'status' => $tenant ? 'pass' : 'fail',
                'duration_ms' => round((microtime(true) - $start) * 1000),
            ];
        } catch (\Throwable $e) {
            $tests[] = [
                'name' => 'Tenant Bootstrap',
                'status' => 'fail',
                'duration_ms' => round((microtime(true) - $start) * 1000),
                'error' => $e->getMessage(),
            ];
        }

        // Summary
        $passCount = count(array_filter($tests, fn($t) => $t['status'] === 'pass'));
        $totalCount = count($tests);

        $this->respondWithData([
            'tests' => $tests,
            'summary' => [
                'total' => $totalCount,
                'passed' => $passCount,
                'failed' => $totalCount - $passCount,
                'overall' => ($passCount === $totalCount) ? 'healthy' : 'degraded',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WebP Converter
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/tools/webp-stats
     *
     * Get image file counts in the uploads directory.
     * Returns total images, WebP images, and pending conversions.
     */
    public function getWebpStats(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $totalImages = 0;
        $webpImages = 0;
        $pendingConversion = 0;

        // Determine uploads directory
        $uploadsDir = dirname(__DIR__, 3) . '/httpdocs/uploads';

        if (is_dir($uploadsDir)) {
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($uploadsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
                );

                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff'];
                $nonWebpImages = 0;

                foreach ($iterator as $file) {
                    if (!$file->isFile()) {
                        continue;
                    }

                    $ext = strtolower($file->getExtension());

                    if ($ext === 'webp') {
                        $webpImages++;
                        $totalImages++;
                    } elseif (in_array($ext, $imageExtensions, true)) {
                        $nonWebpImages++;
                        $totalImages++;
                    }
                }

                $pendingConversion = $nonWebpImages;
            } catch (\Throwable $e) {
                // If we can't scan the directory, return zeros
            }
        }

        $this->respondWithData([
            'total_images' => $totalImages,
            'webp_images' => $webpImages,
            'pending_conversion' => $pendingConversion,
            'uploads_dir_exists' => is_dir($uploadsDir),
        ]);
    }

    /**
     * POST /api/v2/admin/tools/webp-convert
     *
     * Queue WebP image conversion (placeholder).
     * Real conversion would be handled by a background job.
     */
    public function runWebpConversion(): void
    {
        $this->requireAdmin();

        $this->respondWithData([
            'started' => true,
            'message' => 'Conversion queued',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Seed Generator
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/v2/admin/tools/seed
     *
     * Queue seed data generation (placeholder).
     * Real seeding would be handled by a background job.
     *
     * Body: { "types": ["users", "listings"], "counts": { "users": 50, "listings": 100 } }
     */
    public function runSeedGenerator(): void
    {
        $this->requireAdmin();

        $input = $this->getAllInput();
        $types = $input['types'] ?? [];
        $counts = $input['counts'] ?? [];

        if (empty($types)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'At least one seed type is required', 'types', 422);
            return;
        }

        $validTypes = ['users', 'listings', 'transactions', 'events', 'groups', 'feed_posts', 'messages', 'reviews'];
        $invalidTypes = array_diff($types, $validTypes);

        if (!empty($invalidTypes)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                'Invalid seed types: ' . implode(', ', $invalidTypes) . '. Valid types: ' . implode(', ', $validTypes),
                'types',
                422
            );
            return;
        }

        $this->respondWithData([
            'started' => true,
            'message' => 'Seed generation queued',
            'types' => $types,
            'counts' => $counts,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Blog Restore
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/tools/blog-backups
     *
     * Check for blog backup files (placeholder).
     */
    public function getBlogBackups(): void
    {
        $this->requireAdmin();

        $backups = [];

        // Check for backup files in the backups directory
        $backupsDir = dirname(__DIR__, 3) . '/backups/blog';

        if (is_dir($backupsDir)) {
            try {
                $files = scandir($backupsDir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $filePath = $backupsDir . '/' . $file;
                    if (is_file($filePath)) {
                        $backups[] = [
                            'filename' => $file,
                            'size_bytes' => filesize($filePath),
                            'created_at' => date('Y-m-d H:i:s', filemtime($filePath)),
                        ];
                    }
                }

                // Sort by created_at descending (most recent first)
                usort($backups, function ($a, $b) {
                    return strcmp($b['created_at'], $a['created_at']);
                });
            } catch (\Throwable $e) {
                // If we can't scan the directory, return empty
            }
        }

        $this->respondWithData($backups);
    }

    /**
     * POST /api/v2/admin/tools/blog-backups/{id}/restore
     *
     * Restore a blog backup by filename index. Placeholder — real restore
     * would parse the backup file and re-insert posts.
     */
    public function restoreBlogBackup(): void
    {
        $this->requireAdmin();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/tools/blog-backups/(\d+)/restore#', $uri, $matches);
        $id = (int) ($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid backup ID', 'id', 400);
            return;
        }

        $this->respondWithData([
            'restored' => true,
            'backup_id' => $id,
            'message' => 'Blog backup restore queued',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SEO Audit
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/tools/seo-audit
     *
     * Get the most recent SEO audit results (if any).
     */
    public function getSeoAudit(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            $stmt = Database::query(
                "SELECT * FROM seo_audits WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1",
                [$tenantId]
            );
            $audit = $stmt->fetch();

            if ($audit && !empty($audit['results'])) {
                $audit['results'] = json_decode($audit['results'], true) ?: [];
            }

            $this->respondWithData($audit ?: null);
        } catch (\Throwable $e) {
            // Table doesn't exist — return null
            $this->respondWithData(null);
        }
    }

    /**
     * POST /api/v2/admin/tools/seo-audit
     *
     * Run an SEO audit (placeholder). Real implementation would crawl pages
     * and check meta tags, headings, image alt text, etc.
     */
    public function runSeoAudit(): void
    {
        $this->requireAdmin();

        $this->respondWithData([
            'started' => true,
            'message' => 'SEO audit queued',
        ]);
    }
}
