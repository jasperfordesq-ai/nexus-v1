<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Models\VolApplication;
use App\Models\VolEmergencyAlert;
use App\Models\VolEmergencyAlertRecipient;
use App\Models\VolOpportunity;
use App\Models\VolOrganization;
use App\Models\VolShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VolunteerEmergencyAlertService — urgent shift-fill requests.
 *
 * Coordinators can send urgent alerts to qualified volunteers when
 * a shift needs to be filled quickly. Filters by skills, availability,
 * and proximity.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait on models.
 */
class VolunteerEmergencyAlertService
{
    /** @var array */
    private static array $errors = [];

    public function __construct()
    {
    }

    /**
     * Get errors from the last operation.
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Create an emergency alert for a shift.
     *
     * @param int $createdBy Coordinator user ID
     * @param array $data [shift_id, message, priority, required_skills, expires_hours]
     * @return int|null Alert ID or null on failure
     */
    public static function createAlert(int $createdBy, array $data): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $shiftId = (int) ($data['shift_id'] ?? 0);
        $message = trim($data['message'] ?? '');
        $priority = $data['priority'] ?? 'urgent';
        $requiredSkills = $data['required_skills'] ?? null;
        $expiresHours = (int) ($data['expires_hours'] ?? 24);

        if (!$shiftId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Shift ID is required', 'field' => 'shift_id'];
            return null;
        }

