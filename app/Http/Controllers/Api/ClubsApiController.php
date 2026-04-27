<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * ClubsApiController — public directory of Vereine (clubs/associations).
 *
 * AG15: Swiss civic life is built around Vereine. This controller exposes
 * a read-only, publicly accessible directory of vol_organizations where
 * org_type = 'club'.
 *
 * Endpoints:
 *   GET /api/v2/clubs   — paginated list, optional ?search=
 */
class ClubsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/clubs
     *
     * Returns a paginated list of active clubs for the current tenant.
     * No authentication required — tenant is resolved from slug/host header.
     *
     * Query params:
     *   search   string  Optional name/description filter
     *   page     int     Page number (default 1)
     *   per_page int     Items per page (default 20, max 50)
     */
    public function index(): JsonResponse
    {
        $tenantId = TenantContext::getId();

        $search  = $this->query('search');
        $page    = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 50);
        $offset  = ($page - 1) * $perPage;

        $conditions = ['o.tenant_id = ?', "o.org_type = 'club'", "o.status = 'active'"];
        $params     = [$tenantId];

        if ($search) {
            $conditions[] = '(o.name LIKE ? OR o.description LIKE ?)';
            $pattern = '%' . addcslashes(trim($search), '%_\\') . '%';
            $params[] = $pattern;
            $params[] = $pattern;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) DB::selectOne(
            "SELECT COUNT(*) AS total FROM vol_organizations o WHERE {$where}",
            $params
        )->total;

        $rows = DB::select(
            "SELECT
                o.id,
                o.name,
                o.description,
                o.logo_url,
                o.contact_email,
                o.website,
                o.meeting_schedule,
                o.created_at,
                (SELECT COUNT(DISTINCT om.user_id)
                 FROM org_members om
                 WHERE om.organization_id = o.id AND om.status = 'active') AS member_count
             FROM vol_organizations o
             WHERE {$where}
             ORDER BY o.name ASC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        $items = array_map(function ($row) {
            return [
                'id'               => (int) $row->id,
                'name'             => $row->name,
                'description'      => $row->description,
                'logo_url'         => $row->logo_url,
                'contact_email'    => $row->contact_email,
                'website'          => $row->website,
                'meeting_schedule' => $row->meeting_schedule,
                'member_count'     => (int) ($row->member_count ?? 0),
                'created_at'       => $row->created_at,
            ];
        }, $rows);

        return $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
    }
}
