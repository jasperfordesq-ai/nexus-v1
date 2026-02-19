<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Admin Cron API Controller
 * Handles cron job logs, settings, and health monitoring for React admin
 */
class AdminCronApiController
{
    /**
     * JSON response helper
     */
    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    /**
     * Get JSON input
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Logs
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/system/cron-jobs/logs
     * Get paginated cron job logs with filters
     */
    public function getLogs()
    {
        $tenantId = TenantContext::getId();
        $params = $_GET;

        $where = ["tenant_id = ?"];
        $values = [$tenantId];

        if (!empty($params['jobId'])) {
            $where[] = "job_id = ?";
            $values[] = $params['jobId'];
        }

        if (!empty($params['status'])) {
            $where[] = "status = ?";
            $values[] = $params['status'];
        }

        if (!empty($params['startDate'])) {
            $where[] = "executed_at >= ?";
            $values[] = $params['startDate'];
        }

        if (!empty($params['endDate'])) {
            $where[] = "executed_at <= ?";
            $values[] = $params['endDate'];
        }

        $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

        // Get total count
        $countStmt = Database::query(
            "SELECT COUNT(*) as total FROM cron_logs WHERE " . implode(' AND ', $where),
            $values
        );
        $total = $countStmt->fetch()['total'] ?? 0;

        // Get logs
        $stmt = Database::query(
            "SELECT * FROM cron_logs WHERE " . implode(' AND ', $where) .
            " ORDER BY executed_at DESC LIMIT ? OFFSET ?",
            array_merge($values, [$limit, $offset])
        );

        $logs = $stmt->fetchAll();

        return $this->jsonResponse([
            'success' => true,
            'data' => $logs,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /**
     * GET /api/v2/admin/system/cron-jobs/logs/{id}
     * Get single log detail
     */
    public function getLogDetail($logId)
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT * FROM cron_logs WHERE id = ? AND tenant_id = ?",
            [$logId, $tenantId]
        );

        $log = $stmt->fetch();

        if (!$log) {
            return $this->jsonResponse(['success' => false, 'error' => 'Log not found'], 404);
        }

        return $this->jsonResponse(['success' => true, 'data' => $log]);
    }

    /**
     * DELETE /api/v2/admin/system/cron-jobs/logs
     * Clear old logs before a specific date
     */
    public function clearLogs()
    {
        $tenantId = TenantContext::getId();
        $beforeDate = $_GET['before'] ?? null;

        if (!$beforeDate) {
            return $this->jsonResponse(['success' => false, 'error' => 'Missing before date'], 400);
        }

        $stmt = Database::query(
            "DELETE FROM cron_logs WHERE tenant_id = ? AND executed_at < ?",
            [$tenantId, $beforeDate]
        );

        $rowCount = $stmt->rowCount();

        return $this->jsonResponse([
            'success' => true,
            'message' => "Deleted {$rowCount} log entries",
            'deleted_count' => $rowCount,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Settings
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/system/cron-jobs/{jobId}/settings
     * Get per-job settings
     */
    public function getJobSettings($jobId)
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT * FROM cron_job_settings WHERE job_id = ? AND tenant_id = ?",
            [$jobId, $tenantId]
        );

        $settings = $stmt->fetch();

        // Return defaults if no settings exist
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
        }

        return $this->jsonResponse(['success' => true, 'data' => $settings]);
    }

    /**
     * PUT /api/v2/admin/system/cron-jobs/{jobId}/settings
     * Update per-job settings
     */
    public function updateJobSettings($jobId)
    {
        $tenantId = TenantContext::getId();
        $data = $this->getJsonInput();

        // Check if settings exist
        $stmt = Database::query(
            "SELECT id FROM cron_job_settings WHERE job_id = ? AND tenant_id = ?",
            [$jobId, $tenantId]
        );

        $existing = $stmt->fetch();

        if ($existing) {
            // Update
            $fields = [];
            $values = [];

            if (isset($data['is_enabled'])) {
                $fields[] = "is_enabled = ?";
                $values[] = $data['is_enabled'] ? 1 : 0;
            }
            if (isset($data['custom_schedule'])) {
                $fields[] = "custom_schedule = ?";
                $values[] = $data['custom_schedule'];
            }
            if (isset($data['notify_on_failure'])) {
                $fields[] = "notify_on_failure = ?";
                $values[] = $data['notify_on_failure'] ? 1 : 0;
            }
            if (isset($data['notify_emails'])) {
                $fields[] = "notify_emails = ?";
                $values[] = $data['notify_emails'];
            }
            if (isset($data['max_retries'])) {
                $fields[] = "max_retries = ?";
                $values[] = (int)$data['max_retries'];
            }
            if (isset($data['timeout_seconds'])) {
                $fields[] = "timeout_seconds = ?";
                $values[] = (int)$data['timeout_seconds'];
            }

            $values[] = $existing['id'];

            Database::query(
                "UPDATE cron_job_settings SET " . implode(', ', $fields) . " WHERE id = ?",
                $values
            );
        } else {
            // Insert
            Database::query(
                "INSERT INTO cron_job_settings
                (job_id, tenant_id, is_enabled, custom_schedule, notify_on_failure, notify_emails, max_retries, timeout_seconds)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $jobId,
                    $tenantId,
                    $data['is_enabled'] ?? 1,
                    $data['custom_schedule'] ?? null,
                    $data['notify_on_failure'] ?? 0,
                    $data['notify_emails'] ?? null,
                    $data['max_retries'] ?? 3,
                    $data['timeout_seconds'] ?? 300,
                ]
            );
        }

        return $this->jsonResponse(['success' => true]);
    }

