<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ReportExportService;

class ReportExportXlsxTest extends TestCase
{
    private function arrayToXlsx(array $headers, array $rows, string $sheetName): string
    {
        $service = new ReportExportService();
        $method = new \ReflectionMethod($service, 'arrayToXlsx');

        return $method->invoke($service, $headers, $rows, $sheetName);
    }

    /** Extract a file from the XLSX (zip) binary, or null when missing. */
    private function zipEntry(string $xlsx, string $entry): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        file_put_contents($tmp, $xlsx);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp), 'generated XLSX should be a readable zip archive');

        $content = $zip->getFromName($entry);
        $zip->close();
        unlink($tmp);

        return $content === false ? null : $content;
    }

    public function test_generates_valid_xlsx_containing_headers_and_data(): void
    {
        $xlsx = $this->arrayToXlsx(
            ['Name', 'Hours', 'Notes'],
            [
                ['Alice Example', 12.5, 'Gardening & repairs'],
                ['Bob Sample', 3, null],
            ],
            'Hours Summary'
        );

        // XLSX files start with the PK zip magic bytes
        $this->assertStringStartsWith("PK", $xlsx);

        $this->assertNotNull($this->zipEntry($xlsx, '[Content_Types].xml'));

        $sheet = $this->zipEntry($xlsx, 'xl/worksheets/sheet1.xml');
        $this->assertNotNull($sheet, 'workbook should contain a first worksheet');

        $sharedStrings = $this->zipEntry($xlsx, 'xl/sharedStrings.xml') ?? '';
        $haystack = $sheet . $sharedStrings;

        foreach (['Name', 'Hours', 'Notes', 'Alice Example', 'Bob Sample'] as $expected) {
            $this->assertStringContainsString($expected, $haystack);
        }
        $this->assertStringContainsString('12.5', $haystack);
        // XML-encoded ampersand from "Gardening & repairs"
        $this->assertStringContainsString('Gardening &amp; repairs', $haystack);
    }

    public function test_sheet_name_is_capped_at_31_chars(): void
    {
        $xlsx = $this->arrayToXlsx(
            ['A'],
            [['x']],
            'An Extremely Long Report Sheet Name That Exceeds The Spec Limit'
        );

        $workbook = $this->zipEntry($xlsx, 'xl/workbook.xml');
        $this->assertNotNull($workbook);

        preg_match('/name="([^"]+)"/', $workbook, $m);
        $this->assertNotEmpty($m[1] ?? '');
        $this->assertLessThanOrEqual(31, mb_strlen($m[1]));
    }

    public function test_non_scalar_cells_are_json_encoded(): void
    {
        $xlsx = $this->arrayToXlsx(
            ['Meta'],
            [[['key' => 'value']]],
            'Edge Cases'
        );

        $sheet = $this->zipEntry($xlsx, 'xl/worksheets/sheet1.xml') ?? '';
        $sharedStrings = $this->zipEntry($xlsx, 'xl/sharedStrings.xml') ?? '';

        $this->assertStringContainsString('value', $sheet . $sharedStrings);
    }

    public function test_formula_injection_is_neutralised(): void
    {
        // A member named "=cmd|..." must NOT become a live formula cell.
        $xlsx = $this->arrayToXlsx(
            ['Name'],
            [['=1+2'], ['=HYPERLINK("http://evil","x")']],
            'Members'
        );

        $sheet = $this->zipEntry($xlsx, 'xl/worksheets/sheet1.xml') ?? '';
        $sharedStrings = $this->zipEntry($xlsx, 'xl/sharedStrings.xml') ?? '';
        $haystack = $sheet . $sharedStrings;

        // No formula cell element should be emitted.
        $this->assertStringNotContainsString('<f>', $sheet);
        // The neutralising single-quote prefix is present on the stored
        // value (the quote is XML-encoded as &#039; in the cell text).
        $this->assertMatchesRegularExpression('/(&#0?39;|\')=1\+2/', $haystack);
    }
}
