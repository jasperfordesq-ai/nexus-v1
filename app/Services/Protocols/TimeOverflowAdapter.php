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
 * TimeOverflowAdapter — Translates between Nexus and TimeOverflow data formats.
 *
 * TimeOverflow is a Ruby on Rails timebanking platform that we federate with
 * via its JSON API (added in our fork at github.com/jasperfordesq-ai/timeoverflow).
 *
 * This adapter handles the entity mapping differences:
 *   - TO "organization" → Nexus "tenant/community"
 *   - TO "member" → Nexus "user"
 *   - TO "offer"/"inquiry" → Nexus "listing"
 *   - TO "transfer" + "movements" → Nexus "transaction"
 *   - TO "account.balance" → Nexus "users.balance"
 *
 * Implements FederationProtocolAdapter for use with the protocol-aware
 * FederationExternalApiClient. All original static methods are preserved
 * for backward compatibility.
 *
 * Usage:
 *   // Fetch listings from a TimeOverflow partner
 *   $result = FederationExternalApiClient::fetchListings($partnerId, ['organization_id' => 1]);
 *   $nexusListings = TimeOverflowAdapter::transformListings($result['data']['data'] ?? []);
 *
 *   // Fetch members from a TimeOverflow partner
 *   $result = FederationExternalApiClient::fetchMembers($partnerId, ['organization_id' => 1]);
 *   $nexusMembers = TimeOverflowAdapter::transformMembers($result['data']['data'] ?? []);
 *
 *   // Create a cross-platform transfer
 *   $payload = TimeOverflowAdapter::buildTransferPayload($nexusTransaction);
 *   FederationExternalApiClient::createTransaction($partnerId, $payload);
 */
class TimeOverflowAdapter implements FederationProtocolAdapter
{
    /**
     * Platform identifier for TimeOverflow partners.
     */
    public const PLATFORM_TYPE = 'timeoverflow';

    /**
     * Default API path for TimeOverflow federation API.
     * Used when registering a new TO external partner.
     */
    public const DEFAULT_API_PATH = '/api/v1';

    // ─────────────────────────────────────────────────────────────────────────
    // Member/User Translation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transform a list of TimeOverflow members into Nexus user format.
     *
     * @param array $toMembers Array of TO member objects from the API
     * @return array Array of Nexus-compatible user objects
     */
    public static function transformMembers(array $toMembers): array
    {
        return array_map([self::class, 'transformMember'], $toMembers);
    }

