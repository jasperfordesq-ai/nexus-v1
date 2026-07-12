<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Authorization;

/**
 * Canonical backend predicate for tenant/platform admin-tier authority.
 *
 * Broker and coordinator are operational roles. They fail closed even when a
 * stale legacy admin flag remains set on the account row.
 */
final class AdminTier
{
    /** @var list<string> */
    public const ROLES = ['admin', 'tenant_admin', 'super_admin', 'god'];

    /** @var list<string> */
    public const OPERATIONAL_ROLES = ['broker', 'coordinator'];

    /** @param object|array<string,mixed>|null $user */
    public static function allows(object|array|null $user): bool
    {
        if ($user === null) {
            return false;
        }

        $role = (string) data_get($user, 'role', '');
        if (in_array($role, self::OPERATIONAL_ROLES, true)) {
            return false;
        }

        return in_array($role, self::ROLES, true)
            || (bool) data_get($user, 'is_admin', false)
            || (bool) data_get($user, 'is_super_admin', false)
            || (bool) data_get($user, 'is_tenant_super_admin', false)
            || (bool) data_get($user, 'is_god', false);
    }
}
