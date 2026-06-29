<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\PublicChangelogService;
use Illuminate\Http\JsonResponse;

class PublicChangelogController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly PublicChangelogService $changelog,
    ) {
    }

    public function index(): JsonResponse
    {
        return $this->respondWithData($this->changelog->summary());
    }
}