    /**
     * Transform a single TimeOverflow member into Nexus user format.
     *
     * TO member fields → Nexus user fields:
     *   id → external_id
     *   member_uid → external_member_uid
     *   username → name
     *   email → email
     *   balance → balance (TO stores in seconds, Nexus in hours)
     *   tags → skills (array)
     *   description → bio
     *   account_id → external_account_id
     */
    public static function transformMember(array $toMember): array
    {
        return [
            'external_id' => $toMember['id'] ?? null,
            'external_member_uid' => $toMember['member_uid'] ?? null,
            'external_account_id' => $toMember['account_id'] ?? null,
            'name' => $toMember['username'] ?? 'Unknown',
            'email' => $toMember['email'] ?? null,
            'bio' => $toMember['description'] ?? null,
            'balance' => self::secondsToHours($toMember['balance'] ?? 0),
            'skills' => $toMember['tags'] ?? [],
            'active' => $toMember['active'] ?? true,
            'is_admin' => $toMember['manager'] ?? false,
            'gender' => $toMember['gender'] ?? null,
            'location' => $toMember['postcode'] ?? null,
            'organization_id' => $toMember['organization_id'] ?? null,
            'last_active_at' => $toMember['last_sign_in_at'] ?? null,
            'created_at' => $toMember['created_at'] ?? null,
            'source_platform' => self::PLATFORM_TYPE,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Listing Translation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transform a list of TimeOverflow listings into Nexus listing format.
     *
     * @param array $toListings Array of TO post/listing objects from the API
     * @return array Array of Nexus-compatible listing objects
     */
    public static function transformListings(array $toListings): array
    {
        return array_map([self::class, 'transformListing'], $toListings);
    }

    /**
     * Transform a single TimeOverflow listing (offer/inquiry) into Nexus format.
     *
     * TO post fields → Nexus listing fields:
     *   id → external_id
     *   type ("offer"/"inquiry") → type ("offer"/"request")
     *   title → title
     *   description → description
     *   category → category_name
     *   tags → tags (array)
     *   is_group → is_group
     */
    public static function transformListing(array $toListing): array
    {
        $type = strtolower($toListing['type'] ?? 'offer');

        return [
            'external_id' => $toListing['id'] ?? null,
            'title' => $toListing['title'] ?? 'Untitled',
            'description' => $toListing['description'] ?? null,
            'type' => $type === 'inquiry' ? 'request' : 'offer',
            'category_name' => $toListing['category'] ?? null,
            'category_id' => $toListing['category_id'] ?? null,
            'tags' => $toListing['tags'] ?? [],
            'is_group' => $toListing['is_group'] ?? false,
            'user_id' => $toListing['user_id'] ?? null,
            'organization_id' => $toListing['organization_id'] ?? null,
            'status' => 'active',
            'federated_visibility' => 'listed',
            'created_at' => $toListing['created_at'] ?? null,
            'updated_at' => $toListing['updated_at'] ?? null,
            'source_platform' => self::PLATFORM_TYPE,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Transaction/Transfer Translation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a transfer payload for the TimeOverflow API from Nexus transaction data.
     *
     * This is used when a Nexus user wants to send time to a TO member.
     *
     * @param array $nexusTransaction Nexus federation_transaction data
     * @param int   $partnerId        External partner ID
     * @return array Payload for POST /api/v1/transfers
     */
    public static function buildTransferPayload(array $nexusTransaction, int $partnerId): array
    {
        return [
            'partner_id' => $partnerId,
            'external_transaction_id' => (string) ($nexusTransaction['id'] ?? ''),
            'direction' => $nexusTransaction['direction'] ?? 'inbound',
            'local_account_id' => $nexusTransaction['remote_account_id'] ?? null,
            'remote_user_identifier' => $nexusTransaction['sender_email'] ?? $nexusTransaction['sender_identifier'] ?? '',
            'amount' => self::hoursToSeconds((float) ($nexusTransaction['amount'] ?? 0)),
            'reason' => $nexusTransaction['description'] ?? 'Federation transfer from Nexus',
        ];
    }

    /**
     * Transform a TimeOverflow federation transaction response into Nexus format.
     *
     * @param array $toResponse Response from POST /api/v1/transfers
     * @return array Nexus-compatible transaction record
     */
    public static function transformTransactionResponse(array $toResponse): array
    {
        $data = $toResponse['data'] ?? $toResponse;

        return [
            'external_transaction_id' => (string) ($data['federation_transaction_id'] ?? ''),
            'external_transfer_id' => (string) ($data['local_transfer_id'] ?? ''),
            'status' => self::mapTransactionStatus($data['status'] ?? 'pending'),
            'amount_hours' => self::secondsToHours((int) ($data['amount'] ?? 0)),
            'direction' => $data['direction'] ?? 'unknown',
            'completed_at' => $data['completed_at'] ?? null,
            'source_platform' => self::PLATFORM_TYPE,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Organization Translation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transform TimeOverflow organizations into Nexus tenant/community format.
     *
     * @param array $toOrganizations Array of TO organization objects
     * @return array Array of Nexus-compatible community objects
     */
    public static function transformOrganizations(array $toOrganizations): array
    {
        return array_map([self::class, 'transformOrganization'], $toOrganizations);
    }

    /**
     * Transform a single TimeOverflow organization into Nexus format.
     */
    public static function transformOrganization(array $toOrg): array
    {
        return [
            'external_id' => $toOrg['id'] ?? null,
            'name' => $toOrg['name'] ?? 'Unknown',
            'description' => $toOrg['description'] ?? null,
            'location_name' => $toOrg['city'] ?? null,
            'address' => $toOrg['address'] ?? null,
            'web' => $toOrg['web'] ?? null,
            'email' => $toOrg['email'] ?? null,
            'phone' => $toOrg['phone'] ?? null,
            'member_count' => $toOrg['member_count'] ?? 0,
            'active_offers_count' => $toOrg['active_offers_count'] ?? null,
            'active_inquiries_count' => $toOrg['active_inquiries_count'] ?? null,
            'created_at' => $toOrg['created_at'] ?? null,
            'source_platform' => self::PLATFORM_TYPE,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: Register a TimeOverflow instance as an external partner
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the registration payload for FederationExternalPartnerService::create().
     *
     * @param string $name        Display name for the TO instance
     * @param string $baseUrl     Base URL of the TO instance (e.g., https://timeoverflow.example.com)
     * @param string $apiKey      Raw API key for authenticating with TO
     * @param array  $permissions Optional permission overrides
     * @return array Ready for FederationExternalPartnerService::create()
     */
    public static function buildRegistrationPayload(
        string $name,
        string $baseUrl,
        string $apiKey,
        array $permissions = []
    ): array {
        return [
            'name' => $name,
            'description' => "TimeOverflow timebank: {$name}",
            'base_url' => rtrim($baseUrl, '/'),
            'api_path' => self::DEFAULT_API_PATH,
            'auth_method' => 'api_key',
            'api_key' => $apiKey,
            'allow_member_search' => $permissions['allow_member_search'] ?? true,
            'allow_listing_search' => $permissions['allow_listing_search'] ?? true,
            'allow_messaging' => $permissions['allow_messaging'] ?? false,
            'allow_transactions' => $permissions['allow_transactions'] ?? true,
            'allow_events' => $permissions['allow_events'] ?? false,
            'allow_groups' => $permissions['allow_groups'] ?? false,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Unit conversion helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Convert TimeOverflow seconds to Nexus hours.
     * TO stores time in seconds; Nexus stores in hours (decimal).
     */
    public static function secondsToHours(int $seconds): float
    {
        return round($seconds / 3600, 2);
    }

    /**
     * Convert Nexus hours to TimeOverflow seconds.
     */
    public static function hoursToSeconds(float $hours): int
    {
        return (int) round($hours * 3600);
    }

    /**
     * Map TimeOverflow transaction status to Nexus status.
     */
    private static function mapTransactionStatus(string $toStatus): string
    {
        return match ($toStatus) {
            'pending' => 'pending',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'disputed' => 'disputed',
            default => 'pending',
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FederationProtocolAdapter interface implementation
    //
    // These methods delegate to the existing static methods above, providing
    // the adapter interface while maintaining full backward compatibility.
    // ─────────────────────────────────────────────────────────────────────────

    public static function getProtocolName(): string
    {
        return self::PLATFORM_TYPE;
    }

    public static function getDefaultApiPath(): string
    {
        return self::DEFAULT_API_PATH;
    }

    public function mapEndpoint(string $action, array $params = []): string
    {
        $id = $params['id'] ?? null;

        return match ($action) {
            'members'       => '/members',
            'member'        => "/members/{$id}",
            'listings'      => '/posts',           // TO calls them "posts"
            'listing'       => "/posts/{$id}",
            'transactions'  => '/transfers',       // TO calls them "transfers"
            'organizations' => '/organizations',
            'health'        => '/health',
            'messages'      => '/messages',
            'reviews'       => '/reviews',         // TO extension
            'events'        => '/events',          // TO extension
            'groups'        => '/groups',          // TO extension
            'connections'   => '/connections',     // TO extension
            'volunteering'  => '/volunteering',    // TO extension
            default         => "/{$action}",
        };
    }

    public function mapHttpMethod(string $action, string $default = 'GET'): string
    {
        return $default;
    }

    public function transformOutboundTransaction(array $nexusTransaction, int $partnerId): array
    {
        return self::buildTransferPayload($nexusTransaction, $partnerId);
    }

    public function transformOutboundMessage(array $nexusMessage): array
    {
        return $nexusMessage;
    }

    /**
     * Graceful extension envelope for entities TimeOverflow doesn't natively support.
     * TO nodes without the extension can ignore it; nodes with it can deserialize.
     */
    private static function extensionEnvelope(string $entity, array $payload): array
    {
        return [
            'type'    => 'nexus_extension_' . $entity,
            'payload' => $payload,
            'source_platform' => self::PLATFORM_TYPE,
        ];
    }

    /**
     * Transform a Nexus listing into TimeOverflow's native /posts payload.
     */
    public function transformOutboundListing(array $listing): array
    {
        $type = strtolower((string) ($listing['type'] ?? 'offer'));
        return [
            'post' => [
                'title'       => $listing['title'] ?? 'Untitled',
                'description' => $listing['description'] ?? null,
                'type'        => $type === 'request' ? 'inquiry' : 'offer',
                'category'    => $listing['category_name'] ?? $listing['category'] ?? null,
                'category_id' => $listing['category_id'] ?? null,
                'tags'        => $listing['tags'] ?? [],
                'user_id'     => $listing['user_id'] ?? null,
                'organization_id' => $listing['organization_id'] ?? null,
                'created_at'  => $listing['created_at'] ?? null,
                'source_platform' => 'nexus',
            ],
        ];
    }

    public function transformOutboundReview(array $review): array
    {
        return self::extensionEnvelope('review', [
            'id'              => $review['id'] ?? null,
            'rating'          => (int) ($review['rating'] ?? 0),
            'comment'         => $review['comment'] ?? null,
            'reviewer_id'     => $review['reviewer_id'] ?? null,
            'receiver_external_id' => $review['receiver_external_id'] ?? null,
            'transaction_ref' => $review['federation_transaction_id']
                ?? $review['transaction_id']
                ?? null,
            'created_at'      => $review['created_at'] ?? null,
        ]);
    }

    public function transformOutboundEvent(array $event): array
    {
        return self::extensionEnvelope('event', [
            'id'          => $event['id'] ?? null,
            'title'       => $event['title'] ?? $event['name'] ?? 'Untitled event',
            'description' => $event['description'] ?? null,
            'starts_at'   => $event['starts_at'] ?? $event['start_time'] ?? null,
            'ends_at'     => $event['ends_at'] ?? $event['end_time'] ?? null,
            'location'    => $event['location'] ?? null,
            'organization_id' => $event['organization_id'] ?? null,
            'created_at'  => $event['created_at'] ?? null,
        ]);
    }

    public function transformOutboundGroup(array $group): array
    {
        return self::extensionEnvelope('group', [
            'id'          => $group['id'] ?? null,
            'name'        => $group['name'] ?? 'Untitled group',
            'description' => $group['description'] ?? null,
            'privacy'     => $group['privacy'] ?? $group['visibility'] ?? 'public',
            'organization_id' => $group['organization_id'] ?? null,
            'created_at'  => $group['created_at'] ?? null,
        ]);
    }

    public function transformOutboundConnection(array $connection): array
    {
        return self::extensionEnvelope('connection', [
            'id'           => $connection['id'] ?? null,
            'requester_id' => $connection['requester_id'] ?? null,
            'recipient_id' => $connection['recipient_id'] ?? null,
            'status'       => $connection['status'] ?? 'pending',
            'note'         => $connection['note'] ?? null,
            'created_at'   => $connection['created_at'] ?? null,
        ]);
    }

    public function transformOutboundVolunteering(array $opportunity): array
    {
        return self::extensionEnvelope('volunteering', [
            'id'          => $opportunity['id'] ?? null,
            'title'       => $opportunity['title'] ?? 'Untitled opportunity',
            'description' => $opportunity['description'] ?? null,
            'organization' => $opportunity['organization_name'] ?? $opportunity['organization'] ?? null,
            'organization_id' => $opportunity['organization_id'] ?? null,
            'starts_at'   => $opportunity['starts_at'] ?? null,
            'ends_at'     => $opportunity['ends_at'] ?? null,
            'location'    => $opportunity['location'] ?? null,
            'hours'       => $opportunity['hours'] ?? null,
            'created_at'  => $opportunity['created_at'] ?? null,
        ]);
    }

    /**
     * Transform a Nexus member profile into TimeOverflow's native member format.
     */
    public function transformOutboundMember(array $member): array
    {
        return [
            'member' => [
                'id'           => $member['id'] ?? null,
                'username'     => $member['username'] ?? $member['name'] ?? null,
                'email'        => $member['email'] ?? null,
                'description'  => $member['bio'] ?? null,
                'balance'      => self::hoursToSeconds((float) ($member['balance'] ?? 0)),
                'tags'         => $member['skills'] ?? [],
                'active'       => (bool) ($member['active'] ?? true),
                'postcode'     => $member['location'] ?? null,
                'organization_id' => $member['organization_id'] ?? null,
                'created_at'   => $member['created_at'] ?? null,
                'source_platform' => 'nexus',
            ],
        ];
    }

    public function transformInboundMember(array $protocolMember): array
    {
        return self::transformMember($protocolMember);
    }

    public function transformInboundMembers(array $protocolMembers): array
    {
        return self::transformMembers($protocolMembers);
    }

    public function transformInboundListing(array $protocolListing): array
    {
        return self::transformListing($protocolListing);
    }

    public function transformInboundListings(array $protocolListings): array
    {
        return self::transformListings($protocolListings);
    }

    public function transformInboundTransaction(array $protocolResponse): array
    {
        return self::transformTransactionResponse($protocolResponse);
    }

    public function normalizeWebhookEvent(string $protocolEvent): string
    {
        // TimeOverflow uses the same event names as Nexus
        return $protocolEvent;
    }

    public function normalizeWebhookPayload(array $rawPayload): array
    {
        return [
            'event' => $rawPayload['event'] ?? 'unknown',
            'data'  => $rawPayload['data'] ?? [],
        ];
    }

    public function unwrapResponse(array $response, string $action = ''): array
    {
        // TimeOverflow wraps responses in {data: [...]} like Nexus v1
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }
}
