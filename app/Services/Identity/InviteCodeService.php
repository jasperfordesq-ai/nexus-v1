<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Identity;

use Nexus\Services\Identity\InviteCodeService as LegacyService;

/**
 * InviteCodeService — Laravel DI wrapper for legacy service.
 *
 * Delegates to \Nexus\Services\Identity\InviteCodeService.
 */
class InviteCodeService
{
    public function __construct()
    {
    }

    /**
     * Generate one or more invite codes for a tenant.
     *
     * @return array Generated codes
     */
    public function generate(int $tenantId, int $createdBy, int $count = 1, ?int $maxUses = 1, ?string $expiresAt = null, ?string $note = null): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::generate($tenantId, $createdBy, $count, $maxUses, $expiresAt, $note);
    }

    /**
     * Validate an invite code for use during registration.
     *
     * @return array{valid: bool, reason?: string, code_id?: int}
     */
    public function validate(int $tenantId, string $code): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::validate($tenantId, $code);
    }

    /**
     * Redeem an invite code (increment uses_count).
     */
    public function redeem(int $tenantId, string $code, int $userId): bool
    {
        if (!class_exists(LegacyService::class)) {
            return false;
        }
        return LegacyService::redeem($tenantId, $code, $userId);
    }

    /**
     * List invite codes for a tenant (admin view).
     */
    public function listForTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::listForTenant($tenantId, $limit, $offset);
    }

    /**
     * Deactivate an invite code.
     */
    public function deactivate(int $tenantId, int $codeId): bool
    {
        if (!class_exists(LegacyService::class)) {
            return false;
        }
        return LegacyService::deactivate($tenantId, $codeId);
    }
}
