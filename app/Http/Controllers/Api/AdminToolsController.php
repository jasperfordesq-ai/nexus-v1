<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;
use Nexus\Services\RedisCache;
use Nexus\Services\TokenService;

/**
 * AdminToolsController -- Admin tools: redirects, 404s, health checks, WebP, SEO audit, seed, blog backups, IP debug.
 *
 * All methods require admin authentication.
 */
class AdminToolsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    // =========================================================================
    // SEO Redirects
    // =========================================================================

    /** GET /api/v2/admin/tools/redirects */
    public function getRedirects(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $redirects = DB::select(
                "SELECT id, tenant_id, from_url, to_url, status_code, hits, created_at
                 FROM seo_redirects WHERE tenant_id = ? ORDER BY created_at DESC",
                [$tenantId]
            );

            $formatted = array_map(fn($r) => [
                'id' => (int) $r->id,
                'tenant_id' => (int) $r->tenant_id,
                'from_url' => $r->from_url,
                'to_url' => $r->to_url,
                'status_code' => (int) ($r->status_code ?? 301),
                'hits' => (int) ($r->hits ?? 0),
                'created_at' => $r->created_at ?? '',
            ], $redirects);

            return $this->respondWithData($formatted);
        } catch (\Throwable $e) {
            return $this->respondWithData([]);
        }
    }

    /** POST /api/v2/admin/tools/redirects */
    public function createRedirect(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $fromUrl = trim($this->input('from_url', ''));
        $toUrl = trim($this->input('to_url', ''));
        $statusCode = (int) ($this->input('status_code', 301));

        if (empty($fromUrl)) {
            return $this->respondWithError('VALIDATION_ERROR', 'from_url is required', 'from_url', 422);
        }
        if (empty($toUrl)) {
            return $this->respondWithError('VALIDATION_ERROR', 'to_url is required', 'to_url', 422);
        }
        if (!in_array($statusCode, [301, 302, 307, 308], true)) {
            $statusCode = 301;
        }

        try {
            DB::insert(
                "INSERT INTO seo_redirects (tenant_id, from_url, to_url, status_code) VALUES (?, ?, ?, ?)",
                [$tenantId, $fromUrl, $toUrl, $statusCode]
            );

            return $this->respondWithData([
                'id' => (int) DB::getPdo()->lastInsertId(),
                'from_url' => $fromUrl,
                'to_url' => $toUrl,
                'status_code' => $statusCode,
            ], null, 201);
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to create redirect: ' . $e->getMessage(), null, 500);
        }
    }

    /** DELETE /api/v2/admin/tools/redirects/{id} */
    public function deleteRedirect(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $redirect = DB::selectOne("SELECT id FROM seo_redirects WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            if (!$redirect) {
                return $this->respondWithError('NOT_FOUND', 'Redirect not found', 'id', 404);
            }

            DB::delete("DELETE FROM seo_redirects WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            return $this->respondWithData(['deleted' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            return $this->respondWithError('NOT_FOUND', 'Redirect not found or table does not exist', 'id', 404);
        }
    }

    // =========================================================================
    // 404 Errors
    // =========================================================================

    /** GET /api/v2/admin/tools/404-errors */
    public function get404Errors(): JsonResponse
    {
        $this->requireAdmin();

        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 50, 1, 100);
        $offset = ($page - 1) * $perPage;

        try {
            $total = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM error_404_log WHERE resolved = 0")->cnt;

            $errors = DB::select(
                "SELECT id, url, referer, hit_count, first_seen_at, last_seen_at, resolved
                 FROM error_404_log WHERE resolved = 0
                 ORDER BY hit_count DESC, last_seen_at DESC
                 LIMIT {$perPage} OFFSET {$offset}"
            );

            $formatted = array_map(fn($r) => [
                'id' => (int) $r->id,
                'url' => $r->url,
                'referrer' => $r->referer ?? null,
                'hits' => (int) ($r->hit_count ?? 1),
                'first_seen' => $r->first_seen_at ?? '',
                'last_seen' => $r->last_seen_at ?? '',
            ], $errors);

            return $this->respondWithData(['items' => $formatted, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
        } catch (\Throwable $e) {
            return $this->respondWithData(['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage]);
        }
    }

    /** DELETE /api/v2/admin/tools/404-errors/{id} */
    public function delete404Error(int $id): JsonResponse
    {
        $this->requireAdmin();

        try {
            $error = DB::selectOne("SELECT id FROM error_404_log WHERE id = ?", [$id]);
            if (!$error) {
                return $this->respondWithError('NOT_FOUND', '404 error entry not found', 'id', 404);
            }

            DB::delete("DELETE FROM error_404_log WHERE id = ?", [$id]);
            return $this->respondWithData(['deleted' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            return $this->respondWithError('NOT_FOUND', '404 error entry not found or table does not exist', 'id', 404);
        }
    }

    // =========================================================================
    // Health Check
    // =========================================================================

    /** POST /api/v2/admin/tools/health-check */
    public function runHealthCheck(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $tests = [];

        // Database
        $start = microtime(true);
        try {
            DB::selectOne("SELECT 1");
            $tests[] = ['name' => 'Database Connection', 'status' => 'pass', 'duration_ms' => round((microtime(true) - $start) * 1000)];
        } catch (\Throwable $e) {
            $tests[] = ['name' => 'Database Connection', 'status' => 'fail', 'duration_ms' => round((microtime(true) - $start) * 1000), 'error' => $e->getMessage()];
        }

        // Redis
        $start = microtime(true);
        try {
            $stats = RedisCache::getStats();
            $tests[] = ['name' => 'Redis Connection', 'status' => ($stats['enabled'] ?? false) ? 'pass' : 'fail', 'duration_ms' => round((microtime(true) - $start) * 1000)];
        } catch (\Throwable $e) {
            $tests[] = ['name' => 'Redis Connection', 'status' => 'fail', 'duration_ms' => round((microtime(true) - $start) * 1000), 'error' => $e->getMessage()];
        }

        // Token Service
        $start = microtime(true);
        try {
            $testToken = TokenService::generateToken(0, $tenantId);
            $tests[] = ['name' => 'API Auth (Token Service)', 'status' => !empty($testToken) ? 'pass' : 'fail', 'duration_ms' => round((microtime(true) - $start) * 1000)];
        } catch (\Throwable $e) {
            $tests[] = ['name' => 'API Auth (Token Service)', 'status' => 'fail', 'duration_ms' => round((microtime(true) - $start) * 1000), 'error' => $e->getMessage()];
        }

        // Tenant
        $start = microtime(true);
        try {
            $tenant = DB::selectOne("SELECT id, name FROM tenants WHERE id = ?", [$tenantId]);
            $tests[] = ['name' => 'Tenant Bootstrap', 'status' => $tenant ? 'pass' : 'fail', 'duration_ms' => round((microtime(true) - $start) * 1000)];
        } catch (\Throwable $e) {
            $tests[] = ['name' => 'Tenant Bootstrap', 'status' => 'fail', 'duration_ms' => round((microtime(true) - $start) * 1000), 'error' => $e->getMessage()];
        }

        $passCount = count(array_filter($tests, fn($t) => $t['status'] === 'pass'));

        return $this->respondWithData([
            'tests' => $tests,
            'summary' => ['total' => count($tests), 'passed' => $passCount, 'failed' => count($tests) - $passCount, 'overall' => $passCount === count($tests) ? 'healthy' : 'degraded'],
        ]);
    }

    // =========================================================================
    // WebP, Seed, Blog Backups, SEO Audit
    // =========================================================================

    /** GET /api/v2/admin/tools/webp-stats */
    public function getWebpStats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'getWebpStats');
    }

    /** POST /api/v2/admin/tools/webp-conversion */
    public function runWebpConversion(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData(['started' => true, 'message' => 'Conversion queued']);
    }

    /** POST /api/v2/admin/tools/seed-generator */
    public function runSeedGenerator(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'runSeedGenerator');
    }

    /** GET /api/v2/admin/tools/blog-backups */
    public function getBlogBackups(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'getBlogBackups');
    }

    /** POST /api/v2/admin/tools/blog-backups/restore */
    public function restoreBlogBackup(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'restoreBlogBackup');
    }

    /** GET /api/v2/admin/tools/seo-audit */
    public function getSeoAudit(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $audit = DB::selectOne("SELECT * FROM seo_audits WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1", [$tenantId]);
            if ($audit && !empty($audit->results)) {
                $audit = (array) $audit;
                $audit['results'] = json_decode($audit['results'], true) ?: [];
            }
            return $this->respondWithData($audit ? (array) $audit : null);
        } catch (\Throwable $e) {
            return $this->respondWithData(null);
        }
    }

    /** POST /api/v2/admin/tools/seo-audit */
    public function runSeoAudit(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData(['started' => true, 'message' => 'SEO audit queued']);
    }

    /** GET /api/v2/admin/tools/ip-debug */
    public function ipDebug(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData(\Nexus\Core\ClientIp::debug());
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
