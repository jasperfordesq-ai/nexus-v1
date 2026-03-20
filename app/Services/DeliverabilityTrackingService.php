<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class DeliverabilityTrackingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy DeliverabilityTrackingService::createDeliverable().
     */
    public function createDeliverable($ownerId, $title, $description = null, $options = [])
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy DeliverabilityTrackingService::updateDeliverableStatus().
     */
    public function updateDeliverableStatus($deliverableId, $newStatus, $userId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy DeliverabilityTrackingService::completeDeliverable().
     */
    public function completeDeliverable($deliverableId, $userId, $options = [])
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy DeliverabilityTrackingService::recalculateProgress().
     */
    public function recalculateProgress($deliverableId, $userId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy DeliverabilityTrackingService::getAnalytics().
     */
    public function getAnalytics($filters = [])
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
