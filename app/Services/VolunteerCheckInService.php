<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\VolApplication;
use App\Models\VolShift;
use App\Models\VolShiftCheckin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VolunteerCheckInService — QR-based check-in for volunteer shifts.
 *
 * Generates unique QR tokens per shift+volunteer combination.
 * Volunteers scan QR on arrival to mark checked in.
 * Coordinators can verify check-ins via the admin dashboard.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait on models.
 */
class VolunteerCheckInService
{
    public function __construct()
    {
    }

    /**
     * Check in a volunteer for an opportunity (legacy-compatible signature).
     */
    public function checkIn(int $tenantId, int $opportunityId, int $userId): bool
    {
        try {
            $shift = VolShift::where('opportunity_id', $opportunityId)->first();
            if (!$shift) {
                return false;
            }

            $checkin = VolShiftCheckin::where('shift_id', $shift->id)
                ->where('user_id', $userId)
                ->first();

            if (!$checkin) {
                return false;
            }

            if ($checkin->status === 'checked_in') {
                return true; // already checked in
            }

            $checkin->update([
                'status' => 'checked_in',
                'checked_in_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('VolunteerCheckInService::checkIn error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check out a volunteer from an opportunity (legacy-compatible signature).
     */
    public function checkOut(int $tenantId, int $opportunityId, int $userId, ?float $hours = null): bool
    {
        try {
            $shift = VolShift::where('opportunity_id', $opportunityId)->first();
            if (!$shift) {
                return false;
            }

            $checkin = VolShiftCheckin::where('shift_id', $shift->id)
                ->where('user_id', $userId)
                ->where('status', 'checked_in')
                ->first();

            if (!$checkin) {
                return false;
            }

            $checkin->update([
                'status' => 'checked_out',
                'checked_out_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('VolunteerCheckInService::checkOut error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all check-ins for an opportunity.
     */
    public function getCheckIns(int $tenantId, int $opportunityId): array
    {
        try {
            $shiftIds = VolShift::where('opportunity_id', $opportunityId)->pluck('id');

            return VolShiftCheckin::with('user')
                ->whereIn('shift_id', $shiftIds)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'user' => [
                        'id' => $c->user_id,
                        'name' => $c->user->name ?? '',
                        'avatar_url' => $c->user->avatar_url ?? null,
                    ],
                    'status' => $c->status,
                    'checked_in_at' => $c->checked_in_at?->toDateTimeString(),
                    'checked_out_at' => $c->checked_out_at?->toDateTimeString(),
                ])
                ->toArray();
        } catch (\Exception $e) {
            Log::error('VolunteerCheckInService::getCheckIns error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if a volunteer is currently checked in for an opportunity.
     */
    public function isCheckedIn(int $tenantId, int $opportunityId, int $userId): bool
    {
        try {
            $shiftIds = VolShift::where('opportunity_id', $opportunityId)->pluck('id');

            return VolShiftCheckin::whereIn('shift_id', $shiftIds)
                ->where('user_id', $userId)
                ->where('status', 'checked_in')
                ->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get a user's check-in record for a specific shift.
     */
    public function getUserCheckIn(int $userId, int $shiftId, int $tenantId): ?array
    {
        $hasApproved = VolApplication::where('shift_id', $shiftId)
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->exists();

        if (!$hasApproved) {
            return null;
        }

        $checkin = VolShiftCheckin::where('shift_id', $shiftId)
            ->where('user_id', $userId)
            ->first();

        if (!$checkin) {
            return null;
        }

        $baseUrl = config('app.url', 'https://api.project-nexus.ie');

        return [
            'id' => $checkin->id,
            'qr_token' => $checkin->qr_token,
            'qr_url' => $baseUrl . '/api/v2/volunteering/checkin/verify/' . $checkin->qr_token,
            'status' => $checkin->status,
            'checked_in_at' => $checkin->checked_in_at?->toDateTimeString(),
            'checked_out_at' => $checkin->checked_out_at?->toDateTimeString(),
        ];
    }

    /**
     * Generate a QR check-in token for a shift.
     */
    public function generateToken(int $shiftId, int $tenantId): string
    {
        $existing = VolShiftCheckin::where('shift_id', $shiftId)
            ->whereNotNull('qr_token')
            ->value('qr_token');

        if ($existing) {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));

        VolShiftCheckin::create([
            'tenant_id' => $tenantId,
            'shift_id' => $shiftId,
            'qr_token' => $token,
            'status' => 'pending',
        ]);

        return $token;
    }

    /**
     * Resolve shift ID from a check-in token.
     */
    public function getShiftIdByToken(string $token, int $tenantId): ?int
    {
        $shiftId = VolShiftCheckin::where('qr_token', $token)
            ->value('shift_id');

        return $shiftId !== null ? (int) $shiftId : null;
    }

    /**
     * Verify a check-in via QR token scan.
     */
    public function verifyCheckIn(array $data, int $tenantId): array|false
    {
        $token = $data['token'] ?? '';

        $checkin = VolShiftCheckin::with(['shift', 'user'])
            ->where('qr_token', $token)
            ->first();

        if (!$checkin) {
            return false;
        }

        if ($checkin->status === 'checked_in') {
            return [
                'status' => 'already_checked_in',
                'checked_in_at' => $checkin->checked_in_at?->toDateTimeString(),
                'user' => [
                    'id' => $checkin->user_id,
                    'name' => $checkin->user->name ?? '',
                    'avatar_url' => $checkin->user->avatar_url ?? null,
                ],
                'shift' => [
                    'id' => $checkin->shift_id,
                    'start_time' => $checkin->shift->start_time?->toDateTimeString(),
                    'end_time' => $checkin->shift->end_time?->toDateTimeString(),
                ],
            ];
        }

        if ($checkin->status === 'checked_out') {
            return false;
        }

        $checkin->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        return [
            'status' => 'checked_in',
            'checked_in_at' => now()->toDateTimeString(),
            'user' => [
                'id' => $checkin->user_id,
                'name' => $checkin->user->name ?? '',
                'avatar_url' => $checkin->user->avatar_url ?? null,
            ],
            'shift' => [
                'id' => $checkin->shift_id,
                'start_time' => $checkin->shift->start_time?->toDateTimeString(),
                'end_time' => $checkin->shift->end_time?->toDateTimeString(),
            ],
        ];
    }

    /**
     * Resolve volunteer user ID from a check-in token.
     */
    public function getUserIdByToken(string $token): ?int
    {
        $userId = VolShiftCheckin::where('qr_token', $token)
            ->value('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /**
     * Get all check-ins for a shift.
     */
    public function getShiftCheckIns(int $shiftId, int $tenantId): array
    {
        return VolShiftCheckin::with('user')
            ->where('shift_id', $shiftId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'user' => [
                    'id' => $c->user_id,
                    'name' => $c->user->name ?? '',
                    'avatar_url' => $c->user->avatar_url ?? null,
                ],
                'status' => $c->status,
                'checked_in_at' => $c->checked_in_at?->toDateTimeString(),
                'checked_out_at' => $c->checked_out_at?->toDateTimeString(),
            ])
            ->toArray();
    }
}
