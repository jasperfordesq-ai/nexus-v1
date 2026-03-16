<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * IdentityWebhookController -- Identity provider webhook handler.
 *
 * Delegates to legacy: IdentityWebhookController
 */
class IdentityWebhookController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** POST webhooks/identity */
    public function handleWebhook(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\IdentityWebhookController();
            $controller->handleWebhook();
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
