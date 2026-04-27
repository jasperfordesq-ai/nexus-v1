<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Generates, lists, and validates caring-community invite codes.
 *
 * Codes are 6-char uppercase alphanumeric strings stored in caring_invite_codes
 * scoped by tenant.  The public lookup endpoint never returns 404 — it always
 * returns a validity envelope — to prevent enumeration.
 */
class CaringInviteCodeService
{
    private const CODE_LENGTH     = 6;
    private const CODE_CHARS      = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // omit 0/O, 1/I for clarity
    private const MAX_RETRIES     = 10;
    private const LIST_LIMIT      = 20;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate a new invite code and persist it.
     *
     * @return array{success: bool, code?: array<string, mixed>}
     */
    public function generate(int $tenantId, int $createdByUserId, ?string $label, int $expiresDays): array
    {
        if (!Schema::hasTable('caring_invite_codes')) {
            return ['success' => false, 'code_reason' => 'table_missing'];
        }

        $code      = $this->generateUniqueCode($tenantId);
        $expiresAt = now()->addDays($expiresDays);

        try {
            $id = DB::table('caring_invite_codes')->insertGetId([
                'tenant_id'          => $tenantId,
                'code'               => $code,
                'label'              => $label,
                'created_by_user_id' => $createdByUserId,
                'expires_at'         => $expiresAt,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[CaringInvite] generate insert failed', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);

            return ['success' => false];
        }

        $inviteUrl = $this->buildInviteUrl($tenantId, $code);

        return [
            'success' => true,
            'code'    => [
                'id'         => $id,
                'code'       => $code,
                'label'      => $label,
                'expires_at' => $expiresAt->toISOString(),
                'invite_url' => $inviteUrl,
            ],
        ];
    }

    /**
     * List the last N invite codes for this tenant, with usage context.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $tenantId): array
    {
        if (!Schema::hasTable('caring_invite_codes')) {
            return [];
        }

        $rows = DB::select(
            "SELECT
                cic.id,
                cic.code,
                cic.label,
                cic.expires_at,
                cic.used_at,
                cic.created_at,
                u.name AS used_by_name
             FROM caring_invite_codes cic
             LEFT JOIN users u ON u.id = cic.used_by_user_id AND u.tenant_id = cic.tenant_id
             WHERE cic.tenant_id = ?
             ORDER BY cic.created_at DESC
             LIMIT " . self::LIST_LIMIT,
            [$tenantId]
        );

        $inviteBase = $this->buildInviteUrlBase($tenantId);

        return array_map(function (object $row) use ($inviteBase): array {
            $now       = now();
            $expiresAt = \Carbon\Carbon::parse($row->expires_at);
            $isExpired = $expiresAt->isPast();
            $isUsed    = $row->used_at !== null;

            if ($isUsed) {
                $status = 'used';
            } elseif ($isExpired) {
                $status = 'expired';
            } else {
                $status = 'active';
            }

            return [
                'id'          => (int) $row->id,
                'code'        => (string) $row->code,
                'label'       => $row->label ? (string) $row->label : null,
                'expires_at'  => (string) $row->expires_at,
                'used_at'     => $row->used_at ? (string) $row->used_at : null,
                'used_by'     => $row->used_by_name ? (string) $row->used_by_name : null,
                'status'      => $status,
                'created_at'  => (string) $row->created_at,
                'invite_url'  => $inviteBase . $row->code,
            ];
        }, $rows);
    }

    /**
     * Look up a single invite code.  Always returns a validity envelope —
     * never 404 — to prevent enumeration.
     *
     * @return array<string, mixed>
     */
    public function lookup(int $tenantId, string $code): array
    {
        if (!Schema::hasTable('caring_invite_codes')) {
            return ['valid' => false, 'expired' => false, 'already_used' => false, 'tenant_name' => '', 'caring_community_enabled' => false];
        }

        $tenant     = TenantContext::get();
        $tenantName = (string) ($tenant['name'] ?? '');
        $enabled    = TenantContext::hasFeature('caring_community');

        if ($code === '') {
            return ['valid' => false, 'expired' => false, 'already_used' => false, 'tenant_name' => $tenantName, 'caring_community_enabled' => $enabled];
        }

        $row = DB::selectOne(
            'SELECT id, expires_at, used_at FROM caring_invite_codes WHERE tenant_id = ? AND code = ? LIMIT 1',
            [$tenantId, $code]
        );

        if (!$row) {
            return ['valid' => false, 'expired' => false, 'already_used' => false, 'tenant_name' => $tenantName, 'caring_community_enabled' => $enabled];
        }

        $isUsed    = $row->used_at !== null;
        $isExpired = \Carbon\Carbon::parse($row->expires_at)->isPast();

        return [
            'valid'                    => !$isUsed && !$isExpired,
            'expired'                  => $isExpired && !$isUsed,
            'already_used'             => $isUsed,
            'tenant_name'              => $tenantName,
            'caring_community_enabled' => $enabled,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a unique code for this tenant, with collision-retry.
     */
    private function generateUniqueCode(int $tenantId): string
    {
        $chars  = self::CODE_CHARS;
        $length = self::CODE_LENGTH;
        $max    = strlen($chars) - 1;

        for ($i = 0; $i < self::MAX_RETRIES; $i++) {
            $candidate = '';
            for ($c = 0; $c < $length; $c++) {
                $candidate .= $chars[random_int(0, $max)];
            }

            $exists = DB::table('caring_invite_codes')
                ->where('tenant_id', $tenantId)
                ->where('code', $candidate)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        // Extremely unlikely after 10 retries; fall back to a longer code
        return strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
    }

    private function buildInviteUrl(int $tenantId, string $code): string
    {
        return $this->buildInviteUrlBase($tenantId) . $code;
    }

    /**
     * Return the base URL up to (but not including) the code.
     * Uses the tenant's configured frontend URL and slug prefix.
     */
    private function buildInviteUrlBase(int $tenantId): string
    {
        $frontendUrl  = rtrim((string) TenantContext::getFrontendUrl(), '/');
        $slugPrefix   = rtrim((string) TenantContext::getSlugPrefix(), '/');

        return "{$frontendUrl}{$slugPrefix}/join/";
    }
}
