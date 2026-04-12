<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Protocols;

use App\Services\Protocols\TimeOverflowAdapter;
use Tests\Laravel\TestCase;

class TimeOverflowAdapterTest extends TestCase
{
    // ── Unit conversions ─────────────────────────────────────────────

    public function test_secondsToHours_converts_correctly_and_rounds_to_two_decimals(): void
    {
        $this->assertSame(1.0, TimeOverflowAdapter::secondsToHours(3600));
        $this->assertSame(0.5, TimeOverflowAdapter::secondsToHours(1800));
        $this->assertSame(0.0, TimeOverflowAdapter::secondsToHours(0));
        $this->assertSame(2.5, TimeOverflowAdapter::secondsToHours(9000));
    }

    public function test_hoursToSeconds_is_inverse_of_secondsToHours(): void
    {
        $this->assertSame(3600, TimeOverflowAdapter::hoursToSeconds(1.0));
        $this->assertSame(1800, TimeOverflowAdapter::hoursToSeconds(0.5));
        $this->assertSame(0, TimeOverflowAdapter::hoursToSeconds(0));
        $this->assertSame(9000, TimeOverflowAdapter::hoursToSeconds(2.5));
    }

    // ── Member transform ─────────────────────────────────────────────

    public function test_transformMember_maps_fields_and_converts_balance(): void
    {
        $result = TimeOverflowAdapter::transformMember([
            'id' => 10,
            'member_uid' => 'uid-10',
            'account_id' => 99,
            'username' => 'alice',
            'email' => 'alice@example.com',
            'description' => 'bio here',
            'balance' => 7200, // seconds → 2.0 hours
            'tags' => ['gardening'],
            'manager' => true,
        ]);

        $this->assertSame(10, $result['external_id']);
        $this->assertSame('uid-10', $result['external_member_uid']);
        $this->assertSame('alice', $result['name']);
        $this->assertSame('alice@example.com', $result['email']);
        $this->assertSame('bio here', $result['bio']);
        $this->assertSame(2.0, $result['balance']);
        $this->assertSame(['gardening'], $result['skills']);
        $this->assertTrue($result['is_admin']);
        $this->assertSame('timeoverflow', $result['source_platform']);
    }

    public function test_transformMember_provides_sensible_defaults_for_missing_fields(): void
    {
        $result = TimeOverflowAdapter::transformMember([]);

        $this->assertNull($result['external_id']);
        $this->assertSame('Unknown', $result['name']);
        $this->assertSame(0.0, $result['balance']);
        $this->assertSame([], $result['skills']);
        $this->assertTrue($result['active']);      // default true
        $this->assertFalse($result['is_admin']);   // default false
    }

    public function test_transformMembers_maps_over_collection(): void
    {
        $result = TimeOverflowAdapter::transformMembers([
            ['id' => 1, 'username' => 'a'],
            ['id' => 2, 'username' => 'b'],
        ]);

        $this->assertCount(2, $result);
        $this->assertSame('a', $result[0]['name']);
        $this->assertSame('b', $result[1]['name']);
    }

    // ── Listing transform ────────────────────────────────────────────

    public function test_transformListing_maps_inquiry_to_request(): void
    {
        $result = TimeOverflowAdapter::transformListing([
            'id' => 77,
            'type' => 'inquiry',
            'title' => 'Need help',
        ]);

        $this->assertSame('request', $result['type']);
        $this->assertSame(77, $result['external_id']);
        $this->assertSame('Need help', $result['title']);
    }

    public function test_transformListing_defaults_unknown_type_to_offer(): void
    {
        $resultOffer = TimeOverflowAdapter::transformListing(['type' => 'OFFER']);
        $this->assertSame('offer', $resultOffer['type']);

        $resultMissing = TimeOverflowAdapter::transformListing([]);
        $this->assertSame('offer', $resultMissing['type']);
        $this->assertSame('Untitled', $resultMissing['title']);
    }

    // ── Transaction payload ──────────────────────────────────────────

