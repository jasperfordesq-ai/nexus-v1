<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * MemberVerificationBadgeController -- Member verification badges.
 *
 * Delegates to legacy: MemberVerificationBadgeApiController
 */
class MemberVerificationBadgeController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET verification-badges */
    public function getUserBadges(int $id): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\MemberVerificationBadgeApiController();
            $controller->getUserBadges($id);
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** POST admin/badges */
    public function grantBadge(int $id): JsonResponse
    {
        $this->requireAdmin();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\MemberVerificationBadgeApiController();
            $controller->grantBadge($id);
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** DELETE admin/badges */
    public function revokeBadge(int $id, string $type): JsonResponse
    {
        $this->requireAdmin();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\MemberVerificationBadgeApiController();
            $controller->revokeBadge($id, $type);
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** GET admin/badges */
    public function getAdminBadgeList(int $id): JsonResponse
    {
        $this->requireAdmin();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\MemberVerificationBadgeApiController();
            $controller->getAdminBadgeList($id);
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }
}
