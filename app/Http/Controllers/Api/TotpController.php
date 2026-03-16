<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * TotpController -- TOTP two-factor authentication.
 *
 * Delegates to legacy: TotpApiController
 */
class TotpController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** POST totp/verify */
    public function verify(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\TotpApiController();
            $controller->verify();
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

    /** GET totp/status */
    public function status(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\TotpApiController();
            $controller->status();
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