        if (!$message) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Message is required', 'field' => 'message'];
            return null;
        }

        if (!in_array($priority, ['normal', 'urgent', 'critical'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Priority must be normal, urgent, or critical', 'field' => 'priority'];
            return null;
        }

        // Verify shift exists
        $shift = VolShift::find($shiftId);
        if (!$shift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return null;
        }

        // Verify coordinator owns the opportunity's org
        $opportunity = VolOpportunity::with('organization')->find($shift->opportunity_id);
        if (!$opportunity || !$opportunity->organization) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return null;
        }

        // Allow org owner or tenant admin
        if (!self::isAdminOrOrgOwner($createdBy, (int) $opportunity->organization->user_id)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only coordinators or admins can create emergency alerts'];
            return null;
        }

        // Encode skills as JSON string for storage
        $skillsJson = null;
        if ($requiredSkills) {
            if (is_array($requiredSkills)) {
                $skillsJson = json_encode($requiredSkills);
            } elseif (is_string($requiredSkills)) {
                $skills = array_filter(array_map('trim', explode(',', $requiredSkills)));
                $skillsJson = json_encode(array_values($skills));
            }
        }

        $expiresAt = now()->addHours($expiresHours);

        try {
            $alert = VolEmergencyAlert::create([
                'tenant_id' => $tenantId,
                'shift_id' => $shiftId,
                'created_by' => $createdBy,
                'priority' => $priority,
                'message' => $message,
                'required_skills' => $skillsJson,
                'status' => 'active',
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ]);

            // Notify qualified volunteers (best-effort, non-blocking)
            self::notifyQualifiedVolunteers($alert->id, $shiftId, $skillsJson, $tenantId, $priority, $message);

            return $alert->id;
        } catch (\Exception $e) {
            Log::error('VolunteerEmergencyAlertService::createAlert error: ' . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create emergency alert'];
            return null;
        }
    }

    /**
     * Respond to an emergency alert (accept/decline).
     *
     * @param int $alertId Alert ID
     * @param int $userId Responding user
     * @param string $response 'accepted' or 'declined'
     * @return bool Success
     */
    public static function respond(int $alertId, int $userId, string $response): bool
    {
        self::$errors = [];

        if (!in_array($response, ['accepted', 'declined'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Response must be accepted or declined'];
            return false;
        }

        // Verify the user was a recipient with pending response
        $recipient = VolEmergencyAlertRecipient::whereHas('alert', function ($q) {
            $q->where('tenant_id', TenantContext::getId());
        })
            ->where('alert_id', $alertId)
            ->where('user_id', $userId)
            ->where('response', 'pending')
            ->first();

        if (!$recipient) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'You were not invited for this alert or have already responded'];
            return false;
        }

        // Check alert is still active
        $alert = VolEmergencyAlert::where('id', $alertId)
            ->where('status', 'active')
            ->first();

        if (!$alert) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This alert is no longer active'];
            return false;
        }

        try {
            DB::transaction(function () use ($recipient, $response, $alert, $alertId, $userId) {
                $recipient->update([
                    'response' => $response,
                    'responded_at' => now(),
                ]);

                if ($response === 'accepted') {
                    // Mark alert as filled
                    $alert->update([
                        'status' => 'filled',
                        'filled_at' => now(),
                    ]);
                }
            });

            if ($response === 'accepted') {
                // Notify the coordinator (best-effort)
                try {
                    $user = User::find($userId);
                    $userName = $user->name ?? 'A volunteer';

                    \App\Services\NotificationDispatcher::dispatch(
                        (int) $alert->created_by,
                        'global',
                        0,
                        'volunteer_emergency_filled',
                        "{$userName} has accepted your emergency shift request!",
                        '/volunteering',
                        null
                    );
                } catch (\Throwable $e) {
                    // Silent fail for notification
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('VolunteerEmergencyAlertService::respond error: ' . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to process response'];
            return false;
        }
    }

    /**
     * Get active alerts for a user (ones they've been notified about) with cursor pagination.
     *
     * @param int $userId User ID
     * @param array $filters Optional: limit, cursor
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getUserAlerts(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 50);
        $cursor = $filters['cursor'] ?? null;

        $query = VolEmergencyAlert::query()
            ->join('vol_emergency_alert_recipients as r', 'r.alert_id', '=', 'vol_emergency_alerts.id')
            ->join('vol_shifts as s', 'vol_emergency_alerts.shift_id', '=', 's.id')
            ->join('vol_opportunities as o', 's.opportunity_id', '=', 'o.id')
            ->join('vol_organizations as org', 'o.organization_id', '=', 'org.id')
            ->join('users as u', 'vol_emergency_alerts.created_by', '=', 'u.id')
            ->where('r.user_id', $userId)
            ->where('vol_emergency_alerts.status', 'active')
            ->select([
                'vol_emergency_alerts.*',
                'r.response as my_response',
                'r.notified_at',
                's.start_time',
                's.end_time',
                'o.title as opp_title',
                'o.location as opp_location',
                'org.name as org_name',
                'u.name as coordinator_name',
            ]);

        if ($cursor !== null && ($cid = base64_decode($cursor, true)) !== false) {
            $query->where('vol_emergency_alerts.id', '<', (int) $cid);
        }

        $query->orderByDesc('vol_emergency_alerts.id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $mapped = $items->map(fn ($a) => [
            'id' => $a->id,
            'priority' => $a->priority,
            'message' => $a->message,
            'my_response' => $a->my_response,
            'required_skills' => json_decode($a->getRawOriginal('required_skills') ?? '[]', true) ?: [],
            'shift' => [
                'id' => (int) $a->shift_id,
                'start_time' => $a->start_time,
                'end_time' => $a->end_time,
            ],
            'opportunity' => [
                'title' => $a->opp_title,
                'location' => $a->opp_location,
            ],
            'organization' => [
                'name' => $a->org_name,
            ],
            'coordinator' => [
                'name' => $a->coordinator_name,
            ],
            'expires_at' => $a->expires_at?->toDateTimeString(),
            'created_at' => $a->created_at?->toDateTimeString(),
        ]);

        return [
            'items'    => $mapped->toArray(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get alerts created by a coordinator.
     *
     * @param int $coordinatorId Coordinator user ID
     * @return array Alerts with response stats
     */
    public static function getCoordinatorAlerts(int $coordinatorId): array
    {
        $alerts = VolEmergencyAlert::query()
            ->join('vol_shifts as s', 'vol_emergency_alerts.shift_id', '=', 's.id')
            ->join('vol_opportunities as o', 's.opportunity_id', '=', 'o.id')
            ->where('vol_emergency_alerts.created_by', $coordinatorId)
            ->orderByDesc('vol_emergency_alerts.created_at')
            ->limit(50)
            ->select([
                'vol_emergency_alerts.*',
                's.start_time',
                's.end_time',
                'o.title as opp_title',
            ])
            ->get();

        return $alerts->map(function ($a) {
            $totalNotified = VolEmergencyAlertRecipient::where('alert_id', $a->id)->count();
            $totalAccepted = VolEmergencyAlertRecipient::where('alert_id', $a->id)->where('response', 'accepted')->count();
            $totalDeclined = VolEmergencyAlertRecipient::where('alert_id', $a->id)->where('response', 'declined')->count();

            return [
                'id' => $a->id,
                'priority' => $a->priority,
                'message' => $a->message,
                'status' => $a->status,
                'shift' => [
                    'id' => (int) $a->shift_id,
                    'start_time' => $a->start_time,
                    'end_time' => $a->end_time,
                ],
                'opportunity_title' => $a->opp_title,
                'stats' => [
                    'total_notified' => $totalNotified,
                    'total_accepted' => $totalAccepted,
                    'total_declined' => $totalDeclined,
                ],
                'expires_at' => $a->expires_at?->toDateTimeString(),
                'created_at' => $a->created_at?->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * Cancel an active emergency alert.
     *
     * @param int $alertId  Alert ID
     * @param int $userId   User cancelling (must be the creator)
     * @param int $tenantId Tenant ID
     * @return bool Success
     */
    public static function cancelAlert(int $alertId, int $userId, int $tenantId): bool
    {
        self::$errors = [];

        $alert = VolEmergencyAlert::where('id', $alertId)
            ->where('tenant_id', $tenantId)
            ->where('created_by', $userId)
            ->where('status', 'active')
            ->first();

        if (!$alert) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Alert not found or cannot be cancelled'];
            return false;
        }

        try {
            $alert->update(['status' => 'cancelled']);
            return true;
        } catch (\Exception $e) {
            Log::error('VolunteerEmergencyAlertService::cancelAlert error: ' . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel alert'];
            return false;
        }
    }

    /**
     * Get errors from the last cancelAlert() call.
     */
    public static function getCancelErrors(): array
    {
        return self::$errors;
    }

    /**
     * Find and notify qualified volunteers for an emergency alert.
     */
    private static function notifyQualifiedVolunteers(int $alertId, int $shiftId, ?string $skillsJson, int $tenantId, string $priority, string $message): int
    {
        // Find volunteers who:
        // 1. Have approved applications for the opportunity's org
        // 2. Are not already signed up for this specific shift
        $candidates = User::query()
            ->join('vol_applications as va', function ($join) use ($tenantId) {
                $join->on('va.user_id', '=', 'users.id')
                    ->where('va.status', '=', 'approved')
                    ->where('va.tenant_id', '=', $tenantId);
            })
            ->join('vol_opportunities as opp', function ($join) use ($tenantId) {
                $join->on('va.opportunity_id', '=', 'opp.id')
                    ->where('opp.tenant_id', '=', $tenantId);
            })
            ->join('vol_shifts as s', function ($join) use ($shiftId, $tenantId) {
                $join->on('s.opportunity_id', '=', 'opp.id')
                    ->where('s.id', '=', $shiftId)
                    ->where('s.tenant_id', '=', $tenantId);
            })
            ->whereNotIn('users.id', function ($q) use ($shiftId, $tenantId) {
                $q->select('user_id')
                    ->from('vol_applications')
                    ->where('shift_id', $shiftId)
                    ->where('status', 'approved')
                    ->where('tenant_id', $tenantId);
            })
            ->select('users.id as user_id', 'users.email', 'users.name', 'users.skills')
            ->distinct()
            ->limit(50)
            ->get();

        $requiredSkills = [];
        if ($skillsJson) {
            $decoded = json_decode($skillsJson, true);
            $requiredSkills = is_array($decoded) ? $decoded : [];
        }

        $notifiedCount = 0;
        $shift = VolShift::find($shiftId);
        $shiftDate = $shift ? $shift->start_time->format('M j, Y g:ia') : 'upcoming';

        foreach ($candidates as $candidate) {
            // If skills are required, check for match
            if (!empty($requiredSkills)) {
                $userSkillsRaw = $candidate->skills ?? '';
                $userSkills = array_filter(array_map('trim', explode(',', $userSkillsRaw)));
                $hasMatch = false;

                foreach ($requiredSkills as $requiredSkill) {
                    $requiredSkill = trim($requiredSkill);
                    if ($requiredSkill === '') {
                        continue;
                    }
                    foreach ($userSkills as $userSkill) {
                        if (preg_match('/\b' . preg_quote($requiredSkill, '/') . '\b/i', $userSkill)) {
                            $hasMatch = true;
                            break 2;
                        }
                    }
                }

                if (!$hasMatch) {
                    continue;
                }
            }

            try {
                VolEmergencyAlertRecipient::create([
                    'alert_id' => $alertId,
                    'tenant_id' => $tenantId,
                    'user_id' => $candidate->user_id,
                    'notified_at' => now(),
                    'response' => 'pending',
                ]);

                // Send notification (best-effort)
                $priorityLabel = strtoupper($priority);
                \App\Services\NotificationDispatcher::dispatch(
                    (int) $candidate->user_id,
                    'global',
                    0,
                    'volunteer_emergency',
                    "[{$priorityLabel}] {$message} - Shift on {$shiftDate}. Can you help?",
                    '/volunteering',
                    null
                );

                $notifiedCount++;
            } catch (\Throwable $e) {
                Log::warning("Failed to notify volunteer {$candidate->user_id}: " . $e->getMessage());
            }
        }

        return $notifiedCount;
    }

    /**
     * Check if user is admin or org owner.
     */
    private static function isAdminOrOrgOwner(int $userId, int $orgOwnerId): bool
    {
        if ($userId === $orgOwnerId) {
            return true;
        }

        $user = User::find($userId);
        if ($user && in_array($user->role ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'])) {
            return true;
        }

        return false;
    }
}
