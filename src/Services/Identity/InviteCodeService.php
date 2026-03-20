<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * InviteCodeService — Thin delegate forwarding to \App\Services\Identity\InviteCodeService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Identity\InviteCodeService
 */
class InviteCodeService
{

    public static function generate(int $tenantId,
        int $createdBy,
        int $count = 1,
        ?int $maxUses = 1,
        ?string $expiresAt = null,
        ?string $note = null): array
    {
        return \App\Services\Identity\InviteCodeService::generate($tenantId, $createdBy, $count, $maxUses, $expiresAt, $note);
    }

    public static function validate(int $tenantId, string $code): array
    {
        return \App\Services\Identity\InviteCodeService::validate($tenantId, $code);
    }

    public static function redeem(int $tenantId, string $code, int $userId): bool
    {
        return \App\Services\Identity\InviteCodeService::redeem($tenantId, $code, $userId);
    }

    public static function listForTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        return \App\Services\Identity\InviteCodeService::listForTenant($tenantId, $limit, $offset);
    }

    public static function deactivate(int $tenantId, int $codeId): bool
    {
        return \App\Services\Identity\InviteCodeService::deactivate($tenantId, $codeId);
    }
}
