<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\EventRecurrenceCapabilityResource;
use App\Services\EventRecurrenceCapabilityService;
use Illuminate\Http\JsonResponse;

/** Authenticated runtime contract for maintained recurrence clients. */
final class EventRecurrenceCapabilityController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly EventRecurrenceCapabilityService $capabilities,
    ) {}

    public function show(): JsonResponse
    {
        $this->requireAuth();
        $response = $this->respondWithData(
            EventRecurrenceCapabilityResource::fromCapabilities(
                $this->capabilities->capabilities(),
            ),
        );
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->setVary(['Authorization', 'Cookie', 'X-Tenant-ID'], false);

        return $response;
    }
}
