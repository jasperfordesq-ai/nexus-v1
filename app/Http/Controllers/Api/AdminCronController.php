<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * AdminCronController -- Admin cron job logs, settings, and health metrics.
 *
 * All methods require admin authentication.
 */
class AdminCronController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    // =========================================================================
    // Logs
    // =========================================================================

    /** GET /api/v2/admin/cron/logs */
    public function getLogs(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $where = ["(tenant_id = ? OR tenant_id IS NULL)"];
        $values = [$tenantId];

        $jobId = $this->query('jobId');
        if ($jobId) {
            $where[] = "job_id = ?";
            $values[] = $jobId;
        }

        $status = $this->query('status');
        if ($status) {
            $dbStatus = $status === 'failed' ? 'error' : $status;
            $where[] = "status = ?";
            $values[] = $dbStatus;
        }

        $startDate = $this->query('startDate');
        if ($startDate) {
            $where[] = "executed_at >= ?";
            $values[] = $startDate;
        }

        $endDate = $this->query('endDate');
        if ($endDate) {
            $where[] = "executed_at <= ?";
            $values[] = $endDate;
        }

        $limit = $this->queryInt('limit', 50, 1, 200);
        $offset = $this->queryInt('offset', 0, 0);

        $whereClause = implode(' AND ', $where);

        $total = (int) DB::selectOne(
            "SELECT COUNT(*) as total FROM cron_logs WHERE {$whereClause}",
            $values
        )->total;

        $logs = DB::select(
            "SELECT id, job_id, status, executed_at, duration_seconds, output AS error_message, tenant_id
             FROM cron_logs WHERE {$whereClause} ORDER BY executed_at DESC LIMIT ? OFFSET ?",
            array_merge($values, [$limit, $offset])
        );

        $logsArray = array_map(function ($log) {
            $arr = (array) $log;
            if (($arr['status'] ?? '') === 'error') {
                $arr['status'] = 'failed';
            }
            return $arr;
        }, $logs);

        return $this->success(['data' => $logsArray, 'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset]]);
    }

    /** GET /api/v2/admin/cron/logs/{logId} */
    public function getLogDetail(int $logId): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $log = DB::selectOne(
            "SELECT id, job_id, status, executed_at, duration_seconds,
                    LEFT(output, 10000) AS output, tenant_id
             FROM cron_logs WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL)",
            [$logId, $tenantId]
        );

        if (!$log) {
            return $this->error(__('api_controllers_1.admin_cron.log_not_found'), 404);
        }

        $arr = (array) $log;
        if (($arr['status'] ?? '') === 'error') {
            $arr['status'] = 'failed';
        }

        return $this->success($arr);
    }

    /** DELETE /api/v2/admin/cron/logs */
    public function clearLogs(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $beforeDate = $this->query('before');

        if ($beforeDate) {
            $rowCount = DB::delete(
                "DELETE FROM cron_logs WHERE (tenant_id = ? OR tenant_id IS NULL) AND executed_at < ?",
                [$tenantId, $beforeDate]
            );
        } else {
            // Clear all logs for this tenant
            $rowCount = DB::delete(
                "DELETE FROM cron_logs WHERE tenant_id = ? OR tenant_id IS NULL",
                [$tenantId]
            );
        }

        return $this->success(['message' => __('api_controllers_1.admin_cron.deleted_log_entries', ['count' => $rowCount]), 'deleted_count' => $rowCount]);
    }

    // =========================================================================
    // Job Settings
    // =========================================================================

    /** GET /api/v2/admin/cron/jobs/{jobId}/settings */
    public function getJobSettings(int $jobId): JsonResponse
    {
        $this->requireSuperAdmin();

        $settings = DB::selectOne("SELECT * FROM cron_job_settings WHERE job_id = ?", [$jobId]);

        if (!$settings) {
            $settings = [
                'job_id' => $jobId,
                'is_enabled' => true,
                'custom_schedule' => null,
                'notify_on_failure' => false,
                'notify_emails' => null,
                'max_retries' => 3,
                'timeout_seconds' => 300,
            ];
        } else {
            $settings = (array) $settings;
        }

        return $this->success($settings);
    }

    /** PUT /api/v2/admin/cron/jobs/{jobId}/settings */
    public function updateJobSettings(int $jobId): JsonResponse
    {
        $this->requireSuperAdmin();
        $data = $this->getAllInput();

        $existing = DB::selectOne("SELECT id FROM cron_job_settings WHERE job_id = ?", [$jobId]);

        if ($existing) {
            $fields = [];
            $values = [];

            $fieldMap = [
                'is_enabled' => fn($v) => $v ? 1 : 0,
                'custom_schedule' => fn($v) => $v,
                'notify_on_failure' => fn($v) => $v ? 1 : 0,
                'notify_emails' => fn($v) => $v,
                'max_retries' => fn($v) => (int) $v,
                'timeout_seconds' => fn($v) => (int) $v,
            ];

            foreach ($fieldMap as $key => $transform) {
                if (isset($data[$key])) {
                    $fields[] = "{$key} = ?";
                    $values[] = $transform($data[$key]);
                }
            }

            if (!empty($fields)) {
                $values[] = $existing->id;
                DB::update("UPDATE cron_job_settings SET " . implode(', ', $fields) . " WHERE id = ?", $values);
            }
        } else {
            DB::insert(
                "INSERT INTO cron_job_settings (job_id, is_enabled, custom_schedule, notify_on_failure, notify_emails, max_retries, timeout_seconds) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $jobId,
                    $data['is_enabled'] ?? 1,
                    $data['custom_schedule'] ?? null,
                    $data['notify_on_failure'] ?? 0,
                    $data['notify_emails'] ?? null,
                    $data['max_retries'] ?? 3,
                    $data['timeout_seconds'] ?? 300,
                ]
            );
        }

        return $this->success(null);
    }

    // =========================================================================
    // Global Settings
    // =========================================================================

    /** GET /api/v2/admin/cron/settings */
    public function getGlobalSettings(): JsonResponse
    {
        $this->requireSuperAdmin();

        $rows = DB::select("SELECT setting_key, setting_value FROM cron_settings");
        $map = [];
        foreach ($rows as $row) {
            $map[$row->setting_key] = $row->setting_value;
        }

        return $this->success([
            'default_notify_email' => $map['default_notify_email'] ?? null,
            'log_retention_days' => (int) ($map['log_retention_days'] ?? 30),
            'max_concurrent_jobs' => (int) ($map['max_concurrent_jobs'] ?? 5),
        ]);
    }

    /** PUT /api/v2/admin/cron/settings */
    public function updateGlobalSettings(): JsonResponse
    {
        $this->requireSuperAdmin();
        $data = $this->getAllInput();

        $allowedKeys = ['default_notify_email', 'log_retention_days', 'max_concurrent_jobs'];

        foreach ($allowedKeys as $key) {
            if (!isset($data[$key])) continue;
            DB::statement(
                "INSERT INTO cron_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$key, (string) $data[$key]]
            );
        }

        return $this->success(null);
    }

    // =========================================================================
    // Health
    // =========================================================================

    /** GET /api/v2/admin/cron/health */
    public function getHealthMetrics(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $jobsFailed24h = (int) DB::selectOne(
            "SELECT COUNT(*) as count FROM cron_logs WHERE (tenant_id = ? OR tenant_id IS NULL) AND status = 'error' AND executed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$tenantId]
        )->count;

        $recentFailures = DB::select(
            "SELECT l.job_id, COALESCE(j.job_name, l.job_id) as job_name, l.executed_at as failed_at, l.output as reason
             FROM cron_logs l LEFT JOIN cron_jobs j ON j.job_name = l.job_id AND j.tenant_id = ?
             WHERE (l.tenant_id = ? OR l.tenant_id IS NULL) AND l.status = 'error'
             ORDER BY l.executed_at DESC LIMIT 5",
            [$tenantId, $tenantId]
        );

        $rateData = DB::selectOne(
            "SELECT SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successes, COUNT(*) as total
             FROM cron_logs WHERE (tenant_id = ? OR tenant_id IS NULL) AND executed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$tenantId]
        );
        $avgSuccessRate7d = ($rateData->total ?? 0) > 0
            ? round(($rateData->successes ?? 0) / $rateData->total, 2)
            : 1.0;

        $jobsOverdue = DB::select(
            "SELECT j.id as job_id, j.job_name, j.last_run FROM cron_jobs j
             WHERE j.tenant_id = ? AND j.enabled = 1 AND (j.last_run IS NULL OR j.last_run < DATE_SUB(NOW(), INTERVAL 24 HOUR)) LIMIT 5",
            [$tenantId]
        );

        $overdueArr = array_map(fn($j) => array_merge((array) $j, ['expected_interval' => '24 hours']), $jobsOverdue);

        $healthScore = 100;
        $healthScore -= ($jobsFailed24h * 5);
        $healthScore -= ((1.0 - $avgSuccessRate7d) * 50);
        $healthScore -= (count($jobsOverdue) * 10);
        $healthScore = max(0, min(100, $healthScore));

        $alertStatus = $healthScore < 50 ? 'critical' : ($healthScore < 80 ? 'warning' : 'healthy');

        return $this->success([
            'health_score' => (int) $healthScore,
            'recent_failures' => array_map(fn($r) => (array) $r, $recentFailures),
            'jobs_failed_24h' => $jobsFailed24h,
            'jobs_overdue' => $overdueArr,
            'avg_success_rate_7d' => (float) $avgSuccessRate7d,
            'alert_status' => $alertStatus,
        ]);
    }
}
