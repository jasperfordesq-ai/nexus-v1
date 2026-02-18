<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Admin Newsletter API Controller
 * Provides CRUD for newsletters, subscribers, segments, templates, and analytics.
 * Gracefully returns empty data if tables don't exist.
 */
class AdminNewsletterApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    private function tableExists(string $table): bool
    {
        try {
            Database::query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $status = $this->query('status');

        if (!$this->tableExists('newsletters')) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
            return;
        }

        try {
            $where = 'WHERE tenant_id = ?';
            $params = [$tenantId];
            if ($status) {
                $where .= ' AND status = ?';
                $params[] = $status;
            }

            $countStmt = Database::query("SELECT COUNT(*) as cnt FROM newsletters {$where}", $params);
            $total = (int) $countStmt->fetch()['cnt'];

            $params[] = $perPage;
            $params[] = $offset;
            $stmt = Database::query(
                "SELECT * FROM newsletters {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
                $params
            );
            $items = $stmt->fetchAll() ?: [];

            $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    public function show(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT * FROM newsletters WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $item = $stmt->fetch();
            if (!$item) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }
            $this->respondWithData($item);
        } catch (\Exception $e) {
            $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
        }
    }

    public function store(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $name = $this->input('name');
        $subject = $this->input('subject', '');
        $content = $this->input('content', '');
        $status = $this->input('status', 'draft');

        if (!$name) {
            $this->respondWithError('VALIDATION_ERROR', 'Name is required', 'name');
            return;
        }

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter functionality is not yet configured', null, 503);
            return;
        }

        try {
            Database::query(
                "INSERT INTO newsletters (tenant_id, name, subject, content, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                [$tenantId, $name, $subject, $content, $status]
            );
            $id = Database::lastInsertId();
            $this->respondWithData(['id' => $id, 'name' => $name, 'status' => $status], null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('CREATE_FAILED', 'Failed to create newsletter');
        }
    }

    public function update(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter functionality is not yet configured', null, 503);
            return;
        }

        try {
            $fields = [];
            $params = [];
            foreach (['name', 'subject', 'content', 'status'] as $field) {
                $val = $this->input($field);
                if ($val !== null) {
                    $fields[] = "{$field} = ?";
                    $params[] = $val;
                }
            }
            if (empty($fields)) {
                $this->respondWithError('VALIDATION_ERROR', 'No fields to update');
                return;
            }
            $params[] = $id;
            $params[] = $tenantId;
            Database::query(
                "UPDATE newsletters SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?",
                $params
            );
            $this->respondWithData(['id' => $id, 'updated' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update newsletter');
        }
    }

    public function destroy(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter functionality is not yet configured', null, 503);
            return;
        }

        try {
            Database::query("DELETE FROM newsletters WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            $this->noContent();
        } catch (\Exception $e) {
            $this->respondWithError('DELETE_FAILED', 'Failed to delete newsletter');
        }
    }

    public function subscribers(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            $stmt = Database::query(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.status, u.created_at
                 FROM users u
                 WHERE u.tenant_id = ? AND u.newsletter_opt_in = 1
                 ORDER BY u.created_at DESC
                 LIMIT 100",
                [$tenantId]
            );
            $subscribers = $stmt->fetchAll() ?: [];
            $this->respondWithData($subscribers);
        } catch (\Exception $e) {
            // Fallback: return all active users as potential subscribers
            try {
                $stmt = Database::query(
                    "SELECT u.id, u.first_name, u.last_name, u.email, u.status, u.created_at
                     FROM users u
                     WHERE u.tenant_id = ? AND u.status = 'active'
                     ORDER BY u.created_at DESC
                     LIMIT 100",
                    [$tenantId]
                );
                $subscribers = $stmt->fetchAll() ?: [];
                $this->respondWithData($subscribers);
            } catch (\Exception $e2) {
                $this->respondWithData([]);
            }
        }
    }

    public function segments(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_segments')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT * FROM newsletter_segments WHERE tenant_id = ? ORDER BY name ASC",
                [$tenantId]
            );
            $this->respondWithData($stmt->fetchAll() ?: []);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function templates(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_templates')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT * FROM newsletter_templates WHERE tenant_id = ? ORDER BY name ASC",
                [$tenantId]
            );
            $this->respondWithData($stmt->fetchAll() ?: []);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function analytics(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $data = [
            'total_newsletters' => 0,
            'total_sent' => 0,
            'avg_open_rate' => 0,
            'avg_click_rate' => 0,
            'total_subscribers' => 0,
        ];

        if (!$this->tableExists('newsletters')) {
            $this->respondWithData($data);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT COUNT(*) as total, SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) as sent,
                        AVG(open_rate) as avg_open, AVG(click_rate) as avg_click
                 FROM newsletters WHERE tenant_id = ?",
                [$tenantId]
            );
            $row = $stmt->fetch();
            if ($row) {
                $data['total_newsletters'] = (int) ($row['total'] ?? 0);
                $data['total_sent'] = (int) ($row['sent'] ?? 0);
                $data['avg_open_rate'] = round((float) ($row['avg_open'] ?? 0), 1);
                $data['avg_click_rate'] = round((float) ($row['avg_click'] ?? 0), 1);
            }

            $subStmt = Database::query(
                "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND status = 'active'",
                [$tenantId]
            );
            $subRow = $subStmt->fetch();
            $data['total_subscribers'] = (int) ($subRow['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Return defaults
        }

        $this->respondWithData($data);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Bounce Management
    // ─────────────────────────────────────────────────────────────────────────────

    public function getBounces(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $limit = $this->queryInt('limit', 50, 1, 500);
        $offset = $this->queryInt('offset', 0, 0);
        $type = $this->query('type');
        $startDate = $this->query('startDate');
        $endDate = $this->query('endDate');

        if (!$this->tableExists('newsletter_bounces')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $where = 'WHERE b.tenant_id = ?';
            $params = [$tenantId];

            if ($type) {
                $where .= ' AND b.bounce_type = ?';
                $params[] = $type;
            }

            if ($startDate) {
                $where .= ' AND b.bounced_at >= ?';
                $params[] = $startDate;
            }

            if ($endDate) {
                $where .= ' AND b.bounced_at <= ?';
                $params[] = $endDate;
            }

            $stmt = Database::query(
                "SELECT b.*, n.subject as newsletter_subject
                 FROM newsletter_bounces b
                 LEFT JOIN newsletters n ON b.newsletter_id = n.id
                 {$where}
                 ORDER BY b.bounced_at DESC
                 LIMIT {$limit} OFFSET {$offset}",
                $params
            );

            $bounces = $stmt->fetchAll() ?: [];
            $this->respondWithData($bounces);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function getSuppressionList(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_suppression_list')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT email, reason, suppressed_at, bounce_count
                 FROM newsletter_suppression_list
                 WHERE tenant_id = ? AND (expires_at IS NULL OR expires_at > NOW())
                 ORDER BY suppressed_at DESC",
                [$tenantId]
            );

            $list = $stmt->fetchAll() ?: [];
            $this->respondWithData($list);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function unsuppress(string $email): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_suppression_list')) {
            $this->respondWithError('TABLE_MISSING', 'Suppression list not available', null, 503);
            return;
        }

        try {
            Database::query(
                "DELETE FROM newsletter_suppression_list WHERE tenant_id = ? AND email = ?",
                [$tenantId, $email]
            );
            $this->respondWithData(['success' => true, 'email' => $email]);
        } catch (\Exception $e) {
            $this->respondWithError('UNSUPPRESS_FAILED', 'Failed to remove email from suppression list');
        }
    }

    public function suppress(string $email): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_suppression_list')) {
            $this->respondWithError('TABLE_MISSING', 'Suppression list not available', null, 503);
            return;
        }

        try {
            Database::query(
                "INSERT INTO newsletter_suppression_list (tenant_id, email, reason, bounce_count)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE suppressed_at = NOW()",
                [$tenantId, $email, 'manual', 0]
            );
            $this->respondWithData(['success' => true, 'email' => $email]);
        } catch (\Exception $e) {
            $this->respondWithError('SUPPRESS_FAILED', 'Failed to add email to suppression list');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Resend Workflow
    // ─────────────────────────────────────────────────────────────────────────────

    public function getResendInfo(int $newsletterId): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters') || !$this->tableExists('newsletter_queue')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter functionality not available', null, 503);
            return;
        }

        try {
            // Get newsletter stats
            $newsletter = Database::query(
                "SELECT id, total_sent, unique_opens, unique_clicks FROM newsletters WHERE id = ? AND tenant_id = ?",
                [$newsletterId, $tenantId]
            )->fetch();

            if (!$newsletter) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }

            // Count non-openers
            $nonOpeners = Database::query(
                "SELECT COUNT(*) as cnt
                 FROM newsletter_queue q
                 WHERE q.newsletter_id = ? AND q.status = 'sent'
                 AND q.email NOT IN (
                     SELECT DISTINCT email FROM newsletter_opens
                     WHERE newsletter_id = ? AND tenant_id = ?
                 )",
                [$newsletterId, $newsletterId, $tenantId]
            )->fetch()['cnt'] ?? 0;

            // Count non-clickers (opened but didn't click)
            $nonClickers = Database::query(
                "SELECT COUNT(DISTINCT o.email) as cnt
                 FROM newsletter_opens o
                 WHERE o.newsletter_id = ? AND o.tenant_id = ?
                 AND o.email NOT IN (
                     SELECT DISTINCT email FROM newsletter_clicks
                     WHERE newsletter_id = ? AND tenant_id = ?
                 )",
                [$newsletterId, $tenantId, $newsletterId, $tenantId]
            )->fetch()['cnt'] ?? 0;

            $this->respondWithData([
                'newsletter_id' => (int)$newsletter['id'],
                'total_sent' => (int)($newsletter['total_sent'] ?? 0),
                'total_opened' => (int)($newsletter['unique_opens'] ?? 0),
                'total_clicked' => (int)($newsletter['unique_clicks'] ?? 0),
                'non_openers_count' => (int)$nonOpeners,
                'non_clickers_count' => (int)$nonClickers,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('FETCH_FAILED', 'Failed to fetch resend info');
        }
    }

    public function resend(int $newsletterId): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $target = $this->input('target');
        $segmentId = $this->inputInt('segment_id');
        $subjectOverride = $this->input('subject_override');

        if (!in_array($target, ['non_openers', 'non_clickers', 'segment'])) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid target', 'target');
            return;
        }

        if (!$this->tableExists('newsletters') || !$this->tableExists('newsletter_queue')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter functionality not available', null, 503);
            return;
        }

        try {
            // Get newsletter
            $newsletter = Database::query(
                "SELECT * FROM newsletters WHERE id = ? AND tenant_id = ?",
                [$newsletterId, $tenantId]
            )->fetch();

            if (!$newsletter) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }

            // Get recipient list based on target
            $recipients = [];
            if ($target === 'non_openers') {
                $stmt = Database::query(
                    "SELECT q.email
                     FROM newsletter_queue q
                     WHERE q.newsletter_id = ? AND q.status = 'sent'
                     AND q.email NOT IN (
                         SELECT DISTINCT email FROM newsletter_opens
                         WHERE newsletter_id = ? AND tenant_id = ?
                     )",
                    [$newsletterId, $newsletterId, $tenantId]
                );
                $recipients = array_column($stmt->fetchAll(), 'email');
            } elseif ($target === 'non_clickers') {
                $stmt = Database::query(
                    "SELECT DISTINCT o.email
                     FROM newsletter_opens o
                     WHERE o.newsletter_id = ? AND o.tenant_id = ?
                     AND o.email NOT IN (
                         SELECT DISTINCT email FROM newsletter_clicks
                         WHERE newsletter_id = ? AND tenant_id = ?
                     )",
                    [$newsletterId, $tenantId, $newsletterId, $tenantId]
                );
                $recipients = array_column($stmt->fetchAll(), 'email');
            } elseif ($target === 'segment' && $segmentId) {
                // TODO: Implement segment-based resend
                $recipients = [];
            }

            if (empty($recipients)) {
                $this->respondWithError('NO_RECIPIENTS', 'No recipients found for resend target');
                return;
            }

            // Queue the resend
            $subject = $subjectOverride ?: $newsletter['subject'];
            $queuedCount = 0;

            foreach ($recipients as $email) {
                $trackingToken = bin2hex(random_bytes(16));
                Database::query(
                    "INSERT INTO newsletter_queue (newsletter_id, email, status, tracking_token, created_at)
                     VALUES (?, ?, 'pending', ?, NOW())",
                    [$newsletterId, $email, $trackingToken]
                );
                $queuedCount++;
            }

            $this->respondWithData([
                'success' => true,
                'queued_count' => $queuedCount,
                'subject' => $subject,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('RESEND_FAILED', 'Failed to queue resend: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Send-Time Optimizer
    // ─────────────────────────────────────────────────────────────────────────────

    public function getSendTimeData(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $days = $this->queryInt('days', 30, 7, 365);

        if (!$this->tableExists('newsletter_opens')) {
            $this->respondWithData([
                'heatmap' => [],
                'recommendations' => [],
                'insights' => 'Not enough data available yet.',
            ]);
            return;
        }

        try {
            // Get heatmap data (day of week × hour)
            $stmt = Database::query(
                "SELECT DAYOFWEEK(opened_at) as day_of_week, HOUR(opened_at) as hour,
                        COUNT(*) as opens, COUNT(DISTINCT email) as unique_opens
                 FROM newsletter_opens
                 WHERE tenant_id = ? AND opened_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY DAYOFWEEK(opened_at), HOUR(opened_at)
                 ORDER BY day_of_week, hour",
                [$tenantId, $days]
            );

            $heatmapRaw = $stmt->fetchAll() ?: [];
            $heatmap = [];

            foreach ($heatmapRaw as $row) {
                $heatmap[] = [
                    'day_of_week' => (int)$row['day_of_week'],
                    'hour' => (int)$row['hour'],
                    'engagement_score' => (int)$row['opens'],
                    'opens' => (int)$row['opens'],
                    'clicks' => 0, // TODO: Join with clicks if needed
                ];
            }

            // Generate recommendations (top 3 times)
            $scores = [];
            foreach ($heatmap as $cell) {
                $key = "{$cell['day_of_week']}_{$cell['hour']}";
                $scores[$key] = $cell['engagement_score'];
            }
            arsort($scores);
            $topTimes = array_slice($scores, 0, 3, true);

            $recommendations = [];
            $dayNames = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($topTimes as $key => $score) {
                [$day, $hour] = explode('_', $key);
                $recommendations[] = [
                    'day_of_week' => (int)$day,
                    'hour' => (int)$hour,
                    'score' => $score,
                    'description' => $dayNames[(int)$day] . ' at ' . date('g:i A', strtotime("{$hour}:00")),
                ];
            }

            $insights = count($heatmap) > 0
                ? "Based on {$days} days of engagement data, these are your community's most active times."
                : "Not enough data available yet. Send a few newsletters to see engagement patterns.";

            $this->respondWithData([
                'heatmap' => $heatmap,
                'recommendations' => $recommendations,
                'insights' => $insights,
            ]);
        } catch (\Exception $e) {
            $this->respondWithData([
                'heatmap' => [],
                'recommendations' => [],
                'insights' => 'Error loading send-time data.',
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Diagnostics
    // ─────────────────────────────────────────────────────────────────────────────

    public function getDiagnostics(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $diagnostics = [
            'queue_status' => [
                'total' => 0,
                'pending' => 0,
                'sending' => 0,
                'sent' => 0,
                'failed' => 0,
            ],
            'bounce_rate' => 0,
            'sender_score' => 100,
            'configuration' => [
                'smtp_configured' => !empty(getenv('SMTP_HOST')),
                'api_configured' => !empty(getenv('USE_GMAIL_API')) && getenv('USE_GMAIL_API') !== 'false',
                'tracking_enabled' => true,
            ],
            'health_status' => 'healthy',
        ];

        // Queue status
        if ($this->tableExists('newsletter_queue')) {
            try {
                $queueStats = Database::query(
                    "SELECT status, COUNT(*) as cnt FROM newsletter_queue WHERE tenant_id = ? GROUP BY status",
                    [$tenantId]
                )->fetchAll();

                foreach ($queueStats as $row) {
                    $status = $row['status'] ?? 'unknown';
                    $count = (int)$row['cnt'];
                    $diagnostics['queue_status']['total'] += $count;
                    if (isset($diagnostics['queue_status'][$status])) {
                        $diagnostics['queue_status'][$status] = $count;
                    }
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Bounce rate
        if ($this->tableExists('newsletter_bounces') && $this->tableExists('newsletter_queue')) {
            try {
                $totalSent = Database::query(
                    "SELECT COUNT(*) as cnt FROM newsletter_queue WHERE tenant_id = ? AND status = 'sent'",
                    [$tenantId]
                )->fetch()['cnt'] ?? 0;

                $totalBounces = Database::query(
                    "SELECT COUNT(*) as cnt FROM newsletter_bounces WHERE tenant_id = ?",
                    [$tenantId]
                )->fetch()['cnt'] ?? 0;

                if ($totalSent > 0) {
                    $diagnostics['bounce_rate'] = round(($totalBounces / $totalSent) * 100, 2);
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Determine health status
        if ($diagnostics['bounce_rate'] > 10) {
            $diagnostics['health_status'] = 'critical';
        } elseif ($diagnostics['bounce_rate'] > 5 || $diagnostics['queue_status']['failed'] > 10) {
            $diagnostics['health_status'] = 'warning';
        }

        $this->respondWithData($diagnostics);
    }
}
