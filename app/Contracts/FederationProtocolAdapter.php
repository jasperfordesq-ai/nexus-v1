<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Contracts;

/**
 * FederationProtocolAdapter — Interface for protocol-specific data translation.
 *
 * Each external federation protocol (Nexus, TimeOverflow, Komunitin, Credit Commons)
 * implements this interface to handle format differences while sharing the common
 * HTTP infrastructure in FederationExternalApiClient (auth, circuit breaker, retry, logging).
 *
 * Adapters are stateless — all methods receive the data they need as parameters.
 */
interface FederationProtocolAdapter
{
    // ─────────────────────────────────────────────────────────────────────────
    // Protocol identity
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Protocol identifier (e.g., 'nexus', 'timeoverflow', 'komunitin', 'credit_commons').
     */
    public static function getProtocolName(): string;

    /**
     * Default API base path for this protocol (e.g., '/api/v1', '/api/v2').
     */
    public static function getDefaultApiPath(): string;

    // ─────────────────────────────────────────────────────────────────────────
    // Endpoint mapping
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Map a Nexus action to the protocol's endpoint path.
     *
     * Actions: 'members', 'member', 'listings', 'listing', 'transactions',
     *          'messages', 'health', 'accounts', 'about'
     *
     * @param string $action   The Nexus action name
     * @param array  $params   Optional params (e.g., ['id' => 123] for single-resource endpoints)
     * @return string The protocol-specific endpoint path
     */
    public function mapEndpoint(string $action, array $params = []): string;

    /**
     * Map the HTTP method for a given action (some protocols use PATCH instead of POST, etc.).
     *
     * @param string $action  The Nexus action name
     * @param string $default The default HTTP method ('GET', 'POST', etc.)
     * @return string The HTTP method to use
     */
    public function mapHttpMethod(string $action, string $default = 'GET'): string;

    // ─────────────────────────────────────────────────────────────────────────
    // Outbound: Nexus → Protocol format
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transform a Nexus transaction into the protocol's outbound format.
     *
     * @param array $nexusTransaction Nexus federation_transaction data
     * @param int   $partnerId        External partner ID
     * @return array Protocol-formatted transaction payload
     */
    public function transformOutboundTransaction(array $nexusTransaction, int $partnerId): array;

    /**
     * Transform a Nexus message into the protocol's outbound format.
     *
     * @param array $nexusMessage Nexus message data
     * @return array Protocol-formatted message payload
     */
    public function transformOutboundMessage(array $nexusMessage): array;

    // ─────────────────────────────────────────────────────────────────────────
    // Inbound: Protocol format → Nexus
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transform protocol member data into Nexus user format.
     *
     * @param array $protocolMember Single member/account from the protocol
     * @return array Nexus-compatible user array
     */
    public function transformInboundMember(array $protocolMember): array;

    /**
     * Transform a list of protocol members into Nexus format.
     *
     * @param array $protocolMembers Array of members from the protocol
     * @return array Array of Nexus-compatible user arrays
     */
    public function transformInboundMembers(array $protocolMembers): array;

    /**
     * Transform protocol listing data into Nexus listing format.
     *
     * @param array $protocolListing Single listing from the protocol
     * @return array Nexus-compatible listing array
     */
    public function transformInboundListing(array $protocolListing): array;

    /**
     * Transform a list of protocol listings into Nexus format.
     *
     * @param array $protocolListings Array of listings from the protocol
     * @return array Array of Nexus-compatible listing arrays
     */
    public function transformInboundListings(array $protocolListings): array;

    /**
     * Transform a protocol transaction response into Nexus format.
     *
     * @param array $protocolResponse Transaction response from the protocol
     * @return array Nexus-compatible transaction record
     */
    public function transformInboundTransaction(array $protocolResponse): array;

    // ─────────────────────────────────────────────────────────────────────────
    // Inbound webhooks: Protocol event → Nexus event
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Normalize a protocol-specific webhook event name to a Nexus event name.
     *
     * For example, Credit Commons 'transaction.validated' → 'transaction.requested'
     *
     * @param string $protocolEvent The event name from the external platform
     * @return string Nexus-normalized event name
     */
    public function normalizeWebhookEvent(string $protocolEvent): string;

    /**
     * Extract the event data from a protocol-specific webhook payload.
     *
     * Different protocols structure their payloads differently. This method
     * normalizes the payload into Nexus's expected {event, data} structure.
     *
     * @param array $rawPayload The raw webhook payload
     * @return array{event: string, data: array} Normalized payload
     */
    public function normalizeWebhookPayload(array $rawPayload): array;

    // ─────────────────────────────────────────────────────────────────────────
    // Response unwrapping
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Unwrap a protocol response to extract the data array.
     *
     * JSON:API wraps in {data: [{type, id, attributes}]}, Nexus wraps in
     * {success, data}, CC returns flat arrays, etc.
     *
     * @param array  $response The full HTTP response body (decoded JSON)
     * @param string $action   The action that was called (for context)
     * @return array The extracted data
     */
    public function unwrapResponse(array $response, string $action = ''): array;
}
