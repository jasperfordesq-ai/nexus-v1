<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * VolunteerCheckInService � Laravel DI wrapper for legacy \Nexus\Services\VolunteerCheckInService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class VolunteerCheckInService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VolunteerCheckInService::checkIn().
     */
    public function checkIn(int $tenantId, int $opportunityId, int $userId): bool
    {
        if (!class_exists('\Nexus\Services\VolunteerCheckInService')) { return false; }
        return \Nexus\Services\VolunteerCheckInService::checkIn($tenantId, $opportunityId, $userId);
    }

    /**
     * Delegates to legacy VolunteerCheckInService::checkOut().
     */
    public function checkOut(int $tenantId, int $opportunityId, int $userId, ?float $hours = null): bool
    {
        if (!class_exists('\Nexus\Services\VolunteerCheckInService')) { return false; }
        return \Nexus\Services\VolunteerCheckInService::checkOut($tenantId, $opportunityId, $userId, $hours);
    }

    /**
     * Delegates to legacy VolunteerCheckInService::getCheckIns().
     */
    public function getCheckIns(int $tenantId, int $opportunityId): array
    {
        if (!class_exists('\Nexus\Services\VolunteerCheckInService')) { return []; }
        return \Nexus\Services\VolunteerCheckInService::getCheckIns($tenantId, $opportunityId);
    }

    /**
     * Delegates to legacy VolunteerCheckInService::isCheckedIn().
     */
    public function isCheckedIn(int $tenantId, int $opportunityId, int $userId): bool
    {
        if (!class_exists('\Nexus\Services\VolunteerCheckInService')) { return false; }
        return \Nexus\Services\VolunteerCheckInService::isCheckedIn($tenantId, $opportunityId, $userId);
    }

    /**
     * Get a user's check-in record for a specific shift.
     */
    public function getUserCheckIn(int $userId, int $shiftId, int $tenantId): ?array
    {
        if (!class_exists('\Nexus\Services\VolunteerCheckInService')) { return null; }
        return \Nexus\Services\VolunteerCheckInService::getUserCheckIn($shiftId, $userId);
    }

    /**
     * Generate a QR check-in token for a shift+user.
     */
    public function generateToken(int $shiftId, int $tenantId): string
    {
        // Legacy generateToken requires userId; this signature is shift-level.
        // Delegate with tenantId context — the legacy method uses TenantContext internally.
        $token = bin2hex(random_bytes(32));

        $existing = \Illuminate\Support\Facades\DB::table('vol_shift_checkins')
            ->where('shift_id', $shiftId)
            ->where('tenant_id', $tenantId)
            ->value('qr_token');

        if ($existing) {
            return $existing;
        }

        \Illuminate\Support\Facades\DB::table('vol_shift_checkins')->insert([
            'tenant_id'  => $tenantId,
            'shift_id'   => $shiftId,
            'qr_token'   => $token,
            'status'     => 'pending',
            'created_at' => now(),
        ]);

        return $token;
    }

    /**
     * Resolve shift ID from a check-in token.
     */
    public function getShiftIdByToken(string $token, int $tenantId): ?int
    {
        $shiftId = \Illuminate\Support\Facades\DB::table('vol_shift_checkins')
            ->where('qr_token', $token)
            ->where('tenant_id', $tenantId)
            ->value('shift_id');

        return $shiftId !== null ? (int) $shiftId : null;
    }

    /**
     * Verify a check-in via QR token scan.
     */
    public function verifyCheckIn(array $data, int $tenantId): array|false
    {
        $token = $data['token'] ?? '';

        $checkin = \Illuminate\Support\Facades\DB::table('vol_shift_checkins as c')
            ->join('vol_shifts as s', 'c.shift_id', '=', 's.id')
            ->join('users as u', 'c.user_id', '=', 'u.id')
            ->where('c.qr_token', $token)
            ->where('c.tenant_id', $tenantId)
            ->select('c.*', 's.start_time', 's.end_time', 's.opportunity_id', 'u.name as user_name', 'u.avatar_url as user_avatar')
            ->first();

        if (!$checkin) {
            return false;
        }

        if ($checkin->status === 'checked_in') {
            return [
                'status'        => 'already_checked_in',
                'checked_in_at' => $checkin->checked_in_at,
                'user'          => ['id' => (int) $checkin->user_id, 'name' => $checkin->user_name, 'avatar_url' => $checkin->user_avatar],
                'shift'         => ['id' => (int) $checkin->shift_id, 'start_time' => $checkin->start_time, 'end_time' => $checkin->end_time],
            ];
        }

        if ($checkin->status === 'checked_out') {
            return false;
        }

        \Illuminate\Support\Facades\DB::table('vol_shift_checkins')
            ->where('id', $checkin->id)
            ->where('tenant_id', $tenantId)
            ->update(['status' => 'checked_in', 'checked_in_at' => now()]);

        return [
            'status'        => 'checked_in',
            'checked_in_at' => now()->toDateTimeString(),
            'user'          => ['id' => (int) $checkin->user_id, 'name' => $checkin->user_name, 'avatar_url' => $checkin->user_avatar],
            'shift'         => ['id' => (int) $checkin->shift_id, 'start_time' => $checkin->start_time, 'end_time' => $checkin->end_time],
        ];
    }

    /**
     * Resolve volunteer user ID from a check-in token.
     */
    public function getUserIdByToken(string $token): ?int
    {
        $userId = \Illuminate\Support\Facades\DB::table('vol_shift_checkins')
            ->where('qr_token', $token)
            ->value('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /**
     * Get all check-ins for a shift.
     */
    public function getShiftCheckIns(int $shiftId, int $tenantId): array
    {
        $checkins = \Illuminate\Support\Facades\DB::table('vol_shift_checkins as c')
            ->join('users as u', 'c.user_id', '=', 'u.id')
            ->where('c.shift_id', $shiftId)
            ->where('c.tenant_id', $tenantId)
            ->orderBy('c.created_at', 'asc')
            ->select('c.*', 'u.name as user_name', 'u.avatar_url as user_avatar')
            ->get();

        return $checkins->map(function ($c) {
            return [
                'id'             => (int) $c->id,
                'user'           => ['id' => (int) $c->user_id, 'name' => $c->user_name, 'avatar_url' => $c->user_avatar],
                'status'         => $c->status,
                'checked_in_at'  => $c->checked_in_at,
                'checked_out_at' => $c->checked_out_at,
            ];
        })->toArray();
    }
}
