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
}
