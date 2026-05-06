<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Support;

use App\Support\CsvExportSanitizer;
use Tests\Laravel\TestCase;

class CsvExportSanitizerTest extends TestCase
{
    public function test_formula_like_cells_are_prefixed_for_spreadsheet_exports(): void
    {
        $row = CsvExportSanitizer::row([
            '=IMPORTXML("https://example.test")',
            '+SUM(1,1)',
            '-10+20',
            '@cmd',
            "\t=hidden",
            "\r=hidden",
            'ordinary text',
            42,
            false,
            null,
        ]);

        $this->assertSame("'=IMPORTXML(\"https://example.test\")", $row[0]);
        $this->assertSame("'+SUM(1,1)", $row[1]);
        $this->assertSame("'-10+20", $row[2]);
        $this->assertSame("'@cmd", $row[3]);
        $this->assertSame("'\t=hidden", $row[4]);
        $this->assertSame("'\r=hidden", $row[5]);
        $this->assertSame('ordinary text', $row[6]);
        $this->assertSame('42', $row[7]);
        $this->assertSame('0', $row[8]);
        $this->assertSame('', $row[9]);
    }
}
