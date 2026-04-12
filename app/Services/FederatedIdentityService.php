<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Models\FederatedIdentity;
use App\Models\User;

/**
 * FederatedIdentityService — CRUD/lookup for the federated_identities table.
 *
 * Use cases:
 *  - Resolving an inbound federation webhook/payload's `external_user_id` to
 *    the correct local User (reputation portability, messaging, transactions).
 *  - Pushing outbound data: find the remote identity a local user has on a
 *    given partner so we know who to address.
 */
class FederatedIdentityService
{
    /**
     * Resolve an external identity on a partner to a local User.
     */
    public function resolve(int $partnerId, string $externalUserId): ?User
    {
        /** @var FederatedIdentity|null $identity */
        $identity = FederatedIdentity::query()
            ->where('partner_id', $partnerId)
            ->where('external_user_id', $externalUserId)
            ->first();

        return $identity?->user;
    }

    /**
     * Link a local user to an external identity on a partner.
     * Creates the mapping if it doesn't exist, updates the handle otherwise.
     */
    public function link(
        int $localUserId,
        int $partnerId,
        string $externalUserId,
        ?string $handle = null,
    ): FederatedIdentity {
        /** @var FederatedIdentity $identity */
        $identity = FederatedIdentity::query()->updateOrCreate(
            [
                'partner_id' => $partnerId,
                'external_user_id' => $externalUserId,
            ],
            [
                'local_user_id' => $localUserId,
                'external_handle' => $handle,
            ],
        );

        return $identity;
    }

    /**
     * Find all federated identities for a local user (one per partner).
     *
     * @return array<int, FederatedIdentity>
     */
    public function forUser(int $localUserId): array
    {
        return FederatedIdentity::query()
            ->where('local_user_id', $localUserId)
            ->get()
            ->all();
    }

    /**
     * Find the federated identity for a local user on a specific partner.
     */
    public function forUserOnPartner(int $localUserId, int $partnerId): ?FederatedIdentity
    {
        /** @var FederatedIdentity|null $identity */
        $identity = FederatedIdentity::query()
            ->where('local_user_id', $localUserId)
            ->where('partner_id', $partnerId)
            ->first();

        return $identity;
    }
}
