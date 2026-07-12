<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('WebAuthn credential hardening requires MySQL or MariaDB.');
        }

        if (! Schema::hasTable('users') || ! Schema::hasTable('webauthn_credentials')) {
            throw new RuntimeException('The users and webauthn_credentials tables must exist before hardening.');
        }

        $this->assertCredentialOwnershipIntegrity();
        $this->addMetadataColumns();

        // Old deployments allowed signed/null counters. Normalise them before
        // widening to the unsigned WebAuthn signature-counter domain.
        DB::statement(
            'UPDATE `webauthn_credentials` SET `sign_count` = 0 WHERE `sign_count` IS NULL OR `sign_count` < 0',
        );

        // A 1023-byte credential ID needs 1364 base64url characters. ASCII's
        // one-byte collation keeps the complete unique key within InnoDB's
        // 3072-byte limit and, unlike the former utf8 prefix index, preserves
        // the case-sensitive byte identity of the credential.
        // MariaDB DDL auto-commits. Keep index replacement and column changes
        // in one ALTER so a failed rebuild cannot leave credential uniqueness
        // absent between separate statements.
        $credentialAlter = [];
        if ($this->indexExists('webauthn_credentials', 'unique_credential')) {
            $credentialAlter[] = 'DROP INDEX `unique_credential`';
        }
        if ($this->indexExists('webauthn_credentials', 'idx_credential')) {
            $credentialAlter[] = 'DROP INDEX `idx_credential`';
        }
        $credentialAlter[] = 'MODIFY `credential_id` VARCHAR(1364) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT \'Unpadded base64url credential ID (maximum 1023 decoded bytes)\'';
        $credentialAlter[] = 'MODIFY `sign_count` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT \'WebAuthn signature counter for clone detection\'';
        $credentialAlter[] = 'ADD UNIQUE INDEX `unique_credential` (`credential_id`)';
        DB::statement('ALTER TABLE `webauthn_credentials` ' . implode(', ', $credentialAlter));

        // Reconstruct the exact opaque user handle used by the legacy
        // registration flow. It was base64url(SHA-256("user_id:tenant_id")).
        DB::statement(
            <<<'SQL'
                UPDATE `webauthn_credentials`
                SET `user_handle` = REPLACE(
                    REPLACE(
                        TRIM(TRAILING '=' FROM TO_BASE64(
                            UNHEX(SHA2(CONCAT(CAST(`user_id` AS CHAR), ':', CAST(`tenant_id` AS CHAR)), 256))
                        )),
                        '+', '-'
                    ),
                    '/', '_'
                )
                WHERE `user_handle` IS NULL OR `user_handle` = ''
                SQL,
        );
        DB::statement(
            'ALTER TABLE `webauthn_credentials`
                MODIFY `user_handle` VARCHAR(86) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT \'Unpadded base64url WebAuthn user handle (maximum 64 decoded bytes)\'',
        );

        // Historical rows did not persist their RP ID. Only platform-only
        // tenants are unambiguous: a tenant or parent custom domain means the
        // credential could have been registered against either RP ID, so those
        // rows deliberately stay NULL until a successful assertion binds them.
        $platformRpId = $this->configuredRpId();
        if ($platformRpId !== null) {
            DB::update(
                <<<'SQL'
                    UPDATE `webauthn_credentials` AS `wc`
                    INNER JOIN `tenants` AS `t` ON `t`.`id` = `wc`.`tenant_id`
                    LEFT JOIN `tenants` AS `p` ON `p`.`id` = `t`.`parent_id`
                    SET `wc`.`rp_id` = ?
                    WHERE `wc`.`rp_id` IS NULL
                      AND NULLIF(TRIM(`t`.`domain`), '') IS NULL
                      AND NULLIF(TRIM(`t`.`accessible_domain`), '') IS NULL
                      AND (
                          `t`.`parent_id` IS NULL
                          OR (
                              NULLIF(TRIM(`p`.`domain`), '') IS NULL
                              AND NULLIF(TRIM(`p`.`accessible_domain`), '') IS NULL
                          )
                      )
                    SQL,
                [$platformRpId],
            );
        }

        DB::statement(
            'UPDATE `webauthn_credentials`
             SET `updated_at` = COALESCE(`updated_at`, `last_used_at`, `created_at`, CURRENT_TIMESTAMP())',
        );
        DB::statement(
            'ALTER TABLE `webauthn_credentials`
                MODIFY `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP()',
        );

        if (! $this->indexExists('users', 'users_id_tenant_unique')) {
            DB::statement(
                'ALTER TABLE `users` ADD UNIQUE INDEX `users_id_tenant_unique` (`id`, `tenant_id`)',
            );
        }
        if (! $this->indexExists('webauthn_credentials', 'idx_webauthn_user_tenant')) {
            DB::statement(
                'ALTER TABLE `webauthn_credentials` ADD INDEX `idx_webauthn_user_tenant` (`user_id`, `tenant_id`)',
            );
        }
        if (! $this->constraintExists('webauthn_credentials', 'webauthn_credentials_user_tenant_foreign')) {
            DB::statement(
                'ALTER TABLE `webauthn_credentials`
                 ADD CONSTRAINT `webauthn_credentials_user_tenant_foreign`
                 FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`)
                 ON DELETE CASCADE',
            );
        }
    }

    public function down(): void
    {
        // Narrowing credential IDs would destroy valid authenticators, while
        // dropping RP/origin metadata and tenant integrity would silently
        // weaken account boundaries. This security migration is expand-only.
        throw new LogicException(
            'Migration 2026_07_11_000045 is expand-only and cannot be rolled back safely.',
        );
    }

    private function addMetadataColumns(): void
    {
        $definitions = [
            'rp_id' => "ADD `rp_id` VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'RP ID cryptographically bound to this credential' AFTER `attestation_type`",
            'registration_origin' => "ADD `registration_origin` VARCHAR(2048) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'Exact browser origin used for registration' AFTER `rp_id`",
            'user_handle' => "ADD `user_handle` VARCHAR(86) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'Unpadded base64url WebAuthn user handle' AFTER `registration_origin`",
            'aaguid' => "ADD `aaguid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'Canonical authenticator AAGUID' AFTER `user_handle`",
            'backup_eligible' => "ADD `backup_eligible` TINYINT(1) NULL COMMENT 'WebAuthn BE flag at registration' AFTER `aaguid`",
            'backup_state' => "ADD `backup_state` TINYINT(1) NULL COMMENT 'WebAuthn BS flag at registration' AFTER `backup_eligible`",
            'user_verified' => "ADD `user_verified` TINYINT(1) NULL COMMENT 'Whether registration required user verification' AFTER `backup_state`",
            'credential_discoverable' => "ADD `credential_discoverable` TINYINT(1) NULL COMMENT 'Whether the credential is discoverable/resident' AFTER `user_verified`",
            'updated_at' => 'ADD `updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_used_at`',
        ];

        $missing = [];
        foreach ($definitions as $column => $definition) {
            if (! Schema::hasColumn('webauthn_credentials', $column)) {
                $missing[] = $definition;
            }
        }

        if ($missing !== []) {
            DB::statement('ALTER TABLE `webauthn_credentials` ' . implode(', ', $missing));
        }
    }

    private function assertCredentialOwnershipIntegrity(): void
    {
        // Never guess ownership or silently delete a member's authenticator in
        // a schema migration. Run this before any auto-committing DDL and fail
        // with bounded identifiers for explicit quarantine and recovery.
        $countRow = DB::selectOne(
            <<<'SQL'
                SELECT COUNT(*) AS `aggregate`
                FROM `webauthn_credentials` AS `wc`
                LEFT JOIN `users` AS `u`
                  ON `u`.`id` = `wc`.`user_id`
                 AND `u`.`tenant_id` = `wc`.`tenant_id`
                WHERE `u`.`id` IS NULL
                SQL,
        );
        $invalidCredentialCount = $countRow === null ? 0 : (int) ($countRow->aggregate ?? 0);
        if ($invalidCredentialCount === 0) {
            return;
        }

        $invalidIds = DB::table('webauthn_credentials as wc')
            ->leftJoin('users as u', static function ($join): void {
                $join->on('u.id', '=', 'wc.user_id')->on('u.tenant_id', '=', 'wc.tenant_id');
            })
            ->whereNull('u.id')
            ->orderBy('wc.id')
            ->limit(20)
            ->pluck('wc.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        throw new RuntimeException(sprintf(
            'WebAuthn hardening found %d orphaned or cross-tenant credential row(s); quarantine/recover before retrying. Sample IDs: %s',
            $invalidCredentialCount,
            implode(', ', $invalidIds),
        ));
    }

    private function indexExists(string $table, string $index): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS `aggregate`
             FROM `information_schema`.`STATISTICS`
             WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `INDEX_NAME` = ?',
            [$table, $index],
        );

        return $row !== null && (int) ($row->aggregate ?? 0) > 0;
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS `aggregate`
             FROM `information_schema`.`TABLE_CONSTRAINTS`
             WHERE `CONSTRAINT_SCHEMA` = DATABASE()
               AND `TABLE_NAME` = ?
               AND `CONSTRAINT_NAME` = ?
               AND `CONSTRAINT_TYPE` = \'FOREIGN KEY\'',
            [$table, $constraint],
        );

        return $row !== null && (int) ($row->aggregate ?? 0) > 0;
    }

    private function configuredRpId(): ?string
    {
        $value = config('webauthn.rp_id');
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(rtrim(trim($value), '.'));
        if ($value === '' || strlen($value) > 253) {
            return null;
        }
        if ($value !== 'localhost' && filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return null;
        }
        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/D', $value) !== 1) {
            return null;
        }

        return $value;
    }
};
