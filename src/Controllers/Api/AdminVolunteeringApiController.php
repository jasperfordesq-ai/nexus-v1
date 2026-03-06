<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Admin Volunteering API Controller
 * Provides overview stats, approval management, and organization listing.
 * Gracefully returns empty data if tables don't exist.
 */
class AdminVolunteeringApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    private const ALLOWED_TABLES = [
        'vol_opportunities',
        'vol_applications',
        'vol_shifts',
        'vol_shift_signups',
        'vol_organizations',
        'vol_logs',
        'vol_shift_checkins',
        'vol_mood_checkins',
        'vol_emergency_alerts',
    ];

    private const ALLOWED_COLUMNS = [
        'vol_opportunities' => ['created_by', 'user_id', 'is_active', 'status', 'title', 'created_at', 'tenant_id'],
    ];

    private function tableExists(string $table): bool
    {
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            return false;
        }
        try {
            Database::query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!isset(self::ALLOWED_COLUMNS[$table]) || !in_array($column, self::ALLOWED_COLUMNS[$table], true)) {
            return false;
        }

        try {
            $result = Database::query(
                "SELECT COUNT(*) as cnt
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $column]
            )->fetch();

            return ((int)($result['cnt'] ?? 0)) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve the author column used by vol_opportunities across schema variants.
     */
    private function getOpportunityAuthorColumn(): ?string
    {
        if ($this->columnExists('vol_opportunities', 'created_by')) {
            return 'created_by';
        }

        if ($this->columnExists('vol_opportunities', 'user_id')) {
            return 'user_id';
        }

        return null;
    }

    public function index(): void
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            $this->jsonResponse(['error' => 'Feature not available'], 403);
            return;
        }
        $tenantId = TenantContext::getId();

        $data = [
            'stats' => [
                'total_opportunities' => 0,
                'active_opportunities' => 0,
                'total_applications' => 0,
                'pending_applications' => 0,
                'total_hours_logged' => 0,
                'active_volunteers' => 0,
            ],
            'recent_opportunities' => [],
        ];

        if (!$this->tableExists('vol_opportunities')) {
            $this->respondWithData($data);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status IN ('open', 'active') AND is_active=1 THEN 1 ELSE 0 END) as active_count
                 FROM vol_opportunities WHERE tenant_id = ?",
                [$tenantId]
            );
            $row = $stmt->fetch();
            $data['stats']['total_opportunities'] = (int) ($row['total'] ?? 0);
            $data['stats']['active_opportunities'] = (int) ($row['active_count'] ?? 0);
        } catch (\Exception $e) {
            error_log('[AdminVolunteering] Failed to fetch opportunity stats: ' . $e->getMessage());
        }

        if ($this->tableExists('vol_applications')) {
            try {
                $stmt = Database::query(
                    "SELECT COUNT(*) as total,
                            SUM(CASE WHEN va.status='pending' THEN 1 ELSE 0 END) as pending
                     FROM vol_applications va
                     WHERE va.tenant_id = ?",
                    [$tenantId]
                );
                $row = $stmt->fetch();
                $data['stats']['total_applications'] = (int) ($row['total'] ?? 0);
                $data['stats']['pending_applications'] = (int) ($row['pending'] ?? 0);
            } catch (\Exception $e) {
                error_log('[AdminVolunteering] Failed to fetch application stats: ' . $e->getMessage());
            }
        }

        if ($this->tableExists('vol_logs')) {
            try {
                $stmt = Database::query(
                    "SELECT COALESCE(SUM(vl.hours), 0) as total_hours, COUNT(DISTINCT vl.user_id) as volunteers
                     FROM vol_logs vl
                     WHERE vl.tenant_id = ?",
                    [$tenantId]
                );
                $row = $stmt->fetch();
                $data['stats']['total_hours_logged'] = round((float) ($row['total_hours'] ?? 0), 1);
                $data['stats']['active_volunteers'] = (int) ($row['volunteers'] ?? 0);
            } catch (\Exception $e) {
                error_log('[AdminVolunteering] Failed to fetch hours/volunteer stats: ' . $e->getMessage());
            }
        }

        try {
            $authorColumn = $this->getOpportunityAuthorColumn();
            if ($authorColumn !== null) {
                $stmt = Database::query(
                    "SELECT vo.id, vo.title, vo.status, vo.is_active, vo.created_at,
                            CASE
                                WHEN vo.is_active = 1 AND (vo.status = 'open' OR vo.status = 'active') THEN 'active'
                                ELSE vo.status
                            END as ui_status,
                            u.first_name, u.last_name
                     FROM vol_opportunities vo
                     LEFT JOIN users u ON vo.{$authorColumn} = u.id
                     WHERE vo.tenant_id = ?
                     ORDER BY vo.created_at DESC LIMIT 10",
                    [$tenantId]
                );
            } else {
                $stmt = Database::query(
                    "SELECT vo.id, vo.title, vo.status, vo.is_active, vo.created_at,
                            CASE
                                WHEN vo.is_active = 1 AND (vo.status = 'open' OR vo.status = 'active') THEN 'active'
                                ELSE vo.status
                            END as ui_status,
                            NULL as first_name, NULL as last_name
                     FROM vol_opportunities vo
                     WHERE vo.tenant_id = ?
                     ORDER BY vo.created_at DESC LIMIT 10",
                    [$tenantId]
                );
            }
            $rows = $stmt->fetchAll() ?: [];
            $data['recent_opportunities'] = array_map(static function (array $row): array {
                $row['status'] = $row['ui_status'] ?? $row['status'];
                unset($row['ui_status']);
                return $row;
            }, $rows);
        } catch (\Exception $e) {
            error_log('[AdminVolunteering] Failed to fetch recent opportunities: ' . $e->getMessage());
        }

        $this->respondWithData($data);
    }

    public function approvals(): void
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            $this->jsonResponse(['error' => 'Feature not available'], 403);
            return;
        }
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('vol_applications')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT va.*, u.first_name, u.last_name, u.email, vo.title as opportunity_title
                 FROM vol_applications va
                 INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                 LEFT JOIN users u ON va.user_id = u.id
                 WHERE vo.tenant_id = ? AND va.status = 'pending'
                 ORDER BY va.created_at DESC LIMIT 50",
                [$tenantId]
            );
            $this->respondWithData($stmt->fetchAll() ?: []);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function approveApplication(int $id): void
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            $this->jsonResponse(['error' => 'Feature not available'], 403);
            return;
        }
        $this->verifyCsrf();
        $tenantId = TenantContext::getId();

        if (!$id || !$this->tableExists('vol_applications')) {
            $this->respondWithError('NOT_FOUND', 'Application not found', null, 404);
            return;
        }

        try {
            // Verify the application belongs to this tenant
            $app = Database::query(
                "SELECT va.id, va.status, va.user_id, vo.title as opportunity_title
                 FROM vol_applications va
                 INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                 WHERE va.id = ? AND vo.tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$app) {
                $this->respondWithError('NOT_FOUND', 'Application not found', null, 404);
                return;
            }

            Database::query(
                "UPDATE vol_applications SET status = 'approved', updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            $this->respondWithData(['message' => 'Application approved']);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to approve application', null, 500);
        }
    }

    public function declineApplication(int $id): void
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            $this->jsonResponse(['error' => 'Feature not available'], 403);
            return;
        }
        $this->verifyCsrf();
        $tenantId = TenantContext::getId();

        if (!$id || !$this->tableExists('vol_applications')) {
            $this->respondWithError('NOT_FOUND', 'Application not found', null, 404);
            return;
        }

        try {
            $app = Database::query(
                "SELECT va.id FROM vol_applications va
                 INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                 WHERE va.id = ? AND vo.tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$app) {
                $this->respondWithError('NOT_FOUND', 'Application not found', null, 404);
                return;
            }

            Database::query(
                "UPDATE vol_applications SET status = 'declined', updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            $this->respondWithData(['message' => 'Application declined']);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to decline application', null, 500);
        }
    }

    public function organizations(): void
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            $this->jsonResponse(['error' => 'Feature not available'], 403);
            return;
        }
        $tenantId = TenantContext::getId();

        if ($this->tableExists('vol_organizations')) {
            try {
                $stmt = Database::query(
                    "SELECT vo.id,
                            vo.id as org_id,
                            vo.name as org_name,
                            vo.status,
                            vo.created_at,
                            COALESCE((SELECT COUNT(*) FROM org_members om WHERE om.tenant_id = vo.tenant_id AND om.organization_id = vo.id AND om.status = 'active'), 0) as member_count,
                            COALESCE((SELECT COUNT(*) FROM vol_opportunities opp WHERE opp.tenant_id = vo.tenant_id AND opp.organization_id = vo.id AND opp.is_active = 1), 0) as opportunity_count,
                            COALESCE((SELECT SUM(vl.hours) FROM vol_logs vl WHERE vl.tenant_id = vo.tenant_id AND vl.organization_id = vo.id AND vl.status = 'approved'), 0) as total_hours,
                            0 as balance,
                            0 as total_in,
                            0 as total_out
                     FROM vol_organizations vo
                     WHERE vo.tenant_id = ?
                     ORDER BY vo.name ASC
                     LIMIT 100",
                    [$tenantId]
                );
                $this->respondWithData($stmt->fetchAll() ?: []);
                return;
            } catch (\Throwable $e) {
                error_log('[AdminVolunteering] Failed to fetch volunteering organizations: ' . $e->getMessage());
            }
        }

        // Fallback to legacy org_wallets data where volunteering org tables are unavailable.
        try {
            $stmt = Database::query(
                "SELECT ow.*, o.name as org_name
                 FROM org_wallets ow
                 LEFT JOIN organizations o ON ow.org_id = o.id
                 WHERE ow.tenant_id = ?
                 ORDER BY o.name ASC LIMIT 50",
                [$tenantId]
            );
            $this->respondWithData($stmt->fetchAll() ?: []);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }
}
