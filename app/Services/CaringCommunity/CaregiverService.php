<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG68 — Caregiver/Angehörigen Support Flow
 *
 * Handles caregiver links, schedule views, burnout risk detection, and
 * on-behalf help requests for the Caring Community module.
 */
class CaregiverService
{
    public const BURNOUT_THRESHOLD_HOURS_PER_WEEK = 20.0;

    // -------------------------------------------------------------------------
    // Availability guard
    // -------------------------------------------------------------------------

    public function isAvailable(): bool
    {
        return Schema::hasTable('caring_caregiver_links');
    }

    // -------------------------------------------------------------------------
    // Caregiver links
    // -------------------------------------------------------------------------

    /**
     * Return all active caregiver links for a given caregiver, with cared-for user details.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getLinksForCaregiver(int $caregiverId, int $tenantId): array
    {
        return DB::table('caring_caregiver_links as cl')
            ->join('users as u', 'u.id', '=', 'cl.cared_for_id')
            ->where('cl.caregiver_id', $caregiverId)
            ->where('cl.tenant_id', $tenantId)
            ->where('cl.status', 'active')
            ->select([
                'cl.id',
                'cl.cared_for_id',
                'cl.relationship_type',
                'cl.is_primary',
                'cl.start_date',
                'cl.notes',
                'cl.created_at',
                'u.name as cared_for_name',
                'u.avatar_url as cared_for_avatar_url',
            ])
            ->orderByDesc('cl.is_primary')
            ->orderBy('cl.start_date')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Return all active links where this user is the cared-for person.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getLinksForCaredFor(int $caredForId, int $tenantId): array
    {
        return DB::table('caring_caregiver_links as cl')
            ->join('users as u', 'u.id', '=', 'cl.caregiver_id')
            ->where('cl.cared_for_id', $caredForId)
            ->where('cl.tenant_id', $tenantId)
            ->where('cl.status', 'active')
            ->select([
                'cl.id',
                'cl.caregiver_id',
                'cl.relationship_type',
                'cl.is_primary',
                'cl.start_date',
                'cl.notes',
                'cl.created_at',
                'u.name as caregiver_name',
                'u.avatar_url as caregiver_avatar_url',
            ])
            ->orderByDesc('cl.is_primary')
            ->orderBy('cl.start_date')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Create a new caregiver link.
     *
     * @param  array<string,mixed>  $options  Optional: is_primary (bool), notes (string)
     * @return array<string,mixed>
     *
     * @throws \RuntimeException on guard failures
     */
    public function createLink(
        int $caregiverId,
        int $caredForId,
        string $relationshipType,
        int $tenantId,
        array $options = [],
    ): array {
        if ($caregiverId === $caredForId) {
            throw new \RuntimeException('A caregiver cannot be linked to themselves.');
        }

        $existing = DB::table('caring_caregiver_links')
            ->where('caregiver_id', $caregiverId)
            ->where('cared_for_id', $caredForId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if ($existing !== null) {
            throw new \RuntimeException('An active caregiver link already exists for this pair.');
        }

        $id = DB::table('caring_caregiver_links')->insertGetId([
            'tenant_id'         => $tenantId,
            'caregiver_id'      => $caregiverId,
            'cared_for_id'      => $caredForId,
            'relationship_type' => $relationshipType,
            'is_primary'        => (bool) ($options['is_primary'] ?? false),
            'start_date'        => $options['start_date'] ?? now()->toDateString(),
            'notes'             => $options['notes'] ?? null,
            'status'            => 'active',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        /** @var array<string,mixed> $row */
        $row = (array) DB::table('caring_caregiver_links')->where('id', $id)->first();

        return $row;
    }

    /**
     * Deactivate a caregiver link (soft-remove).
     *
     * @throws \RuntimeException if the link does not belong to this caregiver/tenant
     */
    public function removeLink(int $linkId, int $caregiverId, int $tenantId): void
    {
        $affected = DB::table('caring_caregiver_links')
            ->where('id', $linkId)
            ->where('caregiver_id', $caregiverId)
            ->where('tenant_id', $tenantId)
            ->update([
                'status'     => 'inactive',
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw new \RuntimeException('Caregiver link not found or you do not own it.');
        }
    }

    // -------------------------------------------------------------------------
    // Care schedule
    // -------------------------------------------------------------------------

    /**
     * Return upcoming care activities for a given cared-for person.
     *
     * @return array{support_relationships: array<int,array<string,mixed>>, recent_logs: array<int,array<string,mixed>>}
     */
    public function getScheduleForCaredFor(int $caredForId, int $tenantId): array
    {
        // Upcoming active support relationships where this person is recipient
        $supportRelationships = [];
        if (Schema::hasTable('caring_support_relationships')) {
            $supportRelationships = DB::table('caring_support_relationships as sr')
                ->join('users as u', 'u.id', '=', 'sr.supporter_id')
                ->where('sr.recipient_id', $caredForId)
                ->where('sr.tenant_id', $tenantId)
                ->where('sr.status', 'active')
                ->select([
                    'sr.id',
                    'sr.title',
                    'sr.frequency',
                    'sr.expected_hours',
                    'sr.next_check_in_at',
                    'sr.start_date',
                    'u.id as supporter_id',
                    'u.name as supporter_name',
                    'u.avatar_url as supporter_avatar_url',
                ])
                ->orderBy('sr.next_check_in_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        }

        // Recent vol_logs for this cared-for person as recipient (last 30 days)
        $recentLogs = [];
        if (Schema::hasTable('vol_logs')) {
            $recentLogs = DB::table('vol_logs as vl')
                ->join('users as u', 'u.id', '=', 'vl.user_id')
                ->where('vl.support_recipient_id', $caredForId)
                ->where('vl.tenant_id', $tenantId)
                ->where('vl.logged_at', '>=', now()->subDays(30))
                ->select([
                    'vl.id',
                    'vl.logged_at as date',
                    'vl.hours',
                    'vl.status',
                    'u.id as supporter_id',
                    'u.name as supporter_name',
                    'u.avatar_url as supporter_avatar_url',
                ])
                ->orderByDesc('vl.logged_at')
                ->limit(20)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        }

        return [
            'support_relationships' => $supportRelationships,
            'recent_logs'           => $recentLogs,
        ];
    }

    // -------------------------------------------------------------------------
    // Burnout risk
    // -------------------------------------------------------------------------

    /**
     * Compute burnout risk for a caregiver based on vol_logs in the past 7 days.
     *
     * @return array{weekly_hours: float, threshold: float, at_risk: bool, risk_level: 'none'|'moderate'|'high'}
     */
    public function checkBurnoutRisk(int $caregiverId, int $tenantId): array
    {
        $weeklyHours = 0.0;

        if (Schema::hasTable('vol_logs')) {
            $sum = DB::table('vol_logs')
                ->where('user_id', $caregiverId)
                ->where('tenant_id', $tenantId)
                ->whereRaw("logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
                ->whereIn('status', ['approved', 'pending'])
                ->sum('hours');

            $weeklyHours = (float) $sum;
        }

        $threshold = self::BURNOUT_THRESHOLD_HOURS_PER_WEEK;
        $atRisk     = $weeklyHours >= $threshold * 0.5;

        $riskLevel = 'none';
        if ($weeklyHours >= $threshold) {
            $riskLevel = 'high';
        } elseif ($weeklyHours >= $threshold * 0.5) {
            $riskLevel = 'moderate';
        }

        return [
            'weekly_hours' => $weeklyHours,
            'threshold'    => $threshold,
            'at_risk'      => $atRisk,
            'risk_level'   => $riskLevel,
        ];
    }

    // -------------------------------------------------------------------------
    // On-behalf requests
    // -------------------------------------------------------------------------

    /**
     * Create a help request on behalf of a cared-for person.
     *
     * @param  array<string,mixed>  $requestData  Must include: title, description. Optional: category_id.
     * @return array<string,mixed>
     *
     * @throws \RuntimeException if no active link exists
     */
    public function createRequestOnBehalf(
        int $caregiverId,
        int $caredForId,
        array $requestData,
        int $tenantId,
    ): array {
        // Guard: caregiver must have an active link to the cared-for person in this tenant
        $link = DB::table('caring_caregiver_links')
            ->where('caregiver_id', $caregiverId)
            ->where('cared_for_id', $caredForId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if ($link === null) {
            throw new \RuntimeException('You do not have an active caregiver link to this person.');
        }

        $id = DB::table('caring_help_requests')->insertGetId([
            'tenant_id'        => $tenantId,
            'user_id'          => $caredForId,
            'requested_by_id'  => $caregiverId,
            'is_on_behalf'     => true,
            'title'            => $requestData['title'],
            'description'      => $requestData['description'] ?? null,
            'category_id'      => $requestData['category_id'] ?? null,
            'status'           => 'open',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        /** @var array<string,mixed> $row */
        $row = (array) DB::table('caring_help_requests')->where('id', $id)->first();

        return $row;
    }
}
