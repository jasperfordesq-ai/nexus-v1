<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Migrations;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MarketplaceMigrationSafetySourceTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function ownershipMarkerMigrations(): iterable
    {
        yield 'checkout hardening' => ['2026_07_12_000072_harden_marketplace_checkout_state.php'];
        yield 'dispute workflows' => ['2026_07_12_000074_complete_marketplace_dispute_appeal_workflows.php'];
        yield 'checkout sessions' => ['2026_07_13_000076_bind_marketplace_checkout_sessions.php'];
        yield 'checkout mode' => ['2026_07_13_000077_bind_marketplace_stripe_checkout_mode.php'];
    }

    /** @return iterable<string, array{string}> */
    public static function indexMigrations(): iterable
    {
        yield 'checkout hardening' => ['2026_07_12_000072_harden_marketplace_checkout_state.php'];
        yield 'dispute workflows' => ['2026_07_12_000074_complete_marketplace_dispute_appeal_workflows.php'];
        yield 'checkout sessions' => ['2026_07_13_000076_bind_marketplace_checkout_sessions.php'];
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function ownedColumnDeclarations(): iterable
    {
        yield 'checkout hardening' => [
            '2026_07_12_000072_harden_marketplace_checkout_state.php',
            [
                "string('checkout_key', 64)",
                "unsignedBigInteger('shipping_option_id')",
                "timestamp('payment_expires_at')",
                "unsignedBigInteger('wallet_transaction_id')",
                "unsignedBigInteger('loyalty_redemption_id')",
                "string('funds_flow', 32)",
                "timestamp('reversed_at')",
                "string('reversal_reason', 100)",
                "timestamp('expires_at')",
                "unsignedInteger('wallet_transaction_id')",
            ],
        ];
        yield 'dispute workflows' => [
            '2026_07_12_000074_complete_marketplace_dispute_appeal_workflows.php',
            [
                "unsignedBigInteger('wallet_refund_transaction_id')",
                "string('prior_order_status', 32)",
                "string('stripe_dispute_id', 255)",
                "string('stripe_dispute_status', 50)",
                "string('dispute_previous_order_status', 32)",
                "unsignedBigInteger('appealed_by')",
                "json('enforcement_snapshot')",
                "unsignedBigInteger('marketplace_enforcement_report_id')",
                "unsignedBigInteger('marketplace_suspension_report_id')",
            ],
        ];
        yield 'checkout sessions' => [
            '2026_07_13_000076_bind_marketplace_checkout_sessions.php',
            [
                "string('checkout_session_id', 255)",
                "string('checkout_fingerprint', 64)",
            ],
        ];
        yield 'checkout mode' => [
            '2026_07_13_000077_bind_marketplace_stripe_checkout_mode.php',
            ["string('stripe_checkout_mode', 24)"],
        ];
    }

    #[DataProvider('ownershipMarkerMigrations')]
    public function testRollbackRequiresDurableColumnOwnership(string $migration): void
    {
        $source = $this->migrationSource($migration);

        self::assertStringContainsString('private const OWNER =', $source);
        self::assertStringContainsString('->comment(self::OWNER)', $source);
        self::assertStringContainsString("->where('COLUMN_COMMENT', self::OWNER)", $source);
        self::assertStringContainsString('pre-marker', $source);
    }

    /** @param list<string> $declarations */
    #[DataProvider('ownedColumnDeclarations')]
    public function testEveryAddedColumnCarriesTheOwnershipMarker(
        string $migration,
        array $declarations,
    ): void {
        $source = $this->migrationSource($migration);

        foreach ($declarations as $declaration) {
            self::assertMatchesRegularExpression(
                '/' . preg_quote($declaration, '/') . '.{0,250}?->comment\(self::OWNER\)/s',
                $source,
                "{$migration} does not mark {$declaration} as migration-owned.",
            );
        }
    }

    #[DataProvider('indexMigrations')]
    public function testIndexCreationAndRollbackAreIndependentlyGuarded(string $migration): void
    {
        $source = $this->migrationSource($migration);

        self::assertStringContainsString('Schema::hasIndex($table, $index)', $source);
        self::assertStringContainsString('assertIndexDefinition($table, $columns, $index, $unique)', $source);
        self::assertStringContainsString("->orderBy('SEQ_IN_INDEX')", $source);
        self::assertStringContainsString("->get(['COLUMN_NAME', 'NON_UNIQUE'])", $source);
        self::assertStringContainsString('CREATE {$kind}INDEX', $source);
        self::assertStringContainsString("COMMENT '", $source);
        self::assertStringContainsString("->where('INDEX_COMMENT', self::OWNER)", $source);
        self::assertStringContainsString('if (! $this->ownsIndex($table, $index))', $source);
        self::assertStringNotContainsString('->dropIndex(', $source);
        self::assertStringNotContainsString('->dropUnique(', $source);
    }

    public function testCheckoutCleanupRemainsExplicitlyIrreversible(): void
    {
        $source = $this->migrationSource('2026_07_12_000072_harden_marketplace_checkout_state.php');

        self::assertStringContainsString('Historical duplicate offer orders are preserved', $source);
        self::assertStringContainsString('The duplicate cleanup in up() is intentionally irreversible', $source);
        self::assertStringContainsString("->where('TABLE_COMMENT', self::OWNER)", $source);
    }

    public function testCheckoutModeBackfillRunsAfterRetryableColumnDdl(): void
    {
        $source = $this->migrationSource('2026_07_13_000077_bind_marketplace_stripe_checkout_mode.php');

        self::assertStringContainsString('Backfills must run on retries', $source);
        self::assertStringContainsString("->whereNotNull('checkout_session_id')", $source);
        self::assertStringContainsString("->whereNotNull('payment_intent_id')", $source);
    }

    private function migrationSource(string $migration): string
    {
        $source = file_get_contents(dirname(__DIR__, 4) . '/database/migrations/' . $migration);
        self::assertIsString($source);

        return $source;
    }
}