    /**
     * GET /api/v2/admin/system/cron-jobs/settings
     * Get global cron settings
     */
    public function getGlobalSettings()
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT * FROM cron_settings WHERE tenant_id = ?",
            [$tenantId]
        );

        $settings = $stmt->fetch();

        // Return defaults if no settings exist
        if (!$settings) {
            $settings = [
                'default_notify_email' => null,
                'log_retention_days' => 30,
                'max_concurrent_jobs' => 5,
            ];
        }

        return $this->jsonResponse(['success' => true, 'data' => $settings]);
    }

    /**
     * PUT /api/v2/admin/system/cron-jobs/settings
     * Update global cron settings
     */
    public function updateGlobalSettings()
    {
        $tenantId = TenantContext::getId();
        $data = $this->getJsonInput();

        // Check if settings exist
        $stmt = Database::query(
            "SELECT id FROM cron_settings WHERE tenant_id = ?",
            [$tenantId]
        );

        $existing = $stmt->fetch();

        if ($existing) {
            // Update
            $fields = [];
            $values = [];

            if (isset($data['default_notify_email'])) {
                $fields[] = "default_notify_email = ?";
                $values[] = $data['default_notify_email'];
            }
            if (isset($data['log_retention_days'])) {
                $fields[] = "log_retention_days = ?";
                $values[] = (int)$data['log_retention_days'];
            }
            if (isset($data['max_concurrent_jobs'])) {
                $fields[] = "max_concurrent_jobs = ?";
                $values[] = (int)$data['max_concurrent_jobs'];
            }

            $values[] = $existing['id'];

            Database::query(
                "UPDATE cron_settings SET " . implode(', ', $fields) . " WHERE id = ?",
                $values
            );
        } else {
            // Insert
            Database::query(
                "INSERT INTO cron_settings (tenant_id, default_notify_email, log_retention_days, max_concurrent_jobs)
                VALUES (?, ?, ?, ?)",
                [
                    $tenantId,
                    $data['default_notify_email'] ?? null,
                    $data['log_retention_days'] ?? 30,
                    $data['max_concurrent_jobs'] ?? 5,
                ]
            );
        }

        return $this->jsonResponse(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Health
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/system/cron-jobs/health
     * Get health metrics and status
     */
    public function getHealthMetrics()
    {
        $tenantId = TenantContext::getId();

        // Jobs failed in last 24h
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM cron_logs
             WHERE tenant_id = ? AND status = 'failed'
             AND executed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$tenantId]
        );
        $jobsFailed24h = $stmt->fetch()['count'] ?? 0;

        // Recent failures (last 5)
        $stmt = Database::query(
            "SELECT job_name, executed_at as failed_at, output as reason
             FROM cron_logs
             WHERE tenant_id = ? AND status = 'failed'
             ORDER BY executed_at DESC LIMIT 5",
            [$tenantId]
        );
        $recentFailures = $stmt->fetchAll();

        // Average success rate over 7 days
        $stmt = Database::query(
            "SELECT
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successes,
                COUNT(*) as total
             FROM cron_logs
             WHERE tenant_id = ?
             AND executed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$tenantId]
        );
        $rateData = $stmt->fetch();
        $avgSuccessRate7d = $rateData['total'] > 0
            ? round($rateData['successes'] / $rateData['total'], 2)
            : 1.0;

        // Check for overdue jobs (simplified - jobs that haven't run in 24h)
        $stmt = Database::query(
            "SELECT DISTINCT j.id as job_id, j.name as job_name, j.schedule, j.last_run_at as last_run
             FROM cron_jobs j
             LEFT JOIN cron_logs l ON j.id = l.job_id AND l.tenant_id = ?
             WHERE j.tenant_id = ? AND j.status = 'active'
             AND (j.last_run_at IS NULL OR j.last_run_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
             LIMIT 5",
            [$tenantId, $tenantId]
        );
        $jobsOverdue = $stmt->fetchAll();

        // Add expected_interval to overdue jobs
        foreach ($jobsOverdue as &$job) {
            $job['expected_interval'] = '24 hours'; // Simplified
        }

        // Calculate health score (0-100)
        $healthScore = 100;
        $healthScore -= ($jobsFailed24h * 5); // -5 per failure in 24h
        $healthScore -= ((1.0 - $avgSuccessRate7d) * 50); // -50 max for low success rate
        $healthScore -= (count($jobsOverdue) * 10); // -10 per overdue job
        $healthScore = max(0, min(100, $healthScore));

        // Determine alert status
        $alertStatus = 'healthy';
        if ($healthScore < 50) {
            $alertStatus = 'critical';
        } elseif ($healthScore < 80) {
            $alertStatus = 'warning';
        }

        return $this->jsonResponse([
            'success' => true,
            'data' => [
                'health_score' => (int)$healthScore,
                'recent_failures' => $recentFailures,
                'jobs_failed_24h' => (int)$jobsFailed24h,
                'jobs_overdue' => $jobsOverdue,
                'avg_success_rate_7d' => (float)$avgSuccessRate7d,
                'alert_status' => $alertStatus,
            ],
        ]);
    }
}
