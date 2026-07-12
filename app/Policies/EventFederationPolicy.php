<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Policies;

use App\Core\TenantContext;
use App\Models\Event;
use App\Models\User;

/** Authorization boundary for payload-free Event federation operations. */
final class EventFederationPolicy
{
    public function __construct(
        private readonly EventPolicy $events = new EventPolicy(),
    ) {}

    public function viewStatus(User $user, Event $event): bool
    {
        return $this->sameTenant($user, $event) && $this->events->manage($user, $event);
    }

    public function viewTenantDiagnostics(User $user): bool
    {
        $tenantId = TenantContext::currentId();

        return $tenantId !== null
            && (int) $user->getAttribute('tenant_id') === $tenantId
            && (string) $user->getAttribute('status') === 'active'
            && (
                in_array((string) $user->getAttribute('role'), [
                    'admin',
                    'tenant_admin',
                    'super_admin',
                ], true)
                || (bool) $user->getAttribute('is_admin')
                || (bool) $user->getAttribute('is_super_admin')
                || (bool) $user->getAttribute('is_tenant_super_admin')
            );
    }

    private function sameTenant(User $user, Event $event): bool
    {
        $tenantId = TenantContext::currentId();

        return $tenantId !== null
            && (int) $user->getAttribute('tenant_id') === $tenantId
            && (int) $event->getAttribute('tenant_id') === $tenantId;
    }
}
