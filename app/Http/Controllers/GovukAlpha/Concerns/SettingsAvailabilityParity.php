<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Settings — weekly availability grid (accessible, no-JS).
 *
 * Composed into AlphaController. Mirrors the React AvailabilityGrid: a member
 * sets recurring weekly time slots backed by MemberAvailabilityService +
 * member_availability. Backend day_of_week is 0=Sunday..6=Saturday; the form
 * displays Monday-first but posts the correct backend index per slot, so no
 * day-shift bug. setBulkAvailability deletes all recurring slots then re-inserts
 * the non-empty, valid (start<end) ones, so it is idempotent.
 */
trait SettingsAvailabilityParity
{
    /** Display order Mon-first; values are backend day_of_week indices. */
    private const AVAILABILITY_DISPLAY_DAYS = [1, 2, 3, 4, 5, 6, 0];

    /** How many blank slot rows to offer per day (no-JS; blanks are skipped). */
    private const AVAILABILITY_SLOTS_PER_DAY = 3;

    public function settingsAvailability(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Group existing recurring slots by backend day index for prefill.
        $byDay = [];
        try {
            $rows = app(\App\Services\MemberAvailabilityService::class)->getUserAvailability($userId);
            foreach ($rows as $r) {
                if (! (bool) ($r['is_recurring'] ?? false)) {
                    continue; // only recurring slots populate the weekly grid
                }
                $day = (int) ($r['day_of_week'] ?? -1);
                if ($day < 0 || $day > 6) {
                    continue;
                }
                $byDay[$day][] = [
                    'start' => substr((string) ($r['start_time'] ?? ''), 0, 5),
                    'end'   => substr((string) ($r['end_time'] ?? ''), 0, 5),
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::settings-availability', [
            'title' => __('govuk_alpha_settings.availability.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'settings',
            'availabilityByDay' => $byDay,
            'displayDays' => self::AVAILABILITY_DISPLAY_DAYS,
            'slotsPerDay' => self::AVAILABILITY_SLOTS_PER_DAY,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function settingsUpdateAvailability(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Form posts slots[<backendDay>][<i>][start|end]. Flatten to the flat
        // array setBulkAvailability accepts.
        $raw = $request->input('slots');
        $raw = is_array($raw) ? $raw : [];

        $flat = [];
        $hasInvalid = false;
        foreach ($raw as $day => $slots) {
            $day = (int) $day;
            if ($day < 0 || $day > 6 || ! is_array($slots)) {
                continue;
            }
            foreach ($slots as $slot) {
                if (! is_array($slot)) {
                    continue;
                }
                $start = trim((string) ($slot['start'] ?? ''));
                $end = trim((string) ($slot['end'] ?? ''));
                if ($start === '' && $end === '') {
                    continue; // blank row — skip
                }
                if ($start === '' || $end === '' || $start >= $end) {
                    $hasInvalid = true; // partially filled or end<=start
                    continue;
                }
                $flat[] = ['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end];
            }
        }

        if ($hasInvalid) {
            return redirect()
                ->route('govuk-alpha.settings.availability', ['tenantSlug' => $tenantSlug, 'status' => 'availability-invalid'])
                ->withFragment('availability');
        }

        $ok = false;
        try {
            $ok = app(\App\Services\MemberAvailabilityService::class)->setBulkAvailability($userId, $flat);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.settings.availability', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'availability-saved' : 'availability-failed',
        ]);
    }
}
