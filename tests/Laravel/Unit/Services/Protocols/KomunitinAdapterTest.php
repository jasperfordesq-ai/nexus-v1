<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Protocols;

use Tests\Laravel\TestCase;
use App\Services\Protocols\KomunitinAdapter;

class KomunitinAdapterTest extends TestCase
{
    private KomunitinAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new KomunitinAdapter();
    }

    public function test_getProtocolName_returns_komunitin(): void
    {
        $this->assertEquals('komunitin', KomunitinAdapter::getProtocolName());
    }

    public function test_mapEndpoint_maps_to_currency_scoped_paths(): void
    {
        // Members/accounts are scoped to a currency code
        $this->assertEquals('/default/accounts', $this->adapter->mapEndpoint('members'));
        $this->assertEquals('/EUR/accounts', $this->adapter->mapEndpoint('members', ['currency_code' => 'EUR']));
        $this->assertEquals('/EUR/accounts/abc-123', $this->adapter->mapEndpoint('member', ['currency_code' => 'EUR', 'id' => 'abc-123']));

        // Transactions are scoped to currency
        $this->assertEquals('/default/transfers', $this->adapter->mapEndpoint('transactions'));
        $this->assertEquals('/USD/transfers/tx-1', $this->adapter->mapEndpoint('transaction', ['currency_code' => 'USD', 'id' => 'tx-1']));

        // Listings map to offers
        $this->assertEquals('/offers', $this->adapter->mapEndpoint('listings'));
        $this->assertEquals('/offers/42', $this->adapter->mapEndpoint('listing', ['id' => 42]));

        // Other endpoints
        $this->assertEquals('/currencies', $this->adapter->mapEndpoint('currencies'));
        $this->assertEquals('/health', $this->adapter->mapEndpoint('health'));
    }

    public function test_transformOutboundTransaction_wraps_in_jsonapi(): void
    {
        $nexusTx = [
            'amount' => 1.5,
            'description' => 'Garden help',
            'status' => 'completed',
            'sender_account_id' => 'sender-acc-1',
            'receiver_account_id' => 'receiver-acc-2',
        ];

        $result = $this->adapter->transformOutboundTransaction($nexusTx, 1);

        // Check JSON:API structure
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('transfers', $result['data']['type']);

        // Check attributes
        $attrs = $result['data']['attributes'];
        $this->assertEquals(150, $attrs['amount']); // 1.5 hours = 150 minor units
        $this->assertEquals('Garden help', $attrs['meta']);
        $this->assertEquals('committed', $attrs['state']); // completed → committed

        // Check relationships
        $this->assertEquals('accounts', $result['data']['relationships']['payer']['data']['type']);
        $this->assertEquals('sender-acc-1', $result['data']['relationships']['payer']['data']['id']);
        $this->assertEquals('accounts', $result['data']['relationships']['payee']['data']['type']);
        $this->assertEquals('receiver-acc-2', $result['data']['relationships']['payee']['data']['id']);
    }

    public function test_transformInboundMember_extracts_from_jsonapi_attributes(): void
    {
        $jsonApiMember = [
            'id' => 'acc-uuid-1',
            'type' => 'accounts',
            'attributes' => [
                'code' => 'alice-slug',
                'balance' => 500,
                'status' => 'active',
                'email' => 'alice@example.com',
                'description' => 'Loves gardening',
                'location' => 'Dublin',
                'tags' => ['gardening', 'cooking'],
                'created' => '2026-01-01T00:00:00Z',
            ],
        ];

        $result = $this->adapter->transformInboundMember($jsonApiMember);

        $this->assertEquals('acc-uuid-1', $result['external_id']);
        $this->assertEquals('acc-uuid-1', $result['external_account_id']);
        $this->assertEquals('alice-slug', $result['name']);
        $this->assertEquals('alice@example.com', $result['email']);
        $this->assertEquals('Loves gardening', $result['bio']);
        $this->assertEquals(5.0, $result['balance']); // 500 minor units = 5.0 hours
        $this->assertEquals(['gardening', 'cooking'], $result['skills']);
        $this->assertTrue($result['active']);
        $this->assertEquals('Dublin', $result['location']);
        $this->assertEquals('komunitin', $result['source_platform']);
    }

    public function test_transformInboundTransaction_maps_committed_to_completed(): void
    {
        $jsonApiTransfer = [
            'data' => [
                'id' => 'tx-uuid-1',
                'type' => 'transfers',
                'attributes' => [
                    'amount' => 200,
                    'meta' => 'Dog walking',
                    'state' => 'committed',
                    'updated' => '2026-02-15T14:30:00Z',
                ],
            ],
        ];

        $result = $this->adapter->transformInboundTransaction($jsonApiTransfer);

        $this->assertEquals('tx-uuid-1', $result['external_transaction_id']);
        $this->assertEquals('completed', $result['status']); // committed → completed
        $this->assertEquals(2.0, $result['amount_hours']); // 200 minor units = 2.0 hours
        $this->assertEquals('Dog walking', $result['description']);
        $this->assertEquals('komunitin', $result['source_platform']);
    }

    public function test_hoursToMinorUnits_converts_correctly(): void
    {
        $this->assertEquals(150, KomunitinAdapter::hoursToMinorUnits(1.5));
        $this->assertEquals(100, KomunitinAdapter::hoursToMinorUnits(1.0));
        $this->assertEquals(0, KomunitinAdapter::hoursToMinorUnits(0.0));
        $this->assertEquals(250, KomunitinAdapter::hoursToMinorUnits(2.5));
        $this->assertEquals(75, KomunitinAdapter::hoursToMinorUnits(0.75));
    }

    public function test_minorUnitsToHours_converts_correctly(): void
    {
        $this->assertEquals(2.5, KomunitinAdapter::minorUnitsToHours(250));
        $this->assertEquals(1.0, KomunitinAdapter::minorUnitsToHours(100));
        $this->assertEquals(0.0, KomunitinAdapter::minorUnitsToHours(0));
        $this->assertEquals(0.5, KomunitinAdapter::minorUnitsToHours(50));
        $this->assertEquals(1.5, KomunitinAdapter::minorUnitsToHours(150));
    }

    public function test_unwrapResponse_flattens_jsonapi_collection(): void
    {
        $response = [
            'data' => [
                [
                    'type' => 'accounts',
                    'id' => 'acc-1',
                    'attributes' => ['code' => 'alice', 'balance' => 500],
                    'relationships' => [
                        'currency' => ['data' => ['type' => 'currencies', 'id' => 'cur-1']],
                    ],
                ],
                [
                    'type' => 'accounts',
                    'id' => 'acc-2',
                    'attributes' => ['code' => 'bob', 'balance' => 300],
                ],
            ],
        ];

        $result = $this->adapter->unwrapResponse($response);

        $this->assertCount(2, $result);
        $this->assertEquals('alice', $result[0]['code']);
        $this->assertEquals('acc-1', $result[0]['id']);
        $this->assertEquals('cur-1', $result[0]['currency_id']); // Flattened relationship
        $this->assertEquals('bob', $result[1]['code']);
        $this->assertEquals('acc-2', $result[1]['id']);
    }

    public function test_unwrapResponse_flattens_jsonapi_single_resource(): void
    {
        $response = [
            'data' => [
                'type' => 'accounts',
                'id' => 'acc-1',
                'attributes' => ['code' => 'alice', 'balance' => 500],
            ],
        ];

        $result = $this->adapter->unwrapResponse($response);

        $this->assertEquals('alice', $result['code']);
        $this->assertEquals(500, $result['balance']);
        $this->assertEquals('acc-1', $result['id']);
    }

    public function test_normalizeWebhookEvent_maps_transfer_events(): void
    {
        $this->assertEquals('transaction.completed', $this->adapter->normalizeWebhookEvent('transfer.committed'));
        $this->assertEquals('transaction.completed', $this->adapter->normalizeWebhookEvent('transfer.completed'));
        $this->assertEquals('transaction.cancelled', $this->adapter->normalizeWebhookEvent('transfer.cancelled'));
        $this->assertEquals('transaction.cancelled', $this->adapter->normalizeWebhookEvent('transfer.rejected'));
        $this->assertEquals('transaction.requested', $this->adapter->normalizeWebhookEvent('transfer.pending'));
        $this->assertEquals('transaction.requested', $this->adapter->normalizeWebhookEvent('transfer.new'));
        $this->assertEquals('member.opted_in', $this->adapter->normalizeWebhookEvent('account.created'));
        $this->assertEquals('member.opted_out', $this->adapter->normalizeWebhookEvent('account.deleted'));
        // Unknown events pass through
        $this->assertEquals('custom.event', $this->adapter->normalizeWebhookEvent('custom.event'));
    }

    public function test_floatToRate_converts_simple_rates(): void
    {
        $rate1 = KomunitinAdapter::floatToRate(1.0);
        $this->assertEquals(1, $rate1['n']);
        $this->assertEquals(1, $rate1['d']);

        $rate05 = KomunitinAdapter::floatToRate(0.5);
        $this->assertEquals(1, $rate05['n']);
        $this->assertEquals(2, $rate05['d']);

        $rate2 = KomunitinAdapter::floatToRate(2.0);
        $this->assertEquals(2, $rate2['n']);
        $this->assertEquals(1, $rate2['d']);

        // Zero/negative
        $rate0 = KomunitinAdapter::floatToRate(0.0);
        $this->assertEquals(0, $rate0['n']);
        $this->assertEquals(1, $rate0['d']);
    }

    public function test_rateToFloat_converts_back(): void
    {
        $this->assertEquals(1.5, KomunitinAdapter::rateToFloat(['n' => 3, 'd' => 2]));
        $this->assertEquals(1.0, KomunitinAdapter::rateToFloat(['n' => 1, 'd' => 1]));
        $this->assertEquals(0.5, KomunitinAdapter::rateToFloat(['n' => 1, 'd' => 2]));
        $this->assertEquals(2.0, KomunitinAdapter::rateToFloat(['n' => 2, 'd' => 1]));

        // Edge case: d=0 returns 1.0
        $this->assertEquals(1.0, KomunitinAdapter::rateToFloat(['n' => 5, 'd' => 0]));
    }

    public function test_wrapAsJsonApi_creates_correct_structure(): void
    {
        $result = KomunitinAdapter::wrapAsJsonApi('accounts', ['code' => 'alice', 'balance' => 500], 'acc-1');

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('accounts', $result['data']['type']);
        $this->assertEquals('acc-1', $result['data']['id']);
        $this->assertEquals('alice', $result['data']['attributes']['code']);
        $this->assertEquals(500, $result['data']['attributes']['balance']);
    }

    public function test_wrapAsJsonApi_without_id(): void
    {
        $result = KomunitinAdapter::wrapAsJsonApi('transfers', ['amount' => 100]);

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('transfers', $result['data']['type']);
        $this->assertArrayNotHasKey('id', $result['data']);
        $this->assertEquals(100, $result['data']['attributes']['amount']);
    }

    public function test_floatToRate_and_rateToFloat_round_trip(): void
    {
        $values = [0.25, 0.5, 0.75, 1.0, 1.5, 2.0, 3.0, 0.1];

        foreach ($values as $original) {
            $rate = KomunitinAdapter::floatToRate($original);
            $roundTrip = KomunitinAdapter::rateToFloat($rate);
            $this->assertEqualsWithDelta($original, $roundTrip, 0.001, "Round-trip failed for {$original}");
        }
    }
}
