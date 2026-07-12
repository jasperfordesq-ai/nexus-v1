<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Migrations;

use Illuminate\Database\Migrations\Migration;
use PHPUnit\Framework\TestCase;

final class WebAuthnCredentialsHardeningMigrationTest extends TestCase
{
    private const MIGRATION = '2026_07_11_000045_harden_webauthn_credentials.php';

    public function testSchemaDumpPreservesCredentialIdentityAndTenantIntegrity(): void
    {
        $schema = $this->readProjectFile('database/schema/mysql-schema.sql');
        preg_match('/CREATE TABLE `webauthn_credentials` \((.*?)\n\) ENGINE=/s', $schema, $matches);
        $this->assertArrayHasKey(1, $matches, 'webauthn_credentials schema block is missing.');

        $credentials = $matches[1];
        $this->assertStringContainsString(
            '`credential_id` varchar(1364) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            $credentials
        );
        $this->assertStringContainsString('`sign_count` bigint(20) unsigned NOT NULL DEFAULT 0', $credentials);
        $this->assertStringContainsString('UNIQUE KEY `unique_credential` (`credential_id`)', $credentials);
        $this->assertStringNotContainsString('`credential_id`(191)', $credentials);

        foreach ([
            '`rp_id`',
            '`registration_origin`',
            '`user_handle`',
            '`aaguid`',
            '`backup_eligible`',
            '`backup_state`',
            '`user_verified`',
            '`credential_discoverable`',
            '`updated_at`',
        ] as $column) {
            $this->assertStringContainsString($column, $credentials);
        }

        $this->assertMatchesRegularExpression(
            '/FOREIGN KEY \(`user_id`,\s*`tenant_id`\) REFERENCES `users` \(`id`,\s*`tenant_id`\)/',
            $credentials,
        );
        $this->assertStringContainsString(
            'UNIQUE KEY `users_id_tenant_unique` (`id`,`tenant_id`)',
            $schema
        );
        $this->assertStringContainsString(
            '2026_07_11_000045_harden_webauthn_credentials',
            $schema
        );
    }

    public function testMigrationBackfillsOnlySafeLegacyBindingsAndFailsOnMismatches(): void
    {
        $migration = $this->readProjectFile('database/migrations/' . self::MIGRATION);

        $this->assertStringContainsString('base64url(SHA-256("user_id:tenant_id"))', $migration);
        $this->assertStringContainsString('WHERE `user_handle` IS NULL OR `user_handle` = \'\'', $migration);
        $this->assertStringContainsString('WHERE `wc`.`rp_id` IS NULL', $migration);
        $this->assertStringContainsString('NULLIF(TRIM(`t`.`domain`), \'\') IS NULL', $migration);
        $this->assertStringContainsString('assertCredentialOwnershipIntegrity', $migration);
        $this->assertStringContainsString('WHERE `u`.`id` IS NULL', $migration);
        $this->assertStringContainsString('quarantine/recover before retrying', $migration);
        $this->assertStringNotContainsString('DELETE `wc`', $migration);
        $this->assertStringContainsString(
            "DB::statement('ALTER TABLE `webauthn_credentials` ' . implode(', ', \$credentialAlter))",
            $migration
        );
    }

    public function testDownRefusesDestructiveSecurityRollback(): void
    {
        /** @var Migration $migration */
        $migration = require $this->projectRoot() . '/database/migrations/' . self::MIGRATION;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('expand-only');
        $migration->down();
    }

    private function readProjectFile(string $relativePath): string
    {
        $contents = file_get_contents($this->projectRoot() . '/' . $relativePath);
        $this->assertIsString($contents);

        return $contents;
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 4);
    }
}
