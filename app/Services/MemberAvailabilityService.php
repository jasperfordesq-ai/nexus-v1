<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * MemberAvailabilityService — Laravel DI-based service for member availability.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\MemberAvailabilityService.
 * Manages recurring weekly availability and compatible time matching.
 */
class MemberAvailabilityService
{
    /**
     * Get all availability slots for a user.
     */
    public function getAvailability(int $userId): array
    {
        return DB::table('member_availability')
            ->where('user_id', $userId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Set recurring availability for a user on a given day (replaces existing).
     *
     * @param int $dayOfWeek 0=Sunday, 6=Saturday
     * @param array $slots Array of ['start_time' => 'HH:MM', 'end_time' => 'HH:MM']
     */
    public function setAvailability(int $userId, int $dayOfWeek, array $slots): bool
    {
        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            return false;
        }

        return DB::transaction(function () use ($userId, $dayOfWeek, $slots) {
            DB::table('member_availability')
                ->where('user_id', $userId)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_recurring', true)
                ->delete();

            foreach ($slots as $slot) {
                if (empty($slot['start_time']) || empty($slot['end_time'])) {
                    continue;
                }
                if ($slot['start_time'] >= $slot['end_time']) {
                    continue;
                }

                DB::table('member_availability')->insert([
                    'user_id'      => $userId,
                    'day_of_week'  => $dayOfWeek,
                    'start_time'   => $slot['start_time'],
                    'end_time'     => $slot['end_time'],
                    'is_recurring' => true,
                    'note'         => $slot['note'] ?? null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            return true;
        });
    }

    /**
     * Find compatible time slots between two users.
     *
     * @return array Overlapping day/time combinations.
     */
    public function findCompatible(int $userIdA, int $userIdB): array
    {
        $slotsA = collect($this->getAvailability($userIdA))->groupBy('day_of_week');
        $slotsB = collect($this->getAvailability($userIdB))->groupBy('day_of_week');

        $compatible = [];

        foreach ($slotsA as $day => $daySlots) {
            if (! isset($slotsB[$day])) {
                continue;
            }

            foreach ($daySlots as $a) {
                foreach ($slotsB[$day] as $b) {
                    $overlapStart = max($a['start_time'], $b['start_time']);
                    $overlapEnd = min($a['end_time'], $b['end_time']);

                    if ($overlapStart < $overlapEnd) {
                        $compatible[] = [
                            'day_of_week' => (int) $day,
                            'start_time'  => $overlapStart,
                            'end_time'    => $overlapEnd,
                        ];
                    }
                }
            }
        }

        return $compatible;
    }

    /**
     * Delegates to legacy MemberAvailabilityService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\MemberAvailabilityService::getErrors();
    }

    /**
     * Delegates to legacy MemberAvailabilityService::getUserAvailability().
     */
    public function getUserAvailability(int $userId): array
    {
        return \Nexus\Services\MemberAvailabilityService::getUserAvailability($userId);
    }

    /**
     * Delegates to legacy MemberAvailabilityService::setBulkAvailability().
     */
    public function setBulkAvailability(int $userId, array $schedule): bool
    {
        return \Nexus\Services\MemberAvailabilityService::setBulkAvailability($userId, $schedule);
    }

    /**
     * Delegates to legacy MemberAvailabilityService::setDayAvailability().
     */
    public function setDayAvailability(int $userId, int $dayOfWeek, array $slots): bool
    {
        return \Nexus\Services\MemberAvailabilityService::setDayAvailability($userId, $dayOfWeek, $slots);
    }

    /**
     * Delegates to legacy MemberAvailabilityService::addSpecificDate().
     */
    public function addSpecificDate(int $userId, array $data): ?int
    {
        return \Nexus\Services\MemberAvailabilityService::addSpecificDate($userId, $data);
    }

    /**
     * Delegates to legacy MemberAvailabilityService::deleteSlot().
     */
    public function deleteSlot(int $userId, int $slotId): bool
    {
        return \Nexus\Services\MemberAvailabilityService::deleteSlot($userId, $slotId);
    }

    /**
     * Delegates to legacy MemberAvailabilityService::findCompatibleTimes().
     */
    public function findCompatibleTimes(int $userId1, int $userId2): array
    {
        return \Nexus\Services\MemberAvailabilityService::findCompatibleTimes($userId1, $userId2);
    }

    /**
     * Delegates to legacy MemberAvailabilityService::getAvailableMembers().
     */
    public function getAvailableMembers(int $dayOfWeek, ?string $time = null, int $limit = 50): array
    {
        return \Nexus\Services\MemberAvailabilityService::getAvailableMembers($dayOfWeek, $time, $limit);
    }
}
