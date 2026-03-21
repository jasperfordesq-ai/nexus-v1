<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\TransactionExportService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * TransactionExportServiceTest — tests for CSV export generation.
 */
class TransactionExportServiceTest extends TestCase
{
    private TransactionExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionExportService();
        TenantContext::setById(1);
    }

    // =========================================================================
    // exportPersonalStatementCSV
    // =========================================================================

    public function testExportReturnsSuccessWithCSVContent(): void
    {
        $rows = [
            (object) [
                'id' => 1,
                'amount' => '2.50',
                'description' => 'Gardening help',
                'status' => 'completed',
                'transaction_type' => 'transfer',
                'created_at' => '2026-01-15 10:30:00',
                'sender_id' => 100,
                'receiver_id' => 200,
                'sender_first' => 'Alice',
                'sender_last' => 'Smith',
                'receiver_first' => 'Bob',
                'receiver_last' => 'Jones',
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->andReturn($rows);

        $result = $this->service->exportPersonalStatementCSV(100);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('csv', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertNotEmpty($result['csv']);
    }

    public function testExportCSVContainsHeaders(): void
    {
        $rows = [
            (object) [
                'id' => 1,
                'amount' => '1.00',
                'description' => 'Test',
                'status' => 'completed',
                'transaction_type' => 'transfer',
                'created_at' => '2026-01-15 10:30:00',
                'sender_id' => 100,
                'receiver_id' => 200,
                'sender_first' => 'A',
                'sender_last' => 'B',
                'receiver_first' => 'C',
                'receiver_last' => 'D',
            ],
        ];

        DB::shouldReceive('select')->once()->andReturn($rows);

        $result = $this->service->exportPersonalStatementCSV(100);

        $csv = $result['csv'];
        $this->assertStringContainsString('Date', $csv);
        $this->assertStringContainsString('Type', $csv);
        $this->assertStringContainsString('Description', $csv);
        $this->assertStringContainsString('Other Party', $csv);
        $this->assertStringContainsString('Debit', $csv);
        $this->assertStringContainsString('Credit', $csv);
        $this->assertStringContainsString('Status', $csv);
    }

    public function testExportCSVContainsUTF8BOM(): void
    {
        DB::shouldReceive('select')->once()->andReturn([
            (object) [
                'id' => 1,
                'amount' => '1.00',
                'description' => 'Test',
                'status' => 'completed',
                'transaction_type' => 'transfer',
                'created_at' => '2026-01-15 10:30:00',
                'sender_id' => 100,
                'receiver_id' => 200,
                'sender_first' => 'A',
                'sender_last' => 'B',
                'receiver_first' => 'C',
                'receiver_last' => 'D',
            ],
        ]);

        $result = $this->service->exportPersonalStatementCSV(100);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $result['csv']);
    }

    public function testExportShowsDebitForSender(): void
    {
        DB::shouldReceive('select')->once()->andReturn([
            (object) [
                'id' => 1,
                'amount' => '3.00',
                'description' => 'Sent hours',
                'status' => 'completed',
                'transaction_type' => 'transfer',
                'created_at' => '2026-01-15 10:30:00',
                'sender_id' => 100,
                'receiver_id' => 200,
                'sender_first' => 'Alice',
                'sender_last' => 'Smith',
                'receiver_first' => 'Bob',
                'receiver_last' => 'Jones',
            ],
        ]);

        $result = $this->service->exportPersonalStatementCSV(100);
        // User 100 is sender, so should see debit of 3.00
        $this->assertStringContainsString('3.00', $result['csv']);
        $this->assertStringContainsString('Bob Jones', $result['csv']);
    }

    public function testExportShowsCreditForReceiver(): void
    {
        DB::shouldReceive('select')->once()->andReturn([
            (object) [
                'id' => 1,
                'amount' => '2.00',
                'description' => 'Received hours',
                'status' => 'completed',
                'transaction_type' => 'transfer',
                'created_at' => '2026-01-15 10:30:00',
                'sender_id' => 200,
                'receiver_id' => 100,
                'sender_first' => 'Bob',
                'sender_last' => 'Jones',
                'receiver_first' => 'Alice',
                'receiver_last' => 'Smith',
            ],
        ]);

        $result = $this->service->exportPersonalStatementCSV(100);
        $this->assertStringContainsString('2.00', $result['csv']);
        $this->assertStringContainsString('Bob Jones', $result['csv']);
    }

    public function testExportWithDateFiltersIncludesDateInFilename(): void
    {
        DB::shouldReceive('select')->once()->andReturn([]);

        $result = $this->service->exportPersonalStatementCSV(100, [
            'startDate' => '2026-01-01',
            'endDate' => '2026-01-31',
        ]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('2026-01-01', $result['filename']);
        $this->assertStringContainsString('2026-01-31', $result['filename']);
    }

    public function testExportEmptyResultStillSucceeds(): void
    {
        DB::shouldReceive('select')->once()->andReturn([]);

        $result = $this->service->exportPersonalStatementCSV(100);
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['csv']); // has headers at minimum
    }

    public function testExportHandlesSystemTransactionWithNoOtherParty(): void
    {
        DB::shouldReceive('select')->once()->andReturn([
            (object) [
                'id' => 1,
                'amount' => '5.00',
                'description' => 'Starting balance',
                'status' => 'completed',
                'transaction_type' => 'starting_balance',
                'created_at' => '2026-01-15 10:30:00',
                'sender_id' => 0,
                'receiver_id' => 100,
                'sender_first' => null,
                'sender_last' => null,
                'receiver_first' => 'Alice',
                'receiver_last' => 'Smith',
            ],
        ]);

        $result = $this->service->exportPersonalStatementCSV(100);
        $this->assertStringContainsString('System', $result['csv']);
    }

    public function testExportFilenameContainsUserId(): void
    {
        DB::shouldReceive('select')->once()->andReturn([]);

        $result = $this->service->exportPersonalStatementCSV(42);
        $this->assertStringContainsString('statement_42', $result['filename']);
        $this->assertStringContainsString('.csv', $result['filename']);
    }

    // =========================================================================
    // escapeCSV (private, via reflection)
    // =========================================================================

    public function testEscapeCSVHandlesCommas(): void
    {
        $result = $this->callPrivateMethod($this->service, 'escapeCSV', ['hello, world']);
        $this->assertEquals('"hello, world"', $result);
    }

    public function testEscapeCSVHandlesQuotes(): void
    {
        $result = $this->callPrivateMethod($this->service, 'escapeCSV', ['say "hi"']);
        $this->assertEquals('"say ""hi"""', $result);
    }

    public function testEscapeCSVHandlesNewlines(): void
    {
        $result = $this->callPrivateMethod($this->service, 'escapeCSV', ["line1\nline2"]);
        $this->assertEquals("\"line1\nline2\"", $result);
    }

    public function testEscapeCSVPassesThroughSimpleStrings(): void
    {
        $result = $this->callPrivateMethod($this->service, 'escapeCSV', ['simple']);
        $this->assertEquals('simple', $result);
    }
}
