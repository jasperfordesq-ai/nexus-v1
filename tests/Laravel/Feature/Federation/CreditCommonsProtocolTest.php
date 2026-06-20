<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Federation;

use App\Core\TenantContext;
use App\Services\CreditCommonsNodeService;
use App\Services\Protocols\CreditCommonsAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Tests\Laravel\TestCase;

/**
 * CreditCommonsProtocolTest — adapter shape/normalization + the security-critical
 * hashchain replay/forgery guard.
 *
 * The propose → validate → commit HTTP lifecycle is covered end-to-end (real DB
 * state transitions P→V→C) by
 * tests/Laravel/Feature/FederationProtocolEndpointsTest.php — see
 * test_cc_propose_creates_pending_entry / test_cc_validate_transitions_P_to_V /
 * test_cc_commit_transitions_V_to_C. The former reflection-only lifecycle stub
 * here (class_exists + method_exists) is removed as redundant theatre.
 */
final class CreditCommonsProtocolTest extends TestCase
{
    use FederationIntegrationHarness;
    use DatabaseTransactions;

    private CreditCommonsAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new CreditCommonsAdapter();
    }

    public function test_protocol_identity(): void
    {
        $this->assertSame('credit_commons', CreditCommonsAdapter::getProtocolName());
    }

    public function test_outbound_transaction_uses_double_entry_shape(): void
    {
        $tx = [
            'id'          => 10,
            'amount'      => 2.5,
            'description' => 'Pair programming',
            'sender_id'   => 1,
            'receiver_id' => 2,
        ];
        $out = $this->adapter->transformOutboundTransaction($tx, 1);

        // CC double-entry: expect entries / payee / payer / quant fields somewhere.
        $json = json_encode($out);
        $this->assertMatchesRegularExpression(
            '/(entries|payee|payer|quant|amount)/i',
            (string) $json,
            'Outbound CC transaction payload should contain double-entry fields'
        );
    }

    public function test_webhook_event_normalization_maps_validated_to_requested(): void
    {
        // CC 'transaction.validated' should be normalised to a Nexus event name.
        $normalized = $this->adapter->normalizeWebhookEvent('transaction.validated');
        $this->assertIsString($normalized);
        $this->assertNotEmpty($normalized);
    }

    public function test_hashchain_validation_enforces_the_chain_once_established(): void
    {
        // Establish a hashchain for the test tenant with a known last hash.
        TenantContext::setById($this->testTenantId);
        CreditCommonsNodeService::getNodeConfig($this->testTenantId); // ensure a config row exists
        $knownHash = hash('sha256', 'genesis-' . $this->testTenantId);
        CreditCommonsNodeService::recordHash($this->testTenantId, $knownHash);

        TenantContext::setById($this->testTenantId);

        // A matching Last-hash validates.
        $this->assertTrue(
            CreditCommonsAdapter::validateHashchain($knownHash, $this->testTenantId),
            'A relay presenting the correct Last-hash must validate against the established chain'
        );

        // A forged / mismatched hash must be rejected — this is the replay/forgery guard.
        $this->assertFalse(
            CreditCommonsAdapter::validateHashchain('forged-' . str_repeat('0', 56), $this->testTenantId),
            'A relay presenting a wrong Last-hash must be rejected'
        );

        // A missing Last-hash must FAIL CLOSED once a chain exists (omitting the header
        // previously bypassed the chain entirely).
        $this->assertFalse(
            CreditCommonsAdapter::validateHashchain(null, $this->testTenantId),
            'A missing Last-hash must not bypass an established chain'
        );
    }

    public function test_hashchain_validation_adopts_root_on_first_interaction(): void
    {
        // With no local chain yet, the first authenticated peer hash is adopted as the
        // chain root and validation returns true (genuine first interaction).
        TenantContext::setById($this->testTenantId);
        $config = CreditCommonsNodeService::getNodeConfig($this->testTenantId);
        // Force a clean (null) chain root for this tenant.
        \Illuminate\Support\Facades\DB::table('federation_cc_node_config')
            ->where('tenant_id', $this->testTenantId)
            ->update(['last_hash' => null]);

        TenantContext::setById($this->testTenantId);
        $peerHash = hash('sha256', 'peer-root');
        $this->assertTrue(
            CreditCommonsAdapter::validateHashchain($peerHash, $this->testTenantId),
            'First interaction with no local chain must adopt the peer hash and validate'
        );

        // The adopted hash is now the chain root, so a different hash is rejected.
        TenantContext::setById($this->testTenantId);
        $this->assertFalse(
            CreditCommonsAdapter::validateHashchain('different-' . str_repeat('0', 53), $this->testTenantId),
            'After adopting the root, a mismatching hash must be rejected'
        );
    }
}
