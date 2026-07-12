<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Database;

use PHPUnit\Framework\TestCase;

class SchemaDumpPortabilityTest extends TestCase
{
    public function testCommittedSchemaDoesNotContainEnvironmentSpecificDefiners(): void
    {
        $schemaPath = dirname(__DIR__, 4) . '/database/schema/mysql-schema.sql';
        $schema = file_get_contents($schemaPath);

        self::assertNotFalse($schema, 'The committed MySQL schema dump must be readable.');
        self::assertStringNotContainsString(
            '/*!50017 DEFINER=',
            $schema,
            'Schema objects must use the importing database account, not an explicit mysqldump DEFINER clause.',
        );
    }
}
