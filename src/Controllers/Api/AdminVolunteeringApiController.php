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
                        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_count
                 FROM vol_opportunities WHERE tenant_id = ?",
                [$tenantId]
            );
            $row = $stmt->fetch();
            $data['stats']['total_opportunities'] = (int) ($row['total'] ?? 0);
            $data['stats']['active_opportunities'] = (int) ($row['active_count'] ?? 0);
        } catch (\Exception $e) {}

        if ($this->tableExists('vol_applications')) {
            try {
                $stmt = Database::query(
                    "SELECT COUNT(*) as total,
                            SUM(CASE WHEN va.status='pending' THEN 1 ELSE 0 END) as pending
                     FROM vol_applications va
                     INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                     WHERE vo.tenant_id = ?",
                    [$tenantId]
                );
                $row = $stmt->fetch();
                $data['stats']['total_applications'] = (int) ($row['total'] ?? 0);
                $data['stats']['pending_applications'] = (int) ($row['pending'] ?? 0);
            } catch (\Exception $e) {}
        }

        if ($this->tableExists('vol_logs')) {
            try {
                $stmt = Database::query(
                    "SELECT COALESCE(SUM(vl.hours), 0) as total_hours, COUNT(DISTINCT vl.user_id) as volunteers
                     FROM vol_logs vl
                     INNER JOIN vol_opportunities vo ON vl.opportunity_id = vo.id
                     WHERE vo.tenant_id = ?",
                    [$tenantId]
                );
                $row = $stmt->fetch();
                $data['stats']['total_hours_logged'] = round((float) ($row['total_hours'] ?? 0), 1);
                $data['stats']['active_volunteers'] = (int) ($row['volunteers'] ?? 0);
            } catch (\Exception $e) {}
        }

        try {
            $stmt = Database::query(
                "SELECT vo.*, u.first_name, u.last_name
                 FROM vol_opportunities vo
                 LEFT JOIN users u ON vo.user_id = u.id
                 WHERE vo.tenant_id = ?
                 ORDER BY vo.created_at DESC LIMIT 10",
                [$tenantId]
            );
            $data['recent_opportunities'] = $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {}

        $this->respondWithData($data);
    }

    public function approvals(): void
    {
        $this->requireAdmin();
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

    public function approveApplication(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = $this->getRouteParam('id');

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
                "UPDATE vol_applications SET status = 'approved', updated_at = NOW() WHERE id = ?",
                [$id]
            );

            $this->respondWithData(['message' => 'Application approved']);
        } catch (\Exception $e) {
            $this->respondWithError('Failed to approve application');
        }
    }

    public function declineApplication(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = $this->getRouteParam('id');

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
                "UPDATE vol_applications SET status = 'declined', updated_at = NOW() WHERE id = ?",
                [$id]
            );

            $this->respondWithData(['message' => 'Application declined']);
        } catch (\Exception $e) {
            $this->respondWithError('Failed to decline application');
        }
    }

    public function organizations(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Try org_wallets or organizations table for volunteer orgs
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
