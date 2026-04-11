<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Protocols;

use App\Contracts\FederationProtocolAdapter;

/**
 * NexusAdapter — Default passthrough adapter for Nexus-to-Nexus federation.
 *
 * When two Nexus instances federate with each other, data formats are identical
 * so this adapter is essentially a no-op passthrough. It serves as the baseline
 * that other adapters override.
 */
class NexusAdapter implements FederationProtocolAdapter
{
    public const PLATFORM_TYPE = 'nexus';

    public static function getProtocolName(): string
    {
        return self::PLATFORM_TYPE;
    }

    public static function getDefaultApiPath(): string
    {
        return '/api/v2/federation';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Endpoint mapping (Nexus default paths)
    // ─────────────────────────────────────────────────────────────────────────

    public function mapEndpoint(string $action, array $params = []): string
    {
        $id = $params['id'] ?? null;

        return match ($action) {
            'members'      => '/members',
            'member'       => "/members/{$id}",
            'listings'     => '/listings',
            'listing'      => "/listings/{$id}",
            'transactions' => '/transactions',
            'messages'     => '/messages',
            'health'       => '/health',
            default        => "/{$action}",
        };
    }

    public function mapHttpMethod(string $action, string $default = 'GET'): string
    {
        return $default;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Outbound: passthrough (Nexus → Nexus = same format)
    // ─────────────────────────────────────────────────────────────────────────

    public function transformOutboundTransaction(array $nexusTransaction, int $partnerId): array
    {
        return $nexusTransaction;
    }

    public function transformOutboundMessage(array $nexusMessage): array
    {
        return $nexusMessage;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Inbound: passthrough (Nexus → Nexus = same format)
    // ─────────────────────────────────────────────────────────────────────────

    public function transformInboundMember(array $protocolMember): array
    {
        $protocolMember['source_platform'] = self::PLATFORM_TYPE;
        return $protocolMember;
    }

    public function transformInboundMembers(array $protocolMembers): array
    {
        return array_map([$this, 'transformInboundMember'], $protocolMembers);
    }

    public function transformInboundListing(array $protocolListing): array
    {
        $protocolListing['source_platform'] = self::PLATFORM_TYPE;
        return $protocolListing;
    }

    public function transformInboundListings(array $protocolListings): array
    {
        return array_map([$this, 'transformInboundListing'], $protocolListings);
    }

    public function transformInboundTransaction(array $protocolResponse): array
    {
        $protocolResponse['source_platform'] = self::PLATFORM_TYPE;
        return $protocolResponse;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Webhooks: passthrough
    // ─────────────────────────────────────────────────────────────────────────

    public function normalizeWebhookEvent(string $protocolEvent): string
    {
        return $protocolEvent;
    }

    public function normalizeWebhookPayload(array $rawPayload): array
    {
        return [
            'event' => $rawPayload['event'] ?? 'unknown',
            'data'  => $rawPayload['data'] ?? [],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Response unwrapping: Nexus envelope {success, data}
    // ─────────────────────────────────────────────────────────────────────────

    public function unwrapResponse(array $response, string $action = ''): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }
}
