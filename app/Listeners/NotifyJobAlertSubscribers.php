<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\JobVacancyCreated;
use App\Models\JobAlert;
use App\Models\Notification;
use App\Services\RealtimeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Notifies users who have active job alerts when a new matching vacancy is created.
 *
 * Checks keyword, type, commitment, location, and remote-only preferences.
 * Queued to avoid blocking the main request.
 */
class NotifyJobAlertSubscribers implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(JobVacancyCreated $event): void
    {
        try {
            // Ensure tenant context is set (required when running via async queue)
            TenantContext::setById($event->tenantId);

            $vacancy = $event->vacancy;
            $tenantId = $event->tenantId;

            $alerts = JobAlert::where('tenant_id', $tenantId)
                ->where('is_active', 1)
                ->get();

            foreach ($alerts as $alert) {
                if (!$this->matchesAlert($vacancy, $alert)) {
                    continue;
                }

                try {
                    $alertUserId = (int) $alert->user_id;
                    Notification::createNotification(
                        $alertUserId,
                        "New job matching your alert: {$vacancy->title}",
                        "/jobs/{$vacancy->id}",
                        'job_application'
                    );
                    RealtimeService::broadcastAndPush($alertUserId, 'New Job Match', [
                        'type'      => 'job_alert_match',
                        'job_id'    => (int) $vacancy->id,
                        'job_title' => $vacancy->title,
                        'message'   => "New job matching your alert: {$vacancy->title}",
                        'url'       => "/jobs/{$vacancy->id}",
                    ]);

                    // Send email alert
                    try {
                        $user = \App\Models\User::find($alert->user_id);
                        if ($user && $user->email) {
                            \App\Services\JobAlertEmailService::sendImmediateAlert($user, $vacancy, $alert);
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('NotifyJobAlertSubscribers: email dispatch failed: ' . $e->getMessage());
                    }

                    $alert->update(['last_notified_at' => now()]);
                } catch (\Throwable $e) {
                    Log::warning('NotifyJobAlertSubscribers: failed for alert ' . $alert->id, [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('NotifyJobAlertSubscribers listener failed', [
                'vacancy_id' => $event->vacancy->id ?? null,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check whether a vacancy matches the given job alert's criteria.
     */
    private function matchesAlert(\App\Models\JobVacancy $vacancy, JobAlert $alert): bool
    {
        // Keywords: vacancy title or description must contain at least one keyword
        if (!empty($alert->keywords)) {
            $keywords = array_filter(array_map('trim', explode(',', $alert->keywords)));
            if (!empty($keywords)) {
                $title = strtolower($vacancy->title ?? '');
                $description = strtolower($vacancy->description ?? '');
                $found = false;
                foreach ($keywords as $keyword) {
                    if (str_contains($title, strtolower($keyword)) ||
                        str_contains($description, strtolower($keyword))) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return false;
                }
            }
        }

        // Type: alert type must match vacancy type, or alert type is null/empty (any)
        if (!empty($alert->type) && $alert->type !== $vacancy->type) {
            return false;
        }

        // Commitment: alert commitment must match, or null/empty (any)
        if (!empty($alert->commitment) && $alert->commitment !== $vacancy->commitment) {
            return false;
        }

        // Location: alert location must match, or null/empty (any)
        if (!empty($alert->location) && !empty($vacancy->location)) {
            if (stripos($vacancy->location, $alert->location) === false) {
                return false;
            }
        }

        // Remote only: if alert requires remote, vacancy must be remote
        if (!empty($alert->is_remote_only) && empty($vacancy->is_remote)) {
            return false;
        }

        return true;
    }
}
