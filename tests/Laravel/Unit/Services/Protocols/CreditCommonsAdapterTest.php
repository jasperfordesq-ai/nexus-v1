<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Protocols;

use Tests\Laravel\TestCase;
use App\Services\Protocols\CreditCommonsAdapter;

class CreditCommonsAdapterTest extends TestCase
{
    private CreditCommonsAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new CreditCommonsAdapter();
    }

    public function test_getProtocolName_returns_credit_commons(): void
    {
        $this->assertEquals('credit_commons', CreditCommonsAdapter::getProtocolName());
    }

    public function test_mapEndpoint_maps_cc_paths(): void
    {
        $this->assertEquals('/accounts', $this->adapter->mapEndpoint('accounts'));
        $this->assertEquals('/accounts', $this->adapter->mapEndpoint('members'));
        $this->assertEquals('/account/alice', $this->adapter->mapEndpoint('account', ['id' => 'alice']));
        $this->assertEquals('/about', $this->adapter->mapEndpoint('about'));
        $this->assertEquals('/about', $this->adapter->mapEndpoint('health'));
        $this->assertEquals('/transactions', $this->adapter->mapEndpoint('transactions'));
        $this->assertEquals('/transaction/tx-1', $this->adapter->mapEndpoint('transaction', ['id' => 'tx-1']));
        $this->assertEquals('/transaction/tx-1/V', $this->adapter->mapEndpoint('transaction_state', ['id' => 'tx-1', 'state' => 'V']));
        $this->assertEquals('/transaction/relay', $this->adapter->mapEndpoint('transaction_relay'));
        $this->assertEquals('/entries', $this->adapter->mapEndpoint('entries'));
        $this->assertEquals('/entries/tx-1', $this->adapter->mapEndpoint('transaction_entries', ['id' => 'tx-1']));
        $this->assertEquals('/account', $this->adapter->mapEndpoint('account_stats'));
        $this->assertEquals('/account/history', $this->adapter->mapEndpoint('account_history'));
        $this->assertEquals('/forms', $this->adapter->mapEndpoint('forms'));
    }

    public function test_mapHttpMethod_returns_patch_for_transaction_state(): void
    {
        $this->assertEquals('PATCH', $this->adapter->mapHttpMethod('transaction_state'));
        $this->assertEquals('POST', $this->adapter->mapHttpMethod('transaction_relay'));
        $this->assertEquals('GET', $this->adapter->mapHttpMethod('accounts'));
        $this->assertEquals('POST', $this->adapter->mapHttpMethod('some_action', 'POST'));
    }

    public function test_transformOutboundTransaction_creates_cc_format(): void
    {
        $nexusTx = [
            'amount' => 2.5,
            'description' => 'Tutoring session',
            'status' => 'pending',
            'sender_account_path' => 'my-node/alice',
            'receiver_account_path' => 'other-node/bob',
        ];

        $result = $this->adapter->transformOutboundTransaction($nexusTx, 1);

        $this->assertEquals('my-node/alice', $result['payer']);
        $this->assertEquals('other-node/bob', $result['payee']);
        $this->assertEquals(2.5, $result['quant']);
        $this->assertEquals('Tutoring session', $result['description']);
        $this->assertArrayHasKey('workflow', $result);
        $this->assertEquals('0|PC-CE=', $result['workflow']);
    }

    public function test_transformOutboundTransaction_uses_fallback_identifiers(): void
    {
        $nexusTx = [
            'amount' => 1.0,
            'sender_identifier' => 'alice',
            'receiver_user_id' => 42,
        ];

        $result = $this->adapter->transformOutboundTransaction($nexusTx, 1);

        $this->assertEquals('alice', $result['payer']);
        $this->assertEquals('42', $result['payee']);
    }

    public function test_transformInboundMember_from_account_path(): void
    {
        $ccMember = [
            'acc_path' => 'my-node/alice123',
            'balance' => 15.0,
            'volume' => 42,
            'gross_in' => 28,
            'gross_out' => 14,
            'partners' => 5,
            'trades' => 8,
        ];

        $result = $this->adapter->transformInboundMember($ccMember);

        $this->assertEquals('my-node/alice123', $result['external_id']);
        $this->assertEquals('alice123', $result['name']); // Extracted username from path
        $this->assertEquals(15.0, $result['balance']);
        $this->assertEquals('credit_commons', $result['source_platform']);
        $this->assertTrue($result['active']);
        $this->assertEquals(42, $result['trading_stats']['volume']);
        $this->assertEquals(28, $result['trading_stats']['gross_in']);
    }

    public function test_transformInboundMembers_handles_string_paths(): void
    {
        // CC /accounts returns an array of path strings
        $ccMembers = [
            'my-node/alice',
            'my-node/bob',
            'my-node/carol',
        ];

        $result = $this->adapter->transformInboundMembers($ccMembers);

        $this->assertCount(3, $result);
        $this->assertEquals('alice', $result[0]['name']);
        $this->assertEquals('my-node/alice', $result[0]['external_id']);
        $this->assertEquals('bob', $result[1]['name']);
        $this->assertEquals('carol', $result[2]['name']);
        foreach ($result as $member) {
            $this->assertEquals('credit_commons', $member['source_platform']);
        }
    }

    public function test_transformInboundTransaction_maps_cc_states(): void
    {
        // Completed (C)
        $txC = [
            'uuid' => 'uuid-c',
            'state' => 'C',
            'written' => '2026-01-15',
            'entries' => [
                ['payer' => 'node/alice', 'payee' => 'node/bob', 'quant' => 2.5, 'description' => 'Help'],
            ],
        ];
        $resultC = $this->adapter->transformInboundTransaction($txC);
        $this->assertEquals('completed', $resultC['status']);
        $this->assertEquals(2.5, $resultC['amount_hours']);
        $this->assertEquals('uuid-c', $resultC['external_transaction_id']);

        // Pending (P)
        $txP = ['uuid' => 'uuid-p', 'state' => 'P', 'entries' => [['quant' => 1.0]]];
        $resultP = $this->adapter->transformInboundTransaction($txP);
        $this->assertEquals('pending', $resultP['status']);

        // Erased (E)
        $txE = ['uuid' => 'uuid-e', 'state' => 'E', 'entries' => [['quant' => 3.0]]];
        $resultE = $this->adapter->transformInboundTransaction($txE);
        $this->assertEquals('cancelled', $resultE['status']);
    }

    public function test_mapCcStateToNexus_all_states(): void
    {
        $this->assertEquals('completed', CreditCommonsAdapter::mapCcStateToNexus('C'));
        $this->assertEquals('pending', CreditCommonsAdapter::mapCcStateToNexus('V'));
        $this->assertEquals('pending', CreditCommonsAdapter::mapCcStateToNexus('P'));
        $this->assertEquals('cancelled', CreditCommonsAdapter::mapCcStateToNexus('E'));
        $this->assertEquals('cancelled', CreditCommonsAdapter::mapCcStateToNexus('X'));
        $this->assertEquals('pending', CreditCommonsAdapter::mapCcStateToNexus('UNKNOWN'));
    }

    public function test_mapNexusStateToCc_all_statuses(): void
    {
        $this->assertEquals('C', CreditCommonsAdapter::mapNexusStateToCc('completed'));
        $this->assertEquals('P', CreditCommonsAdapter::mapNexusStateToCc('pending'));
        $this->assertEquals('E', CreditCommonsAdapter::mapNexusStateToCc('cancelled'));
        $this->assertEquals('P', CreditCommonsAdapter::mapNexusStateToCc('disputed'));
        $this->assertEquals('P', CreditCommonsAdapter::mapNexusStateToCc('unknown_status'));
    }

    public function test_isValidTransition_allows_P_to_V(): void
    {
        $this->assertTrue(CreditCommonsAdapter::isValidTransition('P', 'V'));
        $this->assertTrue(CreditCommonsAdapter::isValidTransition('P', 'C'));
        $this->assertTrue(CreditCommonsAdapter::isValidTransition('P', 'E'));
        $this->assertTrue(CreditCommonsAdapter::isValidTransition('V', 'C'));
        $this->assertTrue(CreditCommonsAdapter::isValidTransition('V', 'E'));
        $this->assertTrue(CreditCommonsAdapter::isValidTransition('C', 'E'));
        $this->assertTrue(CreditCommonsAdapter::isValidTransition('E', 'X'));
    }

    public function test_isValidTransition_blocks_C_to_P(): void
    {
        $this->assertFalse(CreditCommonsAdapter::isValidTransition('C', 'P'));
        $this->assertFalse(CreditCommonsAdapter::isValidTransition('C', 'V'));
        $this->assertFalse(CreditCommonsAdapter::isValidTransition('E', 'C'));
        $this->assertFalse(CreditCommonsAdapter::isValidTransition('E', 'P'));
        $this->assertFalse(CreditCommonsAdapter::isValidTransition('X', 'C'));
        $this->assertFalse(CreditCommonsAdapter::isValidTransition('X', 'P'));
        $this->assertFalse(CreditCommonsAdapter::isValidTransition('X', 'E'));
    }

    public function test_toAccountPath_preserves_existing_paths(): void
    {
        // Paths with / are already valid CC paths
        $this->assertEquals('my-node/alice', CreditCommonsAdapter::toAccountPath('my-node/alice'));
        $this->assertEquals('a/b', CreditCommonsAdapter::toAccountPath('a/b'));

        // Simple identifiers without / are returned as-is (no node prefix available)
        $this->assertEquals('alice', CreditCommonsAdapter::toAccountPath('alice'));
        $this->assertEquals('42', CreditCommonsAdapter::toAccountPath('42'));
    }

    public function test_extractUsername_from_path(): void
    {
        $this->assertEquals('alice', CreditCommonsAdapter::extractUsername('my-node/alice'));
        $this->assertEquals('bob', CreditCommonsAdapter::extractUsername('other-node/bob'));
        $this->assertEquals('alice', CreditCommonsAdapter::extractUsername('alice'));
    }

    public function test_extractNodeSlug_from_path(): void
    {
        $this->assertEquals('my-node', CreditCommonsAdapter::extractNodeSlug('my-node/alice'));
        $this->assertEquals('other-node', CreditCommonsAdapter::extractNodeSlug('other-node/bob'));
    }

    public function test_extractNodeSlug_returns_null_for_simple_names(): void
    {
        $this->assertNull(CreditCommonsAdapter::extractNodeSlug('alice'));
        $this->assertNull(CreditCommonsAdapter::extractNodeSlug('42'));
    }

    public function test_generateEntries_creates_single_entry_from_nexus_transaction(): void
    {
        $nexusTx = [
            'sender_user_id' => 'alice',
            'receiver_user_id' => 'bob',
            'amount' => 3.0,
            'description' => 'Cooking lesson',
            'status' => 'completed',
        ];

        $entries = CreditCommonsAdapter::generateEntries($nexusTx);

        $this->assertCount(1, $entries);
        $entry = $entries[0];
        $this->assertEquals('alice', $entry['payer']);
        $this->assertEquals('bob', $entry['payee']);
        $this->assertEquals(3.0, $entry['quant']);
        $this->assertEquals('Cooking lesson', $entry['description']);
        $this->assertEquals('C', $entry['state']); // completed → C
    }

    public function test_buildAboutResponse_matches_cc_spec_format(): void
    {
        $nodeConfig = [
            'currency_format' => '<quantity> hours',
            'exchange_rate' => 1.5,
            'node_slug' => 'my-timebank',
            'validated_window' => 600,
            'trade_count' => 42,
            'trader_count' => 15,
            'volume' => 128.5,
            'account_count' => 30,
        ];

        $result = CreditCommonsAdapter::buildAboutResponse($nodeConfig);

        $this->assertEquals('<quantity> hours', $result['format']);
        $this->assertEquals(1.5, $result['rate']);
        $this->assertEquals(['my-timebank'], $result['absolute_path']);
        $this->assertEquals(600, $result['validated_window']);
        $this->assertEquals(42, $result['trades']);
        $this->assertEquals(15, $result['traders']);
        $this->assertEquals(128.5, $result['volume']);
        $this->assertEquals(30, $result['accounts']);
    }

    public function test_buildAboutResponse_uses_defaults(): void
    {
        $result = CreditCommonsAdapter::buildAboutResponse([]);

        $this->assertEquals('<quantity> hours', $result['format']);
        $this->assertEquals(1.0, $result['rate']);
        $this->assertEquals(['nexus'], $result['absolute_path']);
        $this->assertEquals(300, $result['validated_window']);
        $this->assertEquals(0, $result['trades']);
        $this->assertEquals(0, $result['traders']);
        $this->assertEquals(0.0, $result['volume']);
        $this->assertEquals(0, $result['accounts']);
    }
}
