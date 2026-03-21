<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Traits;

use App\Models\ExchangeRequest;
use App\Models\Listing;
use App\Models\User;

/**
 * Creates a complete exchange scenario for testing.
 *
 * Produces a provider user, a requester user, an offer listing
 * owned by the provider, and a pending exchange request from
 * the requester to the provider for that listing.
 */
trait CreatesExchangeData
{
    /**
     * Create a full exchange scenario with provider, requester, listing, and exchange request.
     *
     * @param  array<string, mixed>  $overrides  Optional overrides keyed by entity:
     *     'provider'  => attributes for the provider User,
     *     'requester' => attributes for the requester User,
     *     'listing'   => attributes for the Listing,
     *     'exchange'  => attributes for the ExchangeRequest.
     * @return array{provider: User, requester: User, listing: Listing, exchange: ExchangeRequest}
     */
    protected function createExchangeScenario(array $overrides = []): array
    {
        $provider = User::factory()
            ->forTenant($this->testTenantId)
            ->create($overrides['provider'] ?? []);

        $requester = User::factory()
            ->forTenant($this->testTenantId)
            ->create($overrides['requester'] ?? []);

        $listing = Listing::factory()
            ->forTenant($this->testTenantId)
            ->offer()
            ->create(array_merge(
                ['user_id' => $provider->id],
                $overrides['listing'] ?? [],
            ));

        $exchange = ExchangeRequest::create(array_merge(
            [
                'tenant_id'      => $this->testTenantId,
                'listing_id'     => $listing->id,
                'requester_id'   => $requester->id,
                'provider_id'    => $provider->id,
                'proposed_hours' => 1.00,
                'status'         => 'pending',
            ],
            $overrides['exchange'] ?? [],
        ));

        return [
            'provider'  => $provider,
            'requester' => $requester,
            'listing'   => $listing,
            'exchange'  => $exchange,
        ];
    }
}