    public function test_buildTransferPayload_converts_hours_to_seconds_and_includes_partner(): void
    {
        $payload = TimeOverflowAdapter::buildTransferPayload([
            'id' => 555,
            'amount' => 1.5,  // hours
            'direction' => 'outbound',
            'sender_email' => 'alice@example.com',
            'description' => 'Thanks',
        ], 42);

        $this->assertSame(42, $payload['partner_id']);
        $this->assertSame('555', $payload['external_transaction_id']);
        $this->assertSame('outbound', $payload['direction']);
        $this->assertSame(5400, $payload['amount']); // 1.5h → 5400s
        $this->assertSame('alice@example.com', $payload['remote_user_identifier']);
        $this->assertSame('Thanks', $payload['reason']);
    }

    public function test_buildTransferPayload_falls_back_to_default_reason(): void
    {
        $payload = TimeOverflowAdapter::buildTransferPayload(['amount' => 0], 1);
        $this->assertSame('Federation transfer from Nexus', $payload['reason']);
    }

    // ── Transaction response transform ───────────────────────────────

    public function test_transformTransactionResponse_maps_status_and_converts_amount(): void
    {
        $result = TimeOverflowAdapter::transformTransactionResponse([
            'data' => [
                'federation_transaction_id' => 'ftx-1',
                'local_transfer_id' => 99,
                'status' => 'completed',
                'amount' => 7200, // 2 hours
                'direction' => 'inbound',
            ],
        ]);

        $this->assertSame('ftx-1', $result['external_transaction_id']);
        $this->assertSame('99', $result['external_transfer_id']);
        $this->assertSame('completed', $result['status']);
        $this->assertSame(2.0, $result['amount_hours']);
        $this->assertSame('inbound', $result['direction']);
    }

    public function test_transformTransactionResponse_maps_unknown_status_to_pending(): void
    {
        $result = TimeOverflowAdapter::transformTransactionResponse([
            'data' => ['status' => 'weird_status', 'amount' => 0],
        ]);

        $this->assertSame('pending', $result['status']);
    }

    public function test_transformTransactionResponse_accepts_flat_payload_without_data_wrapper(): void
    {
        $result = TimeOverflowAdapter::transformTransactionResponse([
            'federation_transaction_id' => 'flat-1',
            'status' => 'disputed',
            'amount' => 3600,
        ]);

        $this->assertSame('flat-1', $result['external_transaction_id']);
        $this->assertSame('disputed', $result['status']);
        $this->assertSame(1.0, $result['amount_hours']);
    }

    // ── Organization transform ──────────────────────────────────────

    public function test_transformOrganization_maps_fields(): void
    {
        $result = TimeOverflowAdapter::transformOrganization([
            'id' => 3,
            'name' => 'Dublin Timebank',
            'city' => 'Dublin',
            'member_count' => 120,
        ]);

        $this->assertSame(3, $result['external_id']);
        $this->assertSame('Dublin Timebank', $result['name']);
        $this->assertSame('Dublin', $result['location_name']);
        $this->assertSame(120, $result['member_count']);
        $this->assertSame('timeoverflow', $result['source_platform']);
    }

    // ── Registration payload ─────────────────────────────────────────

    public function test_buildRegistrationPayload_trims_trailing_slash_and_sets_api_defaults(): void
    {
        $payload = TimeOverflowAdapter::buildRegistrationPayload(
            'Barcelona TB',
            'https://bcn.timeoverflow.org/',
            'secret-key'
        );

        $this->assertSame('https://bcn.timeoverflow.org', $payload['base_url']);
        $this->assertSame('/api/v1', $payload['api_path']);
        $this->assertSame('api_key', $payload['auth_method']);
        $this->assertSame('secret-key', $payload['api_key']);
        $this->assertTrue($payload['allow_member_search']);
        $this->assertFalse($payload['allow_messaging']);
    }

    public function test_buildRegistrationPayload_permissions_can_be_overridden(): void
    {
        $payload = TimeOverflowAdapter::buildRegistrationPayload(
            'X',
            'https://x.example',
            'k',
            ['allow_messaging' => true, 'allow_transactions' => false]
        );

        $this->assertTrue($payload['allow_messaging']);
        $this->assertFalse($payload['allow_transactions']);
    }

    // ── Interface accessors ──────────────────────────────────────────

    public function test_getProtocolName_returns_platform_constant(): void
    {
        $this->assertSame('timeoverflow', TimeOverflowAdapter::getProtocolName());
    }

    public function test_getDefaultApiPath_returns_v1(): void
    {
        $this->assertSame('/api/v1', TimeOverflowAdapter::getDefaultApiPath());
    }
}
