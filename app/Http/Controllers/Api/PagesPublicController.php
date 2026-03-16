<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * PagesPublicController -- Public CMS page content.
 *
 * Delegates to legacy: PagesPublicApiController
 */
class PagesPublicController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET pages/slug */
    public function show(string $slug): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\PagesPublicApiController();
            $controller->show($slug);
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
