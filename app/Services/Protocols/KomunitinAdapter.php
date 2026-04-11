<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Protocols;

use App\Contracts\FederationProtocolAdapter;
use Illuminate\Support\Facades\Log;

/**
 * KomunitinAdapter — Translates between Nexus and Komunitin (JSON:API) formats.
 *
 * Komunitin is a community exchange platform that uses the JSON:API specification
 * (https://jsonapi.org) for its accounting API. Key differences from Nexus:
 *
 *   - Data format: JSON:API ({data: [{type, id, attributes, relationships}]})
 *   - Resources: currencies, accounts, transfers (vs Nexus users, listings, transactions)
 *   - Time unit: Komunitin uses configurable currency units; Nexus uses hours
 *   - Account model: Komunitin has explicit account resources; Nexus has user.balance
 *
 * Reference: https://docs.komunitin.org/technology/accounting/api
 *
 * @see \App\Contracts\FederationProtocolAdapter
 */
class KomunitinAdapter implements FederationProtocolAdapter
{
    public const PLATFORM_TYPE = 'komunitin';

    public static function getProtocolName(): string
    {
        return self::PLATFORM_TYPE;
    }

    public static function getDefaultApiPath(): string
    {
        return '/api/v1';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Endpoint mapping (Nexus actions → Komunitin JSON:API endpoints)
    // ─────────────────────────────────────────────────────────────────────────

    public function mapEndpoint(string $action, array $params = []): string
    {
        $id = $params['id'] ?? null;
        $currencyCode = $params['currency_code'] ?? 'default';

        return match ($action) {
            'members', 'accounts' => "/{$currencyCode}/accounts",
            'member', 'account'   => "/{$currencyCode}/accounts/{$id}",
            'transactions'        => "/{$currencyCode}/transfers",
            'transaction'         => "/{$currencyCode}/transfers/{$id}",
            'listings'            => '/offers',
            'listing'             => "/offers/{$id}",
            'currencies'          => '/currencies',
            'currency'            => "/currencies/{$id}",
            'health'              => '/health',
            'messages'            => '/messages',
            default               => "/{$action}",
        };
    }

    public function mapHttpMethod(string $action, string $default = 'GET'): string
    {
        // Komunitin uses standard REST verbs — PATCH for updates, DELETE for removal
        return match ($action) {
            'update_transaction' => 'PATCH',
            'delete_transaction' => 'DELETE',
            default              => $default,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Outbound: Nexus → JSON:API format
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transform a Nexus transaction into a JSON:API transfer resource.
     *
     * JSON:API format:
     * {
     *   "data": {
     *     "type": "transfers",
     *     "attributes": {
     *       "amount": 200,        // in currency minor units (cents)
     *       "meta": "description",
     *       "state": "committed"
     *     },
     *     "relationships": {
     *       "payer": { "data": { "type": "accounts", "id": "sender-account-id" } },
     *       "payee": { "data": { "type": "accounts", "id": "receiver-account-id" } }
     *     }
     *   }
     * }
     */
    public function transformOutboundTransaction(array $nexusTransaction, int $partnerId): array
    {
        $amount = (float) ($nexusTransaction['amount'] ?? 0);

        return [
            'data' => [
                'type' => 'transfers',
                'attributes' => [
                    'amount' => self::hoursToMinorUnits($amount),
                    'meta' => $nexusTransaction['description'] ?? 'Federation transfer from Project NEXUS',
                    'state' => self::mapOutboundTransactionState($nexusTransaction['status'] ?? 'pending'),
                ],
                'relationships' => [
                    'payer' => [
                        'data' => [
                            'type' => 'accounts',
                            'id' => (string) ($nexusTransaction['sender_account_id'] ?? $nexusTransaction['sender_user_id'] ?? ''),
                        ],
                    ],
                    'payee' => [
                        'data' => [
                            'type' => 'accounts',
                            'id' => (string) ($nexusTransaction['receiver_account_id'] ?? $nexusTransaction['receiver_user_id'] ?? ''),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function transformOutboundMessage(array $nexusMessage): array
    {
        return [
            'data' => [
                'type' => 'messages',
                'attributes' => [
                    'subject' => $nexusMessage['subject'] ?? '',
                    'body' => $nexusMessage['body'] ?? '',
                    'sender_name' => $nexusMessage['sender_name'] ?? 'Nexus User',
                ],
                'relationships' => [
                    'recipient' => [
                        'data' => [
                            'type' => 'accounts',
                            'id' => (string) ($nexusMessage['recipient_id'] ?? ''),
                        ],
                    ],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Inbound: JSON:API → Nexus format
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transform a JSON:API account resource into a Nexus user.
     *
     * Komunitin account:
     * {
     *   "type": "accounts",
     *   "id": "abc-123",
     *   "attributes": {
     *     "code": "user-slug",
     *     "balance": 5000,      // minor units
     *     "creditLimit": -10000,
     *     "debitLimit": 50000
     *   },
     *   "relationships": {
     *     "currency": { "data": { "type": "currencies", "id": "..." } },
     *     "member": { "data": { "type": "members", "id": "..." } }
     *   }
     * }
     */
    public function transformInboundMember(array $protocolMember): array
    {
        $attrs = $protocolMember['attributes'] ?? $protocolMember;
        $id = $protocolMember['id'] ?? $attrs['id'] ?? null;

        return [
            'external_id' => $id,
            'external_account_id' => $id,
            'name' => $attrs['code'] ?? $attrs['name'] ?? $attrs['display_name'] ?? 'Unknown',
            'email' => $attrs['email'] ?? null,
            'bio' => $attrs['description'] ?? null,
            'balance' => self::minorUnitsToHours((int) ($attrs['balance'] ?? 0)),
            'skills' => $attrs['tags'] ?? [],
            'active' => ($attrs['status'] ?? 'active') === 'active',
            'location' => $attrs['location'] ?? null,
            'created_at' => $attrs['created'] ?? $attrs['created_at'] ?? null,
            'source_platform' => self::PLATFORM_TYPE,
        ];
    }

    public function transformInboundMembers(array $protocolMembers): array
    {
        return array_map([$this, 'transformInboundMember'], $protocolMembers);
    }

    public function transformInboundListing(array $protocolListing): array
    {
        $attrs = $protocolListing['attributes'] ?? $protocolListing;
        $id = $protocolListing['id'] ?? $attrs['id'] ?? null;

        $type = strtolower($attrs['type'] ?? $attrs['category'] ?? 'offer');

        return [
            'external_id' => $id,
            'title' => $attrs['title'] ?? $attrs['name'] ?? 'Untitled',
            'description' => $attrs['description'] ?? $attrs['content'] ?? null,
            'type' => in_array($type, ['request', 'need', 'inquiry', 'want']) ? 'request' : 'offer',
            'category_name' => $attrs['category'] ?? null,
            'tags' => $attrs['tags'] ?? [],
            'status' => 'active',
            'federated_visibility' => 'listed',
            'created_at' => $attrs['created'] ?? $attrs['created_at'] ?? null,
            'updated_at' => $attrs['updated'] ?? $attrs['updated_at'] ?? null,
            'source_platform' => self::PLATFORM_TYPE,
        ];
    }

    public function transformInboundListings(array $protocolListings): array
    {
        return array_map([$this, 'transformInboundListing'], $protocolListings);
    }

    /**
     * Transform a JSON:API transfer response into Nexus transaction format.
     *
     * Komunitin transfer:
     * {
     *   "type": "transfers",
     *   "id": "uuid-here",
     *   "attributes": {
     *     "amount": 200,
     *     "meta": "description",
     *     "state": "committed",
     *     "created": "2026-01-15T10:30:00Z",
     *     "updated": "2026-01-15T10:30:00Z"
     *   }
     * }
     */
    public function transformInboundTransaction(array $protocolResponse): array
    {
        $data = $protocolResponse['data'] ?? $protocolResponse;
        $attrs = $data['attributes'] ?? $data;

        return [
            'external_transaction_id' => (string) ($data['id'] ?? ''),
            'status' => self::mapInboundTransactionState($attrs['state'] ?? 'pending'),
            'amount_hours' => self::minorUnitsToHours((int) ($attrs['amount'] ?? 0)),
            'description' => $attrs['meta'] ?? $attrs['description'] ?? '',
            'completed_at' => $attrs['updated'] ?? $attrs['created'] ?? null,
            'source_platform' => self::PLATFORM_TYPE,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Webhooks
    // ─────────────────────────────────────────────────────────────────────────

    public function normalizeWebhookEvent(string $protocolEvent): string
    {
        // Komunitin events map closely to Nexus events
        return match ($protocolEvent) {
            'transfer.committed', 'transfer.completed' => 'transaction.completed',
            'transfer.cancelled', 'transfer.rejected'  => 'transaction.cancelled',
            'transfer.pending', 'transfer.new'         => 'transaction.requested',
            'account.created'                          => 'member.opted_in',
            'account.deleted'                          => 'member.opted_out',
            default                                    => $protocolEvent,
        };
    }

    public function normalizeWebhookPayload(array $rawPayload): array
    {
        // JSON:API webhook payloads may have the resource in 'data'
        $event = $rawPayload['event'] ?? $rawPayload['type'] ?? 'unknown';
        $data = $rawPayload['data'] ?? $rawPayload['attributes'] ?? [];

        // If data is a JSON:API resource, extract attributes
        if (isset($data['type']) && isset($data['attributes'])) {
            $inner = $data['attributes'];
            $inner['id'] = $data['id'] ?? null;
            $inner['resource_type'] = $data['type'];

            // Extract relationship IDs
            foreach (($data['relationships'] ?? []) as $relName => $relData) {
                $inner["{$relName}_id"] = $relData['data']['id'] ?? null;
            }

            $data = $inner;
        }

        return [
            'event' => $this->normalizeWebhookEvent($event),
            'data'  => $data,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Response unwrapping: JSON:API envelope
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Unwrap a JSON:API response.
     *
     * JSON:API responses wrap data in:
     *   { "data": [ { "type": "...", "id": "...", "attributes": {...} } ] }
     *
     * For collections, we flatten to an array of attribute objects (with id injected).
     * For single resources, we return the attributes with id.
     */
    public function unwrapResponse(array $response, string $action = ''): array
    {
        if (!isset($response['data'])) {
            return $response;
        }

        $data = $response['data'];

        // Single resource (object with 'type' key)
        if (isset($data['type'])) {
            $result = $data['attributes'] ?? [];
            $result['id'] = $data['id'] ?? null;
            return $result;
        }

        // Collection (array of resources)
        if (is_array($data) && !empty($data)) {
            return array_map(function (array $resource) {
                $item = $resource['attributes'] ?? [];
                $item['id'] = $resource['id'] ?? null;
                // Flatten relationships into the item
                foreach (($resource['relationships'] ?? []) as $relName => $relData) {
                    $item["{$relName}_id"] = $relData['data']['id'] ?? null;
                }
                return $item;
            }, $data);
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JSON:API helper: wrap Nexus data as JSON:API resource
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Wrap a Nexus data array as a JSON:API resource for outbound requests.
     *
     * @param string $type       The JSON:API resource type (e.g., 'transfers', 'accounts')
     * @param array  $attributes The resource attributes
     * @param string|null $id    Optional resource ID
     * @return array JSON:API formatted resource
     */
    public static function wrapAsJsonApi(string $type, array $attributes, ?string $id = null): array
    {
        $resource = [
            'type' => $type,
            'attributes' => $attributes,
        ];

        if ($id !== null) {
            $resource['id'] = $id;
        }

        return ['data' => $resource];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Unit conversion: Nexus hours ↔ Komunitin minor units (cents)
    //
    // Komunitin stores amounts in minor currency units (like cents).
    // For time currencies: 1 hour = 100 minor units (configurable per currency,
    // but 100 is the most common divisor for time-based currencies).
    // ─────────────────────────────────────────────────────────────────────────

    /** Minor units per hour — configurable per Komunitin currency, default 100 */
    public const MINOR_UNITS_PER_HOUR = 100;

    public static function hoursToMinorUnits(float $hours): int
    {
        return (int) round($hours * self::MINOR_UNITS_PER_HOUR);
    }

    public static function minorUnitsToHours(int $minorUnits): float
    {
        return round($minorUnits / self::MINOR_UNITS_PER_HOUR, 2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Exchange rate: Komunitin {n, d} format ↔ float
    //
    // Komunitin represents exchange rates as numerator/denominator pairs.
    // e.g., {n: 3, d: 2} = 1.5x exchange rate
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Convert a float exchange rate to Komunitin's {n, d} format.
     *
     * Uses continued fraction approximation to find the simplest n/d pair.
     */
    public static function floatToRate(float $rate): array
    {
        if ($rate <= 0) {
            return ['n' => 0, 'd' => 1];
        }

        // Simple cases
        if ($rate === 1.0) return ['n' => 1, 'd' => 1];
        if ($rate === 0.5) return ['n' => 1, 'd' => 2];
        if ($rate === 2.0) return ['n' => 2, 'd' => 1];

        // Use scaling to avoid floating point issues: multiply by 10000
        $scale = 10000;
        $n = (int) round($rate * $scale);
        $d = $scale;

        // Simplify with GCD
        $gcd = self::gcd($n, $d);
        return ['n' => $n / $gcd, 'd' => $d / $gcd];
    }

    /**
     * Convert Komunitin's {n, d} rate format to a float.
     */
    public static function rateToFloat(array $rate): float
    {
        $n = (int) ($rate['n'] ?? 1);
        $d = (int) ($rate['d'] ?? 1);

        return $d > 0 ? $n / $d : 1.0;
    }

    /**
     * Greatest common divisor (Euclidean algorithm).
     */
    private static function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);
        while ($b !== 0) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }
        return $a ?: 1;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Transaction state mapping
    // ─────────────────────────────────────────────────────────────────────────

    private static function mapOutboundTransactionState(string $nexusStatus): string
    {
        return match ($nexusStatus) {
            'completed' => 'committed',
            'pending'   => 'pending',
            'cancelled' => 'rejected',
            'disputed'  => 'rejected',
            default     => 'pending',
        };
    }

    private static function mapInboundTransactionState(string $komunitinState): string
    {
        return match ($komunitinState) {
            'committed', 'accepted' => 'completed',
            'pending', 'new'        => 'pending',
            'rejected', 'deleted'   => 'cancelled',
            default                 => 'pending',
        };
    }
}
