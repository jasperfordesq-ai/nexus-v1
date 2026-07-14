<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\CaringCommunity;

use App\Core\TenantContext;
use App\Services\CaringCommunity\IsolatedNodeReadinessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * IsolatedNodeReadinessServiceTest
 *
 * Covers:
 *  - schema(): returns all expected item keys with required shape
 *  - get(): returns items list, gate status, and last_updated_at
 *  - get(): unset items default to status=pending
 *  - update(): happy path persists status + fields, returns item and gate
 *  - update(): unknown item key returns INVALID_ITEM_KEY error
 *  - update(): invalid status value returns INVALID_STATUS error
 *  - update(): invalid enum/choice value returns INVALID_CHOICE error
 *  - update(): invalid URL value returns INVALID_URL error
 *  - update(): owner too long returns OWNER_TOO_LONG error
 *  - update(): notes too long returns NOTES_TOO_LONG error
 *  - gate: closed=true only when all 11 items decided
 *  - gate: blockers list populated when any item is blocked
 *  - update(): partial payload leaves unset fields unchanged
 */
class IsolatedNodeReadinessServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    /** Total items defined in the schema */
    private const SCHEMA_COUNT = 11;

    private IsolatedNodeReadinessService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        $this->svc = new IsolatedNodeReadinessService();

        // Clean up any pre-existing isolated_node settings for this tenant
        // (from prior non-transactional runs) so tests start from a known blank state.
        DB::table('tenant_settings')
            ->where('tenant_id', self::TENANT_ID)
            ->where('setting_key', 'like', IsolatedNodeReadinessService::KEY_PREFIX . '%')
            ->delete();
    }

    // ── schema() ──────────────────────────────────────────────────────────────

    public function test_schema_returns_all_expected_item_keys(): void
    {
        $schema = $this->svc->schema();

        $expectedKeys = [
            'deployment_mode',
            'hosting_owner',
            'smtp_owner',
            'storage_owner',
            'backup_owner',
            'update_cadence',
            'source_release_workflow',
            'telemetry_default',
            'federation_key_exchange',
            'dpo_appointed',
            'incident_runbook_url',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $schema, "Schema missing key: {$key}");
        }

        $this->assertCount(self::SCHEMA_COUNT, $schema);
    }

    public function test_schema_items_have_required_shape(): void
    {
        foreach ($this->svc->schema() as $key => $meta) {
            $this->assertArrayHasKey('label_code', $meta, "Schema item '{$key}' missing 'label_code'");
            $this->assertArrayHasKey('type', $meta, "Schema item '{$key}' missing 'type'");
            $this->assertArrayHasKey('help_code', $meta, "Schema item '{$key}' missing 'help_code'");
            $this->assertSame($key, $meta['label_code']);
            $this->assertSame($key, $meta['help_code']);
            $this->assertContains($meta['type'], ['text', 'enum', 'choice', 'url']);
        }
    }

    public function test_schema_enum_and_choice_items_have_non_empty_choices_array(): void
    {
        foreach ($this->svc->schema() as $key => $meta) {
            if (in_array($meta['type'], ['enum', 'choice'], true)) {
                $this->assertArrayHasKey('choices', $meta, "Schema item '{$key}' (type={$meta['type']}) must have 'choices'");
                $this->assertIsArray($meta['choices']);
                $this->assertNotEmpty($meta['choices'], "Schema item '{$key}' has empty choices array");
            }
        }
    }

    // ── get() ────────────────────────────────────────────────────────────────

    public function test_get_returns_required_top_level_keys(): void
    {
        $result = $this->svc->get(self::TENANT_ID);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('gate', $result);
        $this->assertArrayHasKey('last_updated_at', $result);
    }

    public function test_get_returns_all_schema_items(): void
    {
        $result = $this->svc->get(self::TENANT_ID);

        $this->assertCount(self::SCHEMA_COUNT, $result['items']);
    }

    public function test_get_unset_items_default_to_pending_status(): void
    {
        $result = $this->svc->get(self::TENANT_ID);

        foreach ($result['items'] as $item) {
            $this->assertSame(
                IsolatedNodeReadinessService::STATUS_PENDING,
                $item['status'],
                "Item '{$item['key']}' should default to pending"
            );
            $this->assertNull($item['value']);
            $this->assertNull($item['owner']);
            $this->assertNull($item['notes']);
        }
    }

    public function test_get_items_have_required_fields(): void
    {
        $result = $this->svc->get(self::TENANT_ID);

        foreach ($result['items'] as $item) {
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('label_code', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('help_code', $item);
            $this->assertArrayHasKey('value', $item);
            $this->assertArrayHasKey('owner', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('notes', $item);
        }
    }

    public function test_get_gate_is_not_closed_when_no_items_decided(): void
    {
        $result = $this->svc->get(self::TENANT_ID);

        $this->assertFalse($result['gate']['closed']);
        $this->assertSame(0, $result['gate']['decided_count']);
        $this->assertSame(self::SCHEMA_COUNT, $result['gate']['total_count']);
    }

    // ── update() happy path ───────────────────────────────────────────────────

    public function test_update_persists_status_and_returns_item_and_gate(): void
    {
        $result = $this->svc->update(self::TENANT_ID, 'hosting_owner', [
            'value'  => 'Canton IT Services',
            'owner'  => 'CIO Office',
            'status' => IsolatedNodeReadinessService::STATUS_DECIDED,
            'notes'  => 'Confirmed by procurement.',
        ]);

        $this->assertArrayHasKey('item', $result);
        $this->assertArrayHasKey('gate', $result);
        $this->assertArrayNotHasKey('errors', $result);

        $item = $result['item'];
        $this->assertSame('hosting_owner', $item['key']);
        $this->assertSame('Canton IT Services', $item['value']);
        $this->assertSame('CIO Office', $item['owner']);
        $this->assertSame(IsolatedNodeReadinessService::STATUS_DECIDED, $item['status']);
        $this->assertSame('Confirmed by procurement.', $item['notes']);
    }

    public function test_update_persists_in_database_under_correct_key(): void
    {
        $this->svc->update(self::TENANT_ID, 'smtp_owner', [
            'value'  => 'Postmark',
            'status' => IsolatedNodeReadinessService::STATUS_IN_PROGRESS,
        ]);

        $row = DB::table('tenant_settings')
            ->where('tenant_id', self::TENANT_ID)
            ->where('setting_key', IsolatedNodeReadinessService::KEY_PREFIX . 'smtp_owner')
            ->first();

        $this->assertNotNull($row);
        $envelope = json_decode($row->setting_value, true);
        $this->assertSame('Postmark', $envelope['value']);
        $this->assertSame(IsolatedNodeReadinessService::STATUS_IN_PROGRESS, $envelope['status']);
    }

    public function test_update_partial_payload_leaves_other_fields_unchanged(): void
    {
        // Set initial full state
        $this->svc->update(self::TENANT_ID, 'backup_owner', [
            'value'  => 'BackupCo',
            'owner'  => 'SRE Team',
            'status' => IsolatedNodeReadinessService::STATUS_IN_PROGRESS,
            'notes'  => 'Initial note',
        ]);

        // Update only status — other fields must be preserved
        $result = $this->svc->update(self::TENANT_ID, 'backup_owner', [
            'status' => IsolatedNodeReadinessService::STATUS_DECIDED,
        ]);

        $item = $result['item'];
        $this->assertSame('BackupCo', $item['value']);
        $this->assertSame('SRE Team', $item['owner']);
        $this->assertSame('Initial note', $item['notes']);
        $this->assertSame(IsolatedNodeReadinessService::STATUS_DECIDED, $item['status']);
    }

    public function test_update_valid_enum_choice_is_accepted(): void
    {
        $result = $this->svc->update(self::TENANT_ID, 'deployment_mode', [
            'value'  => 'canton_isolated_node',
            'status' => IsolatedNodeReadinessService::STATUS_DECIDED,
        ]);

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame('canton_isolated_node', $result['item']['value']);
    }

    public function test_update_valid_url_is_accepted(): void
    {
        $result = $this->svc->update(self::TENANT_ID, 'incident_runbook_url', [
            'value'  => 'https://runbooks.example.com/incident',
            'status' => IsolatedNodeReadinessService::STATUS_IN_PROGRESS,
        ]);

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame('https://runbooks.example.com/incident', $result['item']['value']);
    }

    // ── update() validation errors ────────────────────────────────────────────

    public function test_update_unknown_item_key_returns_invalid_item_key_error(): void
    {
        $result = $this->svc->update(self::TENANT_ID, 'non_existent_key', [
            'status' => 'decided',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertSame('INVALID_ITEM_KEY', $result['errors'][0]['code']);
    }

    public function test_update_invalid_status_returns_error(): void
    {
        $result = $this->svc->update(self::TENANT_ID, 'hosting_owner', [
            'status' => 'flying',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('INVALID_STATUS', $codes);
    }

    public function test_update_invalid_enum_choice_returns_invalid_choice_error(): void
    {
        // deployment_mode is 'enum' with defined choices; bogus value must fail
        $result = $this->svc->update(self::TENANT_ID, 'deployment_mode', [
            'value' => 'totally_made_up_mode',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('INVALID_CHOICE', $codes);
    }

    public function test_update_invalid_url_returns_invalid_url_error(): void
    {
        $result = $this->svc->update(self::TENANT_ID, 'incident_runbook_url', [
            'value' => 'not-a-valid-url',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('INVALID_URL', $codes);
    }

    public function test_update_owner_too_long_returns_error(): void
    {
        $result = $this->svc->update(self::TENANT_ID, 'hosting_owner', [
            'owner' => str_repeat('x', 256),
        ]);

        $this->assertArrayHasKey('errors', $result);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('OWNER_TOO_LONG', $codes);
    }

    public function test_update_notes_too_long_returns_error(): void
    {
        $result = $this->svc->update(self::TENANT_ID, 'hosting_owner', [
            'notes' => str_repeat('n', 2001),
        ]);

        $this->assertArrayHasKey('errors', $result);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('NOTES_TOO_LONG', $codes);
    }

    // ── gate logic ────────────────────────────────────────────────────────────

    public function test_gate_is_closed_when_all_items_set_to_decided(): void
    {
        foreach (array_keys($this->svc->schema()) as $key) {
            $payload = ['status' => IsolatedNodeReadinessService::STATUS_DECIDED];

            // Supply a syntactically valid value for typed items to avoid validation errors
            $meta = $this->svc->schema()[$key];
            switch ($meta['type']) {
                case 'enum':
                case 'choice':
                    $payload['value'] = $meta['choices'][0];
                    break;
                case 'url':
                    $payload['value'] = 'https://example.com/runbook';
                    break;
                case 'text':
                    $payload['value'] = 'Test owner value';
                    break;
            }

            $this->svc->update(self::TENANT_ID, $key, $payload);
        }

        $state = $this->svc->get(self::TENANT_ID);

        $this->assertTrue($state['gate']['closed'], 'Gate must be closed when all items are decided');
        $this->assertSame(self::SCHEMA_COUNT, $state['gate']['decided_count']);
        $this->assertSame([], $state['gate']['blockers']);
    }

    public function test_gate_blockers_populated_when_item_is_blocked(): void
    {
        $this->svc->update(self::TENANT_ID, 'dpo_appointed', [
            'status' => IsolatedNodeReadinessService::STATUS_BLOCKED,
        ]);

        $state = $this->svc->get(self::TENANT_ID);

        $this->assertFalse($state['gate']['closed']);
        $this->assertContains('dpo_appointed', $state['gate']['blockers']);
    }

    public function test_gate_status_counts_reflect_all_statuses(): void
    {
        $this->svc->update(self::TENANT_ID, 'hosting_owner', [
            'status' => IsolatedNodeReadinessService::STATUS_IN_PROGRESS,
        ]);

        $state  = $this->svc->get(self::TENANT_ID);
        $counts = $state['gate']['status_counts'];

        $this->assertArrayHasKey(IsolatedNodeReadinessService::STATUS_PENDING, $counts);
        $this->assertArrayHasKey(IsolatedNodeReadinessService::STATUS_IN_PROGRESS, $counts);
        $this->assertArrayHasKey(IsolatedNodeReadinessService::STATUS_DECIDED, $counts);
        $this->assertArrayHasKey(IsolatedNodeReadinessService::STATUS_BLOCKED, $counts);

        $total = array_sum($counts);
        $this->assertSame(self::SCHEMA_COUNT, $total, 'status_counts must sum to total schema items');
        $this->assertSame(1, $counts[IsolatedNodeReadinessService::STATUS_IN_PROGRESS]);
    }

    // ── last_updated_at ───────────────────────────────────────────────────────

    public function test_last_updated_at_is_null_before_any_updates(): void
    {
        $result = $this->svc->get(self::TENANT_ID);
        $this->assertNull($result['last_updated_at']);
    }

    public function test_last_updated_at_is_set_after_an_update(): void
    {
        $this->svc->update(self::TENANT_ID, 'storage_owner', [
            'value'  => 'Hetzner',
            'status' => IsolatedNodeReadinessService::STATUS_IN_PROGRESS,
        ]);

        $result = $this->svc->get(self::TENANT_ID);

        $this->assertNotNull($result['last_updated_at']);
        $this->assertIsString($result['last_updated_at']);
    }
}
