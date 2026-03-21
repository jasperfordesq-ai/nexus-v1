<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ReportExportService;

/**
 * ReportExportServiceTest — tests for report export across different report types.
 */
class ReportExportServiceTest extends TestCase
{
    private ReportExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportExportService();
    }

    // =========================================================================
    // getSupportedTypes
    // =========================================================================

    public function testGetSupportedTypesReturnsAllTypes(): void
    {
        $types = $this->service->getSupportedTypes();

        $this->assertIsArray($types);
        $this->assertArrayHasKey('transactions', $types);
        $this->assertArrayHasKey('members', $types);
        $this->assertArrayHasKey('hours_summary', $types);
        $this->assertArrayHasKey('hours_category', $types);
        $this->assertArrayHasKey('events', $types);
        $this->assertArrayHasKey('listings', $types);
        $this->assertArrayHasKey('inactive', $types);
        $this->assertArrayHasKey('social_value', $types);
        $this->assertCount(8, $types);
    }

    public function testGetSupportedTypesValuesAreStrings(): void
    {
        $types = $this->service->getSupportedTypes();

        foreach ($types as $key => $label) {
            $this->assertIsString($key);
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
        }
    }

    // =========================================================================
    // export — unsupported type
    // =========================================================================

    public function testExportUnsupportedTypeReturnsNoData(): void
    {
        $result = $this->service->export('nonexistent_type', 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No data', $result['message']);
    }

    // =========================================================================
    // export — result structure
    // =========================================================================

    public function testExportResultStructureOnFailure(): void
    {
        // An unsupported type yields empty rows, which triggers the "no data" path
        $result = $this->service->export('unknown', 1);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('csv', $result);
        $this->assertArrayHasKey('filename', $result);
    }

    // =========================================================================
    // exportPdf — unsupported type
    // =========================================================================

    public function testExportPdfUnsupportedTypeReturnsNoData(): void
    {
        $result = $this->service->exportPdf('nonexistent_type', 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No data', $result['message']);
    }

    public function testExportPdfResultStructureOnFailure(): void
    {
        $result = $this->service->exportPdf('unknown', 1);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('pdf', $result);
        $this->assertArrayHasKey('filename', $result);
    }

    // =========================================================================
    // CSV generation helpers (via reflection)
    // =========================================================================

    public function testArrayToCsvGeneratesValidCSV(): void
    {
        $headers = ['Name', 'Age', 'City'];
        $rows = [
            ['Alice', 25, 'Dublin'],
            ['Bob', 30, 'London'],
        ];

        $csv = $this->callPrivateMethod($this->service, 'arrayToCsv', [$headers, $rows]);

        $this->assertIsString($csv);
        // Should start with UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Name', $csv);
        $this->assertStringContainsString('Alice', $csv);
        $this->assertStringContainsString('Bob', $csv);
    }

    public function testArrayToCsvHandlesEmptyRows(): void
    {
        $headers = ['Name', 'Age'];
        $rows = [];

        $csv = $this->callPrivateMethod($this->service, 'arrayToCsv', [$headers, $rows]);

        $this->assertIsString($csv);
        $this->assertStringContainsString('Name', $csv);
    }

    // =========================================================================
    // PDF generation helpers (via reflection)
    // =========================================================================

    public function testGenerateSimplePdfReturnsValidPdf(): void
    {
        $headers = ['Name', 'Amount'];
        $rows = [['Alice', '10.00']];

        $pdf = $this->callPrivateMethod($this->service, 'generateSimplePdf', ['transactions', $headers, $rows]);

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }

    public function testGenerateSimplePdfContainsReportTitle(): void
    {
        $headers = ['Period', 'Hours'];
        $rows = [['2026-01', '100']];

        $pdf = $this->callPrivateMethod($this->service, 'generateSimplePdf', ['hours_summary', $headers, $rows]);

        $this->assertStringContainsString('Hours Summary', $pdf);
    }

    public function testGenerateSimplePdfTruncatesAt500Rows(): void
    {
        $headers = ['ID'];
        $rows = [];
        for ($i = 0; $i < 600; $i++) {
            $rows[] = ["row_{$i}"];
        }

        $pdf = $this->callPrivateMethod($this->service, 'generateSimplePdf', ['members', $headers, $rows]);

        $this->assertStringContainsString('and 100 more rows', $pdf);
    }

    // =========================================================================
    // Date condition helpers (via reflection)
    // =========================================================================

    public function testBuildDateConditionsWithBothDates(): void
    {
        $filters = ['date_from' => '2026-01-01', 'date_to' => '2026-12-31'];
        $conditions = $this->callPrivateMethod($this->service, 'buildDateConditions', [$filters]);

        $this->assertStringContainsString('>=', $conditions);
        $this->assertStringContainsString('<=', $conditions);
    }

    public function testBuildDateConditionsWithNoFilters(): void
    {
        $conditions = $this->callPrivateMethod($this->service, 'buildDateConditions', [[]]);
        $this->assertEquals('', $conditions);
    }

    public function testBuildDateBindingsReturnsCorrectCount(): void
    {
        $filters = ['date_from' => '2026-01-01', 'date_to' => '2026-12-31'];
        $bindings = $this->callPrivateMethod($this->service, 'buildDateBindings', [$filters]);

        $this->assertCount(2, $bindings);
        $this->assertEquals('2026-01-01', $bindings[0]);
        $this->assertEquals('2026-12-31', $bindings[1]);
    }

    public function testBuildDateBindingsEmptyWhenNoFilters(): void
    {
        $bindings = $this->callPrivateMethod($this->service, 'buildDateBindings', [[]]);
        $this->assertEmpty($bindings);
    }
}
